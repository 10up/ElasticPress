<?php

class EPTestMultisite extends WP_UnitTestCase {

	public function __construct() {
		self::$ignore_files = true;
	}

	/**
	 * Helps us keep track of actions that have fired
	 *
	 * @var array
	 * @since 0.1.0
	 */
	protected $fired_actions = array();

	/**
	 * Helps us keep track of applied filters
	 *
	 * @var array
	 * @since 0.1.1
	 */
	protected $applied_filters = array();

	/**
	 * Setup each test.
	 *
	 * @since 0.1.0
	 */
	public function setUp() {
		global $wpdb;
		parent::setUp();
		$wpdb->suppress_errors();

		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );

		$this->factory->blog->create_many( 3, array( 'user_id' => $admin_id ) );

		$sites = wp_get_sites();

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			ep_delete_index();
			ep_put_mapping();

			restore_current_blog();
		}

		wp_set_current_user( $admin_id );

	}

	/**
	 * Clean up after each test. Reset our mocks
	 *
	 * @since 0.1.0
	 */
	public function tearDown() {
		parent::tearDown();

		$this->fired_actions = array();
	}

	/**
	 * Test a simple post sync
	 *
	 * @since 0.9
	 */
	public function testMultiSitePostSync() {
		$sites = wp_get_sites();

		foreach( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			add_action( 'ep_sync_on_transition', function() {
				$this->fired_actions['ep_sync_on_transition'] = true;
			}, 10, 0 );

			$post_id = ep_create_and_sync_post();

			$this->assertTrue( ! empty( $this->fired_actions['ep_sync_on_transition'] ) );

			$post = ep_get_post( $post_id );
			$this->assertTrue( ! empty( $post ) );

			$this->fired_actions = array();

			restore_current_blog();
		}
	}
}