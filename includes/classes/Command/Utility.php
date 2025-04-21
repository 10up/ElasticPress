<?php
/**
 * ElasticPress CLI Utility
 *
 * @since 4.5.0
 * @package elasticpress
 */

namespace ElasticPress\Command;

use WP_CLI;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Utility class for WP CLI commands.
 */
class Utility {

	/**
	 * Internal timer.
	 *
	 * @var float
	 */
	protected static $time_start = null;

	/**
	 * Properly clean up when receiving SIGINT on indexing
	 *
	 * @param int $signal_no Signal number
	 */
	public static function delete_transient_on_int( $signal_no ) {
		if ( SIGINT === $signal_no ) {
			self::delete_transient();
			WP_CLI::log( esc_html__( 'Indexing cleaned up.', 'elasticpress' ) );
			WP_CLI::halt( 0 );
		}
	}

	/**
	 * Delete transient that indicates indexing is occurring
	 */
	public static function delete_transient() {
		\ElasticPress\IndexHelper::factory()->clear_index_meta();

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			delete_site_transient( 'ep_cli_sync_progress' );
			delete_site_transient( 'ep_wpcli_sync_interrupted' );
		} else {
			delete_transient( 'ep_cli_sync_progress' );
			delete_transient( 'ep_wpcli_sync_interrupted' );
		}
	}

	/**
	 * Stops the timer.
	 *
	 * @param int $precision The number of digits from the right of the decimal to display. Default 3.
	 * @return float Time spent so far
	 */
	public static function timer_stop( $precision = 3 ) {
		$diff = microtime( true ) - self::$time_start;
		return (float) number_format( (float) $diff, $precision, '.', '' );
	}

	/**
	 * Starts the timer.
	 *
	 * @return true
	 */
	public static function timer_start() {
		self::$time_start = microtime( true );
		return true;
	}

	/**
	 * Check if sync should be interrupted
	 */
	public static function should_interrupt_sync() {
		$should_interrupt_sync = get_transient( 'ep_wpcli_sync_interrupted' );

		if ( $should_interrupt_sync ) {
			WP_CLI::line( esc_html__( 'Sync was interrupted', 'elasticpress' ) );
			self::delete_transient_on_int( 2 );
			WP_CLI::halt( 0 );
		}
	}

	/**
	 * Given a timestamp in microseconds, returns it in the given format.
	 *
	 * @param float  $microtime Unix timestamp in ms
	 * @param string $format    Desired format
	 * @return string
	 */
	public static function timer_format( $microtime, $format = 'H:i:s.u' ) {
		$microtime_date = \DateTime::createFromFormat( 'U.u', number_format( (float) $microtime, 3, '.', '' ) );
		return $microtime_date->format( $format );
	}

	/**
	 * If put_mapping fails while indexing, stop the index process.
	 *
	 * @param array     $index_meta Index meta info
	 * @param Indexable $indexable  Indexable object
	 * @param bool      $result     Whether the request was successful or not
	 */
	public static function stop_on_failed_mapping( $index_meta, $indexable, $result ) {
		if ( ! $result ) {
			self::delete_transient();

			WP_CLI::error( esc_html__( 'Mapping Failed.', 'elasticpress' ) );
		}
	}

	/**
	 * Ties the `ep_cli_put_mapping` action to `ep_sync_put_mapping`.
	 *
	 * @param array     $index_meta Index meta information
	 * @param Indexable $indexable  Indexable object
	 * @return void
	 */
	public static function call_ep_cli_put_mapping( $index_meta, $indexable ) {
		/**
		 * Fires after CLI put mapping
		 *
		 * @hook ep_cli_put_mapping
		 * @param  {Indexable} $indexable Indexable involved in mapping
		 * @param  {array} $args CLI command position args
		 * @param {array} $assoc_args CLI command associative args
		 */
		do_action( 'ep_cli_put_mapping', $indexable, WP_CLI::get_runner()->arguments, WP_CLI::get_runner()->assoc_args );
	}


	/**
	 * Custom get_transient to WP-CLI env.
	 *
	 * We are using the direct SQL query instead of
	 * the regular function call to retrieve the updated
	 * value to stop the sync. Otherwise, we always get
	 * false after the command is running even when the value
	 * is updated.
	 *
	 * @param mixed  $pre_transient The default value.
	 * @param string $transient Transient name.
	 * @return true|null
	 */
	public static function custom_get_transient( $pre_transient, $transient ) {
		global $wpdb;

		if ( wp_using_ext_object_cache() ) {
			/**
			* When external object cache is used we need to make sure to force a remote fetch,
			* so that the value from the local memory is discarded.
			*/
			$should_interrupt_sync = wp_cache_get( $transient, 'transient', true );
		} else {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery
			$should_interrupt_sync = $wpdb->get_var(
				$wpdb->prepare(
					"
						SELECT option_value
						FROM $wpdb->options
						WHERE option_name = %s
						LIMIT 1
					",
					"_transient_{$transient}"
				)
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery
		}

		return $should_interrupt_sync ? (bool) $should_interrupt_sync : null;
	}
}
