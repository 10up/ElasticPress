<?php

class EPTestRelatedPostsFeature extends EP_Test_Base {

	/**
	 * Setup each test.
	 *
	 * @since 2.1
	 * @group related_posts
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
	 * Log action usage for tests
	 *
	 * @since  2.1
	 */
	public function action_ep_related_html_attached() {
		$this->fired_actions['ep_related_html_attached'] = true;
	}
	
	/**
	 * Test for related post args filter
	 *
	 * @group related_posts
	 */
	public function testFindRelatedPostFilter(){
		$post_id = ep_create_and_sync_post( array( 'post_content' => 'findme test 1' ) );
		ep_create_and_sync_post( array( 'post_content' => 'findme test 2' ) );
		ep_create_and_sync_post( array( 'post_content' => 'findme test 3', 'post_type' => 'page' ) );
		
		ep_refresh_index();
		
		ep_activate_feature( 'related_posts' );
		
		EP_Features::factory()->setup_features();

		$related = ep_find_related( $post_id );
		$this->assertEquals( 1, sizeof( $related ) );

		add_filter( 'ep_find_related_args', array( $this, 'find_related_posts_filter' ), 10, 1 );
		$related = ep_find_related( $post_id );
		$this->assertEquals( 2, sizeof( $related ) );
		remove_filter( 'ep_find_related_args', array( $this, 'find_related_posts_filter' ), 10, 1 );
	}
	
	/**
	 * Detect EP fire
	 * 
	 * @param $args
	 * @return mixed
	 */
	public function find_related_posts_filter( $args ){
		$args['post_type'] = array( 'post', 'page' );
		
		return $args;
	}
}
