<?php

class EPTestSingleSite extends EP_Test_Base {
	/**
	 * Checking if HTTP request returns 404 status code.
	 * @var boolean 
	 */
	var $is_404=false;
	
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

		$this->setup_test_post_type();
	}

	/**
	 * Clean up after each test. Reset our mocks
	 *
	 * @since 0.1.0
	 */
	public function tearDown() {
		parent::tearDown();

		//make sure no one attached to this
		remove_filter( 'ep_sync_terms_allow_hierarchy', array( $this, 'ep_allow_multiple_level_terms_sync' ), 100 );
		$this->fired_actions = array();
	}

	/**
	 * Helper function to test whether a sync has happened
	 *
	 * @since 1.0
	 */
	public function action_sync_on_transition() {
		$this->fired_actions['ep_sync_on_transition'] = true;
	}

	/**
	 * Helper function to test whether a post has been deleted off ES
	 *
	 * @since 1.0
	 */
	public function action_delete_post() {
		$this->fired_actions['ep_delete_post'] = true;
	}

	/**
	 * Helper function to test whether a EP search has happened
	 *
	 * @since 1.0
	 */
	public function action_wp_query_search() {
		$this->fired_actions['ep_wp_query_search'] = true;
	}

	/**
	 * Helper function to check post sync args
	 *
	 * @since 1.0
	 */
	public function filter_post_sync_args( $post_args ) {
		$this->applied_filters['ep_post_sync_args'] = $post_args;

		return $post_args;
	}

	/**
	 * Test a simple post sync
	 *
	 * @since 0.9
	 */
	public function testPostSync() {
		add_action( 'ep_sync_on_transition', array( $this, 'action_sync_on_transition' ), 10, 0 );

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
		add_action( 'ep_delete_post', array( $this, 'action_delete_post' ), 10, 0 );

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

		add_action( 'ep_wp_query_search', array( $this, 'action_wp_query_search' ), 10, 0 );

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

		add_action( 'ep_wp_query_search', array( $this, 'action_wp_query_search' ), 10, 0 );

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

		add_filter( 'ep_post_sync_args', array( $this, 'filter_post_sync_args' ), 10, 1 );

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
	 * @group testPostTermSyncHierarchy
	 *
	 */
	public function testPostTermSyncSingleLevel(){

		$post_id = ep_create_and_sync_post();
		$post = get_post( $post_id );

		$taxName = rand_str( 32 );
		register_taxonomy( $taxName, $post->post_type, array( "label" => $taxName ) );
		register_taxonomy_for_object_type( $taxName, $post->post_type );

		$term1Name = rand_str( 32 );
		$term1 = wp_insert_term( $term1Name, $taxName );

		$term2Name = rand_str( 32 );
		$term2 = wp_insert_term( $term2Name, $taxName, array( 'parent' => $term1['term_id'] ) );

		$term3Name = rand_str( 32 );
		$term3 = wp_insert_term( $term3Name, $taxName, array( 'parent' => $term2['term_id'] ) );

		wp_set_object_terms( $post_id, array( $term3['term_id'] ), $taxName, true );

		ep_sync_post( $post_id );

		$post = ep_get_post( $post_id );

		$terms = $post['terms'];
		$this->assertTrue( isset( $terms[$taxName] ) );

		$indexedTerms = $terms[$taxName];
		$expectedTerms = array( $term3['term_id'] );

		$this->assertTrue( count( $indexedTerms ) > 0 );

		foreach ( $indexedTerms as $term ) {
			$this->assertTrue( in_array( $term['term_id'], $expectedTerms ) );
		}
	}

	public function ep_allow_multiple_level_terms_sync(){
		return true;
	}

	/**
	 * @group testPostTermSyncHierarchy
	 *
	 */
	public function testPostTermSyncHierarchyMultipleLevel(){

		add_filter('ep_sync_terms_allow_hierarchy', array( $this, 'ep_allow_multiple_level_terms_sync' ), 100, 1 );
		$post_id = ep_create_and_sync_post();
		$post = get_post( $post_id );

		$taxName = rand_str( 32 );
		register_taxonomy( $taxName, $post->post_type, array( "label" => $taxName ) );
		register_taxonomy_for_object_type( $taxName, $post->post_type );

		$term1Name = rand_str( 32 );
		$term1 = wp_insert_term( $term1Name, $taxName );

		$term2Name = rand_str( 32 );
		$term2 = wp_insert_term( $term2Name, $taxName, array( 'parent' => $term1['term_id'] ) );

		$term3Name = rand_str( 32 );
		$term3 = wp_insert_term( $term3Name, $taxName, array( 'parent' => $term2['term_id'] ) );

		wp_set_object_terms( $post_id, array( $term3['term_id'] ), $taxName, true );

		ep_sync_post( $post_id );

		$post = ep_get_post( $post_id );

		$terms = $post['terms'];
		$this->assertTrue( isset( $terms[$taxName] ) );
		$this->assertTrue( count( $terms[$taxName] ) === 3 );
		$indexedTerms = $terms[$taxName];
		$expectedTerms = array( $term1['term_id'], $term2['term_id'], $term3['term_id'] );

		$this->assertTrue( count( $indexedTerms ) > 0 );
		
		foreach ( $indexedTerms as $term ) {
			$this->assertTrue( in_array( $term['term_id'], $expectedTerms ) );
		}
	}

	/**
	 * @group testPostTermSyncHierarchy
	 *
	 */
	public function testPostTermSyncHierarchyMultipleLevelQuery(){

		add_filter('ep_sync_terms_allow_hierarchy', array( $this, 'ep_allow_multiple_level_terms_sync' ), 100, 1 );
		$post_id = ep_create_and_sync_post(array("post_title" => "#findme"));
		$post = get_post( $post_id );

		$taxName = rand_str( 32 );
		register_taxonomy( $taxName, $post->post_type, array( "label" => $taxName ) );
		register_taxonomy_for_object_type( $taxName, $post->post_type );

		$term1Name = rand_str( 32 );
		$term1 = wp_insert_term( $term1Name, $taxName );

		$term2Name = rand_str( 32 );
		$term2 = wp_insert_term( $term2Name, $taxName, array( 'parent' => $term1['term_id'] ) );

		$term3Name = rand_str( 32 );
		$term3 = wp_insert_term( $term3Name, $taxName, array( 'parent' => $term2['term_id'] ) );

		wp_set_object_terms( $post_id, array( $term3['term_id'] ), $taxName, true );

		ep_sync_post( $post_id );
		ep_refresh_index();

		add_action( 'ep_wp_query_search', array( $this, 'action_wp_query_search' ), 10, 0 );
		$query = new WP_Query(array('s' => "#findme"));

		$this->assertNotNull( $query->posts[0] );
		$this->assertNotNull( $query->posts[0]->terms );
		$post = $query->posts[0];

		$terms = $post->terms;
		$this->assertTrue( isset( $terms[$taxName] ) );
		$this->assertTrue( count( $terms[$taxName] ) === 3 );
		$indexedTerms = $terms[$taxName];
		$expectedTerms = array( $term1['term_id'], $term2['term_id'], $term3['term_id'] );

		$this->assertTrue( count( $indexedTerms ) > 0 );
		
		foreach ( $indexedTerms as $term ) {
			$this->assertTrue( in_array( $term['term_id'], $expectedTerms ) );
		}
	}

	/**
	 * @group testPostTermSyncHierarchy
	 *
	 */
	public function testPostTermSyncSingleLevelQuery(){

		$post_id = ep_create_and_sync_post( array( "post_title" => "#findme" ) );
		$post = get_post( $post_id );

		$taxName = rand_str( 32 );
		register_taxonomy( $taxName, $post->post_type, array( "label" => $taxName ) );
		register_taxonomy_for_object_type( $taxName, $post->post_type );

		$term1Name = rand_str( 32 );
		$term1 = wp_insert_term( $term1Name, $taxName );

		$term2Name = rand_str( 32 );
		$term2 = wp_insert_term( $term2Name, $taxName, array( 'parent' => $term1['term_id'] ) );

		$term3Name = rand_str( 32 );
		$term3 = wp_insert_term( $term3Name, $taxName, array( 'parent' => $term2['term_id'] ) );

		wp_set_object_terms( $post_id, array( $term3['term_id'] ), $taxName, true );

		ep_sync_post( $post_id );
		ep_refresh_index();

		add_action( 'ep_wp_query_search', array( $this, 'action_wp_query_search' ), 10, 0 );
		$query = new WP_Query(array('s' => "#findme"));

		$this->assertNotNull( $query->posts[0] );
		$this->assertNotNull( $query->posts[0]->terms );
		$post = $query->posts[0];


		$terms = $post->terms;
		$this->assertTrue( isset( $terms[$taxName] ) );

		$indexedTerms = $terms[$taxName];
		$expectedTerms = array( $term3['term_id'] );

		$this->assertTrue( count( $indexedTerms ) > 0 );

		foreach ( $indexedTerms as $term ) {
			$this->assertTrue( in_array( $term['term_id'], $expectedTerms ) );
		}
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

		add_action( 'ep_wp_query_search', array( $this, 'action_wp_query_search' ), 10, 0 );

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
			's'              => 'findme',
			'posts_per_page' => 1,
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 3, $query->found_posts );

		$found_posts[] = $query->posts[0]->ID;

		$args = array(
			's'              => 'findme',
			'posts_per_page' => 1,
			'paged'          => 2,
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 3, $query->found_posts );

		$found_posts[] = $query->posts[0]->ID;

		$args = array(
			's'              => 'findme',
			'posts_per_page' => 1,
			'paged'          => 3,
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 3, $query->found_posts );

		$found_posts[] = $query->posts[0]->ID;

		$args = array(
			's'              => 'findme',
			'posts_per_page' => 1,
			'paged'          => 4,
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 0, $query->post_count );
		$this->assertEquals( 3, $query->found_posts );

		$this->assertEquals( 3, count( array_unique( $found_posts ) ) );
	}

	/**
	 * Test a taxonomy query
	 *
	 * @since 1.0
	 */
	public function testTaxQuery() {
		ep_create_and_sync_post( array( 'post_content' => 'findme test 1', 'tags_input' => array( 'one', 'two' ) ) );
		ep_create_and_sync_post( array( 'post_content' => 'findme test 2' ) );
		ep_create_and_sync_post( array( 'post_content' => 'findme test 3', 'tags_input' => array( 'one', 'three' ) ) );

		ep_refresh_index();

		$args = array(
			's'         => 'findme',
			'tax_query' => array(
				array(
					'taxonomy' => 'post_tag',
					'terms'    => array( 'one' ),
					'field'    => 'slug',
				)
			)
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );
	}

	/**
	 * Test a category_name query
	 *
	 * @since 1.5
	 */
	public function testCategoryNameQuery() {
		$cat_one = wp_insert_category( array( 'cat_name' => 'one') );
		$cat_two = wp_insert_category( array( 'cat_name' => 'two') );
		$cat_three = wp_insert_category( array( 'cat_name' => 'three') );
		ep_create_and_sync_post( array( 'post_content' => 'findme test 1', 'post_category' => array( $cat_one, $cat_two ) ) );
		ep_create_and_sync_post( array( 'post_content' => 'findme test 2' ) );
		ep_create_and_sync_post( array( 'post_content' => 'findme test 3', 'post_category' => array( $cat_one, $cat_three) ) );

		ep_refresh_index();

		$args = array(
			's'             => 'findme',
			'category_name' => 'one'
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );
	}

	/**
	 * Test a post__in query
	 *
	 * @since 1.5
	 */
	public function testPostInQuery() {
		$post_ids = array();

		$post_ids[0] = ep_create_and_sync_post( array( 'post_content' => 'findme test 1' ) );
		$post_ids[1] = ep_create_and_sync_post( array( 'post_content' => 'findme test 2' ) );
		$post_ids[2] = ep_create_and_sync_post( array( 'post_content' => 'findme test 3' ) );

		ep_refresh_index();

		$args = array(
			's'        => 'findme',
			'post__in' => array( $post_ids[0], $post_ids[1] ),
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );
	}

	/**
	 * Test a post__not_in query
	 *
	 * @since 1.5
	 */
	public function testPostNotInQuery() {
		$post_ids = array();

		$post_ids[0] = ep_create_and_sync_post( array( 'post_content' => 'findme test 1' ) );
		$post_ids[1] = ep_create_and_sync_post( array( 'post_content' => 'findme test 2' ) );
		$post_ids[2] = ep_create_and_sync_post( array( 'post_content' => 'findme test 3' ) );

		ep_refresh_index();

		$args = array(
			's'            => 'findme',
			'post__not_in' => array( $post_ids[0] ),
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );
	}

	/**
	 * Test an author ID query
	 *
	 * @since 1.0
	 */
	public function testAuthorIDQuery() {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );

		ep_create_and_sync_post( array( 'post_content' => 'findme test 1', 'post_author' => $user_id ) );
		ep_create_and_sync_post( array( 'post_content' => 'findme test 2' ) );
		ep_create_and_sync_post( array( 'post_content' => 'findme test 3', 'post_author' => $user_id ) );

		ep_refresh_index();

		$args = array(
			's'      => 'findme',
			'author' => $user_id,
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );
	}

	/**
	 * Test an author name query
	 *
	 * @since 1.0
	 */
	public function testAuthorNameQuery() {
		$user_id = $this->factory->user->create( array( 'user_login' => 'john', 'role' => 'administrator' ) );

		ep_create_and_sync_post( array( 'post_content' => 'findme test 1', 'post_author' => $user_id ) );
		ep_create_and_sync_post( array( 'post_content' => 'findme test 2' ) );
		ep_create_and_sync_post( array( 'post_content' => 'findme test 3', 'post_author' => $user_id ) );

		ep_refresh_index();

		$args = array(
			's'           => 'findme',
			'author_name' => 'john',
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );
	}

	/**
	 * Test a post type query for pages
	 *
	 * @since 1.3
	 */
	public function testPostTypeQueryPage() {
		ep_create_and_sync_post( array( 'post_content' => 'findme test 1', 'post_type' => 'page' ) );
		ep_create_and_sync_post( array( 'post_content' => 'findme test 2' ) );
		ep_create_and_sync_post( array( 'post_content' => 'findme test 3', 'post_type' => 'page' ) );

		ep_refresh_index();

		$args = array(
			's'         => 'findme',
			'post_type' => 'page',
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );
	}


	/**
	 * Test a post type query for posts
	 *
	 * @since 1.3
	 */
	public function testPostTypeQueryPost() {
		ep_create_and_sync_post( array( 'post_content' => 'findme test 1', 'post_type' => 'page' ) );
		ep_create_and_sync_post( array( 'post_content' => 'findme test 2' ) );
		ep_create_and_sync_post( array( 'post_content' => 'findme test 3', 'post_type' => 'page' ) );

		ep_refresh_index();

		$args = array(
			's'         => 'findme',
			'post_type' => 'post',
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, $query->found_posts );
	}

	/**
	 * Test a query with no post type
	 *
	 * @since 1.3
	 */
	public function testNoPostTypeSearchQuery() {
		ep_create_and_sync_post( array( 'post_content' => 'findme test 1', 'post_type' => 'page' ) );
		ep_create_and_sync_post( array( 'post_content' => 'findme test 2' ) );
		ep_create_and_sync_post( array( 'post_content' => 'findme test 3' ) );

		ep_refresh_index();

		// post_type defaults to "any"
		$args = array(
			's' => 'findme',
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 3, $query->post_count );
		$this->assertEquals( 3, $query->found_posts );
	}

	/**
	 * Add attachment post type for indexing
	 *
	 * @since 1.6
	 * @param array $post_types
	 * @return array
	 */
	public function _add_attachment_post_type( $post_types ) {
		$post_types[] = 'attachment';
		return $post_types;
	}

	/**
	 * Setup attachment post status for indexing
	 *
	 * @since 1.6
	 * @param array $post_statuses
	 * @return array
	 */
	public function _add_attachment_post_status( $post_statuses ) {
		$post_statuses[] = 'inherit';
		return $post_statuses;
	}

	/**
	 * Test an attachment query
	 *
	 * @since 1.6
	 */
	public function testAttachmentQuery() {
		add_filter( 'ep_indexable_post_types', array( $this, '_add_attachment_post_type' ) );
		add_filter( 'ep_indexable_post_status', array( $this, '_add_attachment_post_status' ) );

		ep_create_and_sync_post( array( 'post_content' => 'findme test 1', 'post_type' => 'attachment' ) );
		ep_create_and_sync_post( array( 'post_content' => 'findme test 2' ) );
		ep_create_and_sync_post( array( 'post_content' => 'findme test 3' ) );

		ep_refresh_index();

		// post_type defaults to "any"
		$args = array(
			'post_type'              => 'attachment',
			'post_status'            => 'any',
			'elasticpress_integrate' => true,
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, $query->found_posts );

		remove_filter( 'ep_indexable_post_types', array( $this, '_add_attachment_post_type' ) );
		remove_filter( 'ep_indexable_post_status', array( $this, '_add_attachment_post_status' ) );
	}

	/**
	 * Test a query with no post type on non-search query
	 *
	 * @since 1.3
	 */
	public function testNoPostTypeNonSearchQuery() {
		ep_create_and_sync_post( array( 'post_content' => 'findme test 1', 'post_type' => 'page' ) );
		ep_create_and_sync_post( array( 'post_content' => 'findme test 2' ) );
		ep_create_and_sync_post( array( 'post_content' => 'findme test 3' ) );

		ep_refresh_index();

		// post_type defaults to "any"
		$args = array(
			'ep_integrate' => true,
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 3, $query->post_count );
		$this->assertEquals( 3, $query->found_posts );
	}

	/**
	 * Test a query with "any" post type
	 *
	 * @since 1.3
	 */
	public function testAnyPostTypeQuery() {
		ep_create_and_sync_post( array( 'post_content' => 'findme test 1', 'post_type' => 'page' ) );
		ep_create_and_sync_post( array( 'post_content' => 'findme test 2' ) );
		ep_create_and_sync_post( array( 'post_content' => 'findme test 3', 'post_type' => 'page' ) );

		ep_refresh_index();

		$args = array(
			's'         => 'findme',
			'post_type' => 'any',
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 3, $query->post_count );
		$this->assertEquals( 3, $query->found_posts );
	}

	/**
	 * Test meta mapping for complex arrays. All complex arrays are serialized
	 *
	 * @since 1.7
	 */
	public function testSearchMetaMappingComplexArray() {
		ep_create_and_sync_post( array( 'post_content' => 'post content' ), array( 'test_key' => array( 'test' ) ) );

		ep_refresh_index();
		$args = array(
			'ep_integrate' => true,
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );

		$this->assertEquals( 1, count( $query->posts[0]->post_meta['test_key'] ) ); // Make sure there is only one value

		$this->assertTrue( is_array( unserialize( $query->posts[0]->post_meta['test_key'][0] ) ) ); // Make sure value is properly serialized
	}

	/**
	 * Test meta mapping for complex objects. All complex objects are serialized
	 *
	 * @since 1.7
	 */
	public function testSearchMetaMappingComplexObject() {
		$object = new stdClass();
		$object->test = 'hello';

		ep_create_and_sync_post( array( 'post_content' => 'post content' ), array( 'test_key' => $object ) );

		ep_refresh_index();
		$args = array(
			'ep_integrate' => true,
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );

		$this->assertEquals( 1, count( $query->posts[0]->post_meta['test_key'] ) ); // Make sure there is only one value

		$this->assertEquals( 'hello', unserialize( $query->posts[0]->post_meta['test_key'][0] )->test ); // Make sure value is properly serialized
	}

	/**
	 * Test meta mapping for simple string
	 *
	 * @since 1.7
	 */
	public function testSearchMetaMappingString() {
		ep_create_and_sync_post( array( 'post_content' => 'post content' ), array( 'test_key' => 'test' ) );

		ep_refresh_index();
		$args = array(
			'ep_integrate' => true,
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );

		$this->assertEquals( 1, count( $query->posts[0]->post_meta['test_key'] ) ); // Make sure there is only one value

		$this->assertEquals( 'test', $query->posts[0]->post_meta['test_key'][0] );
	}

	/**
	 * Test meta mapping for simple integer
	 *
	 * @since 1.7
	 */
	public function testSearchMetaMappingInteger() {
		ep_create_and_sync_post( array( 'post_content' => 'post content' ), array( 'test_key' => 5 ) );

		ep_refresh_index();
		$args = array(
			'ep_integrate' => true,
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );

		$this->assertEquals( 1, count( $query->posts[0]->post_meta['test_key'] ) ); // Make sure there is only one value

		$this->assertTrue( ( 5 === $query->posts[0]->post_meta['test_key'][0] ) );
	}

	/**
	 * Test a query that fuzzy searches meta
	 *
	 * @since 1.0
	 */
	public function testSearchMetaQuery() {
		ep_create_and_sync_post( array( 'post_content' => 'the post content' ) );
		ep_create_and_sync_post( array( 'post_content' => 'the post content findme' ) );
		ep_create_and_sync_post( array( 'post_content' => 'post content' ), array( 'test_key' => 'findme' ) );

		ep_refresh_index();
		$args = array(
			's'             => 'findme',
			'search_fields' => array(
				'post_title',
				'post_excerpt',
				'post_content',
				'meta' => 'test_key'
			),
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );
	}

	/**
	 * Test a query that fuzzy searches taxonomy terms
	 *
	 * @since 1.0
	 */
	public function testSearchTaxQuery() {
		ep_create_and_sync_post( array( 'post_content' => 'the post content' ) );
		ep_create_and_sync_post( array( 'post_content' => 'the post content findme' ) );
		ep_create_and_sync_post( array( 'post_content' => 'post content', 'tags_input' => array( 'findme 2' ) ) );

		ep_refresh_index();
		$args = array(
			's'             => 'one findme two',
			'search_fields' => array(
				'post_title',
				'post_excerpt',
				'post_content',
				'taxonomies' => array( 'post_tag' )
			),
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );
	}

	/**
	 * Test a fuzzy author name query
	 *
	 * @since 1.0
	 */
	public function testSearchAuthorQuery() {
		$user_id = $this->factory->user->create( array( 'user_login' => 'john', 'role' => 'administrator' ) );

		ep_create_and_sync_post( array( 'post_content' => 'findme test 1' ) );
		ep_create_and_sync_post( array( 'post_content' => 'findme test 2' ) );
		ep_create_and_sync_post( array( 'post_content' => 'findme test 3', 'post_author' => $user_id ) );

		ep_refresh_index();

		$args = array(
			's'             => 'john boy',
			'search_fields' => array(
				'post_title',
				'post_excerpt',
				'post_content',
				'author_name'
			),
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, $query->found_posts );
	}

	/**
	 * Test a crazy advanced query
	 *
	 * @since 1.0
	 */
	public function testAdvancedQuery() {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );

		ep_create_and_sync_post( array( 'post_content' => '' ) );
		ep_create_and_sync_post( array( 'post_content' => 'findme', 'post_type' => 'ep_test' ) );
		ep_create_and_sync_post( array(
			'post_content' => 'findme',
			'post_type'    => 'ep_test',
			'tags_input'   => array( 'superterm' )
		) );
		ep_create_and_sync_post( array(
			'post_content' => 'findme',
			'post_type'    => 'ep_test',
			'tags_input'   => array( 'superterm' ),
			'post_author'  => $user_id,
		) );
		ep_create_and_sync_post( array(
			'post_content' => 'findme',
			'post_type'    => 'ep_test',
			'tags_input'   => array( 'superterm' ),
			'post_author'  => $user_id,
		), array( 'test_key' => 'meta value' ) );

		ep_refresh_index();

		$args = array(
			's'             => 'meta value',
			'post_type'     => 'ep_test',
			'tax_query'     => array(
				array(
					'taxonomy' => 'post_tag',
					'terms'    => array( 'superterm' ),
					'field'    => 'slug',
				)
			),
			'search_fields' => array(
				'post_title',
				'post_excerpt',
				'post_content',
				'meta' => 'test_key'
			),
			'author'        => $user_id,
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, $query->found_posts );
	}

	/**
	 * Test post_title orderby query
	 *
	 * @since 1.1
	 */
	public function testSearchPostTitleOrderbyQuery() {
		ep_create_and_sync_post( array( 'post_title' => 'ordertest 333' ) );
		ep_create_and_sync_post( array( 'post_title' => 'ordertest 111' ) );
		ep_create_and_sync_post( array( 'post_title' => 'ordertest 222' ) );

		ep_refresh_index();

		$args = array(
			's'       => 'ordertest',
			'orderby' => 'title',
			'order'   => 'DESC',
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 3, $query->post_count );
		$this->assertEquals( 3, $query->found_posts );
		$this->assertEquals( 'ordertest 333', $query->posts[0]->post_title );
		$this->assertEquals( 'ordertest 222', $query->posts[1]->post_title );
		$this->assertEquals( 'ordertest 111', $query->posts[2]->post_title );
	}

	/**
	 * Test post_date orderby query
	 *
	 * @since 1.4
	 */
	public function testSearchPostDateOrderbyQuery() {
		ep_create_and_sync_post( array( 'post_title' => 'ordertes 333' ) );
		sleep( 3 );

		ep_create_and_sync_post( array( 'post_title' => 'ordertest 111' ) );
		sleep( 3 );

		ep_create_and_sync_post( array( 'post_title' => 'ordertest 222' ) );

		ep_refresh_index();

		$args = array(
			's'       => 'ordertest',
			'orderby' => 'date',
			'order'   => 'DESC',
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 3, $query->post_count );
		$this->assertEquals( 3, $query->found_posts );
		$this->assertEquals( 'ordertest 222', $query->posts[0]->post_title );
		$this->assertEquals( 'ordertest 111', $query->posts[1]->post_title );
		$this->assertEquals( 'ordertes 333', $query->posts[2]->post_title );
	}

	/**
	 * Test post_date default order for ep_integrate query with no search
	 *
	 * @since 1.7
	 */
	public function testSearchPostDateOrderbyQueryEPIntegrate() {
		ep_create_and_sync_post( array( 'post_title' => 'ordertest 333' ) );
		sleep( 3 );

		ep_create_and_sync_post( array( 'post_title' => 'ordertest ordertest order test 111' ) );
		sleep( 3 );

		ep_create_and_sync_post( array( 'post_title' => 'ordertest 222' ) );

		ep_refresh_index();

		$args = array(
			'ep_integrate' => true,
			'order'        => 'desc',
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 3, $query->post_count );
		$this->assertEquals( 3, $query->found_posts );
		$this->assertEquals( 'ordertest 222', $query->posts[0]->post_title );
		$this->assertEquals( 'ordertest ordertest order test 111', $query->posts[1]->post_title );
		$this->assertEquals( 'ordertest 333', $query->posts[2]->post_title );
	}

	/**
	 * Test relevance orderby query advanced
	 *
	 * @since 1.2
	 */
	public function testSearchRelevanceOrderbyQueryAdvanced() {
		$posts = array();

		$posts[5] = ep_create_and_sync_post( array( 'post_title' => 'ordertet with even more lorem ipsum to make a longer field' ) );

		$posts[2] = ep_create_and_sync_post( array( 'post_title' => 'ordertest ordertet lorem ipsum' ) );

		ep_create_and_sync_post( array( 'post_title' => 'Lorem ipsum' ) );

		$posts[4] = ep_create_and_sync_post( array( 'post_title' => 'ordertet with some lorem ipsum' ) );

		$posts[1] = ep_create_and_sync_post( array( 'post_title' => 'ordertest ordertest lorem ipsum' ) );

		ep_create_and_sync_post( array( 'post_title' => 'Lorem ipsum', 'post_content' => 'Some post content filler text.' ) );

		$posts[3] = ep_create_and_sync_post( array( 'post_title' => 'ordertet ordertet lorem ipsum' ) );

		$posts[0] = ep_create_and_sync_post( array( 'post_title' => 'Ordertest ordertest ordertest' ) );

		ep_create_and_sync_post( array( 'post_title' => 'Lorem ipsum' ) );

		ep_create_and_sync_post( array( 'post_title' => 'Lorem ipsum' ) );

		ep_refresh_index();

		$args = array(
			's'       => 'ordertest',
			'orderby' => 'relevance',
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 6, $query->post_count );
		$this->assertEquals( 6, $query->found_posts );

		$i = 0;
		foreach ( $query->posts as $post ) {
			$this->assertEquals( $posts[$i], $post->ID );

			$i++;
		}
	}

	/**
	 * Test relevance orderby query
	 *
	 * @since 1.1
	 */
	public function testSearchRelevanceOrderbyQuery() {
		ep_create_and_sync_post();
		ep_create_and_sync_post( array( 'post_title' => 'ordertet' ) );
		ep_create_and_sync_post( array( 'post_title' => 'ordertest' ) );

		ep_refresh_index();

		$args = array(
			's'       => 'ordertest',
			'orderby' => 'relevance',
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );
		$this->assertEquals( 'ordertest', $query->posts[0]->post_title );
		$this->assertEquals( 'ordertet', $query->posts[1]->post_title );
	}

	/**
	 * Test post_name orderby query
	 *
	 * @since 1.1
	 */
	public function testSearchPostNameOrderbyQuery() {
		ep_create_and_sync_post( array( 'post_title' => 'postname-ordertest-333' ) );
		ep_create_and_sync_post( array( 'post_title' => 'postname-ordertest-111' ) );
		ep_create_and_sync_post( array( 'post_title' => 'postname-ordertest-222' ) );


		ep_refresh_index();

		$args = array(
			's'       => 'postname ordertest',
			'orderby' => 'name',
			'order'   => 'ASC',
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 3, $query->post_count );
		$this->assertEquals( 3, $query->found_posts );
		$this->assertEquals( 'postname-ordertest-111', $query->posts[0]->post_name );
		$this->assertEquals( 'postname-ordertest-222', $query->posts[1]->post_name );
		$this->assertEquals( 'postname-ordertest-333', $query->posts[2]->post_name );
	}

	/**
	 * Test default sort and order parameters
	 *
	 * Default is to use _score and 'desc'
	 *
	 * @since 1.1
	 */
	public function testSearchDefaultOrderbyQuery() {
		ep_create_and_sync_post();
		ep_create_and_sync_post( array( 'post_title' => 'ordertet' ) );
		ep_create_and_sync_post( array( 'post_title' => 'ordertest' ) );

		ep_refresh_index();

		$args = array(
			's' => 'ordertest',
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );
		$this->assertEquals( 'ordertest', $query->posts[0]->post_title );
		$this->assertEquals( 'ordertet', $query->posts[1]->post_title );
	}

	/**
	 * Test default sort and ASC order parameters
	 *
	 * Default is to use _score orderby; using 'asc' order
	 *
	 * @since 1.1
	 */
	public function testSearchDefaultOrderbyASCOrderQuery() {
		ep_create_and_sync_post();
		ep_create_and_sync_post( array( 'post_title' => 'ordertest' ) );
		ep_create_and_sync_post( array( 'post_title' => 'ordertestt' ) );

		ep_refresh_index();

		$args = array(
			's'     => 'ordertest',
			'order' => 'ASC',
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );
		$this->assertEquals( 'ordertestt', $query->posts[0]->post_title );
		$this->assertEquals( 'ordertest', $query->posts[1]->post_title );
	}

	/**
	 * Test unallowed orderby parameter
	 *
	 * Will revert to default _score orderby
	 *
	 * @since 1.1
	 */
	public function testSearchUnallowedOrderbyQuery() {
		ep_create_and_sync_post();
		ep_create_and_sync_post( array( 'post_title' => 'ordertestt' ) );
		ep_create_and_sync_post( array( 'post_title' => 'ordertest' ) );

		ep_refresh_index();

		$args = array(
			's'       => 'ordertest',
			'orderby' => 'SUPERRELEVANCE',
			'order'   => 'ASC',
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );
		$this->assertEquals( 'ordertestt', $query->posts[0]->post_title );
		$this->assertEquals( 'ordertest', $query->posts[1]->post_title );
	}

	/**
	 * Test a normal post trash
	 *
	 * @since 1.2
	 */
	public function testPostDelete() {
		add_action( 'ep_delete_post', array( $this, 'action_delete_post' ), 10, 0 );
		$post_id = ep_create_and_sync_post();

		ep_refresh_index();

		$post = ep_get_post( $post_id );

		// Ensure that our post made it over to elasticsearch
		$this->assertTrue( ! empty( $post ) );

		// Let's normally trash the post
		wp_delete_post( $post_id );

		ep_refresh_index();

		$this->assertTrue( ! empty( $this->fired_actions['ep_delete_post'] ) );

		$post = ep_get_post( $post_id );

		// The post, although it still should exist in WP's trash, should not be in our index
		$this->assertTrue( empty( $post ) );

		$post = get_post( $post_id );
		$this->assertTrue( ! empty( $post ) );

		$this->fired_actions = array();
	}

	/**
	 * Test that a post being directly deleted gets correctly removed from the Elasticsearch index
	 *
	 * @since 1.2
	 */
	public function testPostForceDelete() {
		add_action( 'ep_delete_post', array( $this, 'action_delete_post' ), 10, 0 );
		$post_id = ep_create_and_sync_post();

		ep_refresh_index();

		$post = ep_get_post( $post_id );

		// Ensure that our post made it over to elasticsearch
		$this->assertTrue( ! empty( $post ) );

		// Let's directly delete the post, bypassing the trash
		wp_delete_post( $post_id, true );

		ep_refresh_index();

		$this->assertTrue( ! empty( $this->fired_actions['ep_delete_post'] ) );

		$post = ep_get_post( $post_id );

		// Alright, now the post has been removed from the index, so this should be empty
		$this->assertTrue( empty( $post ) );

		$post = get_post( $post_id );

		// This post should no longer exist in WP's database
		$this->assertTrue( empty( $post ) );

		$this->fired_actions = array();
	}

	/**
	 * Test that empty search string returns all results
	 *
	 * @since 1.2
	 */
	public function testEmptySearchString() {
		ep_create_and_sync_post();
		ep_create_and_sync_post();

		ep_refresh_index();

		$args = array(
			's' => '',
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );
	}

	/**
	 * Test a query that searches and filters by a meta equal query
	 *
	 * @since 1.3
	 */
	public function testMetaQueryEquals() {
		ep_create_and_sync_post( array( 'post_content' => 'the post content' ) );
		ep_create_and_sync_post( array( 'post_content' => 'the post content findme' ) );
		ep_create_and_sync_post( array( 'post_content' => 'post content findme' ), array( 'test_key' => 'value' ) );

		ep_refresh_index();
		$args = array(
			's'             => 'findme',
			'meta_query' => array(
				array(
					'key' => 'test_key',
					'value' => 'value',
				)
			),
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, $query->found_posts );
	}

	/**
	 * Test a query that searches and filters by a meta not equal query
	 *
	 * @since 1.3
	 */
	public function testMetaQueryNotEquals() {
		ep_create_and_sync_post( array( 'post_content' => 'the post content findme' ) );
		ep_create_and_sync_post( array( 'post_content' => 'the post content findme' ) );
		ep_create_and_sync_post( array( 'post_content' => 'post content findme' ), array( 'test_key' => 'value' ) );

		ep_refresh_index();
		$args = array(
			's'             => 'findme',
			'meta_query' => array(
				array(
					'key' => 'test_key',
					'value' => 'value',
					'compare' => '!=',
				)
			),
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );
	}

	/**
	 * Test a query that searches and filters by a meta exists query
	 *
	 * @since 1.3
	 */
	public function testMetaQueryExists() {
		ep_create_and_sync_post( array( 'post_content' => 'the post content findme' ) );
		ep_create_and_sync_post( array( 'post_content' => 'the post content findme' ) );
		ep_create_and_sync_post( array( 'post_content' => 'post content findme' ), array( 'test_key' => 'value' ) );

		ep_refresh_index();
		$args = array(
			's'             => 'findme',
			'meta_query' => array(
				array(
					'key' => 'test_key',
					'compare' => 'exists',
				)
			),
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, $query->found_posts );
	}

	/**
	 * Test a query that searches and filters by a meta not exists query
	 *
	 * @since 1.3
	 */
	public function testMetaQueryNotExists() {
		ep_create_and_sync_post( array( 'post_content' => 'the post content findme' ) );
		ep_create_and_sync_post( array( 'post_content' => 'the post content findme' ) );
		ep_create_and_sync_post( array( 'post_content' => 'post content findme' ), array( 'test_key' => 'value' ) );

		ep_refresh_index();
		$args = array(
			's'             => 'findme',
			'meta_query' => array(
				array(
					'key' => 'test_key',
					'compare' => 'not exists',
				)
			),
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );
	}

	/**
	 * Test a query that searches and filters by a meta greater than to query
	 *
	 * @since 1.4
	 */
	public function testMetaQueryGreaterThan() {
		ep_create_and_sync_post( array( 'post_content' => 'the post content findme' ) );
		ep_create_and_sync_post( array( 'post_content' => 'the post content findme' ) );
		ep_create_and_sync_post( array( 'post_content' => 'post content findme' ), array( 'test_key' => '100' ) );
		ep_create_and_sync_post( array( 'post_content' => 'post content findme' ), array( 'test_key' => '101' ) );

		ep_refresh_index();
		$args = array(
			's'             => 'findme',
			'meta_query' => array(
				array(
					'key' => 'test_key',
					'value' => '100',
					'compare' => '>',
				)
			),
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, $query->found_posts );
	}

	/**
	 * Test a query that searches and filters by a meta greater than or equal to query
	 *
	 * @since 1.4
	 */
	public function testMetaQueryGreaterThanEqual() {
		ep_create_and_sync_post( array( 'post_content' => 'the post content findme' ) );
		ep_create_and_sync_post( array( 'post_content' => 'the post content findme' ) );
		ep_create_and_sync_post( array( 'post_content' => 'post content findme' ), array( 'test_key' => '100' ) );
		ep_create_and_sync_post( array( 'post_content' => 'post content findme' ), array( 'test_key' => '101' ) );

		ep_refresh_index();
		$args = array(
			's'             => 'findme',
			'meta_query' => array(
				array(
					'key' => 'test_key',
					'value' => '100',
					'compare' => '>=',
				)
			),
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );
	}

	/**
	 * Test a query that searches and filters by a meta less than to query
	 *
	 * @since 1.4
	 */
	public function testMetaQueryLessThan() {
		ep_create_and_sync_post( array( 'post_content' => 'the post content findme' ) );
		ep_create_and_sync_post( array( 'post_content' => 'the post content findme' ) );
		ep_create_and_sync_post( array( 'post_content' => 'post content findme' ), array( 'test_key' => '100' ) );
		ep_create_and_sync_post( array( 'post_content' => 'post content findme' ), array( 'test_key' => '101' ) );

		ep_refresh_index();
		$args = array(
			's'             => 'findme',
			'meta_query' => array(
				array(
					'key' => 'test_key',
					'value' => '101',
					'compare' => '<',
				)
			),
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, $query->found_posts );
	}

	/**
	 * Test a query that searches and filters by a meta less than or equal to query
	 *
	 * @since 1.4
	 */
	public function testMetaQueryLessThanEqual() {
		ep_create_and_sync_post( array( 'post_content' => 'the post content findme' ) );
		ep_create_and_sync_post( array( 'post_content' => 'the post content findme' ) );
		ep_create_and_sync_post( array( 'post_content' => 'post content findme' ), array( 'test_key' => '100' ) );
		ep_create_and_sync_post( array( 'post_content' => 'post content findme' ), array( 'test_key' => '101' ) );

		ep_refresh_index();
		$args = array(
			's'             => 'findme',
			'meta_query' => array(
				array(
					'key' => 'test_key',
					'value' => '101',
					'compare' => '<=',
				)
			),
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );
	}

	/**
	 * Test an advanced meta filter query
	 *
	 * @since 1.3
	 */
	public function testMetaQueryOrRelation() {
		ep_create_and_sync_post( array( 'post_content' => 'the post content findme' ), array( 'test_key5' => 'value1' )  );
		ep_create_and_sync_post( array( 'post_content' => 'the post content findme' ), array( 'test_key' => 'value1', 'test_key2' => 'value' )  );
		ep_create_and_sync_post( array( 'post_content' => 'post content findme' ), array( 'test_key6' => 'value', 'test_key2' => 'value2', 'test_key3' => 'value' ) );

		ep_refresh_index();
		$args = array(
			's'             => 'findme',
			'meta_query' => array(
				array(
					'key' => 'test_key5',
					'compare' => 'exists',
				),
				array(
					'key' => 'test_key6',
					'value' => 'value',
					'compare' => '=',
				),
				'relation' => 'or',
			),
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );
	}

	/**
	 * Test an advanced meta filter query
	 *
	 * @since 1.3
	 */
	public function testMetaQueryAdvanced() {
		ep_create_and_sync_post( array( 'post_content' => 'the post content findme' ), array( 'test_key' => 'value1' )  );
		ep_create_and_sync_post( array( 'post_content' => 'the post content findme' ), array( 'test_key' => 'value1', 'test_key2' => 'value' )  );
		ep_create_and_sync_post( array( 'post_content' => 'post content findme' ), array( 'test_key' => 'value', 'test_key2' => 'value2', 'test_key3' => 'value' ) );

		ep_refresh_index();
		$args = array(
			's'             => 'findme',
			'meta_query' => array(
				array(
					'key' => 'test_key3',
					'compare' => 'exists',
				),
				array(
					'key' => 'test_key2',
					'value' => 'value2',
					'compare' => '=',
				),
				array(
					'key' => 'test_key',
					'value' => 'value1',
					'compare' => '!=',
				)
			),
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, $query->found_posts );
	}

	/**
	 * Test a query that searches and filters by a meta value like the query
	 * @since 1.5
	 */
	public function testMetaQueryLike() {
		ep_create_and_sync_post( array( 'post_content' => 'the post content findme' ) );
		ep_create_and_sync_post( array( 'post_content' => 'the post content findme' ) );
		ep_create_and_sync_post( array( 'post_content' => 'post content findme' ), array( 'test_key' => 'ALICE in wonderland' ) );
		ep_create_and_sync_post( array( 'post_content' => 'post content findme' ), array( 'test_key' => 'alice in melbourne' ) );
		ep_create_and_sync_post( array( 'post_content' => 'post content findme' ), array( 'test_key' => 'AlicE in america' ) );

		ep_refresh_index();
		$args = array(
			's'             => 'findme',
			'meta_query' => array(
				array(
					'key' => 'test_key',
					'value' => 'alice',
					'compare' => 'LIKE',
				)
			),
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 3, $query->post_count );
		$this->assertEquals( 3, $query->found_posts );
	}

	/**
	 * Test exclude_from_search post type flag
	 * Ensure that we do not search that post type when all post types are searched
	 *
	 * @since 1.3
	 */
	public function testExcludeFromSearch() {
		$post_ids = array();

		$post_ids[0] = ep_create_and_sync_post();
		$post_ids[1] = ep_create_and_sync_post();
		$post_ids[2] = ep_create_and_sync_post( array( 'post_content' => 'findme' ) );
		$post_ids[3] = ep_create_and_sync_post();
		$post_ids[4] = ep_create_and_sync_post( array( 'post_content' => 'findme' ) );

		register_post_type( 'exclude-me', array(
			'public' => true,
			'exclude_from_search' => true,
		) );

		$post_ids[5] = ep_create_and_sync_post( array( 'post_type' => 'exclude-me', 'post_content' => 'findme' ) );

		ep_refresh_index();

		$args = array(
			's' => 'findme',
		);

		add_action( 'ep_wp_query_search', array( $this, 'action_wp_query_search' ), 10, 0 );

		$query = new WP_Query( $args );

		$this->assertTrue( ! empty( $this->fired_actions['ep_wp_query_search'] ) );

		$this->assertEquals( $query->post_count, 2 );
		$this->assertEquals( $query->found_posts, 2 );

		wp_reset_postdata();
	}

	/**
	 * Test what happens when no post types are available to be searched
	 *
	 * @since 1.3
	 */
	public function testNoAvailablePostTypesToSearch() {
		$post_ids = array();

		$post_ids[0] = ep_create_and_sync_post();
		$post_ids[1] = ep_create_and_sync_post();
		$post_ids[2] = ep_create_and_sync_post( array( 'post_content' => 'findme' ) );
		$post_ids[3] = ep_create_and_sync_post();
		$post_ids[4] = ep_create_and_sync_post( array( 'post_content' => 'findme' ) );

		$GLOBALS['wp_post_types'];

		$backup_post_types = $GLOBALS['wp_post_types'];

		// Set all post types to be excluded from search
		foreach ( $GLOBALS['wp_post_types'] as $post_type ) {
			$post_type->exclude_from_search = true;
		}

		ep_refresh_index();

		$args = array(
			's' => 'findme',
		);

		add_action( 'ep_wp_query_search', array( $this, 'action_wp_query_search' ), 10, 0 );

		$query = new WP_Query( $args );

		$this->assertTrue( empty( $this->fired_actions['ep_wp_query_search'] ) );

		$this->assertEquals( $query->post_count, 0 );
		$this->assertEquals( $query->found_posts, 0 );

		wp_reset_postdata();

		// Reset the main $wp_post_types item
		$GLOBALS['wp_post_types'] = $backup_post_types;
	}

	/**
	 * Test cache_results is off by default
	 *
	 * @since 1.5
	 */
	public function testCacheResultsDefaultOff() {
		ep_create_and_sync_post();

		ep_refresh_index();

		$args = array(
			'ep_integrate' => true,
		);

		$query = new WP_Query( $args );

		$this->assertFalse( $query->query_vars['cache_results'] ) ;
	}

	/**
	 * Test cache_results can be turned on
	 *
	 * @since 1.5
	 */
	public function testCacheResultsOn() {
		ep_create_and_sync_post();

		ep_refresh_index();

		$args = array(
			'ep_integrate' => true,
			'cache_results' => true,
		);

		$query = new WP_Query( $args );

		$this->assertTrue( $query->query_vars['cache_results'] ) ;
	}

	/**
	 * Test using cache_results actually populates the cache
	 *
	 * @since 1.5
	 */
	public function testCachedResultIsInCache() {
		ep_create_and_sync_post();

		ep_refresh_index();

		wp_cache_flush();

		$args = array(
			'ep_integrate' => true,
			'cache_results' => true,
		);

		$query = new WP_Query( $args );

		$cache = wp_cache_get( $query->posts[0]->ID, 'posts' );

		$this->assertTrue( ! empty( $cache ) );
	}

	/**
	 * Test setting cache results to false doesn't store anything in the cache
	 *
	 * @since 1.5
	 */
	public function testCachedResultIsNotInCache() {
		ep_create_and_sync_post();

		ep_refresh_index();

		wp_cache_flush();

		$args = array(
			'ep_integrate' => true,
		);

		$query = new WP_Query( $args );

		$cache = wp_cache_get( $query->posts[0]->ID, 'posts' );

		$this->assertTrue( empty( $cache ) );
	}
	
		
	/**
	 * Test if $post object values exist after receiving odd values from the 'ep_search_post_return_args' filter.
	 * @group 306
	 * @link https://github.com/10up/ElasticPress/issues/306
	 */
	public function testPostReturnArgs() {
		add_filter( 'ep_search_post_return_args', array( $this, 'ep_search_post_return_args_filter' ) );
		ep_create_and_sync_post( array( 'post_content' => 'findme' ) );
		ep_refresh_index();
		$args	 = array(
			's' => 'findme'
		);
		$query	 = new WP_Query( $args );
		remove_filter( 'ep_search_post_return_args', array( $this, 'ep_search_post_return_args_filter' ) );
	}

	/**
	 * Adds fake_item to post_return_args.
	 * @param array $args
	 * @return string
	 */
	public function ep_search_post_return_args_filter( $args ) {
		$args[] = 'fake_item';
		return $args;
	}

	/**
	 * Test get hosts method
	 */
	public function testGetHost() {

		global $ep_backup_host;

		//Check host constant
		$host_1 = ep_get_host( true );

		//Test only host in array
		$ep_backup_host = array( 'http://127.0.0.1:9200' );

		$host_2 = ep_get_host( true, true );

		//Test no good hosts
		$ep_backup_host = array( 'bad host 1', 'bad host 2' );

		$host_3 = ep_get_host( true, true );

		//Test good host 1st array item
		$ep_backup_host = array( 'http://127.0.0.1:9200', 'bad host 2' );

		$host_4 = ep_get_host( true, true );

		//Test good host last array item
		$ep_backup_host = array( 'bad host 1', 'http://127.0.0.1:9200' );

		$host_5 = ep_get_host( true, true );

		$this->assertInternalType( 'string', $host_1 );
		$this->assertInternalType( 'string', $host_2 );
		$this->assertWPError( $host_3 );
		$this->assertInternalType( 'string', $host_4 );
		$this->assertInternalType( 'string', $host_5 );

	}

	/**
	 * Test wrapper around wp_remote_request
	 */
	public function testEPRemoteRequest() {

		global $ep_backup_host;

		$ep_backup_host = false;

		define( 'EP_FORCE_HOST_REFRESH', true );

		//Test with EP_HOST constant
		$request_1 = false;
		$request   = ep_remote_request( '', array() );

		if ( ! is_wp_error( $request ) ) {
			if ( isset( $request['response']['code'] ) && 200 === $request['response']['code'] ) {
				$request_1 = true;
			}
		}

		//Test with only backups

		define( 'EP_HOST_USE_ONLY_BACKUPS', true );

		$request_2      = false;
		$ep_backup_host = array( 'http://127.0.0.1:9200' );
		$request        = ep_remote_request( '', array() );

		if ( ! is_wp_error( $request ) ) {
			if ( isset( $request['response']['code'] ) && 200 === $request['response']['code'] ) {
				$request_2 = true;
			}
		}

		$request_3      = false;
		$ep_backup_host = array( 'bad host 1', 'bad host 2' );
		$request        = ep_remote_request( '', array() );

		if ( is_wp_error( $request ) ) {
			$request_3 = $request;
		}

		$request_4      = false;
		$ep_backup_host = array( 'http://127.0.0.1:9200', 'bad host 2' );
		$request        = ep_remote_request( '', array() );

		if ( ! is_wp_error( $request ) ) {
			if ( isset( $request['response']['code'] ) && 200 === $request['response']['code'] ) {
				$request_4 = true;
			}
		}

		$request_5      = false;
		$ep_backup_host = array( 'bad host 1', 'http://127.0.0.1:9200' );
		$request        = ep_remote_request( '', array() );

		if ( ! is_wp_error( $request ) ) {
			if ( isset( $request['response']['code'] ) && 200 === $request['response']['code'] ) {
				$request_5 = true;
			}
		}

		$this->assertTrue( $request_1 );
		$this->assertTrue( $request_2 );
		$this->assertWPError( $request_3 );
		$this->assertTrue( $request_4 );
		$this->assertTrue( $request_5 );

	}

	public function mock_indexable_post_status($post_statuses){
		$post_statuses = array();
		$post_statuses[] = "draft";
		return $post_statuses;
	}

	public function testPostInvalidDateTime(){
		add_filter( 'ep_indexable_post_status', array( $this, 'mock_indexable_post_status' ), 10, 1 );
		$post_id = ep_create_and_sync_post( array( 'post_status' => 'draft' ) );

		ep_refresh_index();

		ep_sync_post($post_id);

		wp_cache_flush();

		$wp_post = get_post($post_id);
		$post = ep_get_post($post_id);

		$invalid_datetime = "0000-00-00 00:00:00";
		if( $wp_post->post_date_gmt == $invalid_datetime ){
			$this->assertNull( $post[ 'post_date_gmt'] );
		}

		if( $wp_post->post_modified_gmt == $invalid_datetime ){
			$this->assertNull( $post[ 'post_modified_gmt' ] );
		}
		$this->assertNotNull( $post );
		remove_filter( 'ep_indexable_post_status', array( $this, 'mock_indexable_post_status' ), 10);
	}
	
	/**
	 * Test to verify that a post type that is set to exclude_from_search isn't indexable.
	 * @group 321
	 * @since 1.6
	 * @link https://github.com/10up/ElasticPress/issues/321
	 */
	public function testExcludeIndexablePostType() {
		$post_types = ep_get_indexable_post_types();
		$this->assertArrayNotHasKey( 'ep_test_excluded', $post_types );
		$this->assertArrayNotHasKey( 'ep_test_not_public', $post_types );
	}
	
	/**
	 * Test to make sure that brand new posts with 'auto-draft' post status do not fire delete or sync.
	 * @group 343
	 * @since 1.6
	 * @link https://github.com/10up/ElasticPress/issues/343
	 */
	public function testAutoDraftPostStatus() {
		// Let's test inserting an 'auto-draft' post.
		add_action( 'http_api_debug', array( $this, '_check_404' ), 10, 5 );
		$new_post = wp_insert_post( array( 'post_title' => 'Auto Draft', 'post_status' => 'auto-draft' ) );

		$this->assertFalse( $this->is_404, 'auto-draft post status on wp_insert_post action.' );

		// Now let's test inserting a 'publish' post.
		$this->is_404 = false;
		add_action( 'http_api_debug', array( $this, '_check_404' ), 10, 5 );
		$new_post = wp_insert_post( array( 'post_title' => 'Published', 'post_status' => 'publish' ) );

		$this->assertFalse( $this->is_404, 'publish post status on wp_insert_post action.' );
	}

	/**
	 * Runs on http_api_debug action to check for a returned 404 status code.
	 * @param array|WP_Error $response  HTTP response or WP_Error object.
	 * @param string $type Context under which the hook is fired.
	 * @param string $class HTTP transport used.
	 * @param array $args HTTP request arguments.
	 * @param string $url The request URL.
	 */
	function _check_404( $response, $type, $class, $args, $url ) {
		$response_code = $response[ 'response' ][ 'code' ];
		if ( 404 == $response_code ) {
			$this->is_404 = true;
		}
		remove_action( 'http_api_debug', array( $this, '_check_404' ) );
	}

}
