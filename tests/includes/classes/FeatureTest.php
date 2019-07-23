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
}
