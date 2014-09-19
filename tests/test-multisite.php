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

		$this->factory->blog->create_many( 1, array( 'user_id' => $admin_id ) );

		$sites = ep_get_sites();
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

		EP_WP_Query_Integration::factory()->setup();

	}

	/**
	 * Clean up after each test. Reset our mocks
	 *
	 * @since 0.1.0
	 */
	public function tearDown() {
		parent::tearDown();

		$this->fired_actions = array();

		$sites = ep_get_sites();
		$indexes = array();

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			ep_delete_index();

			restore_current_blog();
		}

		ep_delete_network_alias();
	}

	/**
	 * Test a simple post sync
	 *
	 * @since 0.9
	 */
	public function testPostSync() {
		$sites = ep_get_sites();

		foreach( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			add_action( 'ep_sync_on_transition', function() {
				$this->fired_actions['ep_sync_on_transition'] = true;
			}, 10, 0 );

			$post_id = ep_create_and_sync_post();

			ep_refresh_index();

			$this->assertTrue( ! empty( $this->fired_actions['ep_sync_on_transition'] ) );

			$post = ep_get_post( $post_id );
			$this->assertTrue( ! empty( $post ) );

			$this->fired_actions = array();

			restore_current_blog();
		}
	}

	/**
	 * Test that a post becoming unpublished correctly gets removed from the Elasticsearch index
	 *
	 * @since 0.9.3
	 */
	public function testPostUnpublish() {
		$sites = ep_get_sites();

		foreach( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			add_action( 'ep_delete_post', function() {
				$this->fired_actions['ep_delete_post'] = true;
			}, 10, 0 );

			$post_id = ep_create_and_sync_post();

			ep_refresh_index();

			$post = ep_get_post( $post_id );

			// Ensure that our post made it over to elasticsearch
			$this->assertTrue( ! empty( $post ) );

			// Let's transition the post status from published to draft
			wp_update_post( array( 'ID' => $post_id, 'post_status' => 'draft' ) );

			ep_refresh_index();

			$this->assertTrue( ! empty( $this->fired_actions['ep_delete_post'] ) );

			$post = ep_get_post( $post_id );

			// Alright, now the post has been removed from the index, so this should be empty
			$this->assertTrue( empty( $post ) );

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
		$sites = ep_get_sites();

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			ep_create_and_sync_post( array( 'post_content' => 'findme' ) );
			ep_create_and_sync_post();
			ep_create_and_sync_post( array( 'post_content' => 'findme' ) );

			ep_refresh_index();

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

		$this->assertEquals( 2, $other_site_post_count );

		wp_reset_postdata();
	}

	/**
	 * Test a simple post title search
	 *
	 * @since 0.9
	 */
	public function testWPQuerySearchTitle() {
		$sites = ep_get_sites();

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			ep_create_and_sync_post();
			ep_create_and_sync_post( array( 'post_title' => 'findme' ) );

			ep_refresh_index();

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

		$this->assertEquals( $query->post_count, 2 );
		$this->assertEquals( $query->found_posts, 2 );
	}

	/**
	 * Test a simple post excerpt search
	 *
	 * @since 0.9
	 */
	public function testWPQuerySearchExcerpt() {
		$sites = ep_get_sites();

		$i = 0;

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			ep_create_and_sync_post();

			if ( $i > 0 ) {
				ep_create_and_sync_post( array( 'post_excerpt' => 'findme' ) );
			}

			ep_refresh_index();

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

		$this->assertEquals( $query->post_count, 1 );
		$this->assertEquals( $query->found_posts, 1 );
	}

	/**
	 * Test pagination
	 *
	 * @since 0.9
	 */
	public function testPagination() {
		$sites = ep_get_sites();

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			ep_create_and_sync_post( array( 'post_title' => 'findme' ) );
			ep_create_and_sync_post( array( 'post_title' => 'findme' ) );

			ep_refresh_index();

			restore_current_blog();
		}

		$args = array(
			's' => 'findme',
			'sites' => 'all',
			'posts_per_page' => 2,
		);

		$query = new WP_Query( $args );

		$found_posts = array();

		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 4, $query->found_posts );

		$found_posts[] = $query->posts[0]->site_id . $query->posts[0]->ID;
		$found_posts[] = $query->posts[1]->site_id . $query->posts[1]->ID;

		$args = array(
			's' => 'findme',
			'sites' => 'all',
			'posts_per_page' => 2,
			'paged' => 2,
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 4, $query->found_posts );

		$found_posts[] = $query->posts[0]->site_id . $query->posts[0]->ID;
		$found_posts[] = $query->posts[1]->site_id . $query->posts[1]->ID;

		$this->assertEquals( 4, count( array_unique( $found_posts ) ) );
	}

	/**
	 * Test query restoration after wp_reset_postdata
	 *
	 * @since 0.9.2
	 */
	public function testQueryRestorationResetPostData() {
		$old_blog_id = get_current_blog_id();

		$main_post_id = $this->factory->post->create();

		query_posts( array( 'p' => $main_post_id ) );
		$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];

		$sites = ep_get_sites();

		$i = 0;

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			ep_create_and_sync_post( array( 'post_title' => 'findme' ) );
			ep_create_and_sync_post( array( 'post_title' => 'findme' ) );

			if ( $i > 0 ) {
				ep_create_and_sync_post( array( 'post_title' => 'notfirstblog' ) );
			}

			ep_refresh_index();

			restore_current_blog();

			$i++;
		}

		$args = array(
			's' => 'notfirstblog',
			'sites' => 'all',
		);

		$query = new WP_Query( $args );

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				global $post;
				$query->the_post();

				// do stuff!
			}
		}

		wp_reset_postdata();

		$new_blog_id = get_current_blog_id();

		$this->assertEquals( $old_blog_id, $new_blog_id );
	}

	/**
	 * Test query restoration after wp_reset_query
	 *
	 * @since 0.9.2
	 */
	public function testQueryRestorationResetQuery() {
		$old_blog_id = get_current_blog_id();

		$main_post_id = $this->factory->post->create();

		query_posts( array( 'p' => $main_post_id ) );
		$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];

		$sites = ep_get_sites();

		$i = 0;

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			ep_create_and_sync_post( array( 'post_title' => 'findme' ) );
			ep_create_and_sync_post( array( 'post_title' => 'findme' ) );

			if ( $i > 0 ) {
				ep_create_and_sync_post( array( 'post_title' => 'notfirstblog' ) );
			}

			ep_refresh_index();

			restore_current_blog();

			$i++;
		}

		$args = array(
			's' => 'notfirstblog',
			'sites' => 'all',
		);

		$query = new WP_Query( $args );

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				global $post;
				$query->the_post();

				// do stuff!
			}
		}

		wp_reset_query();

		$new_blog_id = get_current_blog_id();

		$this->assertEquals( $old_blog_id, $new_blog_id );
	}
}