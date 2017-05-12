<?php

class EPTestFeatureActivation extends EP_Test_Base {
	/**
	 * Checking if HTTP request returns 404 status code.
	 * 
	 * @var boolean
	 */
	public $is_404 = false;

	/**
	 * Setup each test.
	 *
	 * @since 2.2
	 */
	public function setUp() {
		global $wpdb;
		parent::setUp();
		$wpdb->suppress_errors();

		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );

		wp_set_current_user( $admin_id );

		ep_delete_index();
		ep_put_mapping();

		EP_WP_Query_Integration::factory()->setup();
		EP_Sync_Manager::factory()->setup();
		EP_Sync_Manager::factory()->sync_post_queue = array();

		$this->setup_test_post_type();
	}

	/**
	 * Clean up after each test. Reset our mocks
	 *
	 * @since 2.2
	 */
	public function tearDown() {
		parent::tearDown();

		//make sure no one attached to this
		remove_filter( 'ep_sync_terms_allow_hierarchy', array( $this, 'ep_allow_multiple_level_terms_sync' ), 100 );
		$this->fired_actions = array();
	}

	/**
	 * Make sure no feature settings or req statuses exist at the start.
	 * 
	 * @group feature-activation
	 * @since  2.2
	 */
	public function testNoActiveFeatures() {
		delete_site_option( 'ep_feature_requirement_statuses' );
		delete_site_option( 'ep_feature_settings' );

		EP_Features::factory()->setup_features();

		foreach ( EP_Features::factory()->registered_features as $feature ) {
			$this->assertEquals( false, $feature->is_active() );
		}
	}

	/**
	 * Test that default EP features are auto-activated
	 * 
	 * @group feature-activation
	 * @since  2.2
	 */
	public function testAutoActivated() {
		delete_site_option( 'ep_feature_requirement_statuses' );
		delete_site_option( 'ep_feature_settings' );

		EP_Features::factory()->handle_feature_activation();
		EP_Features::factory()->setup_features();

		$this->assertEquals( true, EP_Features::factory()->registered_features['search']->is_active() );
		$this->assertEquals( 0, EP_Features::factory()->registered_features['search']->requirements_status()->code );

		$this->assertEquals( false, EP_Features::factory()->registered_features['protected_content']->is_active() );
		$this->assertEquals( 1, EP_Features::factory()->registered_features['protected_content']->requirements_status()->code );

		$this->assertEquals( true, EP_Features::factory()->registered_features['woocommerce']->is_active() );
		$this->assertEquals( 0, EP_Features::factory()->registered_features['woocommerce']->requirements_status()->code );

		$this->assertEquals( true, EP_Features::factory()->registered_features['related_posts']->is_active() );
		$this->assertEquals( 0, EP_Features::factory()->registered_features['related_posts']->requirements_status()->code );
	}

	/**
	 * Test that default EP requirement statuses are set properly
	 * 
	 * @group feature-activation
	 * @since  2.2
	 */
	public function testRequirementStatuses() {
		delete_site_option( 'ep_feature_requirement_statuses' );
		delete_site_option( 'ep_feature_settings' );

		EP_Features::factory()->handle_feature_activation();
		EP_Features::factory()->setup_features();

		$requirements_statuses = get_site_option( 'ep_feature_requirement_statuses' );

		$this->assertEquals( 0, $requirements_statuses['search'] );
		$this->assertEquals( 1, $requirements_statuses['protected_content'] );
		$this->assertEquals( 0, $requirements_statuses['related_posts'] );
		$this->assertEquals( 0, $requirements_statuses['woocommerce'] );
	}

	/**
	 * Test that a simple EP feature is registered and auto-activated properly
	 * 
	 * @group feature-activation
	 * @since  2.2
	 */
	public function testAutoActivateWithSimpleFeature() {
		delete_site_option( 'ep_feature_requirement_statuses' );
		delete_site_option( 'ep_feature_settings' );

		ep_register_feature( 'test', array(
			'title' => 'Test',
			'requires_install_reindex' => true,
		) );

		EP_Features::factory()->handle_feature_activation();
		EP_Features::factory()->setup_features();

		$this->assertEquals( true, EP_Features::factory()->registered_features['test']->is_active() );
		$this->assertEquals( 0, EP_Features::factory()->registered_features['test']->requirements_status()->code );
	}

	public function simple_feature_requirements_status_cb() {
		$on = get_site_option( 'ep_test_feature_on', 2 );

		$status = new EP_Feature_Requirements_Status( $on );

		return $status;
	}

	/**
	 * Test that a simple EP feature is registered and auto-activated properly. Also
	 * test that when it's req status changes, it's enabled.
	 * 
	 * @group feature-activation
	 * @since  2.2
	 */
	public function testAutoActivateWithFeature() {
		delete_site_option( 'ep_feature_requirement_statuses' );
		delete_site_option( 'ep_feature_settings' );

		ep_register_feature( 'test', array(
			'title' => 'Test',
			'requires_install_reindex' => true,
			'requirements_status_cb' => array( $this, 'simple_feature_requirements_status_cb' ),
		) );

		EP_Features::factory()->handle_feature_activation();
		EP_Features::factory()->setup_features();

		$requirements_statuses = get_site_option( 'ep_feature_requirement_statuses' );

		$this->assertEquals( false, EP_Features::factory()->registered_features['test']->is_active() );
		$this->assertEquals( 2, EP_Features::factory()->registered_features['test']->requirements_status()->code );
		$this->assertEquals( 2, $requirements_statuses['test'] );

		update_site_option( 'ep_test_feature_on', 0 );

		EP_Features::factory()->handle_feature_activation();

		$requirements_statuses = get_site_option( 'ep_feature_requirement_statuses' );

		$this->assertEquals( true, EP_Features::factory()->registered_features['test']->is_active() );
		$this->assertEquals( 0, EP_Features::factory()->registered_features['test']->requirements_status()->code );
		$this->assertEquals( 0, $requirements_statuses['test'] );
	}

	/**
	 * Test that a simple EP feature is registered and auto-activated properly. Also
	 * test that when it's req status changes, it's disabled.
	 * 
	 * @group feature-activation
	 * @since  2.2
	 */
	public function testAutoDeactivateWithFeature() {
		delete_site_option( 'ep_feature_requirement_statuses' );
		delete_site_option( 'ep_feature_settings' );

		ep_register_feature( 'test', array(
			'title' => 'Test',
			'requires_install_reindex' => true,
			'requirements_status_cb' => array( $this, 'simple_feature_requirements_status_cb' ),
		) );

		update_site_option( 'ep_test_feature_on', 0 );

		EP_Features::factory()->handle_feature_activation();
		EP_Features::factory()->setup_features();

		$requirements_statuses = get_site_option( 'ep_feature_requirement_statuses' );

		$this->assertEquals( true, EP_Features::factory()->registered_features['test']->is_active() );
		$this->assertEquals( 0, EP_Features::factory()->registered_features['test']->requirements_status()->code );
		$this->assertEquals( 0, $requirements_statuses['test'] );

		update_site_option( 'ep_test_feature_on', 2 );

		EP_Features::factory()->handle_feature_activation();

		$requirements_statuses = get_site_option( 'ep_feature_requirement_statuses' );

		$this->assertEquals( false, EP_Features::factory()->registered_features['test']->is_active() );
		$this->assertEquals( 2, EP_Features::factory()->registered_features['test']->requirements_status()->code );
		$this->assertEquals( 2, $requirements_statuses['test'] );
	}

	/**
	 * Test that a simple EP feature is registered and auto-activated properly. Test that when the req
	 * status changes to 1, nothing happens.
	 * 
	 * @group feature-activation
	 * @since  2.2
	 */
	public function testReqChangeNothingWithFeature() {
		delete_site_option( 'ep_feature_requirement_statuses' );
		delete_site_option( 'ep_feature_settings' );

		ep_register_feature( 'test', array(
			'title' => 'Test',
			'requires_install_reindex' => true,
			'requirements_status_cb' => array( $this, 'simple_feature_requirements_status_cb' ),
		) );

		update_site_option( 'ep_test_feature_on', 0 );

		EP_Features::factory()->handle_feature_activation();
		EP_Features::factory()->setup_features();

		$requirements_statuses = get_site_option( 'ep_feature_requirement_statuses' );

		$this->assertEquals( true, EP_Features::factory()->registered_features['test']->is_active() );
		$this->assertEquals( 0, EP_Features::factory()->registered_features['test']->requirements_status()->code );
		$this->assertEquals( 0, $requirements_statuses['test'] );

		update_site_option( 'ep_test_feature_on', 1 );

		EP_Features::factory()->handle_feature_activation();

		$requirements_statuses = get_site_option( 'ep_feature_requirement_statuses' );

		$this->assertEquals( true, EP_Features::factory()->registered_features['test']->is_active() );
		$this->assertEquals( 1, EP_Features::factory()->registered_features['test']->requirements_status()->code );
		$this->assertEquals( 1, $requirements_statuses['test'] );
	}
}
