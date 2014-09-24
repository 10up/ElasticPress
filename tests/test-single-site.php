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

		ep_delete_index();
		ep_put_mapping();

		ep_activate();

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
	}

	/**
	 * Test a simple post sync
	 *
	 * @since 0.9
	 */
	public function testPostSync() {
		add_action( 'ep_sync_on_transition', function() {
			$this->fired_actions['ep_sync_on_transition'] = true;
		}, 10, 0 );

		$post_id = ep_create_and_sync_post();

		ep_refresh_index();

		$this->assertTrue( ! empty( $this->fired_actions['ep_sync_on_transition'] ) );

		$post = ep_get_post( $post_id );
		$this->assertTrue( ! empty( $post ) );
	}

	/**
	 * Test that a post becoming unpublished correctly gets removed from the Elasticsearch index
	 *
	 * @since 0.9.3
	 */
	public function testPostUnpublish() {
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
	}

	/**
	 * Test WP Query search on post content
	 *
	 * @since 0.9
	 */
	public function testWPQuerySearchContent() {
		$post_ids = array();

		$post_ids[0] = ep_create_and_sync_post();
		$post_ids[1] = ep_create_and_sync_post();
		$post_ids[2] = ep_create_and_sync_post( array( 'post_content' => 'findme' ) );
		$post_ids[3] = ep_create_and_sync_post();
		$post_ids[4] = ep_create_and_sync_post( array( 'post_content' => 'findme' ) );

		ep_refresh_index();

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
		}

		wp_reset_postdata();
	}

	/**
	 * Test WP Query search on post title
	 *
	 * @since 0.9
	 */
	public function testWPQuerySearchTitle() {
		$post_ids = array();

		$post_ids[0] = ep_create_and_sync_post();
		$post_ids[1] = ep_create_and_sync_post();
		$post_ids[2] = ep_create_and_sync_post( array( 'post_title' => 'findme test' ) );
		$post_ids[3] = ep_create_and_sync_post( array( 'post_title' => 'findme test2' ) );
		$post_ids[4] = ep_create_and_sync_post( array( 'post_title' => 'findme test2' ) );

		ep_refresh_index();

		$args = array(
			's' => 'findme',
		);

		add_action( 'ep_wp_query_search', function() {
			$this->fired_actions['ep_wp_query_search'] = true;
		}, 10, 0 );

		$query = new WP_Query( $args );

		$this->assertTrue( ! empty( $this->fired_actions['ep_wp_query_search'] ) );

		$this->assertEquals( $query->post_count, 3 );
		$this->assertEquals( $query->found_posts, 3 );
	}

	/**
	 * Make sure proper taxonomies are synced with post. Hidden taxonomies should be skipped!
	 *
	 * @since 0.1.1
	 */
	public function testPostTermSync() {

		add_filter( 'ep_post_sync_args', function( $post_args ) {
			$this->applied_filters['ep_post_sync_args'] = $post_args;

			return $post_args;
		}, 10, 1 );

		$post_id = ep_create_and_sync_post( array(
			'tags_input' => array( 'test-tag', 'test-tag2' )
		) );

		ep_refresh_index();

		// Check if ES post sync filter has been triggered
		$this->assertTrue( ! empty( $this->applied_filters['ep_post_sync_args'] ) );

		// Check if tag was synced
		$post = ep_get_post( $post_id );
		$this->assertTrue( ! empty( $post['terms']['post_tag'] ) );
	}

	/**
	 * Test WP Query search on post excerpt
	 *
	 * @since 0.9
	 */
	public function testWPQuerySearchExcerpt() {
		$post_ids = array();

		$post_ids[0] = ep_create_and_sync_post();
		$post_ids[1] = ep_create_and_sync_post();
		$post_ids[2] = ep_create_and_sync_post( array( 'post_excerpt' => 'findme test' ) );
		$post_ids[3] = ep_create_and_sync_post();
		$post_ids[4] = ep_create_and_sync_post();

		ep_refresh_index();

		$args = array(
			's' => 'findme',
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
		ep_create_and_sync_post( array( 'post_excerpt' => 'findme test 1' ) );
		ep_create_and_sync_post( array( 'post_excerpt' => 'findme test 2' ) );
		ep_create_and_sync_post( array( 'post_excerpt' => 'findme test 3' ) );

		ep_refresh_index();

		/**
		 * Tests posts_per_page
		 */

		$found_posts = array();

		$args = array(
			's' => 'findme',
			'posts_per_page' => 1,
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 3, $query->found_posts );

		$found_posts[] = $query->posts[0]->ID;

		$args = array(
			's' => 'findme',
			'posts_per_page' => 1,
			'paged' => 2,
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 3, $query->found_posts );

		$found_posts[] = $query->posts[0]->ID;

		$args = array(
			's' => 'findme',
			'posts_per_page' => 1,
			'paged' => 3,
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 3, $query->found_posts );

		$found_posts[] = $query->posts[0]->ID;

		$args = array(
			's' => 'findme',
			'posts_per_page' => 1,
			'paged' => 4,
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 0, $query->post_count );
		$this->assertEquals( 3, $query->found_posts );

		$this->assertEquals( 3, count( array_unique( $found_posts ) ) );
	}
}