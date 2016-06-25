<?php

class EPTestRelatedPostsModule extends EP_Test_Base {

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
	 * Log action usage for tests
	 *
	 * @since  2.1
	 */
	public function action_ep_related_html_attached() {
		$this->fired_actions['ep_related_html_attached'] = true;
	}

	/**
	 * Test that related posts is off
	 *
	 * @since 2.1
	 */
	public function testRelatedPostsOff() {
		delete_option( 'ep_active_modules' );

		$post_ids = array();

		ep_create_and_sync_post();

		ep_refresh_index();

		add_action( 'ep_related_html_attached', array( $this, 'action_ep_related_html_attached' ), 10, 0 );

		$args = array(
			'ep_integrate' => true,
		);

		$query = new WP_Query( $args );

		ob_start();
		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();

				the_content();
			}
		}
		ob_get_clean();

		$this->assertTrue( empty( $this->fired_actions['ep_related_html_attached'] ) );
	}

	/**
	 * Test that related posts is on
	 *
	 * @since 2.1
	 */
	public function testRelatedPostsOn() {
		$post_ids = array();

		ep_create_and_sync_post();

		ep_refresh_index();

		ep_activate_module( 'related_posts' );

		EP_Modules::factory()->setup_modules();

		add_action( 'ep_related_html_attached', array( $this, 'action_ep_related_html_attached' ), 10 );

		$args = array(
			'ep_integrate' => true,
		);

		$query = new WP_Query( $args );

		ob_start();
		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();

				the_content();
			}
		}
		ob_get_clean();

		$this->assertTrue( ! empty( $this->fired_actions['ep_related_html_attached'] ) );
	}
}
