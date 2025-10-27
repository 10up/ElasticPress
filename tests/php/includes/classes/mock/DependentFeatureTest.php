<?php
/**
 * Dependent feature test class
 *
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress;

/**
 * Dependent feature test class that requires another feature
 */
class DependentFeatureTest extends ElasticPress\Feature {
	/**
	 * Track if setup was called
	 *
	 * @var bool
	 */
	public $setup_called = false;

	/**
	 * Create dependent feature test class
	 */
	public function __construct() {
		$this->slug                     = 'dependent_test';
		$this->title                    = 'Dependent Test';
		$this->requires_feature         = 'test';
		$this->requires_install_reindex = false;

		parent::__construct();
	}

	/**
	 * Return requirement status
	 *
	 * @return ElasticPress\FeatureRequirementsStatus
	 */
	public function requirements_status() {
		$on = get_site_option( 'ep_dependent_test_feature_on', 0 );

		$status = new ElasticPress\FeatureRequirementsStatus( $on );

		return $status;
	}

	/**
	 * Track setup calls
	 */
	public function setup() {
		$this->setup_called = true;
	}

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
		];
	}
}
