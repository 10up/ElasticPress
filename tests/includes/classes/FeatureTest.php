<?php
/**
 * Feature test class
 *
 * @group elasticpress
 */
namespace ElasticPressTest;

use ElasticPress;

class FeatureTest extends ElasticPress\Feature {
	public function __construct() {
		$this->slug                     = 'test';
		$this->title                    = 'Test';
		$this->requires_install_reindex = true;

		parent::__construct();
	}

	public function requirements_status() {
		$on = get_site_option( 'ep_test_feature_on', 0 );

		$status = new ElasticPress\FeatureRequirementsStatus( $on );

		return $status;
	}

	public function output_feature_box_long() { }

	public function output_feature_box_summary() { }

	public function setup() { }
}
