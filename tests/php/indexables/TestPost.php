<?php
/**
 * Test post indexable functionality
 *
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress;

/**
 * Test post indexable class
 */
class TestPost extends BaseTestCase {
	/**
	 * Checking if HTTP request returns 404 status code.
	 *
	 * @var boolean
	 */
	public $is_404 = false;

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

		ElasticPress\Elasticsearch::factory()->delete_all_indices();
		ElasticPress\Indexables::factory()->get( 'post' )->put_mapping();

		ElasticPress\Indexables::factory()->get( 'post' )->sync_manager->sync_queue = [];

		$this->setup_test_post_type();

		/**
		 * Most of our search test are bundled into core tests for legacy reasons
		 */
		ElasticPress\Features::factory()->activate_feature( 'search' );
		ElasticPress\Features::factory()->setup_features();

		// Need to call this since it's hooked to init
		ElasticPress\Features::factory()->get_registered_feature( 'search' )->search_setup();
	}

	/**
	 * Clean up after each test. Reset our mocks
	 *
	 * @since 0.1.0
	 */
	public function tearDown() {
		parent::tearDown();

		// make sure no one attached to this
		remove_filter( 'ep_sync_terms_allow_hierarchy', array( $this, 'ep_allow_multiple_level_terms_sync' ), 100 );
		$this->fired_actions = array();
	}

	/**
	 * Test the building of index mappings
	 * 
	 * @since 3.6
	 * @group post
	 */
	public function testPostBuildMapping() {
		$mapping_and_settings = ElasticPress\Indexables::factory()->get( 'post' )->build_mapping();

		// The mapping is currently expected to have both `mappings` and `settings` elements
		$this->assertArrayHasKey( 'settings', $mapping_and_settings, 'Built mapping is missing settings array' );
		$this->assertArrayHasKey( 'mappings', $mapping_and_settings, 'Built mapping is missing mapping array' );
	}

	/**
	 * Test the building of index settings
	 * 
	 * @since 3.6
	 * @group post
	 */
	public function testPostBuildSettings() {
		$settings = ElasticPress\Indexables::factory()->get( 'post' )->build_settings();

		$expected_keys = array(
			'index.number_of_shards',
			'index.number_of_replicas',
			'index.mapping.total_fields.limit',
			'index.max_shingle_diff',
			'index.max_result_window',
			'index.mapping.ignore_malformed',
			'analysis',
		);

		$actual_keys = array_keys( $settings );

		$diff = array_diff( $expected_keys, $actual_keys );

		$this->assertEquals( $diff, array() );
	}

	/**
	 * Test a simple post sync
	 *
	 * @since 0.9
	 * @group post
	 */
	public function testPostSync() {
		add_action( 'ep_sync_on_transition', array( $this, 'action_sync_on_transition' ), 10, 0 );

		$post_id = Functions\create_and_sync_post();

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$this->assertTrue( ! empty( $this->fired_actions['ep_sync_on_transition'] ) );

		$post = ElasticPress\Indexables::factory()->get( 'post' )->get( $post_id );
		$this->assertTrue( ! empty( $post ) );
	}

	/**
	 * Test a post sync on meta add
	 *
	 * @since 2.0
	 * @group post
	 */
	public function testPostSyncOnMetaAdd() {
		add_action( 'ep_sync_on_meta_update', array( $this, 'action_sync_on_meta_update' ), 10, 0 );

		$post_id = Functions\create_and_sync_post();

		$this->fired_actions = array();

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		update_post_meta( $post_id, 'test', 1 );

		ElasticPress\Indexables::factory()->get( 'post' )->sync_manager->index_sync_queue();

		$this->assertTrue( ! empty( $this->fired_actions['ep_sync_on_meta_update'] ) );

		$post = ElasticPress\Indexables::factory()->get( 'post' )->get( $post_id );
		$this->assertTrue( ! empty( $post ) );
	}

	/**
	 * Test a post sync on meta update
	 *
	 * @since 2.0
	 * @group post
	 */
	public function testPostSyncOnMetaUpdate() {
		add_action( 'ep_sync_on_meta_update', array( $this, 'action_sync_on_meta_update' ), 10, 0 );

		$post_id = Functions\create_and_sync_post();

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		update_post_meta( $post_id, 'test', 1 );

		$this->fired_actions = array();

		update_post_meta( $post_id, 'test', 2 );

		ElasticPress\Indexables::factory()->get( 'post' )->sync_manager->index_sync_queue();

		$this->assertTrue( ! empty( $this->fired_actions['ep_sync_on_meta_update'] ) );

		$post = ElasticPress\Indexables::factory()->get( 'post' )->get( $post_id );
		$this->assertTrue( ! empty( $post ) );
	}

	/**
	 * Test a post sync on comment count update
	 *
	 * @group post
	 */
	public function testPostSyncOnCommentCountUpdate() {
		$post_id = Functions\create_and_sync_post();

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$post_no_comments = ElasticPress\Indexables::factory()->get( 'post' )->get( $post_id );

		wp_insert_comment(
			array(
				'comment_post_ID' => $post_id,
				'comment_author' => 'Testy Testman',
				'comment_content' => 'Test comment',
			)
		);

		ElasticPress\Indexables::factory()->get( 'post' )->sync_manager->index_sync_queue();

		$post_with_comments = ElasticPress\Indexables::factory()->get( 'post' )->get( $post_id );

		$this->assertEquals( 0, intval( $post_no_comments['comment_count'] ), 'comment count for post should be 0 initially' );
		$this->assertEquals( 1, intval( $post_with_comments['comment_count'] ), 'comment count for post should be 1 after sync' );
	}

	/**
	 * Test pagination with offset
	 *
	 * @since 2.1
	 * @group post
	 */
	public function testPaginationWithOffset() {
		// Setting date to ensure it's not a coinflip on whether or not they share a timestamp.
		Functions\create_and_sync_post( array( 'post_title' => 'one', 'post_date' => '2020-08-24 12:30:00' ) );
		Functions\create_and_sync_post( array( 'post_title' => 'two', 'post_date' => '2020-08-25 12:30:00' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$query = new \WP_Query(
			array(
				'post_type'      => 'post',
				'ep_integrate'   => true,
				'posts_per_page' => 1,
				'offset'         => 1,
				'orderby'        => 'date',
				'order'          => 'ASC',
			)
		);

		$this->assertEquals( 1, count( $query->posts ) );
		$this->assertEquals( 'two', $query->posts[0]->post_title );
	}

	/**
	 * Test WP Query search on post content
	 *
	 * @since 0.9
	 * @group post
	 */
	public function testWPQuerySearchContent() {
		$post_ids = array();

		$post_ids[0] = Functions\create_and_sync_post();
		$post_ids[1] = Functions\create_and_sync_post();
		$post_ids[2] = Functions\create_and_sync_post( array( 'post_content' => 'findme' ) );
		$post_ids[3] = Functions\create_and_sync_post();
		$post_ids[4] = Functions\create_and_sync_post( array( 'post_content' => 'findme' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's' => 'findme',
		);

		add_action( 'ep_wp_query_search', array( $this, 'action_wp_query_search' ), 10, 0 );

		$query = new \WP_Query( $args );

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
	 * @group post
	 */
	public function testWPQuerySearchTitle() {
		$post_ids = array();

		$post_ids[0] = Functions\create_and_sync_post();
		$post_ids[1] = Functions\create_and_sync_post();
		$post_ids[2] = Functions\create_and_sync_post( array( 'post_title' => 'findme test' ) );
		$post_ids[3] = Functions\create_and_sync_post( array( 'post_title' => 'findme test2' ) );
		$post_ids[4] = Functions\create_and_sync_post( array( 'post_title' => 'findme test2' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's' => 'findme',
		);

		add_action( 'ep_wp_query_search', array( $this, 'action_wp_query_search' ), 10, 0 );

		$query = new \WP_Query( $args );

		$this->assertTrue( ! empty( $this->fired_actions['ep_wp_query_search'] ) );

		$this->assertEquals( $query->post_count, 3 );
		$this->assertEquals( $query->found_posts, 3 );
	}

	/**
	 * Make sure proper taxonomies are synced with post. Hidden taxonomies should be skipped!
	 *
	 * @since 0.1.1
	 * @group post
	 */
	public function testPostTermSync() {

		add_filter( 'ep_post_sync_args', array( $this, 'filter_post_sync_args' ), 10, 1 );

		$post_id = Functions\create_and_sync_post(
			array(
				'tags_input' => array( 'test-tag', 'test-tag2' ),
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		// Check if ES post sync filter has been triggered
		$this->assertTrue( ! empty( $this->applied_filters['ep_post_sync_args'] ) );

		// Check if tag was synced
		$post = ElasticPress\Indexables::factory()->get( 'post' )->get( $post_id );
		$this->assertTrue( ! empty( $post['terms']['post_tag'] ) );
	}

	/**
	 * Make sure that when terms associated with a post are updated that the post is reindexed
	 *
	 * @since 3.5
	 * @group post
	 */
	public function testPostTermSyncOnTermEdited() {
		$term = wp_insert_term( 'Test Category', 'category', array( 'slug' => 'test-category' ) );

		$post_id = Functions\create_and_sync_post(
			array(
				'post_category' => array( $term['term_id'] ),
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		add_filter( 'ep_post_sync_args', array( $this, 'filter_post_sync_args' ), 10, 1 );

		// Update the term
		wp_update_term( $term['term_id'], 'category', array( 'slug' => 'new-slug', 'name' => 'New Name' ) );

		$this->applied_filters = array();

		ElasticPress\Indexables::factory()->get( 'post' )->sync_manager->index_sync_queue();

		// Check if ES post sync filter has been triggered
		$this->assertTrue( ! empty( $this->applied_filters['ep_post_sync_args'] ) );

		// Check if new term slug was synced
		$post = ElasticPress\Indexables::factory()->get( 'post' )->get( $post_id );

		$this->assertEquals( 'new-slug', $post['terms']['category'][ 0 ]['slug'] );
		$this->assertEquals( 'New Name', $post['terms']['category'][ 0 ]['name'] );
	}

	/**
	 * Make sure that when terms associated with a post are deleted that the posts are reindexed
	 *
	 * @since 3.5
	 * @group post
	 */
	public function testPostTermSyncOnSetObjectTerms() {
		$term = wp_insert_term( 'Test Tag', 'post_tag', array( 'slug' => 'test-tag' ) );

		$post_id = Functions\create_and_sync_post(
			array(
				'tags_input' => array( $term['term_id'] ),
			)
		);

		$post_id_two = Functions\create_and_sync_post(
			array(
				'tags_input' => array( $term['term_id'] ),
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		// Double check that our post is associated with this term (otherwise the test isn't doing anything)
		$post = ElasticPress\Indexables::factory()->get( 'post' )->get( $post_id );
		$post_two = ElasticPress\Indexables::factory()->get( 'post' )->get( $post_id_two );

		$this->assertEquals( $term['term_id'], $post['terms']['post_tag'][ 0 ]['term_id'] );
		$this->assertEquals( $term['term_id'], $post_two['terms']['post_tag'][ 0 ]['term_id'] );

		add_filter( 'ep_post_sync_args', array( $this, 'filter_post_sync_args' ), 10, 1 );

		// Delete the term
		wp_delete_term( $term['term_id'], 'post_tag' );

		$this->applied_filters = array();

		ElasticPress\Indexables::factory()->get( 'post' )->sync_manager->index_sync_queue();

		// Check if ES post sync filter has been triggered
		$this->assertTrue( ! empty( $this->applied_filters['ep_post_sync_args'] ) );

		// Check if new term slug was synced
		$post = ElasticPress\Indexables::factory()->get( 'post' )->get( $post_id );
		$post_two = ElasticPress\Indexables::factory()->get( 'post' )->get( $post_id_two );

		// And the post documents no longer have any tags
		$this->assertArrayNotHasKey( 'post_tag', $post['terms'] );
		$this->assertArrayNotHasKey( 'post_tag', $post_two['terms'] );
	}

	/**
	 * Ensure all the expected data is present when syncing terms.
	 *
	 * @group post
	 */
	public function testPostTermSyncData() {
		global $wpdb;

		$test_post_id = Functions\create_and_sync_post();
		$test_post    = get_post( $test_post_id );

		// Create a new taxonomy & term (with a parent).
		$taxonomy_name = rand_str( 32 );
		register_taxonomy( $taxonomy_name, $test_post->post_type, array( 'label' => $taxonomy_name ) );
		register_taxonomy_for_object_type( $taxonomy_name, $test_post->post_type );
		$parent_term_parent  = wp_insert_term( rand_str( 32 ), $taxonomy_name );
		$parent_term         = wp_insert_term( rand_str( 32 ), $taxonomy_name, [ 'parent' => $parent_term_parent['term_id'] ] );
		$test_term           = wp_insert_term( rand_str( 32 ), $taxonomy_name, [ 'parent' => $parent_term['term_id'] ] );

		// Assign the test term to our post and sync it up.
		wp_set_object_terms( $test_post_id, array( $test_term['term_id'] ), $taxonomy_name, true );
		$wpdb->query( $wpdb->prepare( "UPDATE $wpdb->term_relationships SET term_order = 123 WHERE object_id = %d AND term_taxonomy_id = %d;", $test_post_id, $test_term[ 'term_taxonomy_id' ] ) );

		ElasticPress\Indexables::factory()->get( 'post' )->index( $test_post_id, true );
		$indexed_post_data = ElasticPress\Indexables::factory()->get( 'post' )->get( $test_post_id );

		// Now ensure all the appropiate term data is present.
		$indexed_term_data  = $indexed_post_data['terms'][ $taxonomy_name ];
		$this->assertTrue( count( $indexed_term_data ) === 3 );

		foreach ( $indexed_term_data as $indexed_term ) {
			$this->assertTrue( in_array( $indexed_term['term_id'], [ $test_term['term_id'], $parent_term['term_id'], $parent_term_parent['term_id'] ], true ) );

			$actual_data      = get_term( $indexed_term['term_id'], $taxonomy_name, ARRAY_A );
			$term_order_query = $wpdb->get_results( $wpdb->prepare( "SELECT term_order FROM $wpdb->term_relationships WHERE object_id = %d AND term_taxonomy_id = %d;", $test_post_id, $indexed_term[ 'term_taxonomy_id' ] ), OBJECT );

			$expected_data = [
				'term_id'          => $actual_data[ 'term_id' ],
				'slug'             => $actual_data[ 'slug' ],
				'name'             => $actual_data[ 'name' ],
				'parent'           => $actual_data[ 'parent' ],
				'term_taxonomy_id' => $actual_data[ 'term_taxonomy_id' ],
				'term_order'       => isset( $term_order_query[0] ) ? $term_order_query[0]->term_order : 0,
			];

			$this->assertEquals( $indexed_term, $expected_data );

			if ( $test_term['term_id'] === $indexed_term['term_id'] ) {
				// Additional check to make extra sure term_order is syncing correctly.
				$this->assertEquals( 123, $indexed_term[ 'term_order' ] );
			}
		}
	}

	/**
	 * Make sure proper non-hierarchical taxonomies are synced with post when ep_sync_terms_allow_hierarchy is
	 * set to false.
	 *
	 * @group post
	 */
	public function testPostTermSyncSingleLevel() {
		add_filter( 'ep_sync_terms_allow_hierarchy', array( $this, 'ep_disallow_multiple_level_terms_sync' ), 100, 1 );

		$post_id = Functions\create_and_sync_post();
		$post    = get_post( $post_id );

		$tax_name = rand_str( 32 );
		register_taxonomy( $tax_name, $post->post_type, array( 'label' => $tax_name ) );
		register_taxonomy_for_object_type( $tax_name, $post->post_type );

		$term_1_name = rand_str( 32 );
		$term1       = wp_insert_term( $term_1_name, $tax_name );

		$term_2_name = rand_str( 32 );
		$term2       = wp_insert_term( $term_2_name, $tax_name, array( 'parent' => $term1['term_id'] ) );

		$term_3_name = rand_str( 32 );
		$term3       = wp_insert_term( $term_3_name, $tax_name, array( 'parent' => $term2['term_id'] ) );

		wp_set_object_terms( $post_id, array( $term3['term_id'] ), $tax_name, true );

		ElasticPress\Indexables::factory()->get( 'post' )->index( $post_id, true );

		$post = ElasticPress\Indexables::factory()->get( 'post' )->get( $post_id );

		$terms = $post['terms'];
		$this->assertTrue( isset( $terms[ $tax_name ] ) );

		$indexed_terms  = $terms[ $tax_name ];
		$expected_terms = array( $term3['term_id'] );

		$this->assertTrue( count( $indexed_terms ) > 0 );

		foreach ( $indexed_terms as $term ) {
			$this->assertTrue( in_array( $term['term_id'], $expected_terms, true ) );
		}
	}

	/**
	 * Helper filter for term syncing
	 *
	 * @return boolean
	 */
	public function ep_disallow_multiple_level_terms_sync() {
		return false;
	}

	/**
	 * Make sure proper hierarchical taxonomies are synced with post and parent terms are included.
	 *
	 * @group post
	 */
	public function testPostTermSyncHierarchyMultipleLevel() {

		$post_id = Functions\create_and_sync_post();
		$post    = get_post( $post_id );

		$tax_name = rand_str( 32 );
		register_taxonomy( $tax_name, $post->post_type, array( 'label' => $tax_name ) );
		register_taxonomy_for_object_type( $tax_name, $post->post_type );

		$term_1_name = rand_str( 32 );
		$term1       = wp_insert_term( $term_1_name, $tax_name );

		$term_2_name = rand_str( 32 );
		$term2       = wp_insert_term( $term_2_name, $tax_name, array( 'parent' => $term1['term_id'] ) );

		$term_3_name = rand_str( 32 );
		$term3       = wp_insert_term( $term_3_name, $tax_name, array( 'parent' => $term2['term_id'] ) );

		wp_set_object_terms( $post_id, array( $term3['term_id'] ), $tax_name, true );

		ElasticPress\Indexables::factory()->get( 'post' )->index( $post_id, true );

		$post = ElasticPress\Indexables::factory()->get( 'post' )->get( $post_id );

		$terms = $post['terms'];
		$this->assertTrue( isset( $terms[ $tax_name ] ) );
		$this->assertTrue( count( $terms[ $tax_name ] ) === 3 );
		$indexed_terms  = $terms[ $tax_name ];
		$expected_terms = array( $term1['term_id'], $term2['term_id'], $term3['term_id'] );

		$this->assertTrue( count( $indexed_terms ) > 0 );

		foreach ( $indexed_terms as $term ) {
			$this->assertTrue( in_array( $term['term_id'], $expected_terms, true ) );
		}
	}

	/**
	 * Make sure proper hierarchical taxonomies are synced with post, terms are searchable, and
	 * parent terms are not included.
	 *
	 * @group post
	 */
	public function testPostTermSyncHierarchyMultipleLevelQuery() {

		add_filter( 'ep_sync_terms_allow_hierarchy', array( $this, 'ep_disallow_multiple_level_terms_sync' ), 100, 1 );
		$post_id = Functions\create_and_sync_post( array( 'post_title' => '#findme' ) );
		$post    = get_post( $post_id );

		$tax_name = rand_str( 32 );
		register_taxonomy( $tax_name, $post->post_type, array( 'label' => $tax_name ) );
		register_taxonomy_for_object_type( $tax_name, $post->post_type );

		$term_1_name = rand_str( 32 );
		$term1       = wp_insert_term( $term_1_name, $tax_name );

		$term_2_name = rand_str( 32 );
		$term2       = wp_insert_term( $term_2_name, $tax_name, array( 'parent' => $term1['term_id'] ) );

		$term_3_name = rand_str( 32 );
		$term3       = wp_insert_term( $term_3_name, $tax_name, array( 'parent' => $term2['term_id'] ) );

		wp_set_object_terms( $post_id, array( $term3['term_id'] ), $tax_name, true );

		ElasticPress\Indexables::factory()->get( 'post' )->index( $post_id, true );
		ElasticPress\Elasticsearch::factory()->refresh_indices();

		add_action( 'ep_wp_query_search', array( $this, 'action_wp_query_search' ), 10, 0 );
		$query = new \WP_Query( array( 's' => '#findme' ) );

		$this->assertNotNull( $query->posts[0] );
		$this->assertNotNull( $query->posts[0]->terms );
		$post = $query->posts[0];

		$terms = $post->terms;
		$this->assertTrue( isset( $terms[ $tax_name ] ) );
		$this->assertTrue( count( $terms[ $tax_name ] ) === 1 );
		$indexed_terms  = $terms[ $tax_name ];
		$expected_terms = array( $term3['term_id'] );

		$this->assertTrue( count( $indexed_terms ) > 0 );

		foreach ( $indexed_terms as $term ) {
			$this->assertTrue( in_array( $term['term_id'], $expected_terms, true ) );
		}
	}

	/**
	 * Make sure proper taxonomies are synced with post.
	 *
	 * @group post
	 */
	public function testPostImplicitTaxonomyQueryCustomTax() {

		$post_id = Functions\create_and_sync_post();
		$post    = get_post( $post_id );

		$tax_name = rand_str( 32 );
		register_taxonomy( $tax_name, $post->post_type, array( 'label' => $tax_name ) );
		register_taxonomy_for_object_type( $tax_name, $post->post_type );

		$term_1_name = rand_str( 32 );
		$term1       = wp_insert_term( $term_1_name, $tax_name );

		wp_set_object_terms( $post_id, array( $term1['term_id'] ), $tax_name, true );

		ElasticPress\Indexables::factory()->get( 'post' )->index( $post_id, true );
		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			$tax_name => $term_1_name,
			's'       => '',
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( $query->post_count, 1 );
		$this->assertEquals( $query->found_posts, 1 );
	}


	/**
	 * Make sure proper taxonomies are synced with post.
	 *
	 * @group post
	 */
	public function testPostImplicitTaxonomyQueryCategoryName() {

		$post_id = Functions\create_and_sync_post();
		$post    = get_post( $post_id );

		$term_1_name = rand_str( 32 );
		$term1       = wp_insert_term( $term_1_name, 'category' );

		wp_set_object_terms( $post_id, array( $term1['term_id'] ), 'category', true );

		ElasticPress\Indexables::factory()->get( 'post' )->index( $post_id, true );
		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			'category_name' => $term_1_name,
			's'             => '',
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( $query->post_count, 1 );
		$this->assertEquals( $query->found_posts, 1 );
	}

	/**
	 * Make sure proper taxonomies are synced with post.
	 *
	 * @group post
	 */
	public function testPostImplicitTaxonomyQueryTag() {

		$post_id = Functions\create_and_sync_post();
		$post    = get_post( $post_id );

		$term_1_name = rand_str( 32 );
		$term1       = wp_insert_term( $term_1_name, 'post_tag' );

		wp_set_object_terms( $post_id, array( $term1['term_id'] ), 'post_tag', true );

		ElasticPress\Indexables::factory()->get( 'post' )->index( $post_id, true );
		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			'tag' => $term_1_name,
			's'   => '',
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( $query->post_count, 1 );
		$this->assertEquals( $query->found_posts, 1 );
	}

	/**
	 * Test WP Query search on post excerpt
	 *
	 * @since 0.9
	 * @group post
	 */
	public function testWPQuerySearchExcerpt() {
		$post_ids = array();

		$post_ids[0] = Functions\create_and_sync_post();
		$post_ids[1] = Functions\create_and_sync_post();
		$post_ids[2] = Functions\create_and_sync_post( array( 'post_excerpt' => 'findme test' ) );
		$post_ids[3] = Functions\create_and_sync_post();
		$post_ids[4] = Functions\create_and_sync_post();

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's' => 'findme',
		);

		add_action( 'ep_wp_query_search', array( $this, 'action_wp_query_search' ), 10, 0 );

		$query = new \WP_Query( $args );

		$this->assertTrue( ! empty( $this->fired_actions['ep_wp_query_search'] ) );

		$this->assertEquals( $query->post_count, 1 );
		$this->assertEquals( $query->found_posts, 1 );
	}

	/**
	 * Test pagination
	 *
	 * @since 0.9
	 * @group post
	 */
	public function testPagination() {
		Functions\create_and_sync_post( array( 'post_excerpt' => 'findme test 1' ) );
		Functions\create_and_sync_post( array( 'post_excerpt' => 'findme test 2' ) );
		Functions\create_and_sync_post( array( 'post_excerpt' => 'findme test 3' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		/**
		 * Tests posts_per_page
		 */

		$found_posts = array();

		$args = array(
			's'              => 'findme',
			'posts_per_page' => 1,
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, count( $query->posts ) );
		$this->assertEquals( 3, $query->found_posts );

		$found_posts[] = $query->posts[0]->ID;

		$args = array(
			's'              => 'findme',
			'posts_per_page' => 1,
			'paged'          => 2,
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, count( $query->posts ) );
		$this->assertEquals( 3, $query->found_posts );

		$found_posts[] = $query->posts[0]->ID;

		$args = array(
			's'              => 'findme',
			'posts_per_page' => 1,
			'paged'          => 3,
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, count( $query->posts ) );
		$this->assertEquals( 3, $query->found_posts );

		$found_posts[] = $query->posts[0]->ID;

		$args = array(
			's'              => 'findme',
			'posts_per_page' => 1,
			'paged'          => 4,
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 0, $query->post_count );
		$this->assertEquals( 0, count( $query->posts ) );
		$this->assertEquals( 3, $query->found_posts );

		$this->assertEquals( 3, count( array_unique( $found_posts ) ) );
	}

	/**
	 * Test a taxonomy query with slug field
	 *
	 * @since 1.8
	 * @group post
	 */
	public function testTaxQuerySlug() {
		Functions\create_and_sync_post(
			array(
				'post_content' => 'findme test 1',
				'tags_input'   => array(
					'one',
					'two',
				),
			)
		);
		Functions\create_and_sync_post( array( 'post_content' => 'findme test 2' ) );
		Functions\create_and_sync_post(
			array(
				'post_content' => 'findme test 3',
				'tags_input'   => array(
					'one',
					'three',
				),
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's'         => 'findme',
			'tax_query' => array(
				array(
					'taxonomy' => 'post_tag',
					'terms'    => array( 'one' ),
					'field'    => 'slug',
				),
			),
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );
	}

	/**
	 * Test a taxonomy query with OR relation
	 *
	 * @since 2.0
	 * @group post
	 */
	public function testTaxQueryOrRelation() {
		$cat1 = wp_create_category( 'category one' );
		$cat2 = wp_create_category( 'category two' );

		Functions\create_and_sync_post(
			array(
				'post_content'  => 'findme test 1',
				'tags_input'    => array( 'one', 'two' ),
				'post_category' => array( $cat1 ),
			)
		);
		Functions\create_and_sync_post( array( 'post_content' => 'findme test 2' ) );
		Functions\create_and_sync_post(
			array(
				'post_content'  => 'findme test 3',
				'tags_input'    => array( 'one', 'three' ),
				'post_category' => array( $cat2 ),
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

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
				),
			),
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );
	}

	/**
	 * Test a taxonomy query with term id field
	 *
	 * @since 1.8
	 * @group post
	 */
	public function testTaxQueryTermId() {
		$post = Functions\create_and_sync_post(
			array(
				'post_content' => 'findme test 1',
				'tags_input'   => array(
					'one',
					'two',
				),
			)
		);
		Functions\create_and_sync_post( array( 'post_content' => 'findme test 2' ) );
		Functions\create_and_sync_post(
			array(
				'post_content' => 'findme test 3',
				'tags_input'   => array(
					'one',
					'three',
				),
			)
		);

		$tags   = wp_get_post_tags( $post );
		$tag_id = 0;

		foreach ( $tags as $tag ) {
			if ( 'one' === $tag->slug ) {
				$tag_id = $tag->term_id;
			}
		}

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's'         => 'findme',
			'tax_query' => array(
				array(
					'taxonomy' => 'post_tag',
					'terms'    => array( $tag_id ),
					'field'    => 'term_id',
				),
			),
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );

		$args = array(
			's'         => 'findme',
			'tax_query' => array(
				array(
					'taxonomy' => 'post_tag',
					'terms'    => array( $tag_id ),
				),
			),
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );
	}

	/**
	 * Test a taxonomy query with term name field
	 *
	 * @since 1.8
	 * @group post
	 */
	public function testTaxQueryTermName() {
		$cat1 = wp_create_category( 'category one' );
		$cat2 = wp_create_category( 'category two' );
		$cat3 = wp_create_category( 'category three' );

		$post = Functions\create_and_sync_post(
			array(
				'post_content'  => 'findme test 1',
				'post_category' => array(
					$cat1,
					$cat2,
				),
			)
		);
		Functions\create_and_sync_post( array( 'post_content' => 'findme test 2' ) );
		Functions\create_and_sync_post(
			array(
				'post_content'  => 'findme test 3',
				'post_category' => array(
					$cat1,
					$cat3,
				),
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's'         => 'findme',
			'tax_query' => array(
				array(
					'taxonomy' => 'category',
					'terms'    => array( 'category one' ),
					'field'    => 'name',
				),
			),
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );
	}

	/**
	 * Test a category_name query
	 *
	 * @since 1.5
	 * @group post
	 */
	public function testCategoryNameQuery() {
		$cat_one   = wp_insert_category( array( 'cat_name' => 'one' ) );
		$cat_two   = wp_insert_category( array( 'cat_name' => 'two' ) );
		$cat_three = wp_insert_category( array( 'cat_name' => 'three' ) );
		Functions\create_and_sync_post(
			array(
				'post_content'  => 'findme test 1',
				'post_category' => array(
					$cat_one,
					$cat_two,
				),
			)
		);
		Functions\create_and_sync_post( array( 'post_content' => 'findme test 2' ) );
		Functions\create_and_sync_post(
			array(
				'post_content'  => 'findme test 3',
				'post_category' => array(
					$cat_one,
					$cat_three,
				),
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's'             => 'findme',
			'category_name' => 'one',
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );
	}

	/**
	 * Test a post__in query
	 *
	 * @since 1.5
	 * @group post
	 */
	public function testPostInQuery() {
		$post_ids = array();

		$post_ids[0] = Functions\create_and_sync_post( array( 'post_content' => 'findme test 1' ) );
		$post_ids[1] = Functions\create_and_sync_post( array( 'post_content' => 'findme test 2' ) );
		$post_ids[2] = Functions\create_and_sync_post( array( 'post_content' => 'findme test 3' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's'        => 'findme',
			'post__in' => array( $post_ids[0], $post_ids[1] ),
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );
	}

	/**
	 * Test a post__not_in query
	 *
	 * @since 1.5
	 * @group post
	 */
	public function testPostNotInQuery() {
		$post_ids = array();

		$post_ids[0] = Functions\create_and_sync_post( array( 'post_content' => 'findme test 1' ) );
		$post_ids[1] = Functions\create_and_sync_post( array( 'post_content' => 'findme test 2' ) );
		$post_ids[2] = Functions\create_and_sync_post( array( 'post_content' => 'findme test 3' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's'            => 'findme',
			'post__not_in' => array( $post_ids[0] ),
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );
	}

	/**
	 * Test an author ID query
	 *
	 * @since 1.0
	 * @group post
	 */
	public function testAuthorIDQuery() {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );

		Functions\create_and_sync_post(
			array(
				'post_content' => 'findme test 1',
				'post_author'  => $user_id,
			)
		);
		Functions\create_and_sync_post( array( 'post_content' => 'findme test 2' ) );
		Functions\create_and_sync_post(
			array(
				'post_content' => 'findme test 3',
				'post_author'  => $user_id,
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's'      => 'findme',
			'author' => $user_id,
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );
	}

	/**
	 * Test an author name query
	 *
	 * @since 1.0
	 * @group post
	 */
	public function testAuthorNameQuery() {
		$user_id = $this->factory->user->create(
			array(
				'user_login'   => 'john',
				'first_name'   => 'Bacon',
				'last_name'    => 'Ipsum',
				'display_name' => 'Bacon Ipsum',
				'role'         => 'administrator',
			)
		);

		Functions\create_and_sync_post(
			array(
				'post_content' => 'findme test 1',
				'post_author'  => $user_id,
			)
		);
		Functions\create_and_sync_post( array( 'post_content' => 'findme test 2' ) );
		Functions\create_and_sync_post(
			array(
				'post_content' => 'findme test 3',
				'post_author'  => $user_id,
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's' => 'findme',
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 3, $query->post_count );
		$this->assertEquals( 3, $query->found_posts );

		$args = array(
			's' => 'Bacon Ipsum',
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );
	}

	/**
	 * Test a post type query for pages
	 *
	 * @since 1.3
	 * @group post
	 */
	public function testPostTypeQueryPage() {
		Functions\create_and_sync_post(
			array(
				'post_content' => 'findme test 1',
				'post_type'    => 'page',
			)
		);
		Functions\create_and_sync_post( array( 'post_content' => 'findme test 2' ) );
		Functions\create_and_sync_post(
			array(
				'post_content' => 'findme test 3',
				'post_type'    => 'page',
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's'         => 'findme',
			'post_type' => 'page',
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );
	}


	/**
	 * Test a post type query for posts
	 *
	 * @since 1.3
	 * @group post
	 */
	public function testPostTypeQueryPost() {
		Functions\create_and_sync_post(
			array(
				'post_content' => 'findme test 1',
				'post_type'    => 'page',
			)
		);
		Functions\create_and_sync_post( array( 'post_content' => 'findme test 2' ) );
		Functions\create_and_sync_post(
			array(
				'post_content' => 'findme test 3',
				'post_type'    => 'page',
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's'         => 'findme',
			'post_type' => 'post',
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, $query->found_posts );
	}

	/**
	 * Test a query with no post type
	 *
	 * @since 1.3
	 * @group post
	 */
	public function testNoPostTypeSearchQuery() {
		Functions\create_and_sync_post(
			array(
				'post_content' => 'findme test 1',
				'post_type'    => 'page',
			)
		);
		Functions\create_and_sync_post( array( 'post_content' => 'findme test 2' ) );
		Functions\create_and_sync_post( array( 'post_content' => 'findme test 3' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		// post_type defaults to "any"
		$args = array(
			's' => 'findme',
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 3, $query->post_count );
		$this->assertEquals( 3, $query->found_posts );
	}

	/**
	 * Test a post status query for published posts
	 *
	 * @since 2.1
	 * @group post
	 */
	public function testPostStatusQueryPublish() {
		Functions\create_and_sync_post(
			array(
				'post_content' => 'findme test 1',
				'post_status'  => 'draft',
			)
		);
		Functions\create_and_sync_post( array( 'post_content' => 'findme test 2' ) );
		Functions\create_and_sync_post(
			array(
				'post_content' => 'findme test 3',
				'post_status'  => 'draft',
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's'           => 'findme',
			'post_status' => 'publish',
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, $query->found_posts );
	}

	/**
	 * Test a post status query for draft posts
	 *
	 * @since 2.1
	 * @group post
	 */
	public function testPostStatusQueryDraft() {
		add_filter( 'ep_indexable_post_status', array( $this, 'mock_indexable_post_status' ), 10, 1 );

		Functions\create_and_sync_post(
			array(
				'post_content' => 'findme test 1',
				'post_status'  => 'draft',
			)
		);
		Functions\create_and_sync_post( array( 'post_content' => 'findme test 2' ) );
		Functions\create_and_sync_post(
			array(
				'post_content' => 'findme test 3',
				'post_status'  => 'draft',
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's'           => 'findme',
			'post_status' => 'draft',
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );

		remove_filter( 'ep_indexable_post_status', array( $this, 'mock_indexable_post_status' ), 10 );
	}

	/**
	 * Test a post status query for published or draft posts with 'draft' whitelisted as indexable status
	 *
	 * @since 2.1
	 * @group post
	 */
	public function testPostStatusQueryMulti() {
		add_filter( 'ep_indexable_post_status', array( $this, 'mock_indexable_post_status' ), 10, 1 );

		Functions\create_and_sync_post(
			array(
				'post_content' => 'findme test 1',
				'post_status'  => 'draft',
			)
		);
		Functions\create_and_sync_post( array( 'post_content' => 'findme test 2' ) );
		Functions\create_and_sync_post(
			array(
				'post_content' => 'findme test 3',
				'post_status'  => 'draft',
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's'           => 'findme',
			'post_status' => array(
				'draft',
				'publish',
			),
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 3, $query->post_count );
		$this->assertEquals( 3, $query->found_posts );

		remove_filter( 'ep_indexable_post_status', array( $this, 'mock_indexable_post_status' ), 10 );
	}

	/**
	 * Add attachment post type for indexing
	 *
	 * @since 1.6
	 * @param array $post_types Post types
	 * @return array
	 */
	public function addAttachmentPostType( $post_types ) {
		$post_types[] = 'attachment';
		return $post_types;
	}

	/**
	 * Setup attachment post status for indexing
	 *
	 * @since 1.6
	 * @param array $post_statuses Post statuses
	 * @return array
	 */
	public function addAttachmentPostStatus( $post_statuses ) {
		$post_statuses[] = 'inherit';
		return $post_statuses;
	}

	/**
	 * Test an attachment query
	 *
	 * @since 1.6
	 * @group post
	 */
	public function testAttachmentQuery() {
		add_filter( 'ep_indexable_post_types', array( $this, 'addAttachmentPostType' ) );
		add_filter( 'ep_indexable_post_status', array( $this, 'addAttachmentPostStatus' ) );

		Functions\create_and_sync_post(
			array(
				'post_content' => 'findme test 1',
				'post_type'    => 'attachment',
			)
		);
		Functions\create_and_sync_post( array( 'post_content' => 'findme test 2' ) );
		Functions\create_and_sync_post( array( 'post_content' => 'findme test 3' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		// post_type defaults to "any"
		$args = array(
			'post_type'              => 'attachment',
			'post_status'            => 'any',
			'elasticpress_integrate' => true,
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, $query->found_posts );

		remove_filter( 'ep_indexable_post_types', array( $this, 'addAttachmentPostType' ) );
		remove_filter( 'ep_indexable_post_status', array( $this, 'addAttachmentPostStatus' ) );
	}

	/**
	 * Test a query with no post type on non-search query. Should default to `post` post type
	 *
	 * @since 1.3
	 * @group post
	 */
	public function testNoPostTypeNonSearchQuery() {
		Functions\create_and_sync_post(
			array(
				'post_content' => 'findme test 1',
				'post_type'    => 'page',
			)
		);
		Functions\create_and_sync_post( array( 'post_content' => 'findme test 2' ) );
		Functions\create_and_sync_post( array( 'post_content' => 'findme test 3' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		// post_type defaults to "any"
		$args = array(
			'ep_integrate' => true,
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );
	}

	/**
	 * Test a query with "any" post type
	 *
	 * @since 1.3
	 * @group post
	 */
	public function testAnyPostTypeQuery() {
		Functions\create_and_sync_post(
			array(
				'post_content' => 'findme test 1',
				'post_type'    => 'page',
			)
		);
		Functions\create_and_sync_post( array( 'post_content' => 'findme test 2' ) );
		Functions\create_and_sync_post(
			array(
				'post_content' => 'findme test 3',
				'post_type'    => 'page',
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's'         => 'findme',
			'post_type' => 'any',
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 3, $query->post_count );
		$this->assertEquals( 3, $query->found_posts );
	}

	/**
	 * Test meta shows up in EP post object
	 *
	 * @since 1.7
	 * @group post
	 */
	public function testSearchMetaInPostObject() {
		$object       = new \stdClass();
		$object->test = 'hello';

		Functions\create_and_sync_post( array( 'post_content' => 'post content' ), array( 'test_key' => $object ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();
		$args = array(
			'ep_integrate' => true,
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );

		$this->assertEquals( 1, count( $query->posts[0]->meta['test_key'] ) );
	}

	/**
	 * Test a query that fuzzy searches meta
	 *
	 * @since 1.0
	 * @group post
	 */
	public function testSearchMetaQuery() {
		Functions\create_and_sync_post( array( 'post_content' => 'the post content' ) );
		Functions\create_and_sync_post( array( 'post_content' => 'the post content findme' ) );
		Functions\create_and_sync_post( array( 'post_content' => 'post content' ), array( 'test_key' => 'findme' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();
		$args = array(
			's'             => 'findme',
			'search_fields' => array(
				'post_title',
				'post_excerpt',
				'post_content',
				'meta' => 'test_key',
			),
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );

		// Only check for fields which are provided in search_fields
		// If search_fields is set, it will override/ignore any weighting settings in the UI
		$args = array(
			's'             => 'findme',
			'search_fields' => array(
				'meta' => 'test_key',
			),
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, $query->found_posts );
	}

	/**
	 * Test a query that fuzzy searches taxonomy terms
	 *
	 * @since 1.0
	 * @group post
	 */
	public function testSearchTaxQuery() {
		Functions\create_and_sync_post( array( 'post_content' => 'the post content' ) );
		Functions\create_and_sync_post( array( 'post_content' => 'the post content findme' ) );
		Functions\create_and_sync_post(
			array(
				'post_content' => 'post content',
				'tags_input'   => array( 'findme 2' ),
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();
		$args = array(
			's'             => 'one findme two',
			'search_fields' => array(
				'post_title',
				'post_excerpt',
				'post_content',
				'taxonomies' => array( 'post_tag' ),
			),
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );
	}

	/**
	 * Test a fuzzy author name query
	 *
	 * @since 1.0
	 * @group post
	 */
	public function testSearchAuthorQuery() {
		$user_id = $this->factory->user->create(
			array(
				'user_login' => 'john',
				'role'       => 'administrator',
			)
		);

		Functions\create_and_sync_post( array( 'post_content' => 'findme test 1' ) );
		Functions\create_and_sync_post( array( 'post_content' => 'findme test 2' ) );
		Functions\create_and_sync_post(
			array(
				'post_content' => 'findme test 3',
				'post_author'  => $user_id,
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's'             => 'john boy',
			'search_fields' => array(
				'post_title',
				'post_excerpt',
				'post_content',
				'author_name',
			),
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, $query->found_posts );
	}

	/**
	 * Test a crazy advanced query
	 *
	 * @since 1.0
	 * @group post
	 */
	public function testAdvancedQuery() {
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );

		Functions\create_and_sync_post( array( 'post_content' => '' ) );
		Functions\create_and_sync_post(
			array(
				'post_content' => 'findme',
				'post_type'    => 'ep_test',
			)
		);
		Functions\create_and_sync_post(
			array(
				'post_content' => 'findme',
				'post_type'    => 'ep_test',
				'tags_input'   => array( 'superterm' ),
			)
		);
		Functions\create_and_sync_post(
			array(
				'post_content' => 'findme',
				'post_type'    => 'ep_test',
				'tags_input'   => array( 'superterm' ),
				'post_author'  => $user_id,
			)
		);
		Functions\create_and_sync_post(
			array(
				'post_content' => 'findme',
				'post_type'    => 'ep_test',
				'tags_input'   => array( 'superterm' ),
				'post_author'  => $user_id,
			),
			array( 'test_key' => 'meta value' )
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's'             => 'meta value',
			'post_type'     => 'ep_test',
			'tax_query'     => array(
				array(
					'taxonomy' => 'post_tag',
					'terms'    => array( 'superterm' ),
					'field'    => 'slug',
				),
			),
			'search_fields' => array(
				'post_title',
				'post_excerpt',
				'post_content',
				'meta' => 'test_key',
			),
			'author'        => $user_id,
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, $query->found_posts );
	}

	/**
	 * Test post_title orderby query
	 *
	 * @since 1.1
	 * @group post
	 */
	public function testSearchPostTitleOrderbyQuery() {
		Functions\create_and_sync_post( array( 'post_title' => 'ordertest 333' ) );
		Functions\create_and_sync_post( array( 'post_title' => 'ordertest 111' ) );
		Functions\create_and_sync_post( array( 'post_title' => 'Ordertest 222' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's'       => 'ordertest',
			'orderby' => 'title',
			'order'   => 'DESC',
		);

		$query = new \WP_Query( $args );

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
	 * @group post
	 */
	public function testSearchPostMetaStringOrderbyQueryAsc() {
		Functions\create_and_sync_post( array( 'post_title' => 'ordertest 333' ), array( 'test_key' => 'c' ) );
		Functions\create_and_sync_post( array( 'post_title' => 'Ordertest 222' ), array( 'test_key' => 'B' ) );
		Functions\create_and_sync_post( array( 'post_title' => 'ordertest 111' ), array( 'test_key' => 'a' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's'       => 'ordertest',
			'orderby' => 'meta.test_key.value.sortable',
			'order'   => 'ASC',
		);

		$query = new \WP_Query( $args );

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
	 * @group post
	 */
	public function testSearchPostMetaStringOrderbyQueryAscArray() {
		Functions\create_and_sync_post( array( 'post_title' => 'ordertest 333' ), array( 'test_key' => 'c' ) );
		Functions\create_and_sync_post( array( 'post_title' => 'Ordertest 222' ), array( 'test_key' => 'B' ) );
		Functions\create_and_sync_post( array( 'post_title' => 'ordertest 111' ), array( 'test_key' => 'a' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's'       => 'ordertest',
			'orderby' => array( 'meta.test_key.value.sortable' ),
			'order'   => 'ASC',
		);

		$query = new \WP_Query( $args );

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
	 * @group post
	 */
	public function testSearchPostMetaStringOrderbyQueryAdvanced() {
		Functions\create_and_sync_post(
			array( 'post_title' => 'ordertest 333' ),
			array(
				'test_key'  => 'c',
				'test_key2' => 'c',
			)
		);
		Functions\create_and_sync_post(
			array( 'post_title' => 'ordertest 222' ),
			array(
				'test_key'  => 'f',
				'test_key2' => 'c',
			)
		);
		Functions\create_and_sync_post(
			array( 'post_title' => 'ordertest 111' ),
			array(
				'test_key'  => 'd',
				'test_key2' => 'd',
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's'       => 'ordertest',
			'orderby' => array( 'meta.test_key.value.sortable' => 'asc' ),
		);

		$query = new \WP_Query( $args );

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
	 * @group post
	 */
	public function testAuthorLoginOrderbyQueryAsc() {
		$bob = $this->factory->user->create(
			array(
				'user_login' => 'Bob',
				'role'       => 'administrator',
			)
		);
		$al  = $this->factory->user->create(
			array(
				'user_login' => 'al',
				'role'       => 'administrator',
			)
		);
		$jim = $this->factory->user->create(
			array(
				'user_login' => 'Jim',
				'role'       => 'administrator',
			)
		);

		Functions\create_and_sync_post(
			array(
				'post_title'  => 'findme test 1',
				'post_author' => $al,
			)
		);
		Functions\create_and_sync_post(
			array(
				'post_title'  => 'findme test 2',
				'post_author' => $bob,
			)
		);
		Functions\create_and_sync_post(
			array(
				'post_title'  => 'findme test 3',
				'post_author' => $jim,
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's'       => 'findme',
			'orderby' => 'post_author.login.sortable',
			'order'   => 'asc',
		);

		$query = new \WP_Query( $args );

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
	 * @group post
	 */
	public function testAuthorDisplayNameOrderbyQueryAsc() {
		$bob = $this->factory->user->create(
			array(
				'display_name' => 'Bob',
				'role'         => 'administrator',
			)
		);
		$al  = $this->factory->user->create(
			array(
				'display_name' => 'al',
				'role'         => 'administrator',
			)
		);
		$jim = $this->factory->user->create(
			array(
				'display_name' => 'Jim',
				'role'         => 'administrator',
			)
		);

		Functions\create_and_sync_post(
			array(
				'post_title'  => 'findme test 1',
				'post_author' => $al,
			)
		);
		Functions\create_and_sync_post(
			array(
				'post_title'  => 'findme test 2',
				'post_author' => $bob,
			)
		);
		Functions\create_and_sync_post(
			array(
				'post_title'  => 'findme test 3',
				'post_author' => $jim,
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's'       => 'findme',
			'orderby' => 'post_author.display_name.sortable',
			'order'   => 'desc',
		);

		$query = new \WP_Query( $args );

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
	 * @group post
	 */
	public function testSearchPostMetaNumOrderbyQueryAsc() {
		Functions\create_and_sync_post( array( 'post_title' => 'ordertest 333' ), array( 'test_key' => 3 ) );
		Functions\create_and_sync_post( array( 'post_title' => 'ordertest 444' ), array( 'test_key' => 4 ) );
		Functions\create_and_sync_post( array( 'post_title' => 'Ordertest 222' ), array( 'test_key' => 2 ) );
		Functions\create_and_sync_post( array( 'post_title' => 'ordertest 111' ), array( 'test_key' => 1 ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's'       => 'ordertest',
			'orderby' => 'meta.test_key.long',
			'order'   => 'ASC',
		);

		$query = new \WP_Query( $args );

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
	 * @group post
	 */
	public function testSearchTaxNameOrderbyQueryAsc() {
		$cat1 = wp_create_category( 'Category 1' );
		$cat2 = wp_create_category( 'Another category two' );
		$cat3 = wp_create_category( 'basic category' );
		$cat4 = wp_create_category( 'Category 0' );

		Functions\create_and_sync_post(
			array(
				'post_title'    => 'ordertest 333',
				'post_category' => array( $cat4 ),
			)
		);
		Functions\create_and_sync_post(
			array(
				'post_title'    => 'ordertest 444',
				'post_category' => array( $cat1 ),
			)
		);
		Functions\create_and_sync_post(
			array(
				'post_title'    => 'Ordertest 222',
				'post_category' => array( $cat3 ),
			)
		);
		Functions\create_and_sync_post(
			array(
				'post_title'    => 'ordertest 111',
				'post_category' => array( $cat2 ),
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's'       => 'ordertest',
			'orderby' => 'terms.category.name.sortable',
			'order'   => 'ASC',
		);

		$query = new \WP_Query( $args );

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
	 * @group post
	 */
	public function testSearchPostMetaNumOrderbyQueryDesc() {
		Functions\create_and_sync_post( array( 'post_title' => 'ordertest 333' ), array( 'test_key' => 3 ) );
		Functions\create_and_sync_post( array( 'post_title' => 'ordertest 444' ), array( 'test_key' => 4 ) );
		Functions\create_and_sync_post( array( 'post_title' => 'Ordertest 222' ), array( 'test_key' => 2 ) );
		Functions\create_and_sync_post( array( 'post_title' => 'ordertest 111' ), array( 'test_key' => 1 ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's'       => 'ordertest',
			'orderby' => 'meta.test_key.long',
			'order'   => 'DESC',
		);

		$query = new \WP_Query( $args );

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
	 * @group post
	 */
	public function testSearchPostMetaNumMultipleOrderbyQuery() {
		Functions\create_and_sync_post(
			array( 'post_title' => 'ordertest 444' ),
			array(
				'test_key'  => 3,
				'test_key2' => 2,
			)
		);
		Functions\create_and_sync_post(
			array( 'post_title' => 'ordertest 333' ),
			array(
				'test_key'  => 3,
				'test_key2' => 1,
			)
		);
		Functions\create_and_sync_post(
			array( 'post_title' => 'Ordertest 222' ),
			array(
				'test_key'  => 2,
				'test_key2' => 1,
			)
		);
		Functions\create_and_sync_post(
			array( 'post_title' => 'ordertest 111' ),
			array(
				'test_key'  => 1,
				'test_key2' => 1,
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's'       => 'ordertest',
			'orderby' => 'meta.test_key.long meta.test_key2.long',
			'order'   => 'ASC',
		);

		$query = new \WP_Query( $args );

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
	 * @group post
	 */
	public function testSearchPostDateOrderbyQuery() {
		Functions\create_and_sync_post( array( 'post_title' => 'ordertesr' ) );
		sleep( 3 );

		Functions\create_and_sync_post( array( 'post_title' => 'ordertest 111' ) );
		sleep( 3 );

		Functions\create_and_sync_post( array( 'post_title' => 'Ordertest 222' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's'       => 'ordertest',
			'orderby' => 'date',
			'order'   => 'DESC',
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 3, $query->post_count );
		$this->assertEquals( 3, $query->found_posts );
		$this->assertEquals( 'Ordertest 222', $query->posts[0]->post_title );
		$this->assertEquals( 'ordertest 111', $query->posts[1]->post_title );
		$this->assertEquals( 'ordertesr', $query->posts[2]->post_title );
	}

	/**
	 * Test post_date default order for ep_integrate query with no search
	 *
	 * @since 1.7
	 * @group post
	 */
	public function testSearchPostDateOrderbyQueryEPIntegrate() {
		Functions\create_and_sync_post( array( 'post_title' => 'ordertest 333' ) );
		sleep( 3 );

		Functions\create_and_sync_post( array( 'post_title' => 'ordertest ordertest order test 111' ) );
		sleep( 3 );

		Functions\create_and_sync_post( array( 'post_title' => 'Ordertest 222' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			'ep_integrate' => true,
			'order'        => 'desc',
		);

		$query = new \WP_Query( $args );

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
	 * @group post
	 */
	public function testSearchRelevanceOrderbyQueryAdvanced() {
		$posts = array();

		$posts[5] = Functions\create_and_sync_post( array( 'post_title' => 'ordertet with even more lorem ipsum to make a longer field' ) );

		$posts[2] = Functions\create_and_sync_post( array( 'post_title' => 'ordertest ordertet lorem ipsum' ) );

		Functions\create_and_sync_post( array( 'post_title' => 'Lorem ipsum' ) );

		$posts[4] = Functions\create_and_sync_post( array( 'post_title' => 'ordertet with some lorem ipsum' ) );

		$posts[1] = Functions\create_and_sync_post( array( 'post_title' => 'ordertest ordertest lorem ipsum' ) );

		Functions\create_and_sync_post(
			array(
				'post_title'   => 'Lorem ipsum',
				'post_content' => 'Some post content filler text.',
			)
		);

		$posts[3] = Functions\create_and_sync_post( array( 'post_title' => 'ordertet ordertet lorem ipsum' ) );

		$posts[0] = Functions\create_and_sync_post( array( 'post_title' => 'Ordertest ordertest ordertest' ) );

		Functions\create_and_sync_post( array( 'post_title' => 'Lorem ipsum' ) );

		Functions\create_and_sync_post( array( 'post_title' => 'Lorem ipsum' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's'       => 'ordertest',
			'orderby' => 'relevance',
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 6, $query->post_count );
		$this->assertEquals( 6, $query->found_posts );

		$i = 0;
		foreach ( $query->posts as $post ) {
			$this->assertEquals( $posts[ $i ], $post->ID );

			$i++;
		}
	}

	/**
	 * Test relevance orderby query
	 *
	 * @since 1.1
	 * @group post
	 */
	public function testSearchRelevanceOrderbyQuery() {
		Functions\create_and_sync_post();
		Functions\create_and_sync_post( array( 'post_title' => 'ordertet' ) );
		Functions\create_and_sync_post( array( 'post_title' => 'ordertest' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's'       => 'ordertest',
			'orderby' => 'relevance',
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );
		$this->assertEquals( 'ordertest', $query->posts[0]->post_title );
		$this->assertEquals( 'ordertet', $query->posts[1]->post_title );
	}

	/**
	 * Test post_name orderby query
	 *
	 * @since 1.1
	 * @group post
	 */
	public function testSearchPostNameOrderbyQuery() {
		Functions\create_and_sync_post( array( 'post_title' => 'postname-ordertest-333' ) );
		Functions\create_and_sync_post( array( 'post_title' => 'postname-ordertest-111' ) );
		Functions\create_and_sync_post( array( 'post_title' => 'postname-Ordertest-222' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's'       => 'postname ordertest',
			'orderby' => 'name',
			'order'   => 'ASC',
		);

		$query = new \WP_Query( $args );

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
	 * @group post
	 */
	public function testSearchDefaultOrderbyQuery() {
		Functions\create_and_sync_post();
		Functions\create_and_sync_post( array( 'post_title' => 'Ordertet' ) );
		Functions\create_and_sync_post( array( 'post_title' => 'ordertest' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's' => 'ordertest',
		);

		$query = new \WP_Query( $args );

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
	 * @group post
	 */
	public function testSearchDefaultOrderbyASCOrderQuery() {
		Functions\create_and_sync_post();
		Functions\create_and_sync_post( array( 'post_title' => 'Ordertest' ) );
		Functions\create_and_sync_post( array( 'post_title' => 'ordertestt' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's'     => 'ordertest',
			'order' => 'ASC',
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );
		$this->assertEquals( 'ordertestt', $query->posts[0]->post_title );
		$this->assertEquals( 'Ordertest', $query->posts[1]->post_title );
	}

	/**
	 * Test orderby random
	 *
	 * @since 2.1.1
	 * @group post
	 */
	public function testRandOrderby() {
		Functions\create_and_sync_post( array( 'post_title' => 'ordertest 1' ) );
		Functions\create_and_sync_post( array( 'post_title' => 'ordertest 2' ) );
		Functions\create_and_sync_post( array( 'post_title' => 'ordertest 3' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			'ep_integrate' => true,
			'orderby'      => 'rand',
		);

		$query = new \WP_Query( $args );

		/**
		 * Since it's test for random order, can't check against exact post ID or content
		 * but only found posts and post count.
		 */
		$this->assertEquals( 3, $query->post_count );
		$this->assertEquals( 3, $query->found_posts );
	}

	/**
	 * Test that a post being directly deleted gets correctly removed from the Elasticsearch index
	 *
	 * @since 1.2
	 * @group post
	 */
	public function testPostForceDelete() {
		add_action( 'ep_delete_post', array( $this, 'action_delete_post' ), 10, 0 );
		$post_id = Functions\create_and_sync_post();

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$post = ElasticPress\Indexables::factory()->get( 'post' )->get( $post_id );

		// Ensure that our post made it over to elasticsearch
		$this->assertTrue( ! empty( $post ) );

		// Let's directly delete the post, bypassing the trash
		wp_delete_post( $post_id, true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$this->assertTrue( ! empty( $this->fired_actions['ep_delete_post'] ) );

		$post = ElasticPress\Indexables::factory()->get( 'post' )->get( $post_id );

		// Alright, now the post has been removed from the index, so this should be empty
		$this->assertTrue( empty( $post ) );

		$post = get_post( $post_id );

		// This post should no longer exist in WP's database
		$this->assertTrue( empty( $post ) );

		$this->fired_actions = array();
	}

	/**
	 * Test that a post being trashed gets correctly removed from the Elasticsearch index
	 *
	 * @since 3.5
	 * @group post
	 */
	public function testPostTrashed() {
		add_action( 'ep_delete_post', array( $this, 'action_delete_post' ), 10, 0 );

		$post_id = Functions\create_and_sync_post();

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$post = ElasticPress\Indexables::factory()->get( 'post' )->get( $post_id );

		// Ensure that our post made it over to elasticsearch
		$this->assertTrue( ! empty( $post ) );

		// Let's trash the post
		wp_trash_post( $post_id );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$this->assertTrue( ! empty( $this->fired_actions['ep_delete_post'] ) );

		$post = ElasticPress\Indexables::factory()->get( 'post' )->get( $post_id );

		// Alright, now the post has been removed from the index, so this should be empty
		$this->assertTrue( empty( $post ), '$post from ES is not empty' );

		$post = get_post( $post_id );

		// This post should have the right status
		$this->assertEquals( 'trash', $post->post_status, 'Post status from db is not trashed' );

		// And ensure the post is not queued for re-indexing (such as from core meta changes)
		$queue = ElasticPress\Indexables::factory()->get( 'post' )->sync_manager->sync_queue;

		$this->assertArrayNotHasKey( $post_id, $queue, 'Post is still queue in SyncManager' );

		$this->fired_actions = array();
	}

	/**
	 * Test that empty search string returns all results
	 *
	 * @since 1.2
	 * @group post
	 */
	public function testEmptySearchString() {
		Functions\create_and_sync_post();
		Functions\create_and_sync_post();

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's' => '',
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );
	}

	/**
	 * Test a query that searches and filters by a meta equal query
	 *
	 * @since 1.3
	 * @group post
	 */
	public function testMetaQueryEquals() {
		Functions\create_and_sync_post( array( 'post_content' => 'the post content' ) );
		Functions\create_and_sync_post( array( 'post_content' => 'the post content findme' ) );
		Functions\create_and_sync_post( array( 'post_content' => 'post content findme' ), array( 'test_key' => 'value' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();
		$args = array(
			's'          => 'findme',
			'meta_query' => array(
				array(
					'key'   => 'test_key',
					'value' => 'value',
				),
			),
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, $query->found_posts );
	}

	/**
	 * Test a query that searches and filters by a meta not equal query
	 *
	 * @since 1.3
	 * @group post
	 */
	public function testMetaQueryNotEquals() {
		Functions\create_and_sync_post( array( 'post_content' => 'the post content findme' ) );
		Functions\create_and_sync_post( array( 'post_content' => 'the post content findme' ) );
		Functions\create_and_sync_post( array( 'post_content' => 'post content findme' ), array( 'test_key' => 'value' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();
		$args = array(
			's'          => 'findme',
			'meta_query' => array(
				array(
					'key'     => 'test_key',
					'value'   => 'value',
					'compare' => '!=',
				),
			),
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );
	}

	/**
	 * Test a query that searches and filters by a meta exists query
	 *
	 * @since 1.3
	 * @group post
	 */
	public function testMetaQueryExists() {
		Functions\create_and_sync_post( array( 'post_content' => 'the post content findme' ) );
		Functions\create_and_sync_post( array( 'post_content' => 'the post content findme' ) );
		Functions\create_and_sync_post( array( 'post_content' => 'post content findme' ), array( 'test_key' => 'value' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();
		$args = array(
			's'          => 'findme',
			'meta_query' => array(
				array(
					'key'     => 'test_key',
					'compare' => 'exists',
				),
			),
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, $query->found_posts );
	}

	/**
	 * Test a query that searches and filters by a meta not exists query
	 *
	 * @group post
	 * @since 1.3
	 */
	public function testMetaQueryNotExists() {
		Functions\create_and_sync_post( array( 'post_content' => 'the post content findme' ) );
		Functions\create_and_sync_post( array( 'post_content' => 'the post content findme' ) );
		Functions\create_and_sync_post( array( 'post_content' => 'post content findme' ), array( 'test_key' => 'value' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();
		$args = array(
			's'          => 'findme',
			'meta_query' => array(
				array(
					'key'     => 'test_key',
					'compare' => 'not exists',
				),
			),
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );
	}

	/**
	 * Test a query that searches and filters by a meta greater than to query
	 *
	 * @since 1.4
	 * @group post
	 */
	public function testMetaQueryGreaterThan() {
		Functions\create_and_sync_post( array( 'post_content' => 'the post content findme' ) );
		Functions\create_and_sync_post( array( 'post_content' => 'the post content findme' ) );
		Functions\create_and_sync_post( array( 'post_content' => 'post content findme' ), array( 'test_key' => '100' ) );
		Functions\create_and_sync_post( array( 'post_content' => 'post content findme' ), array( 'test_key' => '101' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();
		$args = array(
			's'          => 'findme',
			'meta_query' => array(
				array(
					'key'     => 'test_key',
					'value'   => '100',
					'compare' => '>',
				),
			),
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, $query->found_posts );
	}

	/**
	 * Test a query that searches and filters by a meta between query
	 *
	 * @since 2.0
	 * @group post
	 */
	public function testMetaQueryBetween() {
		Functions\create_and_sync_post( array( 'post_content' => 'the post content findme' ) );
		Functions\create_and_sync_post( array( 'post_content' => 'the post content findme' ) );
		Functions\create_and_sync_post( array( 'post_content' => 'post content findme' ), array( 'test_key' => '100' ) );
		Functions\create_and_sync_post( array( 'post_content' => 'post content findme' ), array( 'test_key' => '105' ) );
		Functions\create_and_sync_post( array( 'post_content' => 'post content findme' ), array( 'test_key' => '110' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();
		$args = array(
			's'          => 'findme',
			'meta_query' => array(
				array(
					'key'     => 'test_key',
					'value'   => array( 102, 106 ),
					'compare' => 'BETWEEN',
				),
			),
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, $query->found_posts );
	}

	/**
	 * Test a query that searches and filters by a meta greater than or equal to query
	 *
	 * @since 1.4
	 * @group post
	 */
	public function testMetaQueryGreaterThanEqual() {
		Functions\create_and_sync_post( array( 'post_content' => 'the post content findme' ) );
		Functions\create_and_sync_post( array( 'post_content' => 'the post content findme' ) );
		Functions\create_and_sync_post( array( 'post_content' => 'post content findme' ), array( 'test_key' => '100' ) );
		Functions\create_and_sync_post( array( 'post_content' => 'post content findme' ), array( 'test_key' => '101' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();
		$args = array(
			's'          => 'findme',
			'meta_query' => array(
				array(
					'key'     => 'test_key',
					'value'   => '100',
					'compare' => '>=',
				),
			),
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );
	}

	/**
	 * Test a query that searches and filters by a meta less than to query
	 *
	 * @since 1.4
	 * @group post
	 */
	public function testMetaQueryLessThan() {
		Functions\create_and_sync_post( array( 'post_content' => 'the post content findme' ) );
		Functions\create_and_sync_post( array( 'post_content' => 'the post content findme' ) );
		Functions\create_and_sync_post( array( 'post_content' => 'post content findme' ), array( 'test_key' => '100' ) );
		Functions\create_and_sync_post( array( 'post_content' => 'post content findme' ), array( 'test_key' => '101' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();
		$args = array(
			's'          => 'findme',
			'meta_query' => array(
				array(
					'key'     => 'test_key',
					'value'   => '101',
					'compare' => '<',
				),
			),
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, $query->found_posts );
	}

	/**
	 * Test a query that searches and filters by a meta less than or equal to query
	 *
	 * @since 1.4
	 * @group post
	 */
	public function testMetaQueryLessThanEqual() {
		Functions\create_and_sync_post( array( 'post_content' => 'the post content findme' ) );
		Functions\create_and_sync_post( array( 'post_content' => 'the post content findme' ) );
		Functions\create_and_sync_post( array( 'post_content' => 'post content findme' ), array( 'test_key' => '100' ) );
		Functions\create_and_sync_post( array( 'post_content' => 'post content findme' ), array( 'test_key' => '101' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();
		$args = array(
			's'          => 'findme',
			'meta_query' => array(
				array(
					'key'     => 'test_key',
					'value'   => '101',
					'compare' => '<=',
				),
			),
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );
	}

	/**
	 * Test an advanced meta filter query
	 *
	 * @since 1.3
	 * @group post
	 */
	public function testMetaQueryOrRelation() {
		Functions\create_and_sync_post( array( 'post_content' => 'the post content findme' ), array( 'test_key5' => 'value1' ) );
		Functions\create_and_sync_post(
			array( 'post_content' => 'the post content findme' ),
			array(
				'test_key'  => 'value1',
				'test_key2' => 'value',
			)
		);
		Functions\create_and_sync_post(
			array( 'post_content' => 'post content findme' ),
			array(
				'test_key6' => 'value',
				'test_key2' => 'value2',
				'test_key3' => 'value',
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();
		$args = array(
			's'          => 'findme',
			'meta_query' => array(
				array(
					'key'     => 'test_key5',
					'compare' => 'exists',
				),
				array(
					'key'     => 'test_key6',
					'value'   => 'value',
					'compare' => '=',
				),
				'relation' => 'or',
			),
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );
	}

	/**
	 * Test an advanced meta filter query
	 *
	 * @since 1.3
	 * @group post
	 */
	public function testMetaQueryAdvanced() {
		Functions\create_and_sync_post( array( 'post_content' => 'the post content findme' ), array( 'test_key' => 'value1' ) );
		Functions\create_and_sync_post(
			array( 'post_content' => 'the post content findme' ),
			array(
				'test_key'  => 'value1',
				'test_key2' => 'value',
			)
		);
		Functions\create_and_sync_post(
			array( 'post_content' => 'post content findme' ),
			array(
				'test_key'  => 'value',
				'test_key2' => 'value2',
				'test_key3' => 'value',
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();
		$args = array(
			's'          => 'findme',
			'meta_query' => array(
				array(
					'key'     => 'test_key3',
					'compare' => 'exists',
				),
				array(
					'key'     => 'test_key2',
					'value'   => 'value2',
					'compare' => '=',
				),
				array(
					'key'     => 'test_key',
					'value'   => 'value1',
					'compare' => '!=',
				),
			),
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, $query->found_posts );
	}

	/**
	 * Test a query that searches and filters by a meta value like the query
	 *
	 * @since 1.5
	 * @group post
	 */
	public function testMetaQueryLike() {
		Functions\create_and_sync_post( array( 'post_content' => 'the post content findme' ) );
		Functions\create_and_sync_post( array( 'post_content' => 'the post content findme' ) );
		Functions\create_and_sync_post( array( 'post_content' => 'post content findme' ), array( 'test_key' => 'ALICE in wonderland' ) );
		Functions\create_and_sync_post( array( 'post_content' => 'post content findme' ), array( 'test_key' => 'alice in melbourne' ) );
		Functions\create_and_sync_post( array( 'post_content' => 'post content findme' ), array( 'test_key' => 'AlicE in america' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();
		$args = array(
			's'          => 'findme',
			'meta_query' => array(
				array(
					'key'     => 'test_key',
					'value'   => 'alice',
					'compare' => 'LIKE',
				),
			),
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( isset( $query->posts[0]->elasticsearch ) );
		$this->assertEquals( 3, $query->post_count );
		$this->assertEquals( 3, $query->found_posts );
	}

	/**
	 * Test meta queries with multiple keys
	 */
	public function testMetaQueryMultipleArray() {
		Functions\create_and_sync_post( array( 'post_content' => 'findme' ), array( 'meta_key_1' => '1' ) );
		Functions\create_and_sync_post( array( 'post_content' => 'findme' ), array( 'meta_key_1' => '1' ) );
		Functions\create_and_sync_post(
			array( 'post_content' => 'findme' ),
			array(
				'meta_key_1' => '1',
				'meta_key_2' => '4',
			)
		);
		Functions\create_and_sync_post(
			array( 'post_content' => 'findme' ),
			array(
				'meta_key_1' => '1',
				'meta_key_2' => '0',
			)
		);
		Functions\create_and_sync_post(
			array( 'post_content' => 'findme' ),
			array(
				'meta_key_1' => '1',
				'meta_key_3' => '4',
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's'          => 'findme',
			'meta_query' => array(
				array(
					'key'     => 'meta_key_2',
					'value'   => '0',
					'compare' => '>=',
				),
			),
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );

		$args = array(
			's'          => 'findme',
			'meta_query' => array(
				'relation' => 'AND',
				array(
					'key'   => 'meta_key_1',
					'value' => '1',
				),
				array(
					'relation' => 'OR',
					array(
						'key'     => 'meta_key_2',
						'value'   => '2',
						'compare' => '>=',
					),
					array(
						'key'   => 'meta_key_3',
						'value' => '4',
					),
				),
			),
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );
	}

	/**
	 * Test exclude_from_search post type flag
	 * Ensure that we do not search that post type when all post types are searched
	 *
	 * @since 1.3
	 * @group post
	 */
	public function testExcludeFromSearch() {
		$post_ids = array();

		$post_ids[0] = Functions\create_and_sync_post();
		$post_ids[1] = Functions\create_and_sync_post();
		$post_ids[2] = Functions\create_and_sync_post( array( 'post_content' => 'findme' ) );
		$post_ids[3] = Functions\create_and_sync_post();
		$post_ids[4] = Functions\create_and_sync_post( array( 'post_content' => 'findme' ) );

		register_post_type(
			'exclude-me',
			array(
				'public'              => true,
				'exclude_from_search' => true,
			)
		);

		$post_ids[5] = Functions\create_and_sync_post(
			array(
				'post_type'    => 'exclude-me',
				'post_content' => 'findme',
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's' => 'findme',
		);

		add_action( 'ep_wp_query_search', array( $this, 'action_wp_query_search' ), 10, 0 );

		$query = new \WP_Query( $args );

		$this->assertTrue( ! empty( $this->fired_actions['ep_wp_query_search'] ) );

		$this->assertEquals( $query->post_count, 2 );
		$this->assertEquals( $query->found_posts, 2 );

		wp_reset_postdata();
	}

	/**
	 * Test what happens when no post types are available to be searched
	 *
	 * @since 1.3
	 * @group post
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

		$post_ids[0] = Functions\create_and_sync_post();
		$post_ids[1] = Functions\create_and_sync_post();
		$post_ids[2] = Functions\create_and_sync_post( array( 'post_content' => 'findme' ) );
		$post_ids[3] = Functions\create_and_sync_post();
		$post_ids[4] = Functions\create_and_sync_post( array( 'post_content' => 'findme' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's' => 'findme',
		);

		$query = new \WP_Query( $args );

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
	 * @group post
	 */
	public function testCacheResultsDefaultOff() {
		Functions\create_and_sync_post();

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			'ep_integrate' => true,
		);

		$query = new \WP_Query( $args );

		$this->assertFalse( $query->query_vars['cache_results'] );
	}

	/**
	 * Test cache_results can be turned on
	 *
	 * @since 1.5
	 * @group post
	 */
	public function testCacheResultsOn() {
		Functions\create_and_sync_post();

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			'ep_integrate'  => true,
			'cache_results' => true,
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->query_vars['cache_results'] );
	}

	/**
	 * Test using cache_results actually populates the cache
	 *
	 * @since 1.5
	 * @group post
	 */
	public function testCachedResultIsInCache() {
		Functions\create_and_sync_post();

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		wp_cache_flush();

		$args = array(
			'ep_integrate'  => true,
			'cache_results' => true,
		);

		$query = new \WP_Query( $args );

		$cache = wp_cache_get( $query->posts[0]->ID, 'posts' );

		$this->assertTrue( ! empty( $cache ) );
	}

	/**
	 * Test setting cache results to false doesn't store anything in the cache
	 *
	 * @since 1.5
	 * @group post
	 */
	public function testCachedResultIsNotInCache() {
		Functions\create_and_sync_post();

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		wp_cache_flush();

		$args = array(
			'ep_integrate' => true,
		);

		$query = new \WP_Query( $args );

		$cache = wp_cache_get( $query->posts[0]->ID, 'posts' );

		$this->assertTrue( empty( $cache ) );
	}


	/**
	 * Helper method for mocking indexable post statuses
	 *
	 * @param   array $post_statuses Post statuses
	 * @return  array
	 */
	public function mock_indexable_post_status( $post_statuses ) {
		$post_statuses[] = 'draft';
		return $post_statuses;
	}

	/**
	 * Test invalid post date time
	 *
	 * @group post
	 */
	public function testPostInvalidDateTime() {
		add_filter( 'ep_indexable_post_status', array( $this, 'mock_indexable_post_status' ), 10, 1 );
		$post_id = Functions\create_and_sync_post( array( 'post_status' => 'draft' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		ElasticPress\Indexables::factory()->get( 'post' )->index( $post_id, true );

		wp_cache_flush();

		$wp_post = get_post( $post_id );
		$post    = ElasticPress\Indexables::factory()->get( 'post' )->get( $post_id );

		$invalid_datetime = '0000-00-00 00:00:00';
		if ( $wp_post->post_date_gmt === $invalid_datetime ) {
			$this->assertNull( $post['post_date_gmt'] );
		}

		if ( $wp_post->post_modified_gmt === $invalid_datetime ) {
			$this->assertNull( $post['post_modified_gmt'] );
		}
		$this->assertNotNull( $post );
		remove_filter( 'ep_indexable_post_status', array( $this, 'mock_indexable_post_status' ), 10 );
	}

	/**
	 * Test to verify that a post type that is set to exclude_from_search isn't indexable.
	 *
	 * @since 1.6
	 * @group post
	 */
	public function testExcludeIndexablePostType() {
		$post_types = ElasticPress\Indexables::factory()->get( 'post' )->get_indexable_post_types();
		$this->assertArrayNotHasKey( 'ep_test_excluded', $post_types );
		$this->assertArrayNotHasKey( 'ep_test_not_public', $post_types );
	}

	/**
	 * Test to make sure that brand new posts with 'auto-draft' post status do not fire delete or sync.
	 *
	 * @since 1.6
	 * @link https://github.com/10up/ElasticPress/issues/343
	 * @group post
	 */
	public function testAutoDraftPostStatus() {
		// Let's test inserting an 'auto-draft' post.
		add_action( 'http_api_debug', array( $this, 'check404' ), 10, 5 );
		$new_post = wp_insert_post(
			array(
				'post_title'  => 'Auto Draft',
				'post_status' => 'auto-draft',
			)
		);

		$this->assertFalse( $this->is_404, 'auto-draft post status on wp_insert_post action.' );

		// Now let's test inserting a 'publish' post.
		$this->is_404 = false;
		add_action( 'http_api_debug', array( $this, 'check404' ), 10, 5 );
		$new_post = wp_insert_post(
			array(
				'post_title'  => 'Published',
				'post_status' => 'publish',
			)
		);

		$this->assertFalse( $this->is_404, 'publish post status on wp_insert_post action.' );
	}

	/**
	 * Runs on http_api_debug action to check for a returned 404 status code.
	 *
	 * @param array|WP_Error $response  HTTP response or WP_Error object.
	 * @param string         $type Context under which the hook is fired.
	 * @param string         $class HTTP transport used.
	 * @param array          $args HTTP request arguments.
	 * @param string         $url The request URL.
	 */
	public function check404( $response, $type, $class, $args, $url ) {
		$response_code = $response['response']['code'];
		if ( 404 === $response_code ) {
			$this->is_404 = true;
		}

		remove_action( 'http_api_debug', array( $this, 'check404' ) );
	}

	/**
	 * Test to verify meta array is built correctly.
	 *
	 * @since 1.7
	 * @group post
	 */
	public function testPrepareMeta() {

		$post_id     = Functions\create_and_sync_post();
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

		$meta_1 = ElasticPress\Indexables::factory()->get( 'post' )->prepare_meta( $post );

		add_filter( 'ep_prepare_meta_allowed_protected_keys', array( $this, 'filter_ep_prepare_meta_allowed_protected_keys' ) );

		$meta_2 = ElasticPress\Indexables::factory()->get( 'post' )->prepare_meta( $post );

		add_filter( 'ep_prepare_meta_excluded_public_keys', array( $this, 'filter_ep_prepare_meta_excluded_public_keys' ) );

		$meta_3 = ElasticPress\Indexables::factory()->get( 'post' )->prepare_meta( $post );

		$this->assertTrue( is_array( $meta_1 ) && 1 === count( $meta_1 ) );
		$this->assertTrue( is_array( $meta_1 ) && array_key_exists( 'test_meta_1', $meta_1 ) );
		$this->assertTrue( is_array( $meta_2 ) && 2 === count( $meta_2 ) );
		$this->assertTrue( is_array( $meta_2 ) && array_key_exists( 'test_meta_1', $meta_2 ) && array_key_exists( '_test_private_meta_1', $meta_2 ) );
		$this->assertTrue( is_array( $meta_3 ) && 1 === count( $meta_3 ) );
		$this->assertTrue( is_array( $meta_3 ) && array_key_exists( '_test_private_meta_1', $meta_3 ) );

	}

	/**
	 * Helper method for filtering private meta keys
	 *
	 * @param  array $meta_keys Meta keys
	 * @return array
	 */
	public function filter_ep_prepare_meta_allowed_protected_keys( $meta_keys ) {

		$meta_keys[] = '_test_private_meta_1';

		return $meta_keys;

	}

	/**
	 * Helper method for filtering excluded meta keys
	 *
	 * @param  array $meta_keys Meta keys
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
	 * @group post
	 */
	public function testMetaValueTypes() {

		$intval         = ElasticPress\Indexables::factory()->get( 'post' )->prepare_meta_value_types( 13 );
		$floatval       = ElasticPress\Indexables::factory()->get( 'post' )->prepare_meta_value_types( 13.43 );
		$textval        = ElasticPress\Indexables::factory()->get( 'post' )->prepare_meta_value_types( 'some text' );
		$bool_false_val = ElasticPress\Indexables::factory()->get( 'post' )->prepare_meta_value_types( false );
		$bool_true_val  = ElasticPress\Indexables::factory()->get( 'post' )->prepare_meta_value_types( true );
		$dateval        = ElasticPress\Indexables::factory()->get( 'post' )->prepare_meta_value_types( '2015-01-01' );

		$this->assertTrue( is_array( $intval ) && 5 === count( $intval ) );
		$this->assertTrue( is_array( $intval ) && array_key_exists( 'long', $intval ) && 13 === $intval['long'] );
		$this->assertTrue( is_array( $floatval ) && 5 === count( $floatval ) );
		$this->assertTrue( is_array( $floatval ) && array_key_exists( 'double', $floatval ) && 13.43 === $floatval['double'] );
		$this->assertTrue( is_array( $textval ) && 6 === count( $textval ) );
		$this->assertTrue( is_array( $textval ) && array_key_exists( 'raw', $textval ) && 'some text' === $textval['raw'] );
		$this->assertTrue( is_array( $bool_false_val ) && 3 === count( $bool_false_val ) );
		$this->assertTrue( is_array( $bool_false_val ) && array_key_exists( 'boolean', $bool_false_val ) && false === $bool_false_val['boolean'] );
		$this->assertTrue( is_array( $bool_true_val ) && 3 === count( $bool_true_val ) );
		$this->assertTrue( is_array( $bool_true_val ) && array_key_exists( 'boolean', $bool_true_val ) && true === $bool_true_val['boolean'] );
		$this->assertTrue( is_array( $dateval ) && 6 === count( $dateval ) );
		$this->assertTrue( is_array( $dateval ) && array_key_exists( 'datetime', $dateval ) && '2015-01-01 00:00:00' === $dateval['datetime'] );

	}

	/**
	 * Test meta key query
	 *
	 * @since 2.1
	 * @group post
	 */
	public function testMetaKeyQuery() {

		Functions\create_and_sync_post( array( 'post_content' => 'the post content findme' ) );
		Functions\create_and_sync_post( array( 'post_content' => 'post content findme' ), array( 'test_key' => 'test' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's'          => 'findme',
			'meta_key'   => 'test_key',
			'meta_value' => 'test',
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, $query->found_posts );

	}

	/**
	 * Test meta key query with num
	 *
	 * @since 2.1
	 * @group post
	 */
	public function testMetaKeyQueryNum() {

		Functions\create_and_sync_post( array( 'post_content' => 'the post content findme' ) );
		Functions\create_and_sync_post( array( 'post_content' => 'post content findme' ), array( 'test_key' => 5 ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();
		$args = array(
			's'              => 'findme',
			'meta_key'       => 'test_key',
			'meta_value_num' => 5,
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, $query->found_posts );

	}

	/**
	 * Test mix meta_key with meta_query
	 *
	 * @since 2.1
	 * @group post
	 */
	public function testMetaKeyQueryMix() {

		Functions\create_and_sync_post( array( 'post_content' => 'the post content findme' ) );
		Functions\create_and_sync_post(
			array( 'post_content' => 'post content findme' ),
			array(
				'test_key'   => 5,
				'test_key_2' => 'aaa',
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();
		$args = array(
			's'              => 'findme',
			'meta_key'       => 'test_key',
			'meta_value_num' => 5,
			'meta_query'     => array(
				array(
					'key'   => 'test_key_2',
					'value' => 'aaa',
				),
			),
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, $query->found_posts );

	}

	/**
	 * Test numeric integer meta queries
	 *
	 * @since 1.7
	 * @group post
	 */
	public function testMetaValueTypeQueryNumeric() {

		Functions\create_and_sync_post( array( 'post_content' => 'the post content findme' ) );
		Functions\create_and_sync_post( array( 'post_content' => 'post content findme' ), array( 'test_key' => 100 ) );
		Functions\create_and_sync_post( array( 'post_content' => 'the post content findme' ) );
		Functions\create_and_sync_post( array( 'post_content' => 'post content findme' ), array( 'test_key' => 101 ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();
		$args = array(
			's'          => 'findme',
			'meta_query' => array(
				array(
					'key'     => 'test_key',
					'value'   => 101,
					'compare' => '>=',
					'type'    => 'numeric',
				),
			),
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, $query->found_posts );

		$args = array(
			's'          => 'findme',
			'meta_query' => array(
				array(
					'key'     => 'test_key',
					'value'   => 100,
					'compare' => '=',
					'type'    => 'numeric',
				),
			),
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, $query->found_posts );

		$args = array(
			's'          => 'findme',
			'meta_query' => array(
				array(
					'key'     => 'test_key',
					'value'   => 103,
					'compare' => '<=',
					'type'    => 'numeric',
				),
			),
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );

	}

	/**
	 * Test decimal meta queries
	 *
	 * @since 1.7
	 * @group post
	 */
	public function testMetaValueTypeQueryDecimal() {

		Functions\create_and_sync_post( array( 'post_content' => 'the post content findme' ) );
		Functions\create_and_sync_post( array( 'post_content' => 'post content findme' ), array( 'test_key' => 15.5 ) );
		Functions\create_and_sync_post( array( 'post_content' => 'the post content findme' ) );
		Functions\create_and_sync_post( array( 'post_content' => 'post content findme' ), array( 'test_key' => 16.5 ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();
		$args = array(
			's'          => 'findme',
			'meta_query' => array(
				array(
					'key'     => 'test_key',
					'value'   => 16.5,
					'compare' => '<',
					'type'    => 'decimal',
				),
			),
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, $query->found_posts );

		$args = array(
			's'          => 'findme',
			'meta_query' => array(
				array(
					'key'     => 'test_key',
					'value'   => 16.5,
					'compare' => '=',
					'type'    => 'decimal',
				),
			),
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, $query->found_posts );
	}

	/**
	 * Test character meta queries. Really just defaults to a normal string query
	 *
	 * @since 1.7
	 * @group post
	 */
	public function testMetaValueTypeQueryChar() {

		Functions\create_and_sync_post( array( 'post_content' => 'the post content findme' ) );
		Functions\create_and_sync_post( array( 'post_content' => 'post content findme' ), array( 'test_key' => 'abc' ) );
		Functions\create_and_sync_post( array( 'post_content' => 'the post content findme' ) );
		Functions\create_and_sync_post( array( 'post_content' => 'post content findme' ), array( 'test_key' => 'acc' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();
		$args = array(
			's'          => 'findme',
			'meta_query' => array(
				array(
					'key'     => 'test_key',
					'value'   => 'abc',
					'compare' => '=',
					'type'    => 'char',
				),
			),
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, $query->found_posts );
	}

	/**
	 * Test date meta queries
	 *
	 * @since 1.7
	 * @group post
	 */
	public function testMetaValueTypeQueryDate() {
		Functions\create_and_sync_post( array( 'post_content' => 'post content findme' ), array( 'test_key' => '11/13/15' ) );
		Functions\create_and_sync_post( array( 'post_content' => 'the post content findme' ) );
		Functions\create_and_sync_post( array( 'post_content' => 'post content findme' ), array( 'test_key' => '11/15/15' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();
		$args = array(
			's'          => 'findme',
			'meta_query' => array(
				array(
					'key'     => 'test_key',
					'value'   => '2015-11-14',
					'compare' => '>',
					'type'    => 'date',
				),
			),
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );

		$args = array(
			's'          => 'findme',
			'meta_query' => array(
				array(
					'key'     => 'test_key',
					'value'   => '2015-11-15',
					'compare' => '=',
					'type'    => 'date',
				),
			),
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );

	}

	/**
	 * Test time meta queries
	 *
	 * @since 1.7
	 * @group post
	 */
	public function testMetaValueTypeQueryTime() {
		Functions\create_and_sync_post( array( 'post_content' => 'the post content findme' ) );
		Functions\create_and_sync_post( array( 'post_content' => 'post content findme' ), array( 'test_key' => '5:00am' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();
		$args = array(
			's'          => 'findme',
			'meta_query' => array(
				array(
					'key'     => 'test_key',
					'value'   => '17:00:00',
					'compare' => '<',
					'type'    => 'time',
				),
			),
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );

		$args = array(
			's'          => 'findme',
			'meta_query' => array(
				array(
					'key'     => 'test_key',
					'value'   => '05:00:00',
					'compare' => '=',
					'type'    => 'time',
				),
			),
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );
	}

	/**
	 * Test date time meta queries
	 *
	 * @since 1.7
	 * @group post
	 */
	public function testMetaValueTypeQueryDatetime() {
		Functions\create_and_sync_post( array( 'post_content' => 'the post content findme' ) );
		Functions\create_and_sync_post( array( 'post_content' => 'post content findme' ), array( 'test_key' => '5:00am 1/2/12' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();
		$args = array(
			's'          => 'findme',
			'meta_query' => array(
				array(
					'key'     => 'test_key',
					'value'   => '2013-03-02 06:00:15',
					'compare' => '<',
					'type'    => 'datetime',
				),
			),
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );

		$args = array(
			's'          => 'findme',
			'meta_query' => array(
				array(
					'key'     => 'test_key',
					'value'   => '2012-01-02 05:00:00',
					'compare' => '=',
					'type'    => 'datetime',
				),
			),
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );

		$args = array(
			's'          => 'findme',
			'meta_query' => array(
				array(
					'key'     => 'test_key',
					'value'   => '2011-01-02 07:30:00',
					'compare' => '>',
					'type'    => 'datetime',
				),
			),
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );
	}

	/**
	 * Test a post_parent query
	 *
	 * @group post
	 * @since 2.0
	 */
	public function testPostParentQuery() {
		$parent_post = Functions\create_and_sync_post( array( 'post_content' => 'findme test 1' ) );
		Functions\create_and_sync_post(
			array(
				'post_content' => 'findme test 2',
				'post_parent'  => $parent_post,
			)
		);
		Functions\create_and_sync_post( array( 'post_content' => 'findme test 3' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's'           => 'findme',
			'post_parent' => $parent_post,
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, $query->found_posts );
	}

	/**
	 * Test Tax Query NOT IN operator
	 *
	 * @since 2.1
	 * @group post
	 */
	public function testTaxQueryNotIn() {
		Functions\create_and_sync_post(
			array(
				'post_content' => 'findme test 1',
				'tags_input'   => array(
					'one',
					'two',
				),
			)
		);
		Functions\create_and_sync_post(
			array(
				'post_content' => 'findme test 2',
				'tags_input'   => array( 'one' ),
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's'         => 'findme',
			'tax_query' => array(
				array(
					'taxonomy' => 'post_tag',
					'terms'    => array( 'one' ),
					'field'    => 'slug',
				),
			),
		);

		$query = new \WP_Query( $args );

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
				),
			),
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, $query->found_posts );
	}

	/**
	 * Test Tax Query EXISTS operator
	 *
	 * @since 2.5
	 * @group post
	 */
	public function testTaxQueryExists() {
		Functions\create_and_sync_post(
			array(
				'post_content' => 'findme test 1',
				'tags_input'   => array(
					'one',
					'two',
				),
			)
		);
		Functions\create_and_sync_post(
			array(
				'post_content' => 'findme test 2',
				'tags_input'   => array( 'one' ),
			)
		);
		Functions\create_and_sync_post( array( 'post_content' => 'findme test 3' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's'         => 'findme',
			'tax_query' => array(
				array(
					'taxonomy' => 'post_tag',
					'operator' => 'EXISTS',
				),
			),
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );

		$args = array(
			's'         => 'findme',
			'tax_query' => array(
				'relation' => 'AND',
				array(
					'taxonomy' => 'post_tag',
					'operator' => 'EXISTS',
				),
				array(
					'taxonomy' => 'post_tag',
					'terms'    => array( 'two' ),
					'field'    => 'slug',
					'operator' => 'IN',
				),
			),
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, $query->found_posts );
	}

	/**
	 * Test Tax Query NOT EXISTS operator
	 *
	 * @since 2.5
	 * @group post
	 */
	public function testTaxQueryNotExists() {
		Functions\create_and_sync_post(
			array(
				'post_content' => 'findme test 1',
				'tags_input'   => array(
					'one',
					'two',
				),
			)
		);
		Functions\create_and_sync_post(
			array(
				'post_content' => 'findme test 2',
				'tags_input'   => array( 'one' ),
			)
		);
		Functions\create_and_sync_post( array( 'post_content' => 'findme test 3' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's'         => 'findme',
			'tax_query' => array(
				array(
					'taxonomy' => 'post_tag',
					'operator' => 'NOT EXISTS',
				),
			),
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, $query->found_posts );
	}

	/**
	 * Test post_mime_type query
	 *
	 * @since 2.3
	 */
	public function testPostMimeTypeQuery() {
		Functions\create_and_sync_post(
			array(
				'post_type'      => 'attachment',
				'post_mime_type' => 'image/jpeg',
				'post_status'    => 'inherit',
			)
		);
		Functions\create_and_sync_post(
			array(
				'post_type'      => 'attachment',
				'post_mime_type' => 'image/jpeg',
				'post_status'    => 'inherit',
			)
		);
		Functions\create_and_sync_post(
			array(
				'post_type'      => 'attachment',
				'post_mime_type' => 'application/pdf',
				'post_status'    => 'inherit',
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			'ep_integrate'   => true,
			'post_mime_type' => 'image',
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 2, $query->post_count );

		$args = array(
			'ep_integrate'   => true,
			'post_mime_type' => array(
				'image/jpeg',
				'application/pdf',
			),
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 3, $query->found_posts );
	}

	/**
	 * Test Tax Query IN operator
	 *
	 * @since 2.4
	 * @group post
	 */
	public function testTaxQueryOperatorIn() {
		Functions\create_and_sync_post(
			array(
				'post_content' => 'findme test 1',
				'tags_input'   => array(
					'one',
					'two',
				),
			)
		);
		Functions\create_and_sync_post(
			array(
				'post_content' => 'findme test 2',
				'tags_input'   => array( 'one' ),
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's'         => 'findme',
			'tax_query' => array(
				array(
					'taxonomy' => 'post_tag',
					'terms'    => array( 'one', 'two' ),
					'field'    => 'slug',
				),
			),
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );

		$args = array(
			's'         => 'findme',
			'tax_query' => array(
				array(
					'taxonomy' => 'post_tag',
					'terms'    => array( 'one', 'two' ),
					'field'    => 'slug',
					'operator' => 'in',
				),
			),
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );
	}

	/**
	 * Test Tax Query and operator
	 *
	 * @since 2.4
	 * @group post
	 */
	public function testTaxQueryOperatorAnd() {
		$this->assertEquals( 1, 1 );
		Functions\create_and_sync_post(
			array(
				'post_content' => 'findme test 1',
				'tags_input'   => array(
					'one',
					'two',
				),
			)
		);
		Functions\create_and_sync_post(
			array(
				'post_content' => 'findme test 2',
				'tags_input'   => array( 'one' ),
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's'         => 'findme',
			'tax_query' => array(
				array(
					'taxonomy' => 'post_tag',
					'terms'    => array( 'one', 'two' ),
					'field'    => 'slug',
					'operator' => 'and',
				),
			),
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, $query->found_posts );
	}

	/**
	 * If a taxonomy is not public but is publicly queryable, it should return a result.
	 *
	 * @link https://github.com/10up/ElasticPress/issues/890
	 * @group post
	 * @since 2.4
	 */
	public function testCustomTaxonomyPublic() {

		$post_id = Functions\create_and_sync_post();
		$post    = get_post( $post_id );

		$tax_name = rand_str( 32 );
		register_taxonomy(
			$tax_name,
			$post->post_type,
			array(
				'label'              => $tax_name,
				'public'             => false,
				'publicly_queryable' => true,
			)
		);
		register_taxonomy_for_object_type( $tax_name, $post->post_type );

		$term_1_name = rand_str( 32 );
		$term1       = wp_insert_term( $term_1_name, $tax_name );

		wp_set_object_terms( $post_id, array( $term1['term_id'] ), $tax_name, true );

		ElasticPress\Indexables::factory()->get( 'post' )->index( $post_id, true );
		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's'         => 'test',
			'tax_query' => array(
				array(
					'taxonomy' => $tax_name,
					'terms'    => array( $term_1_name ),
					'field'    => 'name',
				),
			),
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( $query->post_count, 1 );
		$this->assertEquals( $query->found_posts, 1 );
		$this->assertTrue( isset( $query->posts[0]->elasticsearch ) );
	}

	/**
	 * If a post is sticky and we are on the home page, it should return at the top.
	 *
	 * @group post
	 */
	public function testStickyPostsIncludedOnHome() {
		Functions\create_and_sync_post( array( 'post_title' => 'Normal post 1' ) );
		$sticky_id = Functions\create_and_sync_post( array( 'post_title' => 'Sticky post' ) );
		stick_post( $sticky_id );
		Functions\create_and_sync_post( array( 'post_title' => 'Normal post 2' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$this->go_to( '/' );

		$q = $GLOBALS['wp_query'];

		$this->assertEquals( 'Sticky post', $q->posts[0]->post_title );
	}

	/**
	 * If a post is not sticky and we are not on the home page, it should not return at the top.
	 *
	 * @group post
	 */
	public function testStickyPostsExcludedOnNotHome() {
		Functions\create_and_sync_post( array( 'post_title' => 'Normal post 1' ) );
		$sticky_id = Functions\create_and_sync_post( array( 'post_title' => 'Sticky post' ) );
		stick_post( $sticky_id );
		Functions\create_and_sync_post( array( 'post_title' => 'Normal post 2' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's' => '',
		);

		$query = new \WP_Query( $args );

		$this->assertNotEquals( 'Sticky post', $query->posts[0]->post_title );
	}

	/**
	 * Test a simple date param search by date and monthnum
	 *
	 * @group post
	 */
	public function testSimpleDateMonthNum() {
		Functions\create_date_query_posts();

		$args = array(
			's'              => 'findme',
			'monthnum'       => 12,
			'posts_per_page' => 100,
		);

		$query = new \WP_Query( $args );
		$this->assertEquals( 5, $query->post_count );
		$this->assertEquals( 5, $query->found_posts );

		$args = array(
			's'              => 'findme',
			'day'            => 5,
			'posts_per_page' => 100,
		);

		$query = new \WP_Query( $args );
		$this->assertEquals( $query->post_count, 1 );
		$this->assertEquals( $query->found_posts, 1 );
	}

	/**
	 * Test a simple date param search by day number of week
	 *
	 * @group post
	 */
	public function testSimpleDateDay() {
		Functions\create_date_query_posts();

		$args = array(
			's'              => 'findme',
			'day'            => 5,
			'posts_per_page' => 100,
		);

		$query = new \WP_Query( $args );
		$this->assertEquals( $query->post_count, 1 );
		$this->assertEquals( $query->found_posts, 1 );
	}

	/**
	 * Test a date query with before and after range
	 *
	 * @group post
	 */
	public function testDateQueryBeforeAfter() {
		Functions\create_date_query_posts();

		$args = array(
			's'              => 'findme',
			'posts_per_page' => 100,
			'date_query'     => array(
				array(
					'after'     => 'January 1st, 2012',
					'before'    => array(
						'year'   => 2012,
						'day'    => 2,
						'month'  => 1,
						'hour'   => 23,
						'minute' => 59,
						'second' => 59,
					),
					'inclusive' => true,
				),
			),
		);

		$query = new \WP_Query( $args );
		$this->assertEquals( $query->post_count, 2 );
		$this->assertEquals( $query->found_posts, 2 );
	}

	/**
	 * Test a date query with multiple column range comparison
	 *
	 * @group post
	 */
	public function testDateQueryMultiColumn() {
		Functions\create_date_query_posts();

		$args = array(
			's'              => 'findme',
			'posts_per_page' => 100,
			'date_query'     => array(
				array(
					'column' => 'post_date',
					'after'  => 'January 1st 2012',
				),
				array(
					'column' => 'post_date_gmt',
					'after'  => 'January 3rd 2012 8AM',
				),
			),
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( $query->post_count, 4 );
		$this->assertEquals( $query->found_posts, 4 );
	}

	/**
	 * Test a date query with multiple column range comparison inclusive
	 *
	 * @group post
	 */
	public function testDateQueryMultiColumnInclusive() {
		Functions\create_date_query_posts();

		$args = array(
			's'              => 'findme',
			'posts_per_page' => 100,
			'date_query'     => array(
				array(
					'column' => 'post_date',
					'before' => 'January 5th 2012 11:00PM',
				),
				array(
					'column' => 'post_date',
					'after'  => 'January 5th 2012 10:00PM',
				),
				'inclusive' => true,
			),
		);

		$query = new \WP_Query( $args );
		$this->assertEquals( $query->post_count, 1 );
		$this->assertEquals( $query->found_posts, 1 );
	}


	/**
	 * Test a date query with multiple eltries
	 *
	 * @group post
	 */
	public function testDateQueryWorkingHours() {
		Functions\create_date_query_posts();

		$args = array(
			's'              => 'findme',
			'posts_per_page' => 100,
			'date_query'     => array(
				array(
					'hour'    => 9,
					'compare' => '>=',
				),
				array(
					'hour'    => 17,
					'compare' => '<=',
				),
				array(
					'dayofweek' => array( 2, 6 ),
					'compare'   => 'BETWEEN',
				),
			),
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( $query->post_count, 5 );
		$this->assertEquals( $query->found_posts, 5 );
	}

	/**
	 * Test a date query with multiple column range comparison not inclusive
	 *
	 * @group post
	 */
	public function testDateQueryMultiColumnNotInclusive() {
		Functions\create_date_query_posts();

		$args = array(
			's'              => 'findme',
			'posts_per_page' => 100,
			'date_query'     => array(
				array(
					'column' => 'post_date',
					'before' => 'January 5th 2012',
				),
				array(
					'column' => 'post_date',
					'after'  => 'January 5th 2012',
				),
				'inclusive' => false,
			),
		);

		$query = new \WP_Query( $args );
		$this->assertEquals( $query->post_count, 0 );
		$this->assertEquals( $query->found_posts, 0 );
	}

	/**
	 * Test a simple date query search by year, monthnum and day of week
	 *
	 * @group post
	 */
	public function testDateQuerySimple() {
		Functions\create_date_query_posts();

		$args = array(
			's'              => 'findme',
			'posts_per_page' => 100,
			'date_query'     => array(
				array(
					'year'     => 2012,
					'monthnum' => 1,
					'day'      => 1,
				),
			),
		);

		$query = new \WP_Query( $args );
		$this->assertEquals( $query->post_count, 1 );
		$this->assertEquals( $query->found_posts, 1 );
	}

	/**
	 * Tests the fallback code for filters and relations.
	 *
	 * @group post
	 */
	public function testDateQueryFiltersRelation() {

		$date_query = new \ElasticPress\Indexable\Post\DateQuery(
			[
				'relation' => '',
				[
					'year' => 0,
				],
			]
		);

		$filter = $date_query->get_es_filter();

		$this->assertTrue( is_array( $filter ) );
		$this->assertCount( 1, $filter );

		$keys = array_keys( $filter );
		$this->assertSame( 'and', $keys[0] );
	}

	/**
	 * Tests additional code for validate_date_values() and simple_es_date_filter().
	 *
	 * @group post
	 */
	public function testDateQueryValidateDateValues() {

		$date_query = new \ElasticPress\Indexable\Post\DateQuery( [] );

		$this->assertFalse( $date_query->validate_date_values() );

		$valid = $date_query->validate_date_values(
			[
				'after' => [ '2020' ],
			]
		);

		$this->assertTrue( $valid );

		$valid = $date_query->validate_date_values(
			[
				'year' => [ '2019', '2020' ],
			]
		);

		$this->assertTrue( $valid );

		$results = \ElasticPress\Indexable\Post\DateQuery::simple_es_date_filter(
			[
				'w' => 10,
			]
		);

		$this->assertTrue( is_array( $results ) );
		$this->assertSame( 10, $results['bool']['must'][0]['term']['date_terms.week'] );
	}

	/**
	 * Tests invalid dates for validate_date_values().
	 *
	 * @group post
	 */
	public function testDateQueryValidateDateDoingItWrong() {

		$this->setExpectedIncorrectUsage( 'ElasticPress\Indexable\Post\DateQuery' );

		$date_query = new \ElasticPress\Indexable\Post\DateQuery( [] );

		$valid = $date_query->validate_date_values(
			[
				'compare' => 'BETWEEN',
				'month'   => [ 0, 1 ],
			]
		);

		$this->assertFalse( $valid );

		$valid = $date_query->validate_date_values(
			[
				'compare' => 'BETWEEN',
				'month'   => [ 13, 14 ],
			]
		);

		$this->assertFalse( $valid );

		$valid = $date_query->validate_date_values(
			[
				'month' => '2',
				'day'   => '30',
				'year'  => '2020',
			]
		);

		$this->assertFalse( $valid );

		$valid = $date_query->validate_date_values(
			[
				'month' => '2',
				'day'   => '30',
			]
		);

		$this->assertFalse( $valid );
	}

	/**
	 * Test a date query with BETWEEN comparison
	 *
	 * @group post
	 */
	public function testDateQueryBetween() {
		Functions\create_date_query_posts();

		$args = array(
			's'              => 'findme',
			'posts_per_page' => 100,
			'date_query'     => array(
				array(
					'day'     => array( 1, 5 ),
					'compare' => 'BETWEEN',
				),
			),
		);

		$query = new \WP_Query( $args );
		$this->assertEquals( $query->post_count, 5 );
		$this->assertEquals( $query->found_posts, 5 );
	}

	/**
	 * Test a date query with NOT BETWEEN comparison
	 *
	 * @group post
	 */
	public function testDateQueryNotBetween() {
		Functions\create_date_query_posts();

		$args = array(
			's'              => 'findme',
			'posts_per_page' => 100,
			'date_query'     => array(
				array(
					'day'     => array( 1, 5 ),
					'compare' => 'NOT BETWEEN',
				),
			),
		);

		$query = new \WP_Query( $args );
		$this->assertEquals( $query->post_count, 8 );
		$this->assertEquals( $query->found_posts, 8 );
	}

	/**
	 * Test a date query with BETWEEN comparison on 1 day range
	 *
	 * @group post
	 */
	public function testDateQueryShortBetween() {
		Functions\create_date_query_posts();

		$args = array(
			's'              => 'findme',
			'posts_per_page' => 100,
			'date_query'     => array(
				array(
					'day'     => array( 5, 5 ),
					'compare' => 'BETWEEN',
				),
			),
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, $query->found_posts );
	}

	/**
	 * Test a date query with multiple range comparisons
	 *
	 * Currently created posts don't have that many date based differences
	 * for this test
	 *
	 * @group post
	 */
	public function testDateQueryCompare() {
		Functions\create_date_query_posts();

		$args = array(
			's'              => 'findme',
			'posts_per_page' => 100,
			'date_query'     => array(
				array(
					'monthnum' => 1,
					'compare'  => '<=',
				),
				array(
					'year'    => 2012,
					'compare' => '>=',
				),
				array(
					'day'     => array( 2, 5 ),
					'compare' => 'BETWEEN',
				),
			),
		);

		$date_query = new \ElasticPress\Indexable\Post\DateQuery(
			[
				'w' => 10,
			]
		);

		$filter = $date_query->get_es_filter();

		$this->assertTrue( is_array( $filter ) );
		$this->assertSame( 10, $filter['and']['bool']['must'][0]['term']['date_terms.week'] );

		$query = new \WP_Query( $args );
		$this->assertEquals( $query->post_count, 4 );
		$this->assertEquals( $query->found_posts, 4 );

		$date_query = new \ElasticPress\Indexable\Post\DateQuery(
			[
				'monthnum' => 1,
				'compare'  => '!=',
			]
		);

		$filter = $date_query->get_es_filter();

		$this->assertTrue( is_array( $filter ) );
		$this->assertSame( 1, $filter['and']['bool']['must_not'][0]['term']['date_terms.month'] );

		$date_query = new \ElasticPress\Indexable\Post\DateQuery(
			[
				'monthnum' => [ 1, 2 ],
				'compare'  => 'IN',
			]
		);

		$filter = $date_query->get_es_filter();

		$this->assertTrue( is_array( $filter ) );
		$this->assertSame( 1, $filter['and']['bool']['should'][0]['term']['date_terms.month'] );
		$this->assertSame( 2, $filter['and']['bool']['should'][1]['term']['date_terms.month'] );

		$date_query = new \ElasticPress\Indexable\Post\DateQuery(
			[
				'monthnum' => [ 1, 2 ],
				'compare'  => 'NOT IN',
			]
		);

		$filter = $date_query->get_es_filter();

		$this->assertTrue( is_array( $filter ) );
		$this->assertSame( 1, $filter['and']['bool']['must_not'][0]['term']['date_terms.month'] );
		$this->assertSame( 2, $filter['and']['bool']['must_not'][1]['term']['date_terms.month'] );
	}

	/**
	 * Test a date query with multiple range comparisons where before and after are
	 * structured differently. Test inclusive range.
	 *
	 * @group post
	 */
	public function testDateQueryInclusiveTypeMix() {
		Functions\create_date_query_posts();

		$args = array(
			's'              => 'findme',
			'posts_per_page' => 100,
			'date_query'     => array(
				array(
					'after'     => 'January 4, 2012',
					'before'    => array(
						'year'   => 2012,
						'month'  => 1,
						'day'    => 5,
						'hour'   => 23,
						'minute' => 0,
						'second' => 0,
					),
					'inclusive' => true,
				),
			),
		);

		$query = new \WP_Query( $args );
		$this->assertEquals( $query->post_count, 2 );
		$this->assertEquals( $query->found_posts, 2 );
	}

	/**
	 * Test a date query with multiple range comparisons where before and after are
	 * structured differently. Test exclusive range.
	 *
	 * @group post
	 */
	public function testDateQueryExclusiveTypeMix() {
		Functions\create_date_query_posts();

		$args = array(
			's'              => 'findme',
			'posts_per_page' => 100,
			'date_query'     => array(
				array(
					'after'     => 'January 4, 2012 10:00PM',
					'before'    => array(
						'year'  => 2012,
						'month' => 1,
						'day'   => 5,
					),
					'inclusive' => false,
				),
			),
		);

		$query = new \WP_Query( $args );
		$this->assertEquals( $query->post_count, 0 );
		$this->assertEquals( $query->found_posts, 0 );
	}

	/**
	 * Test another date query with multiple range comparisons
	 *
	 * @group post
	 */
	public function testDateQueryCompare2() {
		Functions\create_date_query_posts();

		$args = array(
			's'              => 'findme',
			'posts_per_page' => 100,
			'date_query'     => array(
				array(
					'monthnum' => 1,
					'compare'  => '<=',
				),
				array(
					'year'    => 2012,
					'compare' => '>=',
				),
				array(
					'day'     => array( 5, 6 ),
					'compare' => 'BETWEEN',
				),
			),
		);

		$query = new \WP_Query( $args );
		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );
	}

	/**
	 * Test date query where posts are only pulled from weekdays
	 *
	 * @group post
	 */
	public function testDateQueryWeekdayRange() {
		Functions\create_date_query_posts();

		$args = array(
			's'              => 'findme',
			'posts_per_page' => 100,
			'date_query'     => array(
				array(
					'dayofweek' => array( 2, 6 ),
					'compare'   => 'BETWEEN',
				),
			),
		);

		$query = new \WP_Query( $args );
		$this->assertEquals( 9, $query->post_count );
		$this->assertEquals( 9, $query->found_posts );
	}

	/**
	 * Check if elasticpress_enabled() properly handles an object without the is_search() method.
	 *
	 * @group post
	 * @link https://github.com/10up/ElasticPress/issues/285
	 */
	public function testQueryWithoutIsSearch() {
		$query = new \stdClass();
		$check = ElasticPress\Indexables::factory()->get( 'post' )->elasticpress_enabled( $query );
		$this->assertFalse( $check );
	}

	/**
	 * Check if elasticpress_enabled() properly handles an object with the is_search() method.
	 *
	 * @group post
	 * @link https://github.com/10up/ElasticPress/issues/285
	 */
	public function testQueryWithIsSearch() {
		$args  = array(
			's' => 'findme',
		);
		$query = new \WP_Query( $args );
		$check = ElasticPress\Indexables::factory()->get( 'post' )->elasticpress_enabled( $query );
		$this->assertTrue( $check );
	}

	/**
	 * Tested nested taxonomy query
	 *
	 * @group post
	 */
	public function testNestedTaxQuery() {
		$cat1 = wp_create_category( 'category one' );
		$cat2 = wp_create_category( 'category two' );

		Functions\create_and_sync_post(
			array(
				'post_content'  => 'findme test 1',
				'tags_input'    => array( 'one', 'two' ),
				'post_category' => array( $cat1 ),
			)
		);

		Functions\create_and_sync_post( array( 'post_content' => 'findme test 2' ) );

		Functions\create_and_sync_post(
			array(
				'post_content'  => 'findme test 3',
				'tags_input'    => array( 'one', 'three' ),
				'post_category' => array( $cat2 ),
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			'ep_integrate' => 1,
			'post_type'    => 'post',
			'tax_query'    => array(
				'relation' => 'OR',
				array(
					'taxonomy' => 'category',
					'field'    => 'slug',
					'terms'    => array( 'category-one' ),
				),
				array(
					'relation' => 'AND',
					array(
						'taxonomy' => 'post_tag',
						'field'    => 'slug',
						'terms'    => array( 'four' ),
						'operator' => 'NOT IN',
					),
					array(
						'taxonomy' => 'post_tag',
						'field'    => 'slug',
						'terms'    => array( 'three' ),
					),
				),
			),
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 2, count( $query->posts ) );
	}

	/**
	 * Test a query with tag__and and tag_id params
	 *
	 * @since 2.0
	 * @group post
	 */
	public function testTagQuery() {
		$post_id_1 = Functions\create_and_sync_post(
			array(
				'post_content' => 'findme test 1',
				'tags_input'   => array( 'one', 'two' ),
			)
		);
		$post_id_2 = Functions\create_and_sync_post(
			array(
				'post_content' => 'findme test 2',
				'tags_input'   => array( 'three', 'four', 'five', 'six' ),
			)
		);

		$post_id_3 = Functions\create_and_sync_post(
			array(
				'post_content' => 'findme test 3',
				'tags_input'   => array( 'one', 'six' ),
			)
		);

		$post_1_tags = get_the_tags( $post_id_1 );
		$post_2_tags = get_the_tags( $post_id_2 );
		$post_3_tags = get_the_tags( $post_id_3 );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's'         => 'findme',
			'post_type' => 'post',
			'tag__and'  => array( $post_1_tags[1]->term_id, $post_2_tags[1]->term_id ),
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );

		// Verify we're only getting the posts we requested.
		$post_names = wp_list_pluck( $query->posts, 'post_name' );

		$this->assertContains( get_post_field( 'post_name', $post_id_1 ), $post_names );
		$this->assertContains( get_post_field( 'post_name', $post_id_2 ), $post_names );
		$this->assertNotContains( get_post_field( 'post_name', $post_id_3 ), $post_names );

		$args = array(
			's'         => 'findme',
			'post_type' => 'post',
			'tag_id'    => $post_3_tags[1]->term_id,
		);

		$query = new \WP_Query( $args );

		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );
	}

	/**
	 * Tests the http_request_args filter.
	 *
	 * @return void
	 * @group post
	 */
	public function testHttpRequestArgsFilter() {
		add_action( 'ep_sync_on_transition', array( $this, 'action_sync_on_transition' ), 10, 0 );

		add_filter(
			'http_request_args',
			function( $args ) {
				$args['headers']['x-my-value'] = '12345';
				return $args;
			}
		);

		add_filter(
			'http_request_args',
			function( $args ) {
				$this->assertSame( '12345', $args['headers']['x-my-value'] );
				return $args;
			},
			PHP_INT_MAX
		);

		$post_id = Functions\create_and_sync_post();

		ElasticPress\Elasticsearch::factory()->refresh_indices();
	}

	/**
	 * Tests the constructor for the Indexable\Post class.
	 *
	 * @return void
	 * @group post
	 */
	public function testPostConstructor() {

		$post = new \ElasticPress\Indexable\Post\Post();

		$this->assertSame( 'Posts', $post->labels['plural'] );
		$this->assertSame( 'Post', $post->labels['singular'] );

		$this->assertTrue( is_a( $post->sync_manager, '\ElasticPress\Indexable\Post\SyncManager' ) );
		$this->assertTrue( is_a( $post->query_integration, '\ElasticPress\Indexable\Post\QueryIntegration' ) );
	}

	/**
	 * Tests the query_db method.
	 *
	 * @return void
	 * @group post
	 */
	public function testQueryDb() {
		$indexable_post_object = new \ElasticPress\Indexable\Post\Post();

		$post_id_1 = Functions\create_and_sync_post();
		$post_id_2 = Functions\create_and_sync_post();
		$post_id_3 = Functions\create_and_sync_post();

		// Test the first loop of the indexing.
		$results = $indexable_post_object->query_db(
			[
				'per_page' => 1,
			]
		);

		$post_ids = wp_list_pluck( $results['objects'], 'ID' );
		$this->assertEquals( $post_id_3, $post_ids[0] );
		$this->assertCount( 1, $results['objects'] );
		$this->assertEquals( 3, $results['total_objects'] );

		// Second loop.
		$results = $indexable_post_object->query_db(
			[
				'per_page' => 1,
				'ep_indexing_last_processed_object_id' => $post_id_3,
			]
		);

		$post_ids = wp_list_pluck( $results['objects'], 'ID' );
		$this->assertEquals( $post_id_2, $post_ids[0] );
		$this->assertCount( 1, $results['objects'] );
		$this->assertEquals( 3, $results['total_objects'] );

		// A custom start_object_id was passed in.
		$results = $indexable_post_object->query_db(
			[
				'per_page' => 1,
				'ep_indexing_start_object_id' => $post_id_1,
			]
		);

		$post_ids = wp_list_pluck( $results['objects'], 'ID' );
		$this->assertEquals( $post_id_1, $post_ids[0] );
		$this->assertCount( 1, $results['objects'] );
		$this->assertEquals( 1, $results['total_objects'] );

		// Passing custom start and last post IDs. Second loop.
		$results = $indexable_post_object->query_db(
			[
				'per_page' => 1,
				'ep_indexing_start_object_id' => $post_id_3,
				'ep_indexing_end_object_id' => $post_id_2,
				'ep_indexing_last_processed_object_id' => $post_id_3,
			]
		);

		$post_ids = wp_list_pluck( $results['objects'], 'ID' );
		$this->assertEquals( $post_id_2, $post_ids[0] );
		$this->assertCount( 1, $results['objects'] );
		$this->assertEquals( 2, $results['total_objects'] );

		// Specific post IDs
		$results = $indexable_post_object->query_db(
			[
				'per_page' => 1,
				'include'  => [ $post_id_1 ],
			]
		);

		$post_ids = wp_list_pluck( $results['objects'], 'ID' );
		$this->assertEquals( $post_id_1, $post_ids[0] );
		$this->assertCount( 1, $results['objects'] );
		$this->assertEquals( 1, $results['total_objects'] );
	}

	/**
	 * Test the filters in query_db.
	 *
	 * @return void
	 * @group post
	 */
	function testQueryDbFilters() {
		$indexable_post_object = new \ElasticPress\Indexable\Post\Post();

		$args_post_ids = [];
		$args_post_ids[] = Functions\create_and_sync_post();
		$args_post_ids[] = Functions\create_and_sync_post();
		$args_post_ids[] = Functions\create_and_sync_post();
		$args_post_ids[] = Functions\create_and_sync_post();

		$defaults_filter = function( $args ) use ( $args_post_ids ) {
			$args['post__in'] = $args_post_ids;
			return $args;
		};

		$index_filter = function( $args ) {
			$args['posts_per_page'] = 3;
			$args['order'] = 'ASC';
			return $args;
		};

		add_filter( 'ep_post_query_db_args', $defaults_filter );
		add_filter( 'ep_index_posts_args', $index_filter );

		$results = $indexable_post_object->query_db( [] );

		remove_filter( 'ep_post_query_db_args', $defaults_filter );
		remove_filter( 'ep_index_posts_args', $index_filter );

		$post_ids = wp_list_pluck( $results['objects'], 'ID' );
		$this->assertCount( 3, $post_ids );
		$this->assertContains( $args_post_ids[2], $post_ids );
		$this->assertNotContains( $args_post_ids[3], $post_ids );
	}

	/**
	 * Tests fallback code inside prepare_document.
	 *
	 * @return void
	 * @group post
	 */
	public function testPrepareDocumentFallbacks() {
		global $wpdb;
		global $wp_taxonomies;

		$post = new \ElasticPress\Indexable\Post\Post();

		$this->assertFalse( $post->prepare_document( null ) );

		// Create a post with invalid data.
		$post_id = Functions\create_and_sync_post();

		// Manually update the post with invalid data.
		$wpdb->update(
			$wpdb->posts,
			[
				'post_author'   => 0,
				'post_date'     => '0000-00-00 00:00:00',
				'post_modified' => '0000-00-00 00:00:00',

			],
			[
				'ID' => $post_id,
			]
		);

		clean_post_cache( $post_id );

		wp_set_post_terms( $post_id, 'testPrepareDocumentFallbacks', 'category', true );

		add_filter( 'ep_sync_taxonomies', '__return_empty_array' );

		$post_args = $post->prepare_document( $post_id );

		remove_filter( 'ep_sync_taxonomies', '__return_empty_array' );

		$this->assertTrue( is_array( $post_args ) );
		$this->assertTrue( is_array( $post_args['terms'] ) );
		$this->assertEmpty( $post_args['terms'] );
		$this->assertSame( null, $post_args['post_date'] );
		$this->assertSame( null, $post_args['post_modified'] );

		// Run it again with a filter to return a taxonomy that's not
		// a WP_Taxonomy class.
		$terms_callback = function() {
			return [
				'testPrepareDocumentFallbacks',
			];
		};

		// We need to create an object that is not a taxonomy to simulate
		// pre 4.7 behavior.
		$invalid_taxonomy = new \stdClass();
		$invalid_taxonomy->object_type = 'post';
		$invalid_taxonomy->public      = true;

		$wp_taxonomies['testPrepareDocumentFallbacks'] = $invalid_taxonomy;

		add_filter( 'ep_sync_taxonomies', $terms_callback );

		$post_args = $post->prepare_document( $post_id );

		remove_filter( 'ep_sync_taxonomies', $terms_callback );

		$this->assertTrue( is_array( $post_args['terms'] ) );
		$this->assertEmpty( $post_args['terms'] );

		unset( $wp_taxonomies['testPrepareDocumentFallbacks'] );
	}

	/**
	 * Tests additional code inside format_args.
	 *
	 * @return void
	 * @group post
	 */
	public function testFormatArgs() {

		$post = new \ElasticPress\Indexable\Post\Post();

		$query = new \WP_Query();
		$posts_per_page = (int) get_option( 'posts_per_page' );

		$args = $post->format_args(
			[
				'cat'       => 123,
				'tag'       => 'tag-slug',
				'post_tag'  => 'post-tag-slug',
			],
			$query
		);

		$this->assertSame( $posts_per_page, $args['size'] );

		$this->assertTrue( is_array( $args['post_filter']['bool']['must'][0]['bool']['must'] ) );

		$must_terms = $args['post_filter']['bool']['must'][0]['bool']['must'];

		$this->assertSame( 123, $must_terms[0]['terms']['terms.category.term_id'][0] );
		$this->assertSame( 'tag-slug', $must_terms[1]['terms']['terms.post_tag.slug'][0] );
		$this->assertSame( 'post-tag-slug', $must_terms[2]['terms']['terms.post_tag.slug'][0] );
	}
}
