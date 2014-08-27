<?php

class EPTestSingleSite extends WP_UnitTestCase {

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

		wp_set_current_user( $admin_id );

		ep_flush();
		ep_put_mapping();

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
	public function testSingleSitePostSync() {
		add_action( 'ep_sync_on_transition', function() {
			$this->fired_actions['ep_sync_on_transition'] = true;
		}, 10, 0 );

		$post_id = ep_create_and_sync_post();

		$this->assertTrue( ! empty( $this->fired_actions['ep_sync_on_transition'] ) );

		$post = ep_get_post( $post_id );
		$this->assertTrue( ! empty( $post ) );
	}

	/**
	 * Test WP Query integration basic in single site
	 *
	 * @since 0.9
	 */
	public function testSingleSiteWPQuery() {
		$post_ids = array();

		$post_ids[0] = ep_create_and_sync_post();
		$post_ids[1] = ep_create_and_sync_post();
		$post_ids[2] = ep_create_and_sync_post( array( 'post_content' => 'findme' ) );
		$post_ids[3] = ep_create_and_sync_post();
		$post_ids[4] = ep_create_and_sync_post( array( 'post_content' => 'findme' ) );

		$args = array(
			's' => 'findme',
		);

		add_action( 'ep_wp_query_search', function() {
			$this->fired_actions['ep_wp_query_search'] = true;
		}, 10, 0 );

		$query = new WP_Query( $args );

		$this->assertTrue( ! empty( $this->fired_actions['ep_wp_query_search'] ) );

		$this->assertEquals( $query->post_count, 2 );
		$this->assertEquals( $query->found_posts, 2 );

		// @Todo: make sure posts contain proper info
	}
}