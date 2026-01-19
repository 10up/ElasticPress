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
	 * Track if setup was called
	 *
	 * @var bool
	 */
	public $setup_called = false;

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

		$status = new ElasticPress\FeatureRequirementsStatus( $on, null, $this );

		return $status;
	}

	/**
	 * Do nothing
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

	/**
	 * Pre-handle feature activation
	 *
	 * Changes the status code of FeatureTestB to 3
	 *
	 * @since 5.3.3
	 * @return void
	 */
	public function pre_handle_feature_activation() {
		add_filter( 'ep_feature_requirements_status_code', [ $this, 'change_feature_b_code' ], 10, 2 );
	}

	/**
	 * Change the status code of FeatureTestB to 3
	 *
	 * @since 5.3.3
	 * @param int                       $code   The status code
	 * @param FeatureRequirementsStatus $status The feature requirements status
	 * @return int The new status code
	 */
	public function change_feature_b_code( $code, $status ) {
		if ( $status->get_feature()->slug === 'test-b' ) {
			return 3;
		}
		return $code;
	}
}
