<?php

class EPTestSingleSite extends EP_Test_Base {
	/**
	 * Checking if HTTP request returns 404 status code.
	 * @var boolean
	 */
	var $is_404 = false;

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
	 * @since 0.1.0
	 */
	public function tearDown() {
		parent::tearDown();

		//make sure no one attached to this
		remove_filter( 'ep_sync_terms_allow_hierarchy', array( $this, 'ep_allow_multiple_level_terms_sync' ), 100 );
		$this->fired_actions = array();
	}

	/**
	 * Test a simple post sync
	 *
	 * @since 0.9
	 * @group single-site
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
	 * Test a post sync on meta add
	 *
	 * @since 2.0
	 * @group single-site
	 */
	public function testPostSyncOnMetaAdd() {
		add_action( 'ep_sync_on_meta_update', array( $this, 'action_sync_on_meta_update' ), 10, 0 );

		$post_id = ep_create_and_sync_post();

		$this->fired_actions = array();

		ep_refresh_index();

		update_post_meta( $post_id, 'test', 1 );

		EP_Sync_Manager::factory()->action_index_sync_queue();

		$this->assertTrue( ! empty( $this->fired_actions['ep_sync_on_meta_update'] ) );

		$post = ep_get_post( $post_id );
		$this->assertTrue( ! empty( $post ) );
	}

	/**
	 * Test a post sync on meta update
	 *
	 * @since 2.0
	 * @group single-site
	 */
	public function testPostSyncOnMetaUpdate() {
		add_action( 'ep_sync_on_meta_update', array( $this, 'action_sync_on_meta_update' ), 10, 0 );

		$post_id = ep_create_and_sync_post();

		ep_refresh_index();

		update_post_meta( $post_id, 'test', 1 );

		$this->fired_actions = array();

		update_post_meta( $post_id, 'test', 2 );

		EP_Sync_Manager::factory()->action_index_sync_queue();

		$this->assertTrue( ! empty( $this->fired_actions['ep_sync_on_meta_update'] ) );

		$post = ep_get_post( $post_id );
		$this->assertTrue( ! empty( $post ) );
	}

	/**
	 * Test pagination with offset
	 *
	 * @since 2.1
	 * @group single-site
	 */
	public function testPaginationWithOffset() {
		ep_create_and_sync_post( array( 'post_title' => 'one' ) );
		ep_create_and_sync_post( array( 'post_title' => 'two' ) );

		ep_refresh_index();

		$query = new WP_Query( array(
			'post_type' => 'post',
			'ep_integrate' => true,
			'posts_per_page' => 1,
			'offset' => 1,
		) );

		$this->assertEquals( 1, count( $query->posts ) );
		$this->assertEquals( 'two', $query->posts[0]->post_title );
	}

	/**
	 * Test WP Query search on post content
	 *
	 * @since 0.9
	 * @group single-site
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
	 * @group single-site
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
	 * @group single-site
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
	 * @group single-site
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
	 * @group single-site
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
	 * @group single-site
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
	 * @group single-site
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
	 * @group single-site
	 */
	public function testPostImplicitTaxonomyQueryCustomTax(){

		$post_id = ep_create_and_sync_post();
		$post = get_post( $post_id );

		$taxName = rand_str( 32 );
		register_taxonomy( $taxName, $post->post_type, array( "label" => $taxName ) );
		register_taxonomy_for_object_type( $taxName, $post->post_type );

		$term1Name = rand_str( 32 );
		$term1 = wp_insert_term( $term1Name, $taxName );

		wp_set_object_terms( $post_id, array( $term1['term_id'] ), $taxName, true );

		ep_sync_post( $post_id );
		ep_refresh_index();

		$args = array(
			$taxName => $term1Name,
			's' => ''
		);

		$query = new WP_Query( $args );

		$this->assertEquals( $query->post_count, 1 );
		$this->assertEquals( $query->found_posts, 1 );
	}


	/**
	 * @group single-site
	 */
	public function testPostImplicitTaxonomyQueryCategoryName(){

		$post_id = ep_create_and_sync_post();
		$post = get_post( $post_id );

		$term1Name = rand_str( 32 );
		$term1 = wp_insert_term( $term1Name, 'category' );

		wp_set_object_terms( $post_id, array( $term1['term_id'] ), 'category', true );

		ep_sync_post( $post_id );
		ep_refresh_index();

		$args = array(
			'category_name' => $term1Name,
			's' => ''
		);

		$query = new WP_Query( $args );

		$this->assertEquals( $query->post_count, 1 );
		$this->assertEquals( $query->found_posts, 1 );
	}

	/**
	 * @group single-site
	 */
	public function testPostImplicitTaxonomyQueryTag(){

		$post_id = ep_create_and_sync_post();
		$post = get_post( $post_id );

		$term1Name = rand_str( 32 );
		$term1 = wp_insert_term( $term1Name, 'post_tag' );

		wp_set_object_terms( $post_id, array( $term1['term_id'] ), 'post_tag', true );

		ep_sync_post( $post_id );
		ep_refresh_index();

		$args = array(
			'tag' => $term1Name,
			's' => ''
		);

		$query = new WP_Query( $args );

		$this->assertEquals( $query->post_count, 1 );
		$this->assertEquals( $query->found_posts, 1 );
	}

	/**
	 * Test WP Query search on post excerpt
	 *
	 * @since 0.9
	 * @group single-site
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
	 * @group single-site
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
		$this->assertEquals( 1, count( $query->posts ) );
		$this->assertEquals( 3, $query->found_posts );

		$found_posts[] = $query->posts[0]->ID;

		$args = array(
			's'              => 'findme',
			'posts_per_page' => 1,
			'paged'          => 2,
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, count( $query->posts ) );
		$this->assertEquals( 3, $query->found_posts );

		$found_posts[] = $query->posts[0]->ID;

		$args = array(
			's'              => 'findme',
			'posts_per_page' => 1,
			'paged'          => 3,
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, count( $query->posts ) );
		$this->assertEquals( 3, $query->found_posts );

		$found_posts[] = $query->posts[0]->ID;

		$args = array(
			's'              => 'findme',
			'posts_per_page' => 1,
			'paged'          => 4,
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 0, $query->post_count );
		$this->assertEquals( 0, count( $query->posts ) );
		$this->assertEquals( 3, $query->found_posts );

		$this->assertEquals( 3, count( array_unique( $found_posts ) ) );
	}

	/**
	 * Test a taxonomy query with slug field
	 *
	 * @since 1.8
	 * @group single-site
	 */
	public function testTaxQuerySlug() {
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
	 * Test a taxonomy query with OR relation
	 *
	 * @since 2.0
	 * @group single-site
	 */
	public function testTaxQueryOrRelation() {
		$cat1 =  wp_create_category( 'category one' );
		$cat2 =  wp_create_category( 'category two' );

		ep_create_and_sync_post( array( 'post_content' => 'findme test 1', 'tags_input' => array( 'one', 'two' ), 'post_category' => array( $cat1 )  ) );
		ep_create_and_sync_post( array( 'post_content' => 'findme test 2' ) );
		ep_create_and_sync_post( array( 'post_content' => 'findme test 3', 'tags_input' => array( 'one', 'three' ), 'post_category' => array( $cat2 )  ) );

		ep_refresh_index();

		$args = array(
			's'         => 'findme',
			'tax_query' => array(
				'relation' => 'or',
				array(
					'taxonomy' => 'post_tag',
					'terms'    => array( 'two' ),
					'field'    => 'slug',
				),
				array(
					'taxonomy' => 'category',
					'terms'    => array( 'category two' ),
					'field'    => 'name',
				)
			)
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );
	}

	/**
	 * Test a taxonomy query with term id field
	 *
	 * @since 1.8
	 * @group single-site
	 */
	public function testTaxQueryTermId() {
		$post = ep_create_and_sync_post( array( 'post_content' => 'findme test 1', 'tags_input' => array( 'one', 'two' ) ) );
		ep_create_and_sync_post( array( 'post_content' => 'findme test 2' ) );
		ep_create_and_sync_post( array( 'post_content' => 'findme test 3', 'tags_input' => array( 'one', 'three' ) ) );

		$tags = wp_get_post_tags( $post );
		$tag_id = 0;

		foreach ( $tags as $tag ) {
			if ( 'one' === $tag->slug ) {
				$tag_id = $tag->term_id;
			}
		}

		ep_refresh_index();

		$args = array(
			's'         => 'findme',
			'tax_query' => array(
				array(
					'taxonomy' => 'post_tag',
					'terms'    => array( $tag_id ),
					'field'    => 'term_id',
				)
			)
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );

		$args = array(
			's'         => 'findme',
			'tax_query' => array(
				array(
					'taxonomy' => 'post_tag',
					'terms'    => array( $tag_id ),
				)
			)
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );
	}

	/**
	 * Test a taxonomy query with term name field
	 *
	 * @since 1.8
	 * @group single-site
	 */
	public function testTaxQueryTermName() {
		$cat1 =  wp_create_category( 'category one' );
		$cat2 =  wp_create_category( 'category two' );
		$cat3 =  wp_create_category( 'category three' );

		$post = ep_create_and_sync_post( array( 'post_content' => 'findme test 1', 'post_category' => array( $cat1, $cat2 ) ) );
		ep_create_and_sync_post( array( 'post_content' => 'findme test 2' ) );
		ep_create_and_sync_post( array( 'post_content' => 'findme test 3', 'post_category' => array( $cat1, $cat3 ) ) );

		ep_refresh_index();

		$args = array(
			's'         => 'findme',
			'tax_query' => array(
				array(
					'taxonomy' => 'category',
					'terms'    => array( 'category one' ),
					'field'    => 'name',
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
	 * @group single-site
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
	 * @group single-site
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
	 * @group single-site
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
	 * @group single-site
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
	 * @group single-site
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
	 * @group single-site
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
	 * @group single-site
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
	 * @group single-site
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
	 * Test a post status query for published posts
	 *
	 * @since 2.1
	 * @group single-site
	 */
	public function testPostStatusQueryPublish() {
		ep_create_and_sync_post( array( 'post_content' => 'findme test 1', 'post_status' => 'draft' ) );
		ep_create_and_sync_post( array( 'post_content' => 'findme test 2' ) );
		ep_create_and_sync_post( array( 'post_content' => 'findme test 3', 'post_status' => 'draft' ) );

		ep_refresh_index();

		$args = array(
			's'         => 'findme',
			'post_status' => 'publish',
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, $query->found_posts );
	}

	/**
	 * Test a post status query for draft posts
	 *
	 * @since 2.1
	 * @group single-site
	 */
	public function testPostStatusQueryDraft() {
		add_filter( 'ep_indexable_post_status', array( $this, 'mock_indexable_post_status' ), 10, 1 );

		ep_create_and_sync_post( array( 'post_content' => 'findme test 1', 'post_status' => 'draft' ) );
		ep_create_and_sync_post( array( 'post_content' => 'findme test 2' ) );
		ep_create_and_sync_post( array( 'post_content' => 'findme test 3', 'post_status' => 'draft' ) );

		ep_refresh_index();

		$args = array(
			's'         => 'findme',
			'post_status' => 'draft',
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );

		remove_filter( 'ep_indexable_post_status', array( $this, 'mock_indexable_post_status' ), 10);
	}

	/**
	 * Test a post status query for published or draft posts with 'draft' whitelisted as indexable status
	 *
	 * @since 2.1
	 * @group single-site
	 */
	public function testPostStatusQueryMulti() {
		add_filter( 'ep_indexable_post_status', array( $this, 'mock_indexable_post_status' ), 10, 1 );

		ep_create_and_sync_post( array( 'post_content' => 'findme test 1', 'post_status' => 'draft' ) );
		ep_create_and_sync_post( array( 'post_content' => 'findme test 2' ) );
		ep_create_and_sync_post( array( 'post_content' => 'findme test 3', 'post_status' => 'draft' ) );

		ep_refresh_index();

		$args = array(
			's'         => 'findme',
			'post_status' => array(
				'draft',
				'publish',
			),
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 3, $query->post_count );
		$this->assertEquals( 3, $query->found_posts );

		remove_filter( 'ep_indexable_post_status', array( $this, 'mock_indexable_post_status' ), 10);
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
	 * @group single-site
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
	 * Test a query with no post type on non-search query. Should default to `post` post type
	 *
	 * @since 1.3
	 * @group single-site
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

		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );
	}

	/**
	 * Test a query with "any" post type
	 *
	 * @since 1.3
	 * @group single-site
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
	 * Test meta shows up in EP post object
	 *
	 * @since 1.7
	 * @group single-site
	 */
	public function testSearchMetaInPostObject() {
		$object = new stdClass();
		$object->test = 'hello';

		ep_create_and_sync_post( array( 'post_content' => 'post content' ), array( 'test_key' => $object ) );

		ep_refresh_index();
		$args = array(
			'ep_integrate' => true,
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );

		$this->assertEquals( 1, count( $query->posts[0]->meta['test_key'] ) );
	}

	/**
	 * Test a query that fuzzy searches meta
	 *
	 * @since 1.0
	 * @group single-site
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
		
		 // Only check for fields which are provided in search_fields.
		$args = array(
			's'             => 'findme',
			'search_fields' => array(
				'meta' => 'test_key'
			),
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, $query->found_posts );
	}

	/**
	 * Test a query that fuzzy searches taxonomy terms
	 *
	 * @since 1.0
	 * @group single-site
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
	 * @group single-site
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
	 * @group single-site
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
	 * @group single-site
	 */
	public function testSearchPostTitleOrderbyQuery() {
		ep_create_and_sync_post( array( 'post_title' => 'ordertest 333' ) );
		ep_create_and_sync_post( array( 'post_title' => 'ordertest 111' ) );
		ep_create_and_sync_post( array( 'post_title' => 'Ordertest 222' ) );

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
		$this->assertEquals( 'Ordertest 222', $query->posts[1]->post_title );
		$this->assertEquals( 'ordertest 111', $query->posts[2]->post_title );
	}

	/**
	 * Test post meta string orderby query asc
	 *
	 * @since 1.8
	 * @group single-site
	 */
	public function testSearchPostMetaStringOrderbyQueryAsc() {
		ep_create_and_sync_post( array( 'post_title' => 'ordertest 333' ), array( 'test_key' => 'c' ) );
		ep_create_and_sync_post( array( 'post_title' => 'Ordertest 222' ), array( 'test_key' => 'B' ) );
		ep_create_and_sync_post( array( 'post_title' => 'ordertest 111' ), array( 'test_key' => 'a' ) );

		ep_refresh_index();

		$args = array(
			's'       => 'ordertest',
			'orderby' => 'meta.test_key.value.sortable',
			'order'   => 'ASC',
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 3, $query->post_count );
		$this->assertEquals( 3, $query->found_posts );
		$this->assertEquals( 'ordertest 111', $query->posts[0]->post_title );
		$this->assertEquals( 'Ordertest 222', $query->posts[1]->post_title );
		$this->assertEquals( 'ordertest 333', $query->posts[2]->post_title );
	}

	/**
	 * Test post meta string orderby query asc array
	 *
	 * @since 2.1
	 * @group single-site
	 */
	public function testSearchPostMetaStringOrderbyQueryAscArray() {
		ep_create_and_sync_post( array( 'post_title' => 'ordertest 333' ), array( 'test_key' => 'c' ) );
		ep_create_and_sync_post( array( 'post_title' => 'Ordertest 222' ), array( 'test_key' => 'B' ) );
		ep_create_and_sync_post( array( 'post_title' => 'ordertest 111' ), array( 'test_key' => 'a' ) );

		ep_refresh_index();

		$args = array(
			's'       => 'ordertest',
			'orderby' => array( 'meta.test_key.value.sortable' ),
			'order'   => 'ASC',
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 3, $query->post_count );
		$this->assertEquals( 3, $query->found_posts );
		$this->assertEquals( 'ordertest 111', $query->posts[0]->post_title );
		$this->assertEquals( 'Ordertest 222', $query->posts[1]->post_title );
		$this->assertEquals( 'ordertest 333', $query->posts[2]->post_title );
	}

	/**
	 * Test post meta string orderby query advanced. Specifically, look at orderby when it is an array
	 * like array( 'key' => 'order direction ' )
	 *
	 * @since 2.1
	 * @group single-site
	 */
	public function testSearchPostMetaStringOrderbyQueryAdvanced() {
		ep_create_and_sync_post( array( 'post_title' => 'ordertest 333' ), array( 'test_key' => 'c', 'test_key2' => 'c' ) );
		ep_create_and_sync_post( array( 'post_title' => 'ordertest 222' ), array( 'test_key' => 'f', 'test_key2' => 'c' ) );
		ep_create_and_sync_post( array( 'post_title' => 'ordertest 111' ), array( 'test_key' => 'd', 'test_key2' => 'd' ) );

		ep_refresh_index();

		$args = array(
			's'       => 'ordertest',
			'orderby' => array( 'meta.test_key.value.sortable' => 'asc', ),
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 3, $query->post_count );
		$this->assertEquals( 3, $query->found_posts );
		$this->assertEquals( 'ordertest 333', $query->posts[0]->post_title );
		$this->assertEquals( 'ordertest 111', $query->posts[1]->post_title );
		$this->assertEquals( 'ordertest 222', $query->posts[2]->post_title );
	}

	/**
	 * Sort by an author login
	 *
	 * @since 1.8
	 * @group single-site
	 */
	public function testAuthorLoginOrderbyQueryAsc() {
		$bob = $this->factory->user->create( array( 'user_login' => 'Bob', 'role' => 'administrator' ) );
		$al = $this->factory->user->create( array( 'user_login' => 'al', 'role' => 'administrator' ) );
		$jim = $this->factory->user->create( array( 'user_login' => 'Jim', 'role' => 'administrator' ) );

		ep_create_and_sync_post( array( 'post_title' => 'findme test 1', 'post_author' => $al ) );
		ep_create_and_sync_post( array( 'post_title' => 'findme test 2', 'post_author' => $bob ) );
		ep_create_and_sync_post( array( 'post_title' => 'findme test 3', 'post_author' => $jim ) );

		ep_refresh_index();

		$args = array(
			's'       => 'findme',
			'orderby' => 'post_author.login.sortable',
			'order'   => 'asc',
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 3, $query->post_count );
		$this->assertEquals( 3, $query->found_posts );

		$this->assertEquals( 'findme test 1', $query->posts[0]->post_title );
		$this->assertEquals( 'findme test 2', $query->posts[1]->post_title );
		$this->assertEquals( 'findme test 3', $query->posts[2]->post_title );
	}

	/**
	 * Sort by an author display name
	 *
	 * @since 1.8
	 * @group single-site
	 */
	public function testAuthorDisplayNameOrderbyQueryAsc() {
		$bob = $this->factory->user->create( array( 'display_name' => 'Bob', 'role' => 'administrator' ) );
		$al = $this->factory->user->create( array( 'display_name' => 'al', 'role' => 'administrator' ) );
		$jim = $this->factory->user->create( array( 'display_name' => 'Jim', 'role' => 'administrator' ) );

		ep_create_and_sync_post( array( 'post_title' => 'findme test 1', 'post_author' => $al ) );
		ep_create_and_sync_post( array( 'post_title' => 'findme test 2', 'post_author' => $bob ) );
		ep_create_and_sync_post( array( 'post_title' => 'findme test 3', 'post_author' => $jim ) );

		ep_refresh_index();

		$args = array(
			's'       => 'findme',
			'orderby' => 'post_author.display_name.sortable',
			'order'   => 'desc',
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 3, $query->post_count );
		$this->assertEquals( 3, $query->found_posts );

		$this->assertEquals( 'findme test 3', $query->posts[0]->post_title );
		$this->assertEquals( 'findme test 2', $query->posts[1]->post_title );
		$this->assertEquals( 'findme test 1', $query->posts[2]->post_title );
	}

	/**
	 * Test post meta number orderby query asc
	 *
	 * @since 1.8
	 * @group single-site
	 */
	public function testSearchPostMetaNumOrderbyQueryAsc() {
		ep_create_and_sync_post( array( 'post_title' => 'ordertest 333' ), array( 'test_key' => 3 ) );
		ep_create_and_sync_post( array( 'post_title' => 'ordertest 444' ), array( 'test_key' => 4 ) );
		ep_create_and_sync_post( array( 'post_title' => 'Ordertest 222' ), array( 'test_key' => 2 ) );
		ep_create_and_sync_post( array( 'post_title' => 'ordertest 111' ), array( 'test_key' => 1 ) );

		ep_refresh_index();

		$args = array(
			's'       => 'ordertest',
			'orderby' => 'meta.test_key.long',
			'order'   => 'ASC',
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 4, $query->post_count );
		$this->assertEquals( 4, $query->found_posts );
		$this->assertEquals( 'ordertest 111', $query->posts[0]->post_title );
		$this->assertEquals( 'Ordertest 222', $query->posts[1]->post_title );
		$this->assertEquals( 'ordertest 333', $query->posts[2]->post_title );
		$this->assertEquals( 'ordertest 444', $query->posts[3]->post_title );
	}

	/**
	 * Test post category orderby query asc
	 *
	 * @since 1.8
	 * @group single-site
	 */
	public function testSearchTaxNameOrderbyQueryAsc() {
		$cat1 =  wp_create_category( 'Category 1' );
		$cat2 =  wp_create_category( 'Another category two' );
		$cat3 =  wp_create_category( 'basic category' );
		$cat4 =  wp_create_category( 'Category 0' );

		ep_create_and_sync_post( array( 'post_title' => 'ordertest 333', 'post_category' => array( $cat4 ) ) );
		ep_create_and_sync_post( array( 'post_title' => 'ordertest 444', 'post_category' => array( $cat1 ) ) );
		ep_create_and_sync_post( array( 'post_title' => 'Ordertest 222', 'post_category' => array( $cat3 ) ) );
		ep_create_and_sync_post( array( 'post_title' => 'ordertest 111', 'post_category' => array( $cat2 ) ) );

		ep_refresh_index();

		$args = array(
			's'       => 'ordertest',
			'orderby' => 'terms.category.name.sortable',
			'order'   => 'ASC',
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 4, $query->post_count );
		$this->assertEquals( 4, $query->found_posts );
		$this->assertEquals( 'ordertest 111', $query->posts[0]->post_title );
		$this->assertEquals( 'Ordertest 222', $query->posts[1]->post_title );
		$this->assertEquals( 'ordertest 333', $query->posts[2]->post_title );
		$this->assertEquals( 'ordertest 444', $query->posts[3]->post_title );
	}

	/**
	 * Test post meta number orderby query desc
	 *
	 * @since 1.8
	 * @group single-site
	 */
	public function testSearchPostMetaNumOrderbyQueryDesc() {
		ep_create_and_sync_post( array( 'post_title' => 'ordertest 333' ), array( 'test_key' => 3 ) );
		ep_create_and_sync_post( array( 'post_title' => 'ordertest 444' ), array( 'test_key' => 4 ) );
		ep_create_and_sync_post( array( 'post_title' => 'Ordertest 222' ), array( 'test_key' => 2 ) );
		ep_create_and_sync_post( array( 'post_title' => 'ordertest 111' ), array( 'test_key' => 1 ) );

		ep_refresh_index();

		$args = array(
			's'       => 'ordertest',
			'orderby' => 'meta.test_key.long',
			'order'   => 'DESC',
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 4, $query->post_count );
		$this->assertEquals( 4, $query->found_posts );
		$this->assertEquals( 'ordertest 111', $query->posts[3]->post_title );
		$this->assertEquals( 'Ordertest 222', $query->posts[2]->post_title );
		$this->assertEquals( 'ordertest 333', $query->posts[1]->post_title );
		$this->assertEquals( 'ordertest 444', $query->posts[0]->post_title );
	}

	/**
	 * Test post meta num multiple fields orderby query asc
	 *
	 * @since 1.8
	 * @group single-site
	 */
	public function testSearchPostMetaNumMultipleOrderbyQuery() {
		ep_create_and_sync_post( array( 'post_title' => 'ordertest 444' ), array( 'test_key' => 3, 'test_key2' => 2 ) );
		ep_create_and_sync_post( array( 'post_title' => 'ordertest 333' ), array( 'test_key' => 3, 'test_key2' => 1 ) );
		ep_create_and_sync_post( array( 'post_title' => 'Ordertest 222' ), array( 'test_key' => 2, 'test_key2' => 1 ) );
		ep_create_and_sync_post( array( 'post_title' => 'ordertest 111' ), array( 'test_key' => 1, 'test_key2' => 1 ) );

		ep_refresh_index();

		$args = array(
			's'       => 'ordertest',
			'orderby' => 'meta.test_key.long meta.test_key2.long',
			'order'   => 'ASC',
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 4, $query->post_count );
		$this->assertEquals( 4, $query->found_posts );
		$this->assertEquals( 'ordertest 111', $query->posts[0]->post_title );
		$this->assertEquals( 'Ordertest 222', $query->posts[1]->post_title );
		$this->assertEquals( 'ordertest 333', $query->posts[2]->post_title );
		$this->assertEquals( 'ordertest 444', $query->posts[3]->post_title );
	}

	/**
	 * Test post_date orderby query
	 *
	 * @since 1.4
	 * @group single-site
	 */
	public function testSearchPostDateOrderbyQuery() {
		ep_create_and_sync_post( array( 'post_title' => 'ordertes 333' ) );
		sleep( 3 );

		ep_create_and_sync_post( array( 'post_title' => 'ordertest 111' ) );
		sleep( 3 );

		ep_create_and_sync_post( array( 'post_title' => 'Ordertest 222' ) );

		ep_refresh_index();

		$args = array(
			's'       => 'ordertest',
			'orderby' => 'date',
			'order'   => 'DESC',
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 3, $query->post_count );
		$this->assertEquals( 3, $query->found_posts );
		$this->assertEquals( 'Ordertest 222', $query->posts[0]->post_title );
		$this->assertEquals( 'ordertest 111', $query->posts[1]->post_title );
		$this->assertEquals( 'ordertes 333', $query->posts[2]->post_title );
	}

	/**
	 * Test post_date default order for ep_integrate query with no search
	 *
	 * @since 1.7
	 * @group single-site
	 */
	public function testSearchPostDateOrderbyQueryEPIntegrate() {
		ep_create_and_sync_post( array( 'post_title' => 'ordertest 333' ) );
		sleep( 3 );

		ep_create_and_sync_post( array( 'post_title' => 'ordertest ordertest order test 111' ) );
		sleep( 3 );

		ep_create_and_sync_post( array( 'post_title' => 'Ordertest 222' ) );

		ep_refresh_index();

		$args = array(
			'ep_integrate' => true,
			'order'        => 'desc',
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 3, $query->post_count );
		$this->assertEquals( 3, $query->found_posts );
		$this->assertEquals( 'Ordertest 222', $query->posts[0]->post_title );
		$this->assertEquals( 'ordertest ordertest order test 111', $query->posts[1]->post_title );
		$this->assertEquals( 'ordertest 333', $query->posts[2]->post_title );
	}

	/**
	 * Test relevance orderby query advanced
	 *
	 * @since 1.2
	 * @group single-site
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
	 * @group single-site
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
	 * @group single-site
	 */
	public function testSearchPostNameOrderbyQuery() {
		ep_create_and_sync_post( array( 'post_title' => 'postname-ordertest-333' ) );
		ep_create_and_sync_post( array( 'post_title' => 'postname-ordertest-111' ) );
		ep_create_and_sync_post( array( 'post_title' => 'postname-Ordertest-222' ) );


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
	 * @group single-site
	 */
	public function testSearchDefaultOrderbyQuery() {
		ep_create_and_sync_post();
		ep_create_and_sync_post( array( 'post_title' => 'Ordertet' ) );
		ep_create_and_sync_post( array( 'post_title' => 'ordertest' ) );

		ep_refresh_index();

		$args = array(
			's' => 'ordertest',
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );
		$this->assertEquals( 'ordertest', $query->posts[0]->post_title );
		$this->assertEquals( 'Ordertet', $query->posts[1]->post_title );
	}

	/**
	 * Test default sort and ASC order parameters
	 *
	 * Default is to use _score orderby; using 'asc' order
	 *
	 * @since 1.1
	 * @group single-site
	 */
	public function testSearchDefaultOrderbyASCOrderQuery() {
		ep_create_and_sync_post();
		ep_create_and_sync_post( array( 'post_title' => 'Ordertest' ) );
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
		$this->assertEquals( 'Ordertest', $query->posts[1]->post_title );
	}
	
	/**
	 * Test orderby random
	 *
	 * @since 2.1.1
	 * @group single-site
	 */
	public function testRandOrderby() {
		ep_create_and_sync_post( array( 'post_title' => 'ordertest 1' ) );
		ep_create_and_sync_post( array( 'post_title' => 'ordertest 2' ) );
		ep_create_and_sync_post( array( 'post_title' => 'ordertest 3' ) );
		
		ep_refresh_index();
		
		$args = array(
			'ep_integrate'  => true,
			'orderby'       => 'rand',
		);
		
		$query = new WP_Query( $args );
		
		/* Since it's test for random order, can't check against exact post ID or content
			but only found posts and post count.
		*/
		$this->assertEquals( 3, $query->post_count );
		$this->assertEquals( 3, $query->found_posts );
	}

	/**
	 * Test that a post being directly deleted gets correctly removed from the Elasticsearch index
	 *
	 * @since 1.2
	 * @group single-site
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
	 * @group single-site
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
	 * @group single-site
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
	 * @group single-site
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
	 * @group single-site
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
	 * @group single-site
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
	 * @group single-site
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
	 * Test a query that searches and filters by a meta between query
	 *
	 * @since 2.0
	 * @group single-site
	 */
	public function testMetaQueryBetween() {
		ep_create_and_sync_post( array( 'post_content' => 'the post content findme' ) );
		ep_create_and_sync_post( array( 'post_content' => 'the post content findme' ) );
		ep_create_and_sync_post( array( 'post_content' => 'post content findme' ), array( 'test_key' => '100' ) );
		ep_create_and_sync_post( array( 'post_content' => 'post content findme' ), array( 'test_key' => '105' ) );
		ep_create_and_sync_post( array( 'post_content' => 'post content findme' ), array( 'test_key' => '110' ) );

		ep_refresh_index();
		$args = array(
			's'             => 'findme',
			'meta_query' => array(
				array(
					'key' => 'test_key',
					'value' => array( 102, 106 ),
					'compare' => 'BETWEEN',
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
	 * @group single-site
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
	 * @group single-site
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
	 * @group single-site
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
	 * @group single-site
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
	 * @group single-site
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
	 * 
	 * @since 1.5
	 * @group single-site
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
	
	public function testMetaQueryMultipleArray() {
		ep_create_and_sync_post( array( 'post_content' => 'findme' ), array( 'meta_key_1' => '1' ) );
		ep_create_and_sync_post( array( 'post_content' => 'findme' ), array( 'meta_key_1' => '1' ) );
		ep_create_and_sync_post( array( 'post_content' => 'findme' ), array( 'meta_key_1' => '1', 'meta_key_2' => '4' ) );
		ep_create_and_sync_post( array( 'post_content' => 'findme' ), array( 'meta_key_1' => '1', 'meta_key_2' => '0' ) );
		ep_create_and_sync_post( array( 'post_content' => 'findme' ), array( 'meta_key_1' => '1', 'meta_key_3' => '4' ) );
		
		ep_refresh_index();
		
		$args = array(
			's'             => 'findme',
			'meta_query' => array(
				array(
					'key' => 'meta_key_2',
					'value' => '0',
					'compare' => '>=',
				)
			),
		);
		
		$query = new WP_Query( $args );
		
		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );
		
		$args = array(
			's'             => 'findme',
			'meta_query' => array(
				'relation' => 'AND',
				array(
					'key' => 'meta_key_1',
					'value' => '1',
				),
				array(
					'relation' => 'OR',
					array(
						'key' => 'meta_key_2',
						'value' => '2',
						'compare' => '>=',
					),
					array(
						'key' => 'meta_key_3',
						'value' => '4',
					),
				),
			),
		);
		
		$query = new WP_Query( $args );
		
		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );
	}

	/**
	 * Test exclude_from_search post type flag
	 * Ensure that we do not search that post type when all post types are searched
	 *
	 * @since 1.3
	 * @group single-site
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
	 * @group single-site
	 */
	public function testNoAvailablePostTypesToSearch() {
		$GLOBALS['wp_post_types'];

		$backup_post_types = $GLOBALS['wp_post_types'];

		// Set all post types to be excluded from search
		foreach ( $GLOBALS['wp_post_types'] as $key => $post_type ) {
			$backup_post_types[ $key ] = clone $post_type;

			$post_type->exclude_from_search = true;
		}

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

		$query = new WP_Query( $args );

		$this->assertEquals( 0, $query->post_count );
		$this->assertEquals( 0, $query->found_posts );

		wp_reset_postdata();

		// Reset the main $wp_post_types item
		$GLOBALS['wp_post_types'] = $backup_post_types;
	}

	/**
	 * Test cache_results is off by default
	 *
	 * @since 1.5
	 * @group single-site
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
	 * @group single-site
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
	 * @group single-site
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
	 * @group single-site
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
	 * 
	 * @link https://github.com/10up/ElasticPress/issues/306
	 * @group single-site
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
	 * Helper method for mocking indexable post statuses
	 *
	 * @param   array $post_statuses
	 * @return  array
	 */
	public function mock_indexable_post_status( $post_statuses ) {
		$post_statuses[] = "draft";
		return $post_statuses;
	}

	/**
	 * Test invalid post date time
	 * 
	 * @group single-site
	 */
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
	 *
	 * @since 1.6
	 * @group single-site
	 */
	public function testExcludeIndexablePostType() {
		$post_types = ep_get_indexable_post_types();
		$this->assertArrayNotHasKey( 'ep_test_excluded', $post_types );
		$this->assertArrayNotHasKey( 'ep_test_not_public', $post_types );
	}

	/**
	 * Test to make sure that brand new posts with 'auto-draft' post status do not fire delete or sync.
	 *
	 * @since 1.6
	 * @link https://github.com/10up/ElasticPress/issues/343
	 * @group single-site
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
	 *
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

	/**
	 * Test to verify meta array is built correctly.
	 *
	 * @since 1.7
	 * @group single-site
	 */
	public function testPrepareMeta() {

		$post_id     = ep_create_and_sync_post();
		$post        = get_post( $post_id );
		$meta_values = array(
			'value 1',
			'value 2',
		);

		add_post_meta( $post_id, 'test_meta_1', 'value 1' );
		add_post_meta( $post_id, 'test_meta_1', 'value 2' );
		add_post_meta( $post_id, 'test_meta_1', $meta_values );
		add_post_meta( $post_id, '_test_private_meta_1', 'value 1' );
		add_post_meta( $post_id, '_test_private_meta_1', 'value 2' );
		add_post_meta( $post_id, '_test_private_meta_1', $meta_values );

		$api = new EP_API();

		$meta_1 = $api->prepare_meta( $post );

		add_filter( 'ep_prepare_meta_allowed_protected_keys', array( $this, 'filter_ep_prepare_meta_allowed_protected_keys' ) );

		$meta_2 = $api->prepare_meta( $post );

		add_filter( 'ep_prepare_meta_excluded_public_keys', array( $this, 'filter_ep_prepare_meta_excluded_public_keys' ) );

		$meta_3 = $api->prepare_meta( $post );

		$this->assertTrue( is_array( $meta_1 ) && 1 === sizeof( $meta_1 ) );
		$this->assertTrue( is_array( $meta_1 ) && array_key_exists( 'test_meta_1', $meta_1 ) );
		$this->assertTrue( is_array( $meta_2 ) && 2 === sizeof( $meta_2 ) );
		$this->assertTrue( is_array( $meta_2 ) && array_key_exists( 'test_meta_1', $meta_2 ) && array_key_exists( '_test_private_meta_1', $meta_2 ) );
		$this->assertTrue( is_array( $meta_3 ) && 1 === sizeof( $meta_3 ) );
		$this->assertTrue( is_array( $meta_3 ) && array_key_exists( '_test_private_meta_1', $meta_3 ) );

	}

	/**
	 * Helper method for filtering private meta keys
	 *
	 * @param  array $meta_keys
	 * @return array
	 */
	public function filter_ep_prepare_meta_allowed_protected_keys( $meta_keys ) {

		$meta_keys[] = '_test_private_meta_1';

		return $meta_keys;

	}

	/**
	 * Helper method for filtering excluded meta keys
	 *
	 * @param  array $meta_keys
	 * @return array
	 */
	public function filter_ep_prepare_meta_excluded_public_keys( $meta_keys ) {

		$meta_keys[] = 'test_meta_1';

		return $meta_keys;

	}

	/**
	 * Test meta preparation
	 *
	 * @since 1.7
	 * @group single-site
	 */
	public function testMetaValueTypes() {

		$api = new EP_API();

		$intval         = $api->prepare_meta_value_types( 13 );
		$floatval       = $api->prepare_meta_value_types( 13.43 );
		$textval        = $api->prepare_meta_value_types( 'some text' );
		$bool_false_val = $api->prepare_meta_value_types( false );
		$bool_true_val  = $api->prepare_meta_value_types( true );
		$dateval        = $api->prepare_meta_value_types( '2015-01-01' );

		$this->assertTrue( is_array( $intval ) && 5 === sizeof( $intval ) );
		$this->assertTrue( is_array( $intval ) && array_key_exists( 'long', $intval ) && 13 === $intval['long'] );
		$this->assertTrue( is_array( $floatval ) && 5 === sizeof( $floatval ) );
		$this->assertTrue( is_array( $floatval ) && array_key_exists( 'double', $floatval ) && 13.43 === $floatval['double'] );
		$this->assertTrue( is_array( $textval ) && 6 === sizeof( $textval ) );
		$this->assertTrue( is_array( $textval ) && array_key_exists( 'raw', $textval ) && 'some text' === $textval['raw'] );
		$this->assertTrue( is_array( $bool_false_val ) && 3 === sizeof( $bool_false_val ) );
		$this->assertTrue( is_array( $bool_false_val ) && array_key_exists( 'boolean', $bool_false_val ) && false === $bool_false_val['boolean'] );
		$this->assertTrue( is_array( $bool_true_val ) && 3 === sizeof( $bool_true_val ) );
		$this->assertTrue( is_array( $bool_true_val ) && array_key_exists( 'boolean', $bool_true_val ) && true === $bool_true_val['boolean'] );
		$this->assertTrue( is_array( $dateval ) && 6 === sizeof( $dateval ) );
		$this->assertTrue( is_array( $dateval ) && array_key_exists( 'datetime', $dateval ) && '2015-01-01 00:00:00' === $dateval['datetime'] );

	}

	/**
	 * Test meta key query
	 *
	 * @since 2.1
	 * @group single-site
	 */
	public function testMetaKeyQuery() {

		ep_create_and_sync_post( array( 'post_content' => 'the post content findme' ) );
		ep_create_and_sync_post( array( 'post_content' => 'post content findme' ), array( 'test_key' => 'test' ) );

		ep_refresh_index();

		$args = array(
			's' => 'findme',
			'meta_key' => 'test_key',
			'meta_value' => 'test',
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, $query->found_posts );

	}

	/**
	 * Test meta key query with num
	 *
	 * @since 2.1
	 * @group single-site
	 */
	public function testMetaKeyQueryNum() {

		ep_create_and_sync_post( array( 'post_content' => 'the post content findme' ) );
		ep_create_and_sync_post( array( 'post_content' => 'post content findme' ), array( 'test_key' => 5 ) );

		ep_refresh_index();
		$args = array(
			's' => 'findme',
			'meta_key' => 'test_key',
			'meta_value_num' => 5,
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, $query->found_posts );

	}

	/**
	 * Test mix meta_key with meta_query
	 *
	 * @since 2.1
	 * @group single-site
	 */
	public function testMetaKeyQueryMix() {

		ep_create_and_sync_post( array( 'post_content' => 'the post content findme' ) );
		ep_create_and_sync_post( array( 'post_content' => 'post content findme' ), array( 'test_key' => 5, 'test_key_2' => 'aaa' ) );

		ep_refresh_index();
		$args = array(
			's' => 'findme',
			'meta_key' => 'test_key',
			'meta_value_num' => 5,
			'meta_query' => array(
				array(
					'key' => 'test_key_2',
					'value' => 'aaa',
				),
			),
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, $query->found_posts );

	}

	/**
	 * Test numeric integer meta queries
	 *
	 * @since 1.7
	 * @group single-site
	 */
	public function testMetaValueTypeQueryNumeric() {

		ep_create_and_sync_post( array( 'post_content' => 'the post content findme' ) );
		ep_create_and_sync_post( array( 'post_content' => 'post content findme' ), array( 'test_key' => 100 ) );
		ep_create_and_sync_post( array( 'post_content' => 'the post content findme' ) );
		ep_create_and_sync_post( array( 'post_content' => 'post content findme' ), array( 'test_key' => 101 ) );

		ep_refresh_index();
		$args = array(
			's' => 'findme',
			'meta_query' => array(
				array(
					'key' => 'test_key',
					'value' => 101,
					'compare' => '>=',
					'type' => 'numeric',
				)
			),
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, $query->found_posts );

		$args = array(
			's' => 'findme',
			'meta_query' => array(
				array(
					'key' => 'test_key',
					'value' => 100,
					'compare' => '=',
					'type' => 'numeric',
				)
			),
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, $query->found_posts );

		$args = array(
			's' => 'findme',
			'meta_query' => array(
				array(
					'key' => 'test_key',
					'value' => 103,
					'compare' => '<=',
					'type' => 'numeric',
				)
			),
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );

	}

	/**
	 * Test decimal meta queries
	 *
	 * @since 1.7
	 * @group single-site
	 */
	public function testMetaValueTypeQueryDecimal() {

		ep_create_and_sync_post( array( 'post_content' => 'the post content findme' ) );
		ep_create_and_sync_post( array( 'post_content' => 'post content findme' ), array( 'test_key' => 15.5 ) );
		ep_create_and_sync_post( array( 'post_content' => 'the post content findme' ) );
		ep_create_and_sync_post( array( 'post_content' => 'post content findme' ), array( 'test_key' => 16.5 ) );

		ep_refresh_index();
		$args = array(
			's' => 'findme',
			'meta_query' => array(
				array(
					'key' => 'test_key',
					'value' => 16.5,
					'compare' => '<',
					'type' => 'decimal',
				)
			),
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, $query->found_posts );

		$args = array(
			's' => 'findme',
			'meta_query' => array(
				array(
					'key' => 'test_key',
					'value' => 16.5,
					'compare' => '=',
					'type' => 'decimal',
				)
			),
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, $query->found_posts );
	}

	/**
	 * Test character meta queries. Really just defaults to a normal string query
	 *
	 * @since 1.7
	 * @group single-site
	 */
	public function testMetaValueTypeQueryChar() {

		ep_create_and_sync_post( array( 'post_content' => 'the post content findme' ) );
		ep_create_and_sync_post( array( 'post_content' => 'post content findme' ), array( 'test_key' => 'abc' ) );
		ep_create_and_sync_post( array( 'post_content' => 'the post content findme' ) );
		ep_create_and_sync_post( array( 'post_content' => 'post content findme' ), array( 'test_key' => 'acc' ) );

		ep_refresh_index();
		$args = array(
			's' => 'findme',
			'meta_query' => array(
				array(
					'key' => 'test_key',
					'value' => 'abc',
					'compare' => '=',
					'type' => 'char',
				)
			),
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, $query->found_posts );
	}

	/**
	 * Test date meta queries
	 *
	 * @since 1.7
	 * @group single-site
	 */
	public function testMetaValueTypeQueryDate() {
		ep_create_and_sync_post( array( 'post_content' => 'post content findme' ), array( 'test_key' => '11/13/15' ) );
		ep_create_and_sync_post( array( 'post_content' => 'the post content findme' ) );
		ep_create_and_sync_post( array( 'post_content' => 'post content findme' ), array( 'test_key' => '11/15/15' ) );

		ep_refresh_index();
		$args = array(
			's' => 'findme',
			'meta_query' => array(
				array(
					'key' => 'test_key',
					'value' => '2015-11-14',
					'compare' => '>',
					'type' => 'date',
				)
			),
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );

		$args = array(
			's' => 'findme',
			'meta_query' => array(
				array(
					'key' => 'test_key',
					'value' => '2015-11-15',
					'compare' => '=',
					'type' => 'date',
				)
			),
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );

	}

	/**
	 * Test time meta queries
	 *
	 * @since 1.7
	 * @group single-site
	 */
	public function testMetaValueTypeQueryTime() {
		ep_create_and_sync_post( array( 'post_content' => 'the post content findme' ) );
		ep_create_and_sync_post( array( 'post_content' => 'post content findme' ), array( 'test_key' => '5:00am' ) );

		ep_refresh_index();
		$args = array(
			's' => 'findme',
			'meta_query' => array(
				array(
					'key' => 'test_key',
					'value' => '17:00:00',
					'compare' => '<',
					'type' => 'time',
				)
			),
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );

		$args = array(
			's' => 'findme',
			'meta_query' => array(
				array(
					'key' => 'test_key',
					'value' => '05:00:00',
					'compare' => '=',
					'type' => 'time',
				)
			),
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );
	}

	/**
	 * Test date time meta queries
	 *
	 * @since 1.7
	 * @group single-site
	 */
	public function testMetaValueTypeQueryDatetime() {
		ep_create_and_sync_post( array( 'post_content' => 'the post content findme' ) );
		ep_create_and_sync_post( array( 'post_content' => 'post content findme' ), array( 'test_key' => '5:00am 1/2/12' ) );

		ep_refresh_index();
		$args = array(
			's' => 'findme',
			'meta_query' => array(
				array(
					'key' => 'test_key',
					'value' => '2013-03-02 06:00:15',
					'compare' => '<',
					'type' => 'datetime',
				)
			),
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );

		$args = array(
			's' => 'findme',
			'meta_query' => array(
				array(
					'key' => 'test_key',
					'value' => '2012-01-02 05:00:00',
					'compare' => '=',
					'type' => 'datetime',
				)
			),
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );

		$args = array(
			's' => 'findme',
			'meta_query' => array(
				array(
					'key' => 'test_key',
					'value' => '2011-01-02 07:30:00',
					'compare' => '>',
					'type' => 'datetime',
				)
			),
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );
	}
	
	/*
	 * Test a post_parent query
	 * @group single-site
	 * @since 2.0
	 */
	public function testPostParentQuery() {
		$parent_post = ep_create_and_sync_post( array( 'post_content' => 'findme test 1') );
		ep_create_and_sync_post( array( 'post_content' => 'findme test 2', 'post_parent' => $parent_post ) );
		ep_create_and_sync_post( array( 'post_content' => 'findme test 3'));

		ep_refresh_index();

		$args = array(
			's'             => 'findme',
			'post_parent' => $parent_post
		);

		$query = new WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, $query->found_posts );
	}

	/**
	 * Test register feature
	 * 
	 * @since 2.1
	 * @group single-site
	 */
	public function testRegisterFeature() {
		ep_register_feature( 'test', array(
			'title' => 'Test',
		) );

		$feature = ep_get_registered_feature( 'test' );

		$this->assertTrue( ! empty( EP_Features::factory()->registered_features['test'] ) );
		$this->assertTrue( ! empty( $feature ) );
	}

	/**
	 * Test setup features
	 * 
	 * @since 2.1
	 * @group single-site
	 */
	public function testSetupFeatures() {
		delete_option( 'ep_active_features' );

		ep_register_feature( 'test', array(
			'title' => 'Test',
		) );

		$feature = ep_get_registered_feature( 'test' );

		$this->assertTrue( ! empty( $feature ) );

		$this->assertTrue( ! $feature->is_active() );

		ep_activate_feature( 'test' );

		EP_Features::factory()->setup_features();

		$this->assertTrue( $feature->is_active() );
	}
	
	/**
	 * Test Tax Query NOT IN operator
	 *
	 * @since 2.1
	 * @group single-site
	 */
	public function testTaxQueryNotIn() {
		ep_create_and_sync_post( array( 'post_content' => 'findme test 1', 'tags_input' => array( 'one', 'two' ) ) );
		ep_create_and_sync_post( array( 'post_content' => 'findme test 2', 'tags_input' => array( 'one' ) ) );
		
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
		
		$args = array(
			's'         => 'findme',
			'tax_query' => array(
				array(
					'taxonomy' => 'post_tag',
					'terms'    => array( 'one' ),
					'field'    => 'slug',
				),
				array(
					'taxonomy' => 'post_tag',
					'terms'    => array( 'two' ),
					'field'    => 'slug',
					'operator' => 'NOT IN',
				)
			)
		);
		
		$query = new WP_Query( $args );
		
		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, $query->found_posts );
	}
	
	/**
	 * Test post_mime_type query
	 *
	 * @since 2.3
	 */
	function testPostMimeTypeQuery() {
		ep_create_and_sync_post( array( 'post_type' => 'attachment', 'post_mime_type' => 'image/jpeg', 'post_status' => 'inherit' ) );
		ep_create_and_sync_post( array( 'post_type' => 'attachment', 'post_mime_type' => 'image/jpeg', 'post_status' => 'inherit' ) );
		ep_create_and_sync_post( array( 'post_type' => 'attachment', 'post_mime_type' => 'application/pdf', 'post_status' => 'inherit' ) );
		
		ep_refresh_index();
		
		$args = array(
			'ep_integrate' => true,
			'post_mime_type' => 'image',
			'post_type' => 'attachment',
			'post_status' => 'inherit'
		);
		
		$query = new WP_Query( $args );
		
		$this->assertEquals( 2, $query->post_count );
		
		$args = array(
			'ep_integrate' => true,
			'post_mime_type' => array(
				'image/jpeg',
				'application/pdf',
			),
			'post_type' => 'attachment',
			'post_status' => 'inherit'
		);
		
		$query = new WP_Query( $args );
		
		$this->assertEquals( 3, $query->found_posts );
	}
}
