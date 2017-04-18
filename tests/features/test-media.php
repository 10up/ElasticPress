<?php

class EPTestMediaFeature extends EP_Test_Base {

	/**
	 * Setup each test.
	 *
	 * @since 2.3
	 * @group media
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

		delete_option( 'ep_active_features' );
	}

	/**
	 * Clean up after each test. Reset our mocks
	 *
	 * @since 2.3
	 * @group media
	 */
	public function tearDown() {
		parent::tearDown();

		//make sure no one attached to this
		remove_filter( 'ep_sync_terms_allow_hierarchy', array( $this, 'ep_allow_multiple_level_terms_sync' ), 100 );
		$this->fired_actions = array();
	}
	
}
