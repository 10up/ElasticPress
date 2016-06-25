<?php

class EPTestSearchModule extends EP_Test_Base {

	/**
	 * Setup each test.
	 *
	 * @since 2.1
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
	 * @since 2.1
	 */
	public function tearDown() {
		parent::tearDown();

		//make sure no one attached to this
		remove_filter( 'ep_sync_terms_allow_hierarchy', array( $this, 'ep_allow_multiple_level_terms_sync' ), 100 );
		$this->fired_actions = array();
	}

	/**
	 * Test that search is off by default
	 *
	 * @since 2.1
	 */
	public function testSearchOff() {
		$post_ids = array();

		ep_create_and_sync_post();
		ep_create_and_sync_post();
		ep_create_and_sync_post( array( 'post_content' => 'findme' ) );

		ep_refresh_index();

		add_action( 'ep_wp_query_search', array( $this, 'action_wp_query_search' ), 10, 0 );

		$args = array(
			's' => 'findme',
		);

		$query = new WP_Query( $args );

		$this->assertTrue( empty( $this->fired_actions['ep_wp_query_search'] ) );
	}
}
