<?php
/**
 * Disable After Failures trait
 *
 * This trait disables the feature after a certain number of failures.
 *
 * @since 5.4.0
 * @package ElasticPress
 */

namespace ElasticPress\Traits;

use ElasticPress\FeatureRequirementsStatus;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

trait DisableAfterFailures {
	/**
	 * Setup the failures count.
	 *
	 * @return void
	 */
	public function setup_failures_count() {
		add_action( 'admin_init', [ $this, 'maybe_reset_failures_count' ] );
	}

	/**
	 * Maybe reset the failures count.
	 *
	 * @return void
	 */
	public function maybe_reset_failures_count() {
		$is_valid_nonce = isset( $_GET['ep_reset_failures_nonce'] ) &&
			wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['ep_reset_failures_nonce'] ) ), 'ep_reset_failures_nonce' );
		if ( ! $is_valid_nonce ) {
			return;
		}

		$feature_slug = isset( $_GET['ep_reset_failures'] ) ? sanitize_text_field( wp_unslash( $_GET['ep_reset_failures'] ) ) : '';
		if ( $feature_slug !== $this->slug ) {
			return;
		}

		if ( defined( '\ElasticPress\FeatureRequirementsStatus::TEMPORARILY_DISABLED' ) && $this->requirements_status()->code !== FeatureRequirementsStatus::TEMPORARILY_DISABLED ) {
			return;
		}

		$this->reset_failures_count();
		wp_safe_redirect( remove_query_arg( [ 'ep_reset_failures', 'ep_reset_failures_nonce' ] ) . '#/' . $this->slug );
		exit;
	}

	/**
	 * Reset the failures count.
	 *
	 * @return void
	 */
	public function reset_failures_count() {
		$transient_key = $this->get_failures_transient_key();
		delete_transient( $transient_key );
		delete_transient( $transient_key . '_lock' );
	}

	/**
	 * If the feature should be temporarily disabled after a certain number of failures.
	 *
	 * @return boolean
	 */
	public function should_disable_after_failures() {
		$stored   = get_transient( $this->get_failures_transient_key() );
		$failures = $this->cleanup_failures( is_array( $stored ) ? $stored : [] );
		return count( $failures ) > $this->get_max_failures_count();
	}

	/**
	 * Update the requirements status.
	 *
	 * @param FeatureRequirementsStatus $status The feature requirements status object.
	 * @return FeatureRequirementsStatus The feature requirements status object.
	 */
	public function update_requirements_status( FeatureRequirementsStatus $status ) {
		$max_failures_count = $this->get_max_failures_count();

		$transient_timeout        = get_option( '_transient_timeout_' . $this->get_failures_transient_key() );
		$time_remaining           = $transient_timeout ? $transient_timeout - time() : 0;
		$time_remaining_formatted = human_readable_duration( gmdate( 'H:i:s', $time_remaining ) );

		$retry_url = add_query_arg(
			[
				'ep_reset_failures'       => $this->slug,
				'ep_reset_failures_nonce' => wp_create_nonce( 'ep_reset_failures_nonce' ),
			]
		);

		$status->code      = 3;
		$status->message[] = wp_sprintf(
			/* translators: 1: Maximum number of failures, 2: Time remaining */
			__( 'The feature has been temporarily disabled after %1$d failures. It will be re-enabled in %2$s. Alternatively, you can <a href="%3$s">reset the counter and re-activate the feature now</a>.', 'elasticpress' ),
			$max_failures_count,
			$time_remaining_formatted,
			$retry_url
		);

		return $status;
	}

	/**
	 * Get the maximum number of failures allowed.
	 *
	 * @return int
	 */
	protected function get_max_failures_count(): int {
		/**
		 * Filter the maximum number of failures allowed.
		 *
		 * @hook ep_max_failures_count
		 * @since 5.4.0
		 * @param {int}     $max_failures_count The maximum number of failures allowed. Default is 3.
		 * @param {Feature} $feature            The feature object.
		 * @return {int} The maximum number of failures allowed.
		 */
		return (int) apply_filters( 'ep_max_failures_count', 3, $this );
	}

	/**
	 * Get the timeframe for the failures.
	 *
	 * @return int
	 */
	protected function get_max_failures_timeframe(): int {
		/**
		 * Filter the timeframe for the failures.
		 *
		 * @hook ep_max_failures_timeframe
		 * @since 5.4.0
		 * @param {int}     $max_failures_timeframe The timeframe for the failures. Default is 1 hour.
		 * @param {Feature} $feature                The feature object.
		 * @return {int} The timeframe for the failures.
		 */
		return (int) apply_filters( 'ep_max_failures_timeframe', HOUR_IN_SECONDS, $this );
	}

	/**
	 * Get the transient key for the failures.
	 *
	 * @return string
	 */
	protected function get_failures_transient_key(): string {
		/**
		 * Filter the transient key for the failures.
		 *
		 * @hook ep_failures_transient_key
		 * @since 5.4.0
		 * @param {string}  $transient_key The transient key for the failures. Default is "ep_{$feature->slug}_failures".
		 * @param {Feature} $feature       The feature object.
		 * @return {string} The transient key for the failures.
		 */
		return apply_filters( 'ep_failures_transient_key', "ep_{$this->slug}_failures", $this );
	}

	/**
	 * Get the minimum time between failures storage (after the limit is hit).
	 *
	 * @return int
	 */
	protected function get_failures_min_time_between_writes(): int {
		$default_min_time_between_writes = wp_using_ext_object_cache() ? 1 : 10;

		/**
		 * Filter the minimum time between failures storage (after the limit is hit).
		 *
		 * @hook ep_failures_min_time_between_writes
		 * @since 5.4.0
		 * @param {int}     $min_time_between_writes The minimum time between failures storage (after the limit is hit). Default is 1 seconds if using external object cache, 10 seconds otherwise.
		 * @param {Feature} $feature                 The feature object.
		 * @return {int} The minimum time between failures storage (after the limit is hit).
		 */
		return (int) apply_filters( 'ep_failures_min_time_between_writes', $default_min_time_between_writes, $this );
	}

	/**
	 * Update the failures count.
	 *
	 * @return void
	 */
	protected function update_failures_count() {
		$transient_key = $this->get_failures_transient_key();

		$stored   = get_transient( $transient_key );
		$failures = is_array( $stored ) ? $stored : [];

		if ( count( $failures ) > $this->get_max_failures_count() ) {
			$time_since_last_failure = time() - max( $failures );

			if ( $time_since_last_failure < $this->get_failures_min_time_between_writes() ) {
				return;
			}
		}

		$failures[] = time();
		$failures   = $this->cleanup_failures( $failures );

		$lock_ttl = max( 1, (int) $this->get_failures_min_time_between_writes() );
		if ( wp_using_ext_object_cache() && ! wp_cache_add( $transient_key . '_lock', 1, 'transient', $lock_ttl ) ) {
			return;
		}

		set_transient(
			$transient_key,
			$failures,
			$this->get_max_failures_timeframe()
		);

		delete_transient( $transient_key . '_lock' );
	}

	/**
	 * Cleanup the failures.
	 *
	 * @param array $failures The failures.
	 * @return array The cleaned failures.
	 */
	protected function cleanup_failures( $failures ) {
		$max_allowed_time = time() - $this->get_max_failures_timeframe();

		$failures = array_filter(
			$failures,
			function ( $failure_time ) use ( $max_allowed_time ) {
				return $failure_time > $max_allowed_time;
			}
		);

		// To avoid bloating the transient, we only keep the last $max_failures_count + 1 failures.
		$failures = array_slice( $failures, - ( $this->get_max_failures_count() + 1 ) );

		return $failures;
	}
}
