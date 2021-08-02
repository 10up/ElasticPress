<?php
/**
 * Handle upgrades.
 *
 * @since  3.x
 * @package elasticpress
 */

namespace ElasticPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Upgrades
 *
 * @package ElasticPress
 */
class Upgrades {

	/**
	 * Store the version number before performing upgrades.
	 * Set in the `setup()` method.
	 *
	 * @var null|string
	 */
	protected $old_version;

	/**
	 * Initialize class
	 */
	public function setup() {
		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			$this->old_version = get_site_option( 'ep_version', false );
		} else {
			$this->old_version = get_option( 'ep_version', false );
		}

		/**
		 * An array with the upgrades routines.
		 * Indexes are the ElasticPress version and values
		 * are an array with the method name and, if needed,
		 * the action name where it should be hooked.
		 */
		$routines = [
			'3.5.2' => [ 'upgrade_3_5_2', 'init' ],
			'3.5.3' => [ 'upgrade_3_5_3', 'init' ],
		];

		array_walk( $routines, [ $this, 'run_upgrade_routine' ] );

		/**
		 * Check if a reindex is needed.
		 */
		add_action( 'plugins_loaded', [ $this, 'check_reindex_needed' ], 5 );

		/**
		 * Update the version number.
		 * Note: if a upgrade routine method is hooked to some action,
		 * this code will be executed *earlier* than the routine method.
		 */
		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			update_site_option( 'ep_version', sanitize_text_field( EP_VERSION ) );
		} else {
			update_option( 'ep_version', sanitize_text_field( EP_VERSION ) );
		}
	}

	/**
	 * Run the upgrade routine, if needed.
	 *
	 * @param array  $routine Array with the info about the method to call.
	 *                        If needed to be run in an action, it'll contain the action name.
	 * @param string $version The version number to be tested.
	 */
	protected function run_upgrade_routine( $routine, $version ) {
		if ( version_compare( $this->old_version, $version, '<' ) ) {
			$function_name = $routine[0];
			$action_tag    = $routine[1];
			$priority      = ( ! empty( $routine[2] ) ) ? $routine[2] : 10;
			if ( $action_tag ) {
				add_action( $action_tag, [ $this, $function_name ], $priority );
			} else {
				$this->$function_name();
			}
		}
	}

	/**
	 * Upgrade routine of v3.5.2.
	 *
	 * If weighting options exist and the WooCommerce feature is enabled,
	 * this method will enable the SKU field.
	 */
	public function upgrade_3_5_2() {
		$weighting_options = get_option( 'elasticpress_weighting', [] );
		if ( empty( $weighting_options ) ) {
			return;
		}

		$woocommerce = Features::factory()->get_registered_feature( 'woocommerce' );
		if ( ! $woocommerce->is_active() ) {
			return;
		}

		if ( empty( $weighting_options['product'] ) ) {
			return;
		}

		if ( ! empty( $weighting_options['product']['author_name'] ) ) {
			unset( $weighting_options['product']['author_name'] );
		}

		$weighting_options['product']['meta._sku.value'] = array(
			'enabled' => true,
			'weight'  => 1,
		);

		update_option( 'elasticpress_weighting', $weighting_options );
	}

	/**
	 * Upgrade routine of v3.5.3.
	 *
	 * Check if synonyms post has the correct post type, otherwise,
	 * change it to the correct one.
	 */
	public function upgrade_3_5_3() {
		delete_option( 'elasticpress_synonyms_post_id' );
		delete_site_option( 'elasticpress_synonyms_post_id' );
	}

	/**
	 * Check if a reindex is needed based on the version number.
	 */
	public function check_reindex_needed() {
		if ( ! is_admin() || defined( 'DOING_AJAX' ) ) {
			return;
		}

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			$last_sync = get_site_option( 'ep_last_sync', 'never' );
		} else {
			$last_sync = get_option( 'ep_last_sync', 'never' );
		}

		// No need to upgrade since we've never synced.
		if ( empty( $last_sync ) || 'never' === $last_sync ) {
			return;
		}

		/**
		 * Reindex if we cross a reindex version in the upgrade
		 */
		$reindex_versions = apply_filters(
			'ep_reindex_versions',
			array(
				'2.2',
				'2.3.1',
				'2.4',
				'2.5.1',
				'2.6',
				'2.7',
				'3.0',
				'3.1',
				'3.3',
				'3.4',
				'3.6.0',
				'3.6.1',
			)
		);

		$need_upgrade_sync = false;

		if ( false !== $this->old_version ) {
			$last_reindex_version = $reindex_versions[ count( $reindex_versions ) - 1 ];

			if ( -1 === version_compare( $this->old_version, $last_reindex_version ) && 0 <= version_compare( EP_VERSION, $last_reindex_version ) ) {
				$need_upgrade_sync = true;
			}
		}

		if ( $need_upgrade_sync ) {
			if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
				update_site_option( 'ep_need_upgrade_sync', true );
			} else {
				update_option( 'ep_need_upgrade_sync', true );
			}
		}
	}

	/**
	 * Return singleton instance of class
	 *
	 * @return self
	 */
	public static function factory() {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new self();
			$instance->setup();
		}

		return $instance;
	}
}
