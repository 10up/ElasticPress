<?php
/**
 * Plugin Name: Simulate Instant Results Conflict
 * Description: Simulates a feature conflict where enabling a setting in Did You Mean makes Instant Results unavailable.
 * Version:     1.0.0
 * Author:      10up Inc.
 * License:     GPLv2 or later
 *
 * @package ElasticPress_Tests_E2e
 */

namespace ElasticPress\Tests\E2E;

use ElasticPress\Features;
use ElasticPress\FeatureRequirementsStatus;

/**
 * Add a conflicting setting to the Did You Mean feature settings schema.
 *
 * @param array  $schema Settings schema array.
 * @param string $slug   Feature slug.
 * @return array Modified settings schema.
 */
function add_conflicting_setting_to_schema( $schema, $slug ) {
	if ( 'did-you-mean' !== $slug ) {
		return $schema;
	}

	$schema[] = array(
		'default' => false,
		'key'     => 'conflicting_setting',
		'label'   => __( 'Conflicting Setting', 'elasticpress' ),
		'type'    => 'toggle',
	);

	return $schema;
}
add_filter( 'ep_feature_settings_schema', __NAMESPACE__ . '\add_conflicting_setting_to_schema', 10, 2 );

/**
 * Check if Instant Results feature should be temporarily disabled.
 *
 * Determines if the Instant Results feature should be disabled based on
 * the Did You Mean feature's conflicting setting state.
 *
 * @param \ElasticPress\Feature|null $feature Feature object to check.
 * @return bool True if feature should be disabled, false otherwise.
 */
function should_disable_instant_results( $feature ) {
	// Validate feature object and slug.
	if ( ! $feature || ! isset( $feature->slug ) || 'instant-results' !== $feature->slug ) {
		return false;
	}

	// Get Did You Mean feature.
	$did_you_mean = Features::factory()->get_registered_feature( 'did-you-mean' );
	if ( ! $did_you_mean || ! $did_you_mean->is_active() ) {
		return false;
	}

	// Check if conflicting setting is enabled.
	$settings = $did_you_mean->get_settings();
	if ( empty( $settings['conflicting_setting'] ) ) {
		return false;
	}

	return true;
}

/**
 * Modify feature requirements status code for Instant Results.
 *
 * Sets the Instant Results feature to temporarily disabled status when
 * the conflicting setting in Did You Mean is enabled.
 *
 * @param int                       $code   The feature requirements status code.
 * @param FeatureRequirementsStatus $status The feature requirements status object.
 * @return int Modified status code.
 */
function modify_instant_results_status_code( $code, $status ) {
	if ( ! should_disable_instant_results( $status->get_feature() ) ) {
		return $code;
	}

	// Use TEMPORARILY_DISABLED constant if available, fallback to 3.
	if ( defined( '\ElasticPress\FeatureRequirementsStatus::TEMPORARILY_DISABLED' ) ) {
		return FeatureRequirementsStatus::TEMPORARILY_DISABLED;
	}

	return 3;
}
add_filter( 'ep_feature_requirements_status_code', __NAMESPACE__ . '\modify_instant_results_status_code', 10, 2 );

/**
 * Add temporarily disabled message to Instant Results feature.
 *
 * Appends an explanation message when the Instant Results feature is
 * temporarily disabled due to the conflicting setting.
 *
 * @param string|array              $message The status message(s).
 * @param FeatureRequirementsStatus $status  The feature requirements status object.
 * @return array Modified message array.
 */
function add_instant_results_disabled_message( $message, $status ) {
	if ( ! should_disable_instant_results( $status->get_feature() ) ) {
		return $message;
	}

	$message   = (array) $message;
	$message[] = esc_html__(
		'This feature is temporarily disabled because it is incompatible with the Conflicting Setting enabled in Did You Mean.',
		'elasticpress'
	);

	return $message;
}
add_filter( 'ep_feature_requirements_status_message', __NAMESPACE__ . '\add_instant_results_disabled_message', 10, 2 );
