<?php

class EPTestCore extends WP_UnitTestCase {

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
	 * Recursive version of PHP's in_array
	 *
	 * @todo Max recursion restriction
	 * @since 0.1.2
	 * @param mixed $needle
	 * @param array $haystack
	 * @return bool
	 */
	private function _deepInArray( $needle, $haystack ) {
		if ( in_array( $needle, $haystack, true ) ) {
			return true;
		}

		$result = false;

		foreach ( $haystack as $new_haystack ) {
			if ( is_array( $new_haystack ) ) {
				$result = $result || $this->_deepInArray( $needle, $new_haystack );
			}
		}

		return $result;
	}

	/**
	 * Create a WP post and "sync" it to Elasticsearch. We are mocking the sync
	 *
	 * @param array $post_args
	 * @param array $post_meta
	 * @param int $site_id
	 * @since 0.1.2
	 * @return int|WP_Error
	 */
	protected function _createAndSyncPost( $post_args = array(), $post_meta = array(), $site_id = null ) {
		if ( $site_id != null ) {
			switch_to_blog( $site_id );
		}

		$post_types = ep_get_indexable_post_types();

		$args = wp_parse_args( array(
			'post_type' => array_values( $post_types )[0],
			'author' => 1,
			'post_status' => 'publish',
			'post_title' => 'Test Post ' . time(),
		), $post_args );

		$post_id = wp_insert_post( $args );

		// Quit if we have a WP_Error object
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		if ( ! empty( $post_meta ) ) {
			foreach ( $post_meta as $key => $value ) {
				// No need for sanitization here
				update_post_meta( $post_id, $key, $value );
			}
		}

		// Force a re-sync
		wp_update_post( array( 'ID' => $post_id ) );

		if ( $site_id != null ) {
			restore_current_blog();
		}

		return $post_id;
	}

	/**
	 * Setup single site for testing
	 *
	 * @since 0.9
	 */
	private function _setupSingleSite() {
		ep_flush();
		ep_put_mapping();
	}

	/**
	 * Setup multisite for testing
	 *
	 * @param int $number_of_sites
	 * @param int $user_id
	 * @since 0.9
	 */
	private function _setupMultiSite( $number_of_sites = 3, $user_id = null ) {
		if ( null === $user_id ) {
			$user_id = get_current_user_id();
		}

		$this->factory->blog->create_many( $number_of_sites, array( 'user_id' => $user_id ) );

		$sites = wp_get_sites();

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			ep_flush();
			ep_put_mapping();

			restore_current_blog();
		}
	}

	/**
	 * Test a simple post sync
	 *
	 * @since 0.9
	 */
	public function testSingleSitePostSync() {
		$this->_setupSingleSite();

		add_action( 'ep_sync_on_transition', function() {
			$this->fired_actions['ep_sync_on_transition'] = true;
		}, 10, 0 );

		$post_id = $this->_createAndSyncPost();

		$this->assertTrue( ! empty( $this->fired_actions['ep_sync_on_transition'] ) );

		$post = ep_get_post( $post_id );
		$this->assertTrue( ! empty( $post ) );
	}

	/**
	 * Test a simple post sync
	 *
	 * @since 0.9
	 */
	public function testMultiSitePostSync() {
		$this->_setupMultiSite();

		$sites = wp_get_sites();

		foreach( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			add_action( 'ep_sync_on_transition', function() {
				$this->fired_actions['ep_sync_on_transition'] = true;
			}, 10, 0 );

			$post_id = $this->_createAndSyncPost();

			$this->assertTrue( ! empty( $this->fired_actions['ep_sync_on_transition'] ) );

			$post = ep_get_post( $post_id );
			$this->assertTrue( ! empty( $post ) );

			$this->fired_actions = array();

			restore_current_blog();
		}
	}

	/**
	 * Test WP Query integration basic in single site
	 *
	 * @since 0.9
	 */
	public function testSingleSiteWPQuery() {
		$post_ids = array();

		$post_ids[0] = $this->_createAndSyncPost();
		$post_ids[1] = $this->_createAndSyncPost();
		$post_ids[2] = $this->_createAndSyncPost( array( 'post_content' => 'findme' ) );
		$post_ids[3] = $this->_createAndSyncPost();
		$post_ids[4] = $this->_createAndSyncPost( array( 'post_content' => 'findme' ) );

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