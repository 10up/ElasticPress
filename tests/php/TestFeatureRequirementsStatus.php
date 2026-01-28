<?php
/**
 * Test the FeatureRequirementsStatus class.
 *
 * @since 5.3.3
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress\FeatureRequirementsStatus;

/**
 * Feature requirements status test class
 */
class TestFeatureRequirementsStatus extends BaseTestCase {
	/**
	 * Test the get_message method.
	 *
	 * @since 5.3.3
	 * @group feature-requirements-status
	 */
	public function test_get_message() {
		$feature = new FeatureTest();
		$status  = new FeatureRequirementsStatus( 0, 'Test message', $feature );
		$this->assertEquals( [ 'Test message' ], $status->get_message() );

		$change_message = function ( $message, $status_feature ) use ( $feature ) {
			$this->assertEquals( $feature, $status_feature->get_feature() );
			return [ 'Changed message' ];
		};
		add_filter( 'ep_feature_requirements_status_message', $change_message, 10, 2 );
		$this->assertEquals( [ 'Changed message' ], $status->get_message() );
	}

	/**
	 * Test the get_code method.
	 *
	 * @since 5.3.3
	 * @group feature-requirements-status
	 */
	public function test_get_code() {
		$feature = new FeatureTest();
		$status  = new FeatureRequirementsStatus( 0, 'Test message', $feature );
		$this->assertEquals( 0, $status->get_code() );

		$change_code = function ( $code, $status_feature ) use ( $feature ) {
			$this->assertEquals( $feature, $status_feature->get_feature() );
			return 1;
		};
		add_filter( 'ep_feature_requirements_status_code', $change_code, 10, 2 );
		$this->assertEquals( 1, $status->get_code() );
	}

	/**
	 * Test the get_feature method.
	 *
	 * @since 5.3.3
	 * @group feature-requirements-status
	 */
	public function test_get_feature() {
		$original_feature = new FeatureTest();
		$changed_feature  = new FeatureTest();
		$status           = new FeatureRequirementsStatus( 0, 'Test message', $original_feature );
		$this->assertEquals( $original_feature, $status->get_feature() );

		$change_feature = function ( $feature, $status_feature ) use ( $original_feature, $changed_feature, $status ) {
			$this->assertEquals( $original_feature, $feature );
			$this->assertEquals( $status_feature, $status );
			return $changed_feature;
		};
		add_filter( 'ep_feature_requirements_status_feature', $change_feature, 10, 2 );
		$this->assertEquals( $changed_feature, $status->get_feature() );
	}
}
