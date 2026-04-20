<?php
/**
 * Plugin Name: Simulate Setting Dependency
 * Description: Registers a feature whose settings schema conditionally changes based on whether Post Search is active. Used to test that settings schema resets on save.
 * Version:     1.0.0
 * Author:      10up Inc.
 * License:     GPLv2 or later
 *
 * @package ElasticPress_Tests_E2e
 */

namespace ElasticPress\Tests\E2E\SettingDependency;

use ElasticPress\Feature;
use ElasticPress\FeatureRequirementsStatus;
use ElasticPress\Features;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A test feature whose settings schema changes based on the Post Search feature's active state.
 *
 * When Post Search is active, additional radio options ("Advanced" and "Expert") appear.
 * When it is inactive, only the base options ("Basic" and "Standard") are shown.
 */
class ConditionalSettingsFeature extends Feature {
	/**
	 * Initialize the feature.
	 */
	public function __construct() {
		$this->slug  = 'test_conditional_settings';
		$this->title = 'Test Conditional Settings';
		$this->group = 'core-search';
		$this->order = 100;

		$this->default_settings = [
			'test_version' => 'basic',
		];

		parent::__construct();
	}

	/**
	 * Set up the feature.
	 *
	 * Hooks into the feature settings option filters so stored values that are
	 * no longer valid (e.g. `advanced` / `expert` when Post Search is
	 * inactive) are normalized back to the default. This mirrors how real
	 * features such as ElasticPress Labs' Search Algorithm feature sanitize
	 * stored values whose options have disappeared, and exercises the value
	 * normalization half of the post-save refresh contract.
	 */
	public function setup() {
		add_filter( 'option_ep_feature_settings', [ $this, 'normalize_stored_value' ] );
		add_filter( 'option_ep_feature_settings_draft', [ $this, 'normalize_stored_value' ] );
	}

	/**
	 * Normalize the stored `test_version` to the default when it is not a
	 * currently-valid option for the active state.
	 *
	 * @param mixed $settings The unfiltered option value.
	 * @return mixed The normalized option value.
	 */
	public function normalize_stored_value( $settings ) {
		if ( ! is_array( $settings ) || empty( $settings[ $this->slug ]['test_version'] ) ) {
			return $settings;
		}

		$allowed       = [ 'basic', 'standard' ];
		$search_active = ! empty( $settings['search']['active'] );
		if ( $search_active ) {
			$allowed = array_merge( $allowed, [ 'advanced', 'expert' ] );
		}

		if ( ! in_array( $settings[ $this->slug ]['test_version'], $allowed, true ) ) {
			$settings[ $this->slug ]['test_version'] = $this->default_settings['test_version'];
		}

		return $settings;
	}

	/**
	 * Requirements status.
	 *
	 * @return FeatureRequirementsStatus
	 */
	public function requirements_status() {
		return new FeatureRequirementsStatus( 1, null, $this );
	}

	/**
	 * Set the i18n strings.
	 */
	public function set_i18n_strings(): void {
		$this->title   = esc_html__( 'Test Conditional Settings', 'elasticpress' );
		$this->summary = esc_html__( 'A test feature with settings that depend on Post Search.', 'elasticpress' );
	}

	/**
	 * Build settings schema with conditional options.
	 *
	 * The "Advanced" and "Expert" options only appear when Post Search is active.
	 */
	protected function set_settings_schema() {
		$options = [
			[
				'label' => esc_html__( 'Basic', 'elasticpress' ),
				'value' => 'basic',
			],
			[
				'label' => esc_html__( 'Standard', 'elasticpress' ),
				'value' => 'standard',
			],
		];

		$search = Features::factory()->get_registered_feature( 'search' );

		if ( $search && $search->is_active() ) {
			$options[] = [
				'label' => esc_html__( 'Advanced (requires Post Search)', 'elasticpress' ),
				'value' => 'advanced',
			];
			$options[] = [
				'label' => esc_html__( 'Expert (requires Post Search)', 'elasticpress' ),
				'value' => 'expert',
			];
		}

		$this->settings_schema = [
			[
				'default' => 'basic',
				'key'     => 'test_version',
				'label'   => esc_html__( 'Version', 'elasticpress' ),
				'options' => $options,
				'type'    => 'radio',
			],
		];
	}
}

add_action(
	'plugins_loaded',
	function () {
		Features::factory()->register_feature( new ConditionalSettingsFeature() );
	},
	11
);
