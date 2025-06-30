<?php
/**
 * Feature test class
 *
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress;

/**
 * Feature test class
 */
class FeatureTest extends ElasticPress\Feature {
	/**
	 * Create feature test class
	 */
	public function __construct() {
		$this->slug                     = 'test';
		$this->title                    = 'Test';
		$this->requires_install_reindex = true;

		parent::__construct();
	}

	/**
	 * Return requirement status
	 *
	 * @return ElasticPress\FeatureRequirementsStatus
	 */
	public function requirements_status() {
		$on = get_site_option( 'ep_test_feature_on', 0 );

		$status = new ElasticPress\FeatureRequirementsStatus( $on );

		return $status;
	}

	/**
	 * Do nothing
	 */
	public function output_feature_box_long() { }

	/**
	 * Do nothing
	 */
	public function output_feature_box_summary() { }

	/**
	 * Do nothing
	 */
	public function setup() { }

	/**
	 * Set settings schema
	 */
	public function set_settings_schema() {
		$this->settings_schema = [
			[
				'default' => '0',
				'key'     => 'field_1',
				'label'   => 'Field 1',
				'type'    => 'text',
			],
			[
				'default'       => '0',
				'key'           => 'field_2',
				'label'         => 'Field 2',
				'type'          => 'text',
				'requires_sync' => true,
			],
			[
				'default'          => '0',
				'key'              => 'field_3',
				'label'            => 'Field 3',
				'type'             => 'text',
				'requires_feature' => 'did-you-mean',
			],
			[
				'default'          => '0',
				'key'              => 'field_4',
				'label'            => 'Field 4',
				'type'             => 'text',
				'requires_feature' => 'search',
			],
		];
	}
}
