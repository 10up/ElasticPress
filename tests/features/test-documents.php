<?php

class EPTestDocumentsFeature extends EP_Test_Base {

	/**
	 * Setup each test.
	 *
	 * @since 2.3
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
	 */
	public function tearDown() {
		parent::tearDown();

		//make sure no one attached to this
		remove_filter( 'ep_sync_terms_allow_hierarchy', array( $this, 'ep_allow_multiple_level_terms_sync' ), 100 );
		$this->fired_actions = array();
	}

	/**
	 * Test that search is integrating with allowed mime type
	 *
	 * @since 2.3
	 * @group documents
	 */
	public function testSearchAllowedMimeType() {
		ep_activate_feature( 'search' );
		ep_activate_feature( 'documents' );
		EP_Features::factory()->setup_features();

		// Need to call this since it's hooked to init
		ep_search_setup();
		ep_documents_setup();

		$post_ids = array();

		ep_create_and_sync_post();
		ep_create_and_sync_post();
		ep_create_and_sync_post( array( 'post_content' => 'findme', 'post_type' => 'attachment', 'post_mime_type' => 'application/msword' ) );

		ep_refresh_index();

		$args = array(
			's' => 'findme',
			'post_type' => array( 'post', 'attachment' ),
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 1, count( $query->posts ) );
	}
	
	/**
	 * Test that search isn't integrating with disallowed mime type
	 *
	 * @since 2.3
	 * @group documents
	 */
	public function testSearchDisallowedMimeType() {
		ep_activate_feature( 'search' );
		ep_activate_feature( 'documents' );
		EP_Features::factory()->setup_features();

		// Need to call this since it's hooked to init
		ep_search_setup();
		ep_documents_setup();

		$post_ids = array();

		ep_create_and_sync_post();
		ep_create_and_sync_post( array( 'post_content' => 'image', 'post_type' => 'attachment', 'post_mime_type' => 'image' ) );
		ep_create_and_sync_post( array( 'post_content' => 'findme', 'post_type' => 'attachment', 'post_mime_type' => 'bad' ) );

		ep_refresh_index();

		$args = array(
			's' => 'findme',
			'post_type' => array( 'post', 'attachment' ),
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 0, count( $query->posts ) );
	}

	/**
	 * Test finding only a normal post
	 *
	 * @since 2.3
	 * @group documents
	 */
	public function testSearchNormalPost() {
		ep_activate_feature( 'search' );
		ep_activate_feature( 'documents' );
		EP_Features::factory()->setup_features();

		// Need to call this since it's hooked to init
		ep_search_setup();
		ep_documents_setup();

		$post_ids = array();

		ep_create_and_sync_post( array( 'post_content' => 'findme', 'post_type' => 'post' ) );
		ep_create_and_sync_post( array( 'post_content' => '', 'post_type' => 'attachment', 'post_mime_type' => 'image' ) );
		ep_create_and_sync_post( array( 'post_content' => '', 'post_type' => 'attachment', 'post_mime_type' => 'bad' ) );

		ep_refresh_index();

		$args = array(
			's' => 'findme',
			'post_type' => array( 'post', 'attachment' ),
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 1, count( $query->posts ) );
	}
}
