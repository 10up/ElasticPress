<?php

class EPTestbbPressModule extends EP_Test_Base {

	/**
	 * Setup each test.
	 *
	 * @since 2.1
	 * @group bbpress
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

		delete_option( 'ep_active_modules' );
	}

	/**
	 * Test that search is off by default
	 *
	 * @since 2.1
	 * @group bbpress
	 */
	public function testSearchOff() {
		EP_Modules::factory()->setup_modules();
		
		$post_ids = array();

		ep_create_and_sync_post();
		ep_create_and_sync_post();
		ep_create_and_sync_post( array( 'post_content' => 'findme', 'post_type' => 'topic' ) );

		ep_refresh_index();

		add_action( 'ep_wp_query_search', array( $this, 'action_wp_query_search' ), 10, 0 );

		$args = array(
			's' => 'findme',
			'post_type' => 'topic',
		);

		$query = new WP_Query( $args );

		$this->assertTrue( empty( $this->fired_actions['ep_wp_query_search'] ) );
	}

	/**
	 * Test that search is on
	 *
	 * @since 2.1
	 * @group bbpress
	 */
	public function testSearchOn() {
		ep_activate_module( 'bbpress' );
		EP_Modules::factory()->setup_modules();

		$GLOBALS['wp_query']->set( 'bbp_search', true );

		$post_ids = array();

		ep_create_and_sync_post();
		ep_create_and_sync_post();
		ep_create_and_sync_post( array( 'post_content' => 'findme', 'post_type' => 'topic' ) );

		ep_refresh_index();

		add_action( 'ep_wp_query_search', array( $this, 'action_wp_query_search' ), 10, 0 );

		$args = array(
			's' => 'findme',
			'post_type' => 'topic',
		);

		$query = new WP_Query( $args );

		$this->assertTrue( ! empty( $this->fired_actions['ep_wp_query_search'] ) );
	}
}
