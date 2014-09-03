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
		$indexes = array();

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			ep_delete_index();
			ep_put_mapping();

			$indexes[] = ep_get_index_name();

			restore_current_blog();
		}


		ep_delete_network_alias();
		ep_create_network_alias( $indexes );

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
	public function testPostSync() {
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

	/**
	 * Test a simple post content search
	 *
	 * @since 0.9
	 */
	public function testWPQuerySearchContent() {
		$sites = wp_get_sites();

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			$post_ids[0] = ep_create_and_sync_post();
			$post_ids[1] = ep_create_and_sync_post();
			$post_ids[2] = ep_create_and_sync_post( array( 'post_content' => 'findme' ) );
			$post_ids[3] = ep_create_and_sync_post();
			$post_ids[4] = ep_create_and_sync_post( array( 'post_content' => 'findme' ) );

			restore_current_blog();
		}

		$args = array(
			's' => 'findme',
			'sites' => 'all',
		);

		add_action( 'ep_wp_query_search', function() {
			$this->fired_actions['ep_wp_query_search'] = true;
		}, 10, 0 );

		$query = new WP_Query( $args );

		$this->assertTrue( ! empty( $this->fired_actions['ep_wp_query_search'] ) );

		$this->assertEquals( $query->post_count, 8 );
		$this->assertEquals( $query->found_posts, 8 );

		$other_site_post_count = 0;
		$original_site_id = get_current_blog_id();

		while ( $query->have_posts() ) {
			$query->the_post();

			global $post;

			$wp_post = get_post( get_the_ID() );

			$this->assertEquals( $post->post_title, get_the_title() );
			$this->assertEquals( $post->post_content, get_the_content() );
			$this->assertEquals( $post->post_date, $wp_post->post_date );
			$this->assertEquals( $post->post_modified, $wp_post->post_modified );
			$this->assertEquals( $post->post_date_gmt, $wp_post->post_date_gmt );
			$this->assertEquals( $post->post_modified_gmt, $wp_post->post_modified_gmt );
			$this->assertEquals( $post->post_name, $wp_post->post_name );
			$this->assertEquals( $post->post_parent, $wp_post->post_parent );
			$this->assertEquals( $post->post_excerpt, $wp_post->post_excerpt );
			$this->assertEquals( $post->site_id, get_current_blog_id() );

			if ( get_current_blog_id() != $original_site_id ) {
				$other_site_post_count++;
			}
		}

		$this->assertEquals( 6, $other_site_post_count );

		wp_reset_postdata();
	}

	/**
	 * Test a simple post title search
	 *
	 * @since 0.9
	 */
	public function testWPQuerySearchTitle() {
		$sites = wp_get_sites();

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			$post_ids[0] = ep_create_and_sync_post();
			$post_ids[1] = ep_create_and_sync_post();
			$post_ids[2] = ep_create_and_sync_post();
			$post_ids[3] = ep_create_and_sync_post();
			$post_ids[4] = ep_create_and_sync_post( array( 'post_title' => 'findme' ) );

			restore_current_blog();
		}

		$args = array(
			's' => 'findme',
			'sites' => 'all',
		);

		add_action( 'ep_wp_query_search', function() {
			$this->fired_actions['ep_wp_query_search'] = true;
		}, 10, 0 );

		$query = new WP_Query( $args );

		$this->assertTrue( ! empty( $this->fired_actions['ep_wp_query_search'] ) );

		$this->assertEquals( $query->post_count, 4 );
		$this->assertEquals( $query->found_posts, 4 );
	}

	/**
	 * Test a simple post excerpt search
	 *
	 * @since 0.9
	 */
	public function testWPQuerySearchExcerpt() {
		$sites = wp_get_sites();

		$i = 0;

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			$post_ids[0] = ep_create_and_sync_post();
			$post_ids[1] = ep_create_and_sync_post();
			$post_ids[2] = ep_create_and_sync_post();
			$post_ids[3] = ep_create_and_sync_post();

			if ( $i > 0 ) {
				$post_ids[4] = ep_create_and_sync_post( array( 'post_excerpt' => 'findme' ) );
			}

			restore_current_blog();

			$i++;
		}

		$args = array(
			's' => 'findme',
			'sites' => 'all',
		);

		add_action( 'ep_wp_query_search', function() {
			$this->fired_actions['ep_wp_query_search'] = true;
		}, 10, 0 );

		$query = new WP_Query( $args );

		$this->assertTrue( ! empty( $this->fired_actions['ep_wp_query_search'] ) );

		$this->assertEquals( $query->post_count, 3 );
		$this->assertEquals( $query->found_posts, 3 );
	}
}