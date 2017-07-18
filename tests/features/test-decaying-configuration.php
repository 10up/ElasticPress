<?php

class EPTestDecayingConfiguration extends EP_Test_Base {
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

		/**
		 * Most of our search test are bundled into core tests for legacy reasons
		 */
		ep_activate_feature( 'search' );
		EP_Features::factory()->setup_features();

		// Need to call this since it's hooked to init
		ep_search_setup();
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
	 * Test if decaying is enabled.
	 *
	 * @since 2.4
	 */
	public function testDecayingEnabled() {
		delete_site_option( 'ep_feature_requirement_statuses' );
		delete_site_option( 'ep_feature_settings' );

		EP_Features::factory()->handle_feature_activation();
		EP_Features::factory()->setup_features();

		ep_update_feature( 'search', array(
			'active'           => true,
			'decaying_enabled' => true,
		) );

		ep_create_and_sync_post( array( 'post_content' => 'findme test 1', 'tags_input' => array( 'one', 'two' ) ) );
		ep_refresh_index();

		add_filter( 'ep_formatted_args', array( $this, 'catch_ep_formatted_args' ) );
		$query = new WP_Query( array(
			's' => 'test',
		) );
		$this->assertTrue( isset( $this->fired_actions['ep_formatted_args'] ) );
		$this->assertTrue( isset(
			$this->fired_actions['ep_formatted_args']['query'],
			$this->fired_actions['ep_formatted_args']['query']['function_score'],
			$this->fired_actions['ep_formatted_args']['query']['function_score']['exp'],
			$this->fired_actions['ep_formatted_args']['query']['function_score']['exp']['post_date_gmt'],
			$this->fired_actions['ep_formatted_args']['query']['function_score']['exp']['post_date_gmt']['scale'],
			$this->fired_actions['ep_formatted_args']['query']['function_score']['exp']['post_date_gmt']['decay'],
			$this->fired_actions['ep_formatted_args']['query']['function_score']['exp']['post_date_gmt']['offset']
		) );
		$this->assertEquals( '14d', $this->fired_actions['ep_formatted_args']['query']['function_score']['exp']['post_date_gmt']['scale'] );
		$this->assertEquals( '7d', $this->fired_actions['ep_formatted_args']['query']['function_score']['exp']['post_date_gmt']['offset'] );
		$this->assertEquals( 0.25, (float)$this->fired_actions['ep_formatted_args']['query']['function_score']['exp']['post_date_gmt']['decay'] );
	}

	/**
	 * Test if decaying is disabled.
	 *
	 * @since 2.4
	 */
	public function testDecayingDisabled() {
		delete_site_option( 'ep_feature_requirement_statuses' );
		delete_site_option( 'ep_feature_settings' );

		EP_Features::factory()->handle_feature_activation();
		EP_Features::factory()->setup_features();

		ep_update_feature( 'search', array(
			'active'           => true,
			'decaying_enabled' => false,
		) );

		ep_create_and_sync_post( array( 'post_content' => 'findme test 1', 'tags_input' => array( 'one', 'two' ) ) );
		ep_refresh_index();

		add_filter( 'ep_formatted_args', array( $this, 'catch_ep_formatted_args' ) );
		$query = new WP_Query( array(
			's' => 'test',
		) );
		$this->assertTrue( isset( $this->fired_actions['ep_formatted_args'] ) );
		$this->assertTrue( ! isset(
			$this->fired_actions['ep_formatted_args']['query']['function_score'],
			$this->fired_actions['ep_formatted_args']['query']['function_score']['exp'],
			$this->fired_actions['ep_formatted_args']['query']['function_score']['exp']['post_date_gmt'],
			$this->fired_actions['ep_formatted_args']['query']['function_score']['exp']['post_date_gmt']['scale'],
			$this->fired_actions['ep_formatted_args']['query']['function_score']['exp']['post_date_gmt']['decay'],
			$this->fired_actions['ep_formatted_args']['query']['function_score']['exp']['post_date_gmt']['offset']
		) );
		$this->assertTrue( isset(
			$this->fired_actions['ep_formatted_args']['query']['bool'],
			$this->fired_actions['ep_formatted_args']['query']['bool']['should']
		) );
	}

	/**
	 * Catch ES query args.
	 *
	 * @param array $args ES query args.
	 */
	public function catch_ep_formatted_args( $args ) {
		$this->fired_actions['ep_formatted_args'] = $args;
	}
}
