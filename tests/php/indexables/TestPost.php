<?php
/**
 * Test post indexable functionality
 *
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress;
use ElasticPress\Indexables;

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
	public function set_up() {
		global $wpdb;
		parent::set_up();
		$wpdb->suppress_errors();

		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );

		wp_set_current_user( $admin_id );

		ElasticPress\Elasticsearch::factory()->delete_all_indices();
		ElasticPress\Indexables::factory()->get( 'post' )->put_mapping();

		ElasticPress\Indexables::factory()->get( 'post' )->sync_manager->reset_sync_queue();

		$this->setup_test_post_type();

		/**
		 * Most of our search test are bundled into core tests for legacy reasons
		 */
		ElasticPress\Features::factory()->activate_feature( 'search' );
		ElasticPress\Features::factory()->setup_features();

		// Need to call this since it's hooked to init
		ElasticPress\Features::factory()->get_registered_feature( 'search' )->search_setup();

		// Allow some meta fields to be indexed.
		add_filter(
			'ep_prepare_meta_allowed_keys',
			function( $allowed_metakeys ) {
				return array_merge(
					$allowed_metakeys,
					[
						'test_key',
						'test_key1',
						'test_key2',
						'test_key3',
						'test_key4',
						'test_key5',
						'test_key6',
					]
				);
			}
		);
	}

	/**
	 * Get Search feature
	 *
	 * @return ElasticPress\Feature\Search\
	 */
	protected function get_feature() {
		return ElasticPress\Features::factory()->get_registered_feature( 'search' );
	}

	/**
	 * Create posts for date query testing
	 *
	 * @since  3.0
	 */
	protected function create_date_query_posts() {
		$post_date = wp_date( 'U', strtotime( 'January 6th, 2012 11:59PM' ) );

		for ( $i = 0; $i <= 10; ++$i ) {
			$this->ep_factory->post->create(
				array(
					'post_title'    => 'post_title ' . $i,
					'post_content'  => 'findme',
					'post_date'     => wp_date( 'Y-m-d H:i:s', strtotime( "-$i days", strtotime( "-$i hours", $post_date ) ) ),
					'post_date_gmt' => wp_date( 'Y-m-d H:i:s', strtotime( "-$i days", strtotime( "-$i hours", $post_date ) ), new \DateTimeZone( 'GMT' ) ),
				)
			);

			ElasticPress\Elasticsearch::factory()->refresh_indices();
		}
	}


	/**
	 * Clean up after each test. Reset our mocks
	 *
	 * @since 0.1.0
	 */
	public function tear_down() {
		parent::tear_down();

		// Unset current_screen so is_admin() is reset.
		if ( isset( $GLOBALS['current_screen'] ) ) {
			unset( $GLOBALS['current_screen'] );
		}

		// make sure no one attached to this
		remove_filter( 'ep_sync_terms_allow_hierarchy', array( $this, 'ep_allow_multiple_level_terms_sync' ), 100 );
		$this->fired_actions = array();
	}

	/**
	 * Test a simple post sync
	 *
	 * @since 0.9
	 * @group post
	 */
	public function testPostSync() {
		add_action( 'ep_sync_on_transition', array( $this, 'action_sync_on_transition' ), 10, 0 );

		$post_id = $this->ep_factory->post->create();

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

		$post_id = $this->ep_factory->post->create();

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

		$post_id = $this->ep_factory->post->create();

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
	 * Test pagination with offset
	 *
	 * @since 2.1
	 * @group post
	 */
	public function testPaginationWithOffset() {
		$this->ep_factory->post->create( array( 'post_title' => 'one' ) );
		$this->ep_factory->post->create( array( 'post_title' => 'two' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$query = new \WP_Query(
			array(
				'post_type'      => 'post',
				'ep_integrate'   => true,
				'posts_per_page' => 1,
				'offset'         => 1,
				'order'          => 'ASC',
				'orderby'        => 'title',
			)
		);

		$this->assertTrue( $query->elasticsearch_success );
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

		$post_ids[0] = $this->ep_factory->post->create();
		$post_ids[1] = $this->ep_factory->post->create();
		$post_ids[2] = $this->ep_factory->post->create( array( 'post_content' => 'findme' ) );
		$post_ids[3] = $this->ep_factory->post->create();
		$post_ids[4] = $this->ep_factory->post->create( array( 'post_content' => 'findme' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's' => 'findme',
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );

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

		$post_ids[0] = $this->ep_factory->post->create();
		$post_ids[1] = $this->ep_factory->post->create();
		$post_ids[2] = $this->ep_factory->post->create( array( 'post_title' => 'findme test' ) );
		$post_ids[3] = $this->ep_factory->post->create( array( 'post_title' => 'findme test2' ) );
		$post_ids[4] = $this->ep_factory->post->create( array( 'post_title' => 'findme test2' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's' => 'findme',
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );

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

		$post_id = $this->ep_factory->post->create(
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
	 * Make sure proper non-hierarchical taxonomies are synced with post when ep_sync_terms_allow_hierarchy is
	 * set to false.
	 *
	 * @group post
	 */
	public function testPostTermSyncSingleLevel() {
		add_filter( 'ep_sync_terms_allow_hierarchy', array( $this, 'ep_disallow_multiple_level_terms_sync' ), 100, 1 );

		$post = $this->ep_factory->post->create_and_get();

		$tax_name = rand_str( 32 );
		register_taxonomy( $tax_name, $post->post_type, array( 'label' => $tax_name ) );
		register_taxonomy_for_object_type( $tax_name, $post->post_type );

		$term_1_name = rand_str( 32 );
		$term1       = wp_insert_term( $term_1_name, $tax_name );

		$term_2_name = rand_str( 32 );
		$term2       = wp_insert_term( $term_2_name, $tax_name, array( 'parent' => $term1['term_id'] ) );

		$term_3_name = rand_str( 32 );
		$term3       = wp_insert_term( $term_3_name, $tax_name, array( 'parent' => $term2['term_id'] ) );

		wp_set_object_terms( $post->ID, array( $term3['term_id'] ), $tax_name, true );

		ElasticPress\Indexables::factory()->get( 'post' )->index( $post->ID, true );

		$post = ElasticPress\Indexables::factory()->get( 'post' )->get( $post->ID );

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

		$post = $this->ep_factory->post->create_and_get();

		$tax_name = rand_str( 32 );
		register_taxonomy( $tax_name, $post->post_type, array( 'label' => $tax_name ) );
		register_taxonomy_for_object_type( $tax_name, $post->post_type );

		$term_1_name = rand_str( 32 );
		$term1       = wp_insert_term( $term_1_name, $tax_name );

		$term_2_name = rand_str( 32 );
		$term2       = wp_insert_term( $term_2_name, $tax_name, array( 'parent' => $term1['term_id'] ) );

		$term_3_name = rand_str( 32 );
		$term3       = wp_insert_term( $term_3_name, $tax_name, array( 'parent' => $term2['term_id'] ) );

		wp_set_object_terms( $post->ID, array( $term3['term_id'] ), $tax_name, true );

		ElasticPress\Indexables::factory()->get( 'post' )->index( $post->ID, true );

		$post = ElasticPress\Indexables::factory()->get( 'post' )->get( $post->ID );

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
		$post = $this->ep_factory->post->create_and_get( array( 'post_title' => '#findme' ) );

		$tax_name = rand_str( 32 );
		register_taxonomy( $tax_name, $post->post_type, array( 'label' => $tax_name ) );
		register_taxonomy_for_object_type( $tax_name, $post->post_type );

		$term_1_name = rand_str( 32 );
		$term1       = wp_insert_term( $term_1_name, $tax_name );

		$term_2_name = rand_str( 32 );
		$term2       = wp_insert_term( $term_2_name, $tax_name, array( 'parent' => $term1['term_id'] ) );

		$term_3_name = rand_str( 32 );
		$term3       = wp_insert_term( $term_3_name, $tax_name, array( 'parent' => $term2['term_id'] ) );

		wp_set_object_terms( $post->ID, array( $term3['term_id'] ), $tax_name, true );

		ElasticPress\Indexables::factory()->get( 'post' )->index( $post->ID, true );
		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$query = new \WP_Query( array( 's' => '#findme' ) );

		$this->assertTrue( $query->elasticsearch_success );

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

		$post = $this->ep_factory->post->create_and_get();

		$tax_name = rand_str( 32 );
		register_taxonomy( $tax_name, $post->post_type, array( 'label' => $tax_name ) );
		register_taxonomy_for_object_type( $tax_name, $post->post_type );

		$term_1_name = rand_str( 32 );
		$term1       = wp_insert_term( $term_1_name, $tax_name );

		wp_set_object_terms( $post->ID, array( $term1['term_id'] ), $tax_name, true );

		ElasticPress\Indexables::factory()->get( 'post' )->index( $post->ID, true );
		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			$tax_name      => $term_1_name,
			'ep_integrate' => true,
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( $query->post_count, 1 );
		$this->assertEquals( $query->found_posts, 1 );
	}


	/**
	 * Make sure proper taxonomies are synced with post.
	 *
	 * @group post
	 */
	public function testPostImplicitTaxonomyQueryCategoryName() {

		$post_id = $this->ep_factory->post->create();

		$term_1_name = rand_str( 32 );
		$term1       = wp_insert_term( $term_1_name, 'category' );

		wp_set_object_terms( $post_id, array( $term1['term_id'] ), 'category', true );

		ElasticPress\Indexables::factory()->get( 'post' )->index( $post_id, true );
		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			'category_name' => $term_1_name,
			'ep_integrate'  => true,
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( $query->post_count, 1 );
		$this->assertEquals( $query->found_posts, 1 );
	}

	/**
	 * Make sure proper taxonomies are synced with post.
	 *
	 * @group post
	 */
	public function testPostImplicitTaxonomyQueryTag() {

		$post_id = $this->ep_factory->post->create();

		$term_1_name = rand_str( 32 );
		$term1       = wp_insert_term( $term_1_name, 'post_tag' );

		wp_set_object_terms( $post_id, array( $term1['term_id'] ), 'post_tag', true );

		ElasticPress\Indexables::factory()->get( 'post' )->index( $post_id, true );
		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			'tag'          => $term_1_name,
			'ep_integrate' => true,
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
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

		$post_ids[0] = $this->ep_factory->post->create();
		$post_ids[1] = $this->ep_factory->post->create();
		$post_ids[2] = $this->ep_factory->post->create( array( 'post_excerpt' => 'findme test' ) );
		$post_ids[3] = $this->ep_factory->post->create();
		$post_ids[4] = $this->ep_factory->post->create();

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's' => 'findme',
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );

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
		$this->ep_factory->post->create( array( 'post_excerpt' => 'findme test 1' ) );
		$this->ep_factory->post->create( array( 'post_excerpt' => 'findme test 2' ) );
		$this->ep_factory->post->create( array( 'post_excerpt' => 'findme test 3' ) );

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

		$this->assertTrue( $query->elasticsearch_success );
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

		$this->assertTrue( $query->elasticsearch_success );
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

		$this->assertTrue( $query->elasticsearch_success );
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

		$this->assertTrue( $query->elasticsearch_success );
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
		$this->ep_factory->post->create(
			array(
				'post_content' => 'findme test 1',
				'tags_input'   => array(
					'one',
					'two',
				),
			)
		);
		$this->ep_factory->post->create( array( 'post_content' => 'findme test 2' ) );
		$this->ep_factory->post->create(
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

		$this->assertTrue( $query->elasticsearch_success );
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

		$this->ep_factory->post->create(
			array(
				'post_content'  => 'findme test 1',
				'tags_input'    => array( 'one', 'two' ),
				'post_category' => array( $cat1 ),
			)
		);
		$this->ep_factory->post->create( array( 'post_content' => 'findme test 2' ) );
		$this->ep_factory->post->create(
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

		$this->assertTrue( $query->elasticsearch_success );
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
		$post = $this->ep_factory->post->create(
			array(
				'post_content' => 'findme test 1',
				'tags_input'   => array(
					'one',
					'two',
				),
			)
		);
		$this->ep_factory->post->create( array( 'post_content' => 'findme test 2' ) );
		$this->ep_factory->post->create(
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

		$this->assertTrue( $query->elasticsearch_success );
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

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );
	}

	/**
	 *
	 * Test a taxonomy query with invalid field value and make sure it falls back to term_id.
	 *
	 * @since 4.4.0
	 * @group post
	 */
	public function testTaxQueryInvalidWithInvalidField() {
		$post = $this->ep_factory->post->create(
			array(
				'post_content' => 'findme test 1',
				'tags_input'   => array(
					'one',
					'two',
				),
			)
		);
		$this->ep_factory->post->create( array( 'post_content' => 'findme test 2' ) );
		$this->ep_factory->post->create(
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

		$args  = array(
			'ep_integrate' => false,
			'tax_query'    => array(
				array(
					'taxonomy' => 'post_tag',
					'terms'    => array( $tag_id ),
					'field'    => 'invalid_field',
				),
			),
		);
		$query = new \WP_Query( $args );
		$this->assertNull( $query->elasticsearch_success );

		$expected_result = wp_list_pluck( $query->posts, 'ID' );

		$args['ep_integrate'] = true;
		$query                = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( $expected_result, wp_list_pluck( $query->posts, 'ID' ) );
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

		$this->ep_factory->post->create(
			array(
				'post_content'  => 'findme test 1',
				'post_category' => array(
					$cat1,
					$cat2,
				),
			)
		);
		$this->ep_factory->post->create( array( 'post_content' => 'findme test 2' ) );
		$this->ep_factory->post->create(
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

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );
	}

	/**
	 * Test a taxonomy query with invalid terms
	 *
	 * @since 4.0.0
	 * @group post
	 */
	public function testTaxQueryWithInvalidTerms() {
		$post = $this->ep_factory->post->create(
			array(
				'post_content' => 'findme test 1',
				'tags_input'   => array(
					'one',
					'two',
				),
			)
		);
		$this->ep_factory->post->create( array( 'post_content' => 'findme test 2' ) );
		$this->ep_factory->post->create(
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
					'terms'    => array( $tag_id, null ),
					'field'    => 'term_id',
				),
			),
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );

		$args = array(
			's'         => 'findme',
			'tax_query' => array(
				array(
					'taxonomy' => 'post_tag',
					'terms'    => array( null, $tag_id, false ),
					'field'    => 'term_id',
				),
			),
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );

		$args = array(
			's'         => 'findme',
			'tax_query' => array(
				array(
					'taxonomy' => 'post_tag',
					'terms'    => array( $tag_id, null ),
				),
			),
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
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
		$this->ep_factory->post->create(
			array(
				'post_content'  => 'findme test 1',
				'post_category' => array(
					$cat_one,
					$cat_two,
				),
			)
		);
		$this->ep_factory->post->create( array( 'post_content' => 'findme test 2' ) );
		$this->ep_factory->post->create(
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

		$this->assertTrue( $query->elasticsearch_success );
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

		$post_ids[0] = $this->ep_factory->post->create( array( 'post_content' => 'findme test 1' ) );
		$post_ids[1] = $this->ep_factory->post->create( array( 'post_content' => 'findme test 2' ) );
		$post_ids[2] = $this->ep_factory->post->create( array( 'post_content' => 'findme test 3' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's'        => 'findme',
			'post__in' => array( $post_ids[0], $post_ids[1] ),
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );
	}

	/**
	 * Test a post__not_in query with non-sequential array indices
	 *
	 * @since 4.7.2
	 * @group post
	 */
	public function testPostNotInQueryWithNonSequentialIndices() {
		$post_ids = array();

		$post_ids[0] = $this->ep_factory->post->create( array( 'post_content' => 'findme test 1' ) );
		$post_ids[1] = $this->ep_factory->post->create( array( 'post_content' => 'findme test 2' ) );
		$post_ids[2] = $this->ep_factory->post->create( array( 'post_content' => 'findme test 3' ) );
		$post_ids[3] = $this->ep_factory->post->create( array( 'post_content' => 'findme test 4' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's'            => 'findme',
			'post__not_in' => array(
				0 => $post_ids[0],
				2 => $post_ids[3],
			),
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );
	}

	/**
	 * Test a category__not_in query
	 *
	 * @since 3.6.0
	 * @group post
	 */
	public function testCategoryNotInQuery() {
		$term = wp_insert_term( 'cattest', 'category' );

		$post_ids = array();

		$post_ids[0] = $this->ep_factory->post->create(
			array(
				'post_content'  => 'findme cat not in test 1',
				'post_category' => array( $term['term_id'] ),
			)
		);
		$post_ids[1] = $this->ep_factory->post->create( array( 'post_content' => 'findme cat not in test 2' ) );
		$post_ids[2] = $this->ep_factory->post->create( array( 'post_content' => 'findme cat not in test 3' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's'                => 'findme cat not in test',
			'category__not_in' => array( $term['term_id'] ),
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );
	}

	/**
	 * Test a tag__not_in query
	 *
	 * @since 3.6.0
	 * @group post
	 */
	public function testTagNotInQuery() {
		$term = wp_insert_term( 'tagtest', 'post_tag' );

		$post_ids = array();

		$post_ids[0] = $this->ep_factory->post->create(
			array(
				'post_content' => 'findme cat not in test 1',
				'tags_input'   => array( $term['term_id'] ),
			)
		);
		$post_ids[1] = $this->ep_factory->post->create( array( 'post_content' => 'findme cat not in test 2' ) );
		$post_ids[2] = $this->ep_factory->post->create( array( 'post_content' => 'findme cat not in test 3' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's'           => 'findme cat not in test',
			'tag__not_in' => array( $term['term_id'] ),
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
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

		$this->ep_factory->post->create(
			array(
				'post_content' => 'findme test 1',
				'post_author'  => $user_id,
			)
		);
		$this->ep_factory->post->create( array( 'post_content' => 'findme test 2' ) );
		$this->ep_factory->post->create(
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

		$this->assertTrue( $query->elasticsearch_success );
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

		$this->ep_factory->post->create(
			array(
				'post_content' => 'findme test 1',
				'post_author'  => $user_id,
			)
		);
		$this->ep_factory->post->create( array( 'post_content' => 'findme test 2' ) );
		$this->ep_factory->post->create(
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

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 3, $query->post_count );
		$this->assertEquals( 3, $query->found_posts );

		$args = array(
			's' => 'Bacon Ipsum',
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
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
		$this->ep_factory->post->create(
			array(
				'post_content' => 'findme test 1',
				'post_type'    => 'page',
			)
		);
		$this->ep_factory->post->create( array( 'post_content' => 'findme test 2' ) );
		$this->ep_factory->post->create(
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

		$this->assertTrue( $query->elasticsearch_success );
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
		$this->ep_factory->post->create(
			array(
				'post_content' => 'findme test 1',
				'post_type'    => 'page',
			)
		);
		$this->ep_factory->post->create( array( 'post_content' => 'findme test 2' ) );
		$this->ep_factory->post->create(
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

		$this->assertTrue( $query->elasticsearch_success );
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
		$this->ep_factory->post->create(
			array(
				'post_content' => 'findme test 1',
				'post_type'    => 'page',
			)
		);
		$this->ep_factory->post->create( array( 'post_content' => 'findme test 2' ) );
		$this->ep_factory->post->create( array( 'post_content' => 'findme test 3' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		// post_type defaults to "any"
		$args = array(
			's' => 'findme',
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
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
		$this->ep_factory->post->create(
			array(
				'post_content' => 'findme test 1',
				'post_status'  => 'draft',
			)
		);
		$this->ep_factory->post->create( array( 'post_content' => 'findme test 2' ) );
		$this->ep_factory->post->create(
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

		$this->assertTrue( $query->elasticsearch_success );
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

		$this->ep_factory->post->create(
			array(
				'post_content' => 'findme test 1',
				'post_status'  => 'draft',
			)
		);
		$this->ep_factory->post->create( array( 'post_content' => 'findme test 2' ) );
		$this->ep_factory->post->create(
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

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );
	}

	/**
	 * Test a post status query for published or draft posts with 'draft' whitelisted as indexable status
	 *
	 * @since 2.1
	 * @group post
	 */
	public function testPostStatusQueryMulti() {
		add_filter( 'ep_indexable_post_status', array( $this, 'mock_indexable_post_status' ), 10, 1 );

		$this->ep_factory->post->create(
			array(
				'post_content' => 'findme test 1',
				'post_status'  => 'draft',
			)
		);
		$this->ep_factory->post->create( array( 'post_content' => 'findme test 2' ) );
		$this->ep_factory->post->create(
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

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 3, $query->post_count );
		$this->assertEquals( 3, $query->found_posts );
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

		$this->ep_factory->post->create(
			array(
				'post_content' => 'findme test 1',
				'post_type'    => 'attachment',
			)
		);
		$this->ep_factory->post->create( array( 'post_content' => 'findme test 2' ) );
		$this->ep_factory->post->create( array( 'post_content' => 'findme test 3' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		// post_type defaults to "any"
		$args = array(
			'post_type'    => 'attachment',
			'post_status'  => 'any',
			'ep_integrate' => true,
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, $query->found_posts );
	}

	/**
	 * Test a query with no post type on non-search query. Should default to `post` post type
	 *
	 * @since 1.3
	 * @group post
	 */
	public function testNoPostTypeNonSearchQuery() {
		$this->ep_factory->post->create(
			array(
				'post_content' => 'findme test 1',
				'post_type'    => 'page',
			)
		);
		$this->ep_factory->post->create( array( 'post_content' => 'findme test 2' ) );
		$this->ep_factory->post->create( array( 'post_content' => 'findme test 3' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		// post_type defaults to "any"
		$args = array(
			'ep_integrate' => true,
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
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
		$this->ep_factory->post->create(
			array(
				'post_content' => 'findme test 1',
				'post_type'    => 'page',
			)
		);
		$this->ep_factory->post->create( array( 'post_content' => 'findme test 2' ) );
		$this->ep_factory->post->create(
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

		$this->assertTrue( $query->elasticsearch_success );
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

		$this->ep_factory->post->create(
			array(
				'post_content' => 'post content',
				'meta_input'   => array( 'test_key' => $object ),
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();
		$args = array(
			'ep_integrate' => true,
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
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
		$this->ep_factory->post->create( array( 'post_content' => 'the post content' ) );
		$this->ep_factory->post->create( array( 'post_content' => 'the post content findme' ) );
		$this->ep_factory->post->create(
			array(
				'post_content' => 'post content',
				'meta_input'   => array( 'test_key' => 'findme' ),
			)
		);

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

		$this->assertTrue( $query->elasticsearch_success );
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

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, $query->found_posts );
	}

	/**
	 * Test a query that fuzzy searches taxonomy terms for the 3.4 algorithm.
	 *
	 * @since 1.0
	 * @group post
	 */
	public function testSearchTaxQuery() {
		// TODO write a new test to match the 3.5 functionality.
		add_filter( 'ep_search_algorithm_version', array( $this, 'set_algorithm_34' ) );

		$post_id_0 = $this->ep_factory->post->create( array( 'post_content' => 'the post content' ) );
		$post_id_1 = $this->ep_factory->post->create( array( 'post_content' => 'the post content findme' ) );
		$post_id_2 = $this->ep_factory->post->create(
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

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );

		$post_ids = wp_list_pluck( $query->posts, 'ID' );

		$this->assertContains( $post_id_1, $post_ids );
		$this->assertContains( $post_id_1, $post_ids );
		$this->assertNotContains( $post_id_0, $post_ids );
	}

	/**
	 * Test a fuzzy author name query
	 *
	 * @since 1.0
	 * @group post
	 */
	public function testSearchAuthorQuery() {

		add_filter( 'ep_search_algorithm_version', array( $this, 'set_algorithm_34' ) );

		$user_id = $this->factory->user->create(
			array(
				'user_login' => 'john',
				'role'       => 'administrator',
			)
		);

		$this->ep_factory->post->create( array( 'post_content' => 'findme test 1' ) );
		$this->ep_factory->post->create( array( 'post_content' => 'findme test 2' ) );
		$this->ep_factory->post->create(
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

		$this->assertTrue( $query->elasticsearch_success );
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

		$this->ep_factory->post->create( array( 'post_content' => '' ) );
		$this->ep_factory->post->create(
			array(
				'post_content' => 'findme',
				'post_type'    => 'ep_test',
			)
		);
		$this->ep_factory->post->create(
			array(
				'post_content' => 'findme',
				'post_type'    => 'ep_test',
				'tags_input'   => array( 'superterm' ),
			)
		);
		$this->ep_factory->post->create(
			array(
				'post_content' => 'findme',
				'post_type'    => 'ep_test',
				'tags_input'   => array( 'superterm' ),
				'post_author'  => $user_id,
			)
		);
		$this->ep_factory->post->create(
			array(
				'post_content' => 'findme',
				'post_type'    => 'ep_test',
				'tags_input'   => array( 'superterm' ),
				'post_author'  => $user_id,
				'meta_input'   => array( 'test_key' => 'meta value' ),
			)
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

		$this->assertTrue( $query->elasticsearch_success );
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
		$this->ep_factory->post->create( array( 'post_title' => 'ordertest 333' ) );
		$this->ep_factory->post->create( array( 'post_title' => 'ordertest 111' ) );
		$this->ep_factory->post->create( array( 'post_title' => 'Ordertest 222' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's'       => 'ordertest',
			'orderby' => 'title',
			'order'   => 'DESC',
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
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
		$this->ep_factory->post->create(
			array(
				'post_title' => 'ordertest 333',
				'meta_input' => array( 'test_key' => 'c' ),
			)
		);
		$this->ep_factory->post->create(
			array(
				'post_title' => 'Ordertest 222',
				'meta_input' => array( 'test_key' => 'B' ),
			)
		);
		$this->ep_factory->post->create(
			array(
				'post_title' => 'ordertest 111',
				'meta_input' => array( 'test_key' => 'a' ),
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's'       => 'ordertest',
			'orderby' => 'meta.test_key.value.sortable',
			'order'   => 'ASC',
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
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
		$this->ep_factory->post->create(
			array(
				'post_title' => 'ordertest 333',
				'meta_input' => array( 'test_key' => 'c' ),
			)
		);
		$this->ep_factory->post->create(
			array(
				'post_title' => 'Ordertest 222',
				'meta_input' => array( 'test_key' => 'B' ),
			)
		);
		$this->ep_factory->post->create(
			array(
				'post_title' => 'ordertest 111',
				'meta_input' => array( 'test_key' => 'a' ),
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's'       => 'ordertest',
			'orderby' => array( 'meta.test_key.value.sortable' ),
			'order'   => 'ASC',
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
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
		$this->ep_factory->post->create(
			array(
				'post_title' => 'ordertest 333',
				'meta_input' => array(
					'test_key'  => 'c',
					'test_key2' => 'c',
				),
			)
		);
		$this->ep_factory->post->create(
			array(
				'post_title' => 'ordertest 222',
				'meta_input' => array(
					'test_key'  => 'f',
					'test_key2' => 'c',
				),
			)
		);
		$this->ep_factory->post->create(
			array(
				'post_title' => 'ordertest 111',
				'meta_input' => array(
					'test_key'  => 'd',
					'test_key2' => 'd',
				),
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's'       => 'ordertest',
			'orderby' => array( 'meta.test_key.value.sortable' => 'asc' ),
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
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

		$this->ep_factory->post->create(
			array(
				'post_title'  => 'findme test 1',
				'post_author' => $al,
			)
		);
		$this->ep_factory->post->create(
			array(
				'post_title'  => 'findme test 2',
				'post_author' => $bob,
			)
		);
		$this->ep_factory->post->create(
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

		$this->assertTrue( $query->elasticsearch_success );

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

		$this->ep_factory->post->create(
			array(
				'post_title'  => 'findme test 1',
				'post_author' => $al,
			)
		);
		$this->ep_factory->post->create(
			array(
				'post_title'  => 'findme test 2',
				'post_author' => $bob,
			)
		);
		$this->ep_factory->post->create(
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

		$this->assertTrue( $query->elasticsearch_success );

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
		$this->ep_factory->post->create(
			array(
				'post_title' => 'ordertest 333',
				'meta_input' => array( 'test_key' => 3 ),
			)
		);
		$this->ep_factory->post->create(
			array(
				'post_title' => 'ordertest 444',
				'meta_input' => array( 'test_key' => 4 ),
			)
		);
		$this->ep_factory->post->create(
			array(
				'post_title' => 'Ordertest 222',
				'meta_input' => array( 'test_key' => 2 ),
			)
		);
		$this->ep_factory->post->create(
			array(
				'post_title' => 'ordertest 111',
				'meta_input' => array( 'test_key' => 1 ),
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's'       => 'ordertest',
			'orderby' => 'meta.test_key.long',
			'order'   => 'ASC',
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
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

		$this->ep_factory->post->create(
			array(
				'post_title'    => 'ordertest 333',
				'post_category' => array( $cat4 ),
			)
		);
		$this->ep_factory->post->create(
			array(
				'post_title'    => 'ordertest 444',
				'post_category' => array( $cat1 ),
			)
		);
		$this->ep_factory->post->create(
			array(
				'post_title'    => 'Ordertest 222',
				'post_category' => array( $cat3 ),
			)
		);
		$this->ep_factory->post->create(
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

		$this->assertTrue( $query->elasticsearch_success );
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
		$this->ep_factory->post->create(
			array(
				'post_title' => 'ordertest 333',
				'meta_input' => array( 'test_key' => 3 ),
			)
		);
		$this->ep_factory->post->create(
			array(
				'post_title' => 'ordertest 444',
				'meta_input' => array( 'test_key' => 4 ),
			)
		);
		$this->ep_factory->post->create(
			array(
				'post_title' => 'Ordertest 222',
				'meta_input' => array( 'test_key' => 2 ),
			)
		);
		$this->ep_factory->post->create(
			array(
				'post_title' => 'ordertest 111',
				'meta_input' => array( 'test_key' => 1 ),
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's'       => 'ordertest',
			'orderby' => 'meta.test_key.long',
			'order'   => 'DESC',
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
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
		$this->ep_factory->post->create(
			array(
				'post_title' => 'ordertest 444',
				'meta_input' => array(
					'test_key'  => 3,
					'test_key2' => 2,
				),
			)
		);
		$this->ep_factory->post->create(
			array(
				'post_title' => 'ordertest 333',
				'meta_input' => array(
					'test_key'  => 3,
					'test_key2' => 1,
				),
			)
		);
		$this->ep_factory->post->create(
			array(
				'post_title' => 'Ordertest 222',
				'meta_input' => array(
					'test_key'  => 2,
					'test_key2' => 1,
				),
			)
		);
		$this->ep_factory->post->create(
			array(
				'post_title' => 'ordertest 111',
				'meta_input' => array(
					'test_key'  => 1,
					'test_key2' => 1,
				),
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's'       => 'ordertest',
			'orderby' => 'meta.test_key.long meta.test_key2.long',
			'order'   => 'ASC',
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
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

		add_filter( 'ep_search_algorithm_version', array( $this, 'set_algorithm_34' ) );

		$this->ep_factory->post->create( array( 'post_title' => 'ordertesr' ) );
		sleep( 3 );

		$this->ep_factory->post->create( array( 'post_title' => 'ordertest 111' ) );
		sleep( 3 );

		$this->ep_factory->post->create( array( 'post_title' => 'Ordertest 222' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's'       => 'ordertest',
			'orderby' => 'date',
			'order'   => 'DESC',
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
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
		$this->ep_factory->post->create( array( 'post_title' => 'ordertest 333' ) );
		sleep( 3 );

		$this->ep_factory->post->create( array( 'post_title' => 'ordertest ordertest order test 111' ) );
		sleep( 3 );

		$this->ep_factory->post->create( array( 'post_title' => 'Ordertest 222' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			'ep_integrate' => true,
			'order'        => 'desc',
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
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

		add_filter( 'ep_search_algorithm_version', array( $this, 'set_algorithm_34' ) );

		$posts = array();

		$posts[5] = $this->ep_factory->post->create( array( 'post_title' => 'ordertet with even more lorem ipsum to make a longer field' ) );

		$posts[2] = $this->ep_factory->post->create( array( 'post_title' => 'ordertest ordertet lorem ipsum' ) );

		$this->ep_factory->post->create( array( 'post_title' => 'Lorem ipsum' ) );

		$posts[4] = $this->ep_factory->post->create( array( 'post_title' => 'ordertet with some lorem ipsum' ) );

		$posts[1] = $this->ep_factory->post->create( array( 'post_title' => 'ordertest ordertest lorem ipsum' ) );

		$this->ep_factory->post->create(
			array(
				'post_title'   => 'Lorem ipsum',
				'post_content' => 'Some post content filler text.',
			)
		);

		$posts[3] = $this->ep_factory->post->create( array( 'post_title' => 'ordertet ordertet lorem ipsum' ) );

		$posts[0] = $this->ep_factory->post->create( array( 'post_title' => 'Ordertest ordertest ordertest' ) );

		$this->ep_factory->post->create( array( 'post_title' => 'Lorem ipsum' ) );

		$this->ep_factory->post->create( array( 'post_title' => 'Lorem ipsum' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's'       => 'ordertest',
			'orderby' => 'relevance',
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
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

		add_filter( 'ep_search_algorithm_version', array( $this, 'set_algorithm_34' ) );

		$this->ep_factory->post->create();
		$this->ep_factory->post->create( array( 'post_title' => 'ordertet' ) );
		$this->ep_factory->post->create( array( 'post_title' => 'ordertest' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's'       => 'ordertest',
			'orderby' => 'relevance',
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
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
		$this->ep_factory->post->create( array( 'post_title' => 'postname-ordertest-333' ) );
		$this->ep_factory->post->create( array( 'post_title' => 'postname-ordertest-111' ) );
		$this->ep_factory->post->create( array( 'post_title' => 'postname-Ordertest-222' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's'       => 'postname ordertest',
			'orderby' => 'name',
			'order'   => 'ASC',
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
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

		add_filter( 'ep_search_algorithm_version', array( $this, 'set_algorithm_34' ) );

		$this->ep_factory->post->create();
		$this->ep_factory->post->create( array( 'post_title' => 'Ordertet' ) );
		$this->ep_factory->post->create( array( 'post_title' => 'ordertest' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's' => 'ordertest',
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
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

		add_filter( 'ep_search_algorithm_version', array( $this, 'set_algorithm_34' ) );

		$this->ep_factory->post->create();
		$this->ep_factory->post->create( array( 'post_title' => 'Ordertest' ) );
		$this->ep_factory->post->create( array( 'post_title' => 'ordertestt' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's'     => 'ordertest',
			'order' => 'ASC',
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
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
		$this->ep_factory->post->create( array( 'post_title' => 'ordertest 1' ) );
		$this->ep_factory->post->create( array( 'post_title' => 'ordertest 2' ) );
		$this->ep_factory->post->create( array( 'post_title' => 'ordertest 3' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			'ep_integrate' => true,
			'orderby'      => 'rand',
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );

		/**
		 * Since it's test for random order, can't check against exact post ID or content
		 * but only found posts and post count.
		 */
		$this->assertEquals( 3, $query->post_count );
		$this->assertEquals( 3, $query->found_posts );
	}

	/**
	 * Test orderby 'none'
	 *
	 * In this case, EP should order by ID ASC, as this is the behavior used by the database.
	 *
	 * @since 4.5.0
	 * @group post
	 */
	public function testNoneOrderbyQuery() {
		$posts   = [];
		$posts[] = $this->ep_factory->post->create( array( 'post_title' => 'ordertest 1' ) );
		$posts[] = $this->ep_factory->post->create( array( 'post_title' => 'ordertest 2' ) );
		$posts[] = $this->ep_factory->post->create( array( 'post_title' => 'ordertest 3' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			'ep_integrate' => true,
			'fields'       => 'ids',
			'orderby'      => 'none',
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );

		$this->assertEquals( 3, $query->post_count );
		$this->assertEquals( $posts, $query->posts );
	}

	/**
	 * Test ordering by named meta_query clauses
	 *
	 * @since 4.6.0
	 * @group post
	 */
	public function testNamedMetaQueryOrderbyQuery() {
		$post_b = $this->ep_factory->post->create( [ 'meta_input' => [ 'test_key' => 'b' ] ] );
		$post_a = $this->ep_factory->post->create( [ 'meta_input' => [ 'test_key' => 'a' ] ] );
		$post_c = $this->ep_factory->post->create( [ 'meta_input' => [ 'test_key' => 'c' ] ] );
		$this->ep_factory->post->create( [ 'post_title' => 'No meta_input' ] );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			'ep_integrate' => true,
			'fields'       => 'ids',
			'meta_query'   => [
				'named_clause' => [
					'key'     => 'test_key',
					'compare' => 'EXISTS',
				],
			],
			'orderby'      => 'named_clause',
			'order'        => 'ASC',
		);

		$query = new \WP_Query( $args );
		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 3, $query->post_count );
		$this->assertEquals( $post_a, $query->posts[0] );
		$this->assertEquals( $post_b, $query->posts[1] );
		$this->assertEquals( $post_c, $query->posts[2] );
	}

	/**
	 * Test that a post being directly deleted gets correctly removed from the Elasticsearch index
	 *
	 * @since 1.2
	 * @group post
	 */
	public function testPostForceDelete() {
		add_action( 'ep_delete_post', array( $this, 'action_delete_post' ), 10, 0 );
		$post_id = $this->ep_factory->post->create();

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
	 * Test that empty search string returns all results
	 *
	 * @since 1.2
	 * @group post
	 */
	public function testEmptySearchString() {
		$this->ep_factory->post->create();
		$this->ep_factory->post->create();

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			'ep_integrate' => true,
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
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
		$this->ep_factory->post->create( array( 'post_content' => 'the post content' ) );
		$this->ep_factory->post->create( array( 'post_content' => 'the post content findme' ) );
		$this->ep_factory->post->create(
			array(
				'post_content' => 'post content findme',
				'meta_input'   => array( 'test_key' => 'value' ),
			)
		);

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

		$this->assertTrue( $query->elasticsearch_success );
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
		$this->ep_factory->post->create( array( 'post_content' => 'the post content findme' ) );
		$this->ep_factory->post->create( array( 'post_content' => 'the post content findme' ) );
		$this->ep_factory->post->create( array( 'post_content' => 'post content findme' ), array( 'test_key' => 'value' ) );

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

		$this->assertTrue( $query->elasticsearch_success );
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
		$this->ep_factory->post->create( array( 'post_content' => 'the post content findme' ) );
		$this->ep_factory->post->create( array( 'post_content' => 'the post content findme' ) );
		$this->ep_factory->post->create(
			array(
				'post_content' => 'post content findme',
				'meta_input'   => array( 'test_key' => 'value' ),
			)
		);

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

		$this->assertTrue( $query->elasticsearch_success );
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
		$this->ep_factory->post->create( array( 'post_content' => 'the post content findme' ) );
		$this->ep_factory->post->create( array( 'post_content' => 'the post content findme' ) );
		$this->ep_factory->post->create( array( 'post_content' => 'post content findme' ), array( 'test_key' => 'value' ) );

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

		$this->assertTrue( $query->elasticsearch_success );
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
		$this->ep_factory->post->create( array( 'post_content' => 'the post content findme' ) );
		$this->ep_factory->post->create( array( 'post_content' => 'the post content findme' ) );
		$this->ep_factory->post->create(
			array(
				'post_content' => 'post content findme',
				'meta_input'   => array( 'test_key' => '100' ),
			)
		);
		$this->ep_factory->post->create(
			array(
				'post_content' => 'post content findme',
				'meta_input'   => array( 'test_key' => '101' ),
			)
		);

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

		$this->assertTrue( $query->elasticsearch_success );
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
		$this->ep_factory->post->create( array( 'post_content' => 'the post content findme' ) );
		$this->ep_factory->post->create( array( 'post_content' => 'the post content findme' ) );
		$this->ep_factory->post->create(
			array(
				'post_content' => 'post content findme',
				'meta_input'   => array( 'test_key' => '100' ),
			)
		);
		$this->ep_factory->post->create(
			array(
				'post_content' => 'post content findme',
				'meta_input'   => array( 'test_key' => '105' ),
			)
		);
		$this->ep_factory->post->create(
			array(
				'post_content' => 'post content findme',
				'meta_input'   => array( 'test_key' => '110' ),
			)
		);

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

		$this->assertTrue( $query->elasticsearch_success );
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
		$this->ep_factory->post->create( array( 'post_content' => 'the post content findme' ) );
		$this->ep_factory->post->create( array( 'post_content' => 'the post content findme' ) );
		$this->ep_factory->post->create(
			array(
				'post_content' => 'post content findme',
				'meta_input'   => array( 'test_key' => '100' ),
			)
		);
		$this->ep_factory->post->create(
			array(
				'post_content' => 'post content findme',
				'meta_input'   => array( 'test_key' => '101' ),
			)
		);

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

		$this->assertTrue( $query->elasticsearch_success );
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
		$this->ep_factory->post->create( array( 'post_content' => 'the post content findme' ) );
		$this->ep_factory->post->create( array( 'post_content' => 'the post content findme' ) );
		$this->ep_factory->post->create(
			array(
				'post_content' => 'post content findme',
				'meta_input'   => array( 'test_key' => '100' ),
			)
		);
		$this->ep_factory->post->create(
			array(
				'post_content' => 'post content findme',
				'meta_input'   => array( 'test_key' => '101' ),
			)
		);

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

		$this->assertTrue( $query->elasticsearch_success );
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
		$this->ep_factory->post->create( array( 'post_content' => 'the post content findme' ) );
		$this->ep_factory->post->create( array( 'post_content' => 'the post content findme' ) );
		$this->ep_factory->post->create(
			array(
				'post_content' => 'post content findme',
				'meta_input'   => array( 'test_key' => '100' ),
			)
		);
		$this->ep_factory->post->create(
			array(
				'post_content' => 'post content findme',
				'meta_input'   => array( 'test_key' => '101' ),
			)
		);

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

		$this->assertTrue( $query->elasticsearch_success );
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
		$this->ep_factory->post->create(
			array(
				'post_content' => 'the post content findme',
				'meta_input'   => array( 'test_key5' => 'value1' ),
			)
		);
		$this->ep_factory->post->create(
			array(
				'post_content' => 'the post content findme',
				'meta_input'   => array(
					'test_key'  => 'value1',
					'test_key2' => 'value',
				),
			)
		);
		$this->ep_factory->post->create(
			array(
				'post_content' => 'post content findme',
				'meta_input'   => array(
					'test_key6' => 'value',
					'test_key2' => 'value2',
					'test_key3' => 'value',
				),
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

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );
	}

	/**
	 * Test an advanced meta filter query with or relation while sorting by Meta key
	 *
	 * @since 4.4.0
	 * @group post
	 */
	public function testMetaQueryOrRelationWithSort() {
		$this->ep_factory->post->create(
			array(
				'post_content' => 'the post content findme',
				'meta_input'   => array( 'test_key' => gmdate( 'Ymd' ) - 5 ),
			)
		);
		$this->ep_factory->post->create(
			array(
				'post_content' => 'the post content findme',
				'meta_input'   => array(
					'test_key'  => gmdate( 'Ymd' ) + 5,
					'test_key2' => gmdate( 'Ymd' ) + 6,
				),
			)
		);
		$this->ep_factory->post->create(
			array(
				'post_content' => 'the post content findme',
				'meta_input'   => array(
					'test_key'  => gmdate( 'Ymd' ) + 5,
					'test_key2' => gmdate( 'Ymd' ) + 6,
				),
			)
		);

		$post = new \ElasticPress\Indexable\Post\Post();
		ElasticPress\Elasticsearch::factory()->refresh_indices();
		$args = array(
			'ep_integrate' => true,
			'meta_key'     => 'test_key',
			'meta_query'   => array(
				'relation' => 'or',
				array(
					'key'     => 'test_key',
					'value'   => gmdate( 'Ymd' ),
					'compare' => '<=',
					'type'    => 'NUMERIC',
				),
				array(
					'key'     => 'test_key2',
					'value'   => gmdate( 'Ymd' ),
					'compare' => '>=',
					'type'    => 'NUMERIC',
				),
			),
			'orderby'      => 'meta_value_num',
			'order'        => 'ASC',
		);

		$query = new \WP_Query( $args );
		$args  = $post->format_args( $args, new \WP_Query() );

		$outer_must = $args['post_filter']['bool']['must'][0]['bool']['must'];

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 3, $query->post_count );
		$this->assertEquals( 3, $query->found_posts );
		$this->assertSame( 'meta.test_key', $outer_must[0]['exists']['field'] );
		$this->assertArrayHasKey( 'meta.test_key.long', $outer_must[1]['bool']['should']['bool']['should'][0]['bool']['must'][0]['range'] );
		$this->assertArrayHasKey( 'meta.test_key2.long', $outer_must[1]['bool']['should']['bool']['should'][1]['bool']['must'][0]['range'] );
	}

	/**
	 * Test an advanced meta filter query
	 *
	 * @since 1.3
	 * @group post
	 */
	public function testMetaQueryAdvanced() {
		$this->ep_factory->post->create( array( 'post_content' => 'the post content findme' ), array( 'test_key' => 'value1' ) );
		$this->ep_factory->post->create(
			array(
				'post_content' => 'the post content findme',
				'meta_input'   => array(
					'test_key'  => 'value1',
					'test_key2' => 'value',
				),
			)
		);
		$this->ep_factory->post->create(
			array(
				'post_content' => 'post content findme',
				'meta_input'   => array(
					'test_key'  => 'value',
					'test_key2' => 'value2',
					'test_key3' => 'value',
				),
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

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, $query->found_posts );
	}

	/**
	 * Test the sanitization of an empty meta query
	 *
	 * @since 4.5.0
	 * @group post
	 */
	public function testMetaQueryEmptySanitization() {
		$this->ep_factory->post->create(
			array(
				'post_content' => 'the post content findme',
				'meta_input'   => array(
					'test_key'  => 'value1',
					'test_key2' => 'value',
				),
			)
		);
		$this->ep_factory->post->create(
			array(
				'post_content' => 'post content findme',
				'meta_input'   => array(
					'test_key'  => 'value',
					'test_key2' => 'value2',
					'test_key3' => 'value',
				),
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();
		$args = array(
			's'          => 'findme',
			'meta_query' => array(
				0 => array(),
			),
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );
	}

	/**
	 * Test a query that searches and filters by a meta value like the query
	 *
	 * @since 1.5
	 * @group post
	 */
	public function testMetaQueryLike() {
		$this->ep_factory->post->create( array( 'post_content' => 'the post content findme' ) );
		$this->ep_factory->post->create( array( 'post_content' => 'the post content findme' ) );
		$this->ep_factory->post->create(
			array(
				'post_content' => 'post content findme',
				'meta_input'   => array( 'test_key' => 'ALICE in wonderland' ),
			)
		);
		$this->ep_factory->post->create(
			array(
				'post_content' => 'post content findme',
				'meta_input'   => array( 'test_key' => 'alice in melbourne' ),
			)
		);
		$this->ep_factory->post->create(
			array(
				'post_content' => 'post content findme',
				'meta_input'   => array( 'test_key' => 'AlicE in america' ),
			)
		);

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

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertTrue( isset( $query->posts[0]->elasticsearch ) );
		$this->assertEquals( 3, $query->post_count );
		$this->assertEquals( 3, $query->found_posts );
	}

	/**
	 * Test a query that searches and filters by a meta value using NOT LIKE operator
	 *
	 * @since 3.6.0
	 * @group post
	 */
	public function testMetaQueryNotLike() {
		$this->ep_factory->post->create(
			array(
				'post_content' => 'post content findme',
				'meta_input'   => array( 'test_key' => 'ALICE in wonderland' ),
			)
		);
		$this->ep_factory->post->create(
			array(
				'post_content' => 'post content findme',
				'meta_input'   => array( 'test_key' => 'alice in melbourne' ),
			)
		);
		$this->ep_factory->post->create(
			array(
				'post_content' => 'post content findme',
				'meta_input'   => array( 'test_key' => 'AlicE in america' ),
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();
		$args = array(
			's'          => 'findme',
			'meta_query' => array(
				array(
					'key'     => 'test_key',
					'value'   => 'melbourne',
					'compare' => 'NOT LIKE',
				),
			),
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertTrue( isset( $query->posts[0]->elasticsearch ) );
		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );
	}

	/**
	 * Test meta queries with multiple keys
	 */
	public function testMetaQueryMultipleArray() {
		$this->ep_factory->post->create(
			array(
				'post_content' => 'findme',
				'meta_input'   => array( 'test_key1' => '1' ),
			)
		);
		$this->ep_factory->post->create(
			array(
				'post_content' => 'findme',
				'meta_input'   => array( 'test_key1' => '1' ),
			)
		);
		$this->ep_factory->post->create(
			array(
				'post_content' => 'findme',
				'meta_input'   => array(
					'test_key1' => '1',
					'test_key2' => '4',
				),
			)
		);
		$this->ep_factory->post->create(
			array(
				'post_content' => 'findme',
				'meta_input'   => array(
					'test_key1' => '1',
					'test_key2' => '0',
				),
			)
		);
		$this->ep_factory->post->create(
			array(
				'post_content' => 'findme',
				'meta_input'   => array(
					'test_key1' => '1',
					'test_key3' => '4',
				),
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's'          => 'findme',
			'meta_query' => array(
				array(
					'key'     => 'test_key2',
					'value'   => '0',
					'compare' => '>=',
				),
			),
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );

		$args = array(
			's'          => 'findme',
			'meta_query' => array(
				'relation' => 'AND',
				array(
					'key'   => 'test_key1',
					'value' => '1',
				),
				array(
					'relation' => 'OR',
					array(
						'key'     => 'test_key2',
						'value'   => '2',
						'compare' => '>=',
					),
					array(
						'key'   => 'test_key3',
						'value' => '4',
					),
				),
			),
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
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

		$post_ids[0] = $this->ep_factory->post->create();
		$post_ids[1] = $this->ep_factory->post->create();
		$post_ids[2] = $this->ep_factory->post->create( array( 'post_content' => 'findme' ) );
		$post_ids[3] = $this->ep_factory->post->create();
		$post_ids[4] = $this->ep_factory->post->create( array( 'post_content' => 'findme' ) );

		register_post_type(
			'exclude-me',
			array(
				'public'              => true,
				'exclude_from_search' => true,
			)
		);

		$post_ids[5] = $this->ep_factory->post->create(
			array(
				'post_type'    => 'exclude-me',
				'post_content' => 'findme',
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's' => 'findme',
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );

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

		$post_ids[0] = $this->ep_factory->post->create();
		$post_ids[1] = $this->ep_factory->post->create();
		$post_ids[2] = $this->ep_factory->post->create( array( 'post_content' => 'findme' ) );
		$post_ids[3] = $this->ep_factory->post->create();
		$post_ids[4] = $this->ep_factory->post->create( array( 'post_content' => 'findme' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's' => 'findme',
		);

		$num_queries = $GLOBALS['wpdb']->num_queries;

		$query = new \WP_Query( $args );

		$this->assertSame( $num_queries, $GLOBALS['wpdb']->num_queries );
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
		$this->ep_factory->post->create();

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			'ep_integrate' => true,
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertFalse( $query->query_vars['cache_results'] );
	}

	/**
	 * Test cache_results can be turned on
	 *
	 * @since 1.5
	 * @group post
	 */
	public function testCacheResultsOn() {
		$this->ep_factory->post->create();

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			'ep_integrate'  => true,
			'cache_results' => true,
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertTrue( $query->query_vars['cache_results'] );
	}

	/**
	 * Test using cache_results actually populates the cache
	 *
	 * @since 1.5
	 * @group post
	 */
	public function testCachedResultIsInCache() {
		$this->ep_factory->post->create();

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		wp_cache_flush();

		$args = array(
			'ep_integrate'  => true,
			'cache_results' => true,
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );

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
		$this->ep_factory->post->create();

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		wp_cache_flush();

		$args = array(
			'ep_integrate' => true,
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );

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
		$post_id = $this->ep_factory->post->create( array( 'post_status' => 'draft' ) );

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

		$post_id     = $this->ep_factory->post->create();
		$post        = get_post( $post_id );
		$meta_values = array(
			'value 1',
			'value 2',
		);

		add_post_meta( $post_id, 'test_key1', 'value 1' );
		add_post_meta( $post_id, 'test_key1', 'value 2' );
		add_post_meta( $post_id, 'test_key1', $meta_values );
		add_post_meta( $post_id, '_test_private_meta_1', 'value 1' );
		add_post_meta( $post_id, '_test_private_meta_1', 'value 2' );
		add_post_meta( $post_id, '_test_private_meta_1', $meta_values );

		$meta_1 = ElasticPress\Indexables::factory()->get( 'post' )->prepare_meta( $post );

		add_filter( 'ep_prepare_meta_allowed_protected_keys', array( $this, 'filter_ep_prepare_meta_allowed_protected_keys' ) );

		$meta_2 = ElasticPress\Indexables::factory()->get( 'post' )->prepare_meta( $post );

		add_filter(
			'ep_meta_mode',
			function () {
				return 'auto';
			}
		);
		add_filter( 'ep_prepare_meta_excluded_public_keys', array( $this, 'filter_ep_prepare_meta_excluded_public_keys' ) );

		$meta_3 = ElasticPress\Indexables::factory()->get( 'post' )->prepare_meta( $post );

		$this->assertTrue( is_array( $meta_1 ) && 1 === count( $meta_1 ) );
		$this->assertTrue( is_array( $meta_1 ) && array_key_exists( 'test_key1', $meta_1 ) );
		$this->assertTrue( is_array( $meta_2 ) && 2 === count( $meta_2 ) );
		$this->assertTrue( is_array( $meta_2 ) && array_key_exists( 'test_key1', $meta_2 ) && array_key_exists( '_test_private_meta_1', $meta_2 ) );
		$this->assertTrue( is_array( $meta_3 ) && 1 === count( $meta_3 ) );
		$this->assertTrue( is_array( $meta_3 ) && array_key_exists( '_test_private_meta_1', $meta_3 ) );
	}

	/**
	 * Test to verify meta array is built correctly when meta handling is set as "Manual" in the weighting dashboard.
	 *
	 * @since 5.0.0
	 * @group post
	 */
	public function testPrepareMetaManual() {
		if ( $this->is_network_activate() ) {
			$this->markTestSkipped();
		}

		$change_meta_mode = function() {
			return 'manual';
		};
		add_filter( 'ep_meta_mode', $change_meta_mode );

		$weighting = ElasticPress\Features::factory()->get_registered_feature( 'search' )->weighting;
		$this->assertSame( $weighting->get_meta_mode(), 'manual' );

		// Set default weighting
		$weighting_default = $weighting->get_weighting_configuration_with_defaults();

		$set_default_weighting = function() use ( $weighting_default ) {
			return $weighting_default;
		};

		add_filter( 'ep_weighting_configuration', $set_default_weighting );

		$post_id = $this->ep_factory->post->create(
			[
				'meta_input' => [
					'not_allowed_key1'     => 'value 1',
					'not_allowed_key2'     => 'value 2',
					'_test_private_meta_1' => 'private value 1',
					'_test_private_meta_2' => 'private value 2',
				],
			]
		);

		$post = get_post( $post_id );

		$prepared_meta = ElasticPress\Indexables::factory()->get( 'post' )->prepare_meta( $post );
		$this->assertEmpty( $prepared_meta );

		/**
		 * Test addition via the ep_prepare_meta_allowed_protected_keys filter.
		 */
		$add_meta_via_allowed_protected = function( $fields, $post ) {
			$this->assertInstanceOf( '\WP_Post', $post );
			$this->assertIsArray( $fields );
			return [ '_test_private_meta_1' ];
		};
		add_filter( 'ep_prepare_meta_allowed_protected_keys', $add_meta_via_allowed_protected, 10, 2 );

		$prepared_meta = ElasticPress\Indexables::factory()->get( 'post' )->prepare_meta( $post );
		$this->assertSame( [ '_test_private_meta_1' ], array_keys( $prepared_meta ) );

		/**
		 * Test addition via the ep_prepare_meta_allowed_keys filter.
		 */
		$add_meta_via_allowed = function( $fields, $post ) {
			$this->assertInstanceOf( '\WP_Post', $post );
			$this->assertIsArray( $fields );

			$fields[] = 'not_allowed_key1';
			return $fields;
		};
		add_filter( 'ep_prepare_meta_allowed_keys', $add_meta_via_allowed, 10, 2 );

		$prepared_meta = ElasticPress\Indexables::factory()->get( 'post' )->prepare_meta( $post );
		$this->assertSame( [ 'not_allowed_key1', '_test_private_meta_1' ], array_keys( $prepared_meta ) );

		// Set changed weighting
		remove_filter( 'ep_weighting_configuration', $set_default_weighting );
		$set_changed_weighting = function() use ( $weighting_default ) {
			$weighting_default['post']['meta.test_key2.value']            = [
				'enabled' => true,
				'weight'  => 1,
			];
			$weighting_default['post']['meta._test_private_meta_2.value'] = [
				'enabled' => true,
				'weight'  => 1,
			];
			return $weighting_default;
		};
		add_filter( 'ep_weighting_configuration', $set_changed_weighting );

		$prepared_meta = ElasticPress\Indexables::factory()->get( 'post' )->prepare_meta( $post );
		$this->assertSame(
			[ 'not_allowed_key1', '_test_private_meta_1', '_test_private_meta_2' ],
			array_keys( $prepared_meta )
		);
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

		$meta_keys[] = 'test_key1';

		return $meta_keys;

	}

	/**
	 * Test to verify that empty meta key should be excluded before sync.
	 *
	 * @since 4.6.1
	 * @group post
	 */
	public function testEmptyMetaKey() {
		global $wpdb;
		$post_id      = $this->ep_factory->post->create();
		$post         = get_post( $post_id );
		$meta_key     = '';
		$meta_value_1 = 'Meta value for empty key';
		$meta_values  = array(
			'value 1',
			'value 2',
		);
		add_post_meta( $post_id, 'test_key1', $meta_values );

		$wpdb->insert(
			$wpdb->postmeta,
			array(
				'post_id'    => $post_id,
				'meta_key'   => $meta_key,
				'meta_value' => $meta_value_1,
			),
			array( '%d', '%s', '%s' )
		);
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->postmeta WHERE meta_key=%s AND post_id = %d", $meta_key, $post_id ) );

		$this->assertSame( $meta_key, $row->meta_key );
		$this->assertSame( $meta_value_1, $row->meta_value );

		$meta_data = ElasticPress\Indexables::factory()->get( 'post' )->prepare_meta( $post );

		$this->assertIsArray( $meta_data );
		$this->assertCount( 1, $meta_data );
		$this->assertArrayHasKey( 'test_key1', $meta_data );
	}

	/**
	 * Test meta preparation
	 *
	 * @since 1.7
	 * @group post
	 */
	public function testMetaValueTypes() {

		$intval            = ElasticPress\Indexables::factory()->get( 'post' )->prepare_meta_value_types( 13 );
		$floatval          = ElasticPress\Indexables::factory()->get( 'post' )->prepare_meta_value_types( 13.43 );
		$textval           = ElasticPress\Indexables::factory()->get( 'post' )->prepare_meta_value_types( 'some text' );
		$float_string      = ElasticPress\Indexables::factory()->get( 'post' )->prepare_meta_value_types( '20.000000' );
		$bool_false_val    = ElasticPress\Indexables::factory()->get( 'post' )->prepare_meta_value_types( false );
		$bool_true_val     = ElasticPress\Indexables::factory()->get( 'post' )->prepare_meta_value_types( true );
		$dateval           = ElasticPress\Indexables::factory()->get( 'post' )->prepare_meta_value_types( '2015-01-01' );
		$recognizable_time = ElasticPress\Indexables::factory()->get( 'post' )->prepare_meta_value_types( 'third monday of January 2020' );
		$relative_format   = ElasticPress\Indexables::factory()->get( 'post' )->prepare_meta_value_types( '+1 year' );

		$this->assertTrue( is_array( $intval ) && 5 === count( $intval ) );
		$this->assertTrue( is_array( $intval ) && array_key_exists( 'long', $intval ) && 13 === $intval['long'] );
		$this->assertTrue( is_array( $floatval ) && 5 === count( $floatval ) );
		$this->assertTrue( is_array( $floatval ) && array_key_exists( 'double', $floatval ) && 13.43 === $floatval['double'] );
		$this->assertTrue( is_array( $textval ) && 6 === count( $textval ) );
		$this->assertTrue( is_array( $textval ) && array_key_exists( 'raw', $textval ) && 'some text' === $textval['raw'] );
		$this->assertTrue( is_array( $float_string ) && 8 === count( $float_string ) );
		$this->assertTrue( is_array( $float_string ) && array_key_exists( 'raw', $float_string ) && '20.000000' === $float_string['raw'] );
		$this->assertTrue( is_array( $bool_false_val ) && 3 === count( $bool_false_val ) );
		$this->assertTrue( is_array( $bool_false_val ) && array_key_exists( 'boolean', $bool_false_val ) && false === $bool_false_val['boolean'] );
		$this->assertTrue( is_array( $bool_true_val ) && 3 === count( $bool_true_val ) );
		$this->assertTrue( is_array( $bool_true_val ) && array_key_exists( 'boolean', $bool_true_val ) && true === $bool_true_val['boolean'] );
		$this->assertTrue( is_array( $dateval ) && 6 === count( $dateval ) );
		$this->assertTrue( is_array( $dateval ) && array_key_exists( 'datetime', $dateval ) && '2015-01-01 00:00:00' === $dateval['datetime'] );
		$this->assertTrue( is_array( $recognizable_time ) && 6 === count( $recognizable_time ) );
		$this->assertTrue( is_array( $recognizable_time ) && array_key_exists( 'datetime', $recognizable_time ) && '2020-01-20 00:00:00' === $recognizable_time['datetime'] );
		$this->assertTrue( is_array( $relative_format ) && 6 === count( $relative_format ) );
		$this->assertTrue( is_array( $relative_format ) && array_key_exists( 'datetime', $relative_format ) && gmdate( 'Y-m-d H:i:s', strtotime( '+1 year' ) ) === $relative_format['datetime'] );

	}

	/**
	 * Test meta date preparation
	 *
	 * @group post
	 */
	public function testMetaValueTypeDate() {
		$meta_types = array();

		$default_date_time = array(
			'date'     => '1970-01-01',
			'datetime' => '1970-01-01 00:00:01',
			'time'     => '00:00:01',
		);

		// Invalid dates
		$textval        = ElasticPress\Indexables::factory()->get( 'post' )->prepare_date_meta_values( $meta_types, 'some text' );
		$k20_string     = ElasticPress\Indexables::factory()->get( 'post' )->prepare_date_meta_values( $meta_types, '20.000000' );
		$bool_false_val = ElasticPress\Indexables::factory()->get( 'post' )->prepare_date_meta_values( $meta_types, false );
		$bool_true_val  = ElasticPress\Indexables::factory()->get( 'post' )->prepare_date_meta_values( $meta_types, true );

		$this->assertEquals( $default_date_time, $textval );
		$this->assertEquals( $default_date_time, $k20_string );
		$this->assertEmpty( $bool_false_val );
		$this->assertEmpty( $bool_true_val );

		// Valid dates
		$intval            = ElasticPress\Indexables::factory()->get( 'post' )->prepare_date_meta_values( $meta_types, time() );
		$floatval          = ElasticPress\Indexables::factory()->get( 'post' )->prepare_date_meta_values( $meta_types, 13.43 );
		$float_string      = ElasticPress\Indexables::factory()->get( 'post' )->prepare_date_meta_values( $meta_types, '20.000001' );
		$dateval           = ElasticPress\Indexables::factory()->get( 'post' )->prepare_date_meta_values( $meta_types, '2015-01-01' );
		$recognizable_time = ElasticPress\Indexables::factory()->get( 'post' )->prepare_date_meta_values( $meta_types, 'third day of January 2020' );
		$relative_format   = ElasticPress\Indexables::factory()->get( 'post' )->prepare_date_meta_values( $meta_types, '+1 year' );

		$this->assertFalse( isset( $intval['date'] ) || isset( $intval['datetime'] ) || isset( $intval['time'] ) );
		$this->assertFalse( isset( $floatval['date'] ) || isset( $floatval['datetime'] ) || isset( $floatval['time'] ) );
		$this->assertTrue( isset( $float_string['date'] ) && isset( $float_string['datetime'] ) && isset( $float_string['time'] ) );
		$this->assertTrue( isset( $dateval['date'] ) && isset( $dateval['datetime'] ) && isset( $dateval['time'] ) );
		$this->assertTrue( isset( $recognizable_time['date'] ) && isset( $recognizable_time['datetime'] ) && isset( $recognizable_time['time'] ) );
		$this->assertTrue( isset( $relative_format['date'] ) && isset( $relative_format['datetime'] ) && isset( $relative_format['time'] ) );
	}

	/**
	 * Test meta key query
	 *
	 * @since 2.1
	 * @group post
	 */
	public function testMetaKeyQuery() {

		$this->ep_factory->post->create( array( 'post_content' => 'the post content findme' ) );
		$this->ep_factory->post->create(
			array(
				'post_content' => 'post content findme',
				'meta_input'   => array( 'test_key' => 'test' ),
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's'          => 'findme',
			'meta_key'   => 'test_key',
			'meta_value' => 'test',
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
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

		$this->ep_factory->post->create( array( 'post_content' => 'the post content findme' ) );
		$this->ep_factory->post->create(
			array(
				'post_content' => 'post content findme',
				'meta_input'   => array( 'test_key' => 5 ),
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();
		$args = array(
			's'              => 'findme',
			'meta_key'       => 'test_key',
			'meta_value_num' => 5,
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
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

		$this->ep_factory->post->create( array( 'post_content' => 'the post content findme' ) );
		$this->ep_factory->post->create(
			array(
				'post_content' => 'post content findme',
				'meta_input'   => array(
					'test_key'  => 5,
					'test_key2' => 'aaa',
				),
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();
		$args = array(
			's'              => 'findme',
			'meta_key'       => 'test_key',
			'meta_value_num' => 5,
			'meta_query'     => array(
				array(
					'key'   => 'test_key2',
					'value' => 'aaa',
				),
			),
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
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

		$this->ep_factory->post->create( array( 'post_content' => 'the post content findme' ) );
		$this->ep_factory->post->create(
			array(
				'post_content' => 'post content findme',
				'meta_input'   => array( 'test_key' => 100 ),
			)
		);
		$this->ep_factory->post->create( array( 'post_content' => 'the post content findme' ) );
		$this->ep_factory->post->create(
			array(
				'post_content' => 'post content findme',
				'meta_input'   => array( 'test_key' => 101 ),
			)
		);

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

		$this->assertTrue( $query->elasticsearch_success );
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

		$this->assertTrue( $query->elasticsearch_success );
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

		$this->assertTrue( $query->elasticsearch_success );
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

		$this->ep_factory->post->create( array( 'post_content' => 'the post content findme' ) );
		$this->ep_factory->post->create(
			array(
				'post_content' => 'post content findme',
				'meta_input'   => array( 'test_key' => 15.5 ),
			)
		);
		$this->ep_factory->post->create( array( 'post_content' => 'the post content findme' ) );
		$this->ep_factory->post->create(
			array(
				'post_content' => 'post content findme',
				'meta_input'   => array( 'test_key' => 16.5 ),
			)
		);

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

		$this->assertTrue( $query->elasticsearch_success );
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

		$this->assertTrue( $query->elasticsearch_success );
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

		$this->ep_factory->post->create( array( 'post_content' => 'the post content findme' ) );
		$this->ep_factory->post->create(
			array(
				'post_content' => 'post content findme',
				'meta_input'   => array( 'test_key' => 'abc' ),
			)
		);
		$this->ep_factory->post->create( array( 'post_content' => 'the post content findme' ) );
		$this->ep_factory->post->create(
			array(
				'post_content' => 'post content findme',
				'meta_input'   => array( 'test_key' => 'acc' ),
			)
		);

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

		$this->assertTrue( $query->elasticsearch_success );
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
		$this->ep_factory->post->create(
			array(
				'post_content' => 'post content findme',
				'meta_input'   => array( 'test_key' => '11/13/15' ),
			)
		);
		$this->ep_factory->post->create( array( 'post_content' => 'the post content findme' ) );
		$this->ep_factory->post->create(
			array(
				'post_content' => 'post content findme',
				'meta_input'   => array( 'test_key' => '11/15/15' ),
			)
		);

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

		$this->assertTrue( $query->elasticsearch_success );
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

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 1, $query->post_count );

	}

	/**
	 * Test time meta queries
	 *
	 * @since 1.7
	 * @group post
	 */
	public function testMetaValueTypeQueryTime() {
		$this->ep_factory->post->create( array( 'post_content' => 'the post content findme' ) );
		$this->ep_factory->post->create(
			array(
				'post_content' => 'post content findme',
				'meta_input'   => array( 'test_key' => '5:00am' ),
			)
		);

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

		$this->assertTrue( $query->elasticsearch_success );
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

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 1, $query->post_count );
	}

	/**
	 * Test date time meta queries
	 *
	 * @since 1.7
	 * @group post
	 */
	public function testMetaValueTypeQueryDatetime() {
		$this->ep_factory->post->create( array( 'post_content' => 'the post content findme' ) );
		$this->ep_factory->post->create(
			array(
				'post_content' => 'post content findme',
				'meta_input'   => array( 'test_key' => '5:00am 1/2/12' ),
			)
		);

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

		$this->assertTrue( $query->elasticsearch_success );
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

		$this->assertTrue( $query->elasticsearch_success );
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

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 1, $query->post_count );
	}

	/**
	 * Test a post_parent query
	 *
	 * @group post
	 * @since 2.0
	 */
	public function testPostParentQuery() {
		$parent_post = $this->ep_factory->post->create( array( 'post_content' => 'findme test 1' ) );
		$this->ep_factory->post->create(
			array(
				'post_content' => 'findme test 2',
				'post_parent'  => $parent_post,
			)
		);
		$this->ep_factory->post->create( array( 'post_content' => 'findme test 3' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's'           => 'findme',
			'post_parent' => $parent_post,
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, $query->found_posts );

		$args = array(
			's'           => 'findme',
			'post_parent' => 0,
			'fields'      => 'ids',
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( $parent_post, $query->posts[0] );

		// Test post_parent__in and post_parent__not_in queries
		$args = array(
			's'               => 'findme',
			'post_parent__in' => array( $parent_post ),
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, $query->found_posts );

		$args = array(
			's'                   => 'findme',
			'post_parent__not_in' => array( $parent_post ),
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );
	}

	/**
	 * Test a post_name__in query
	 *
	 * @group post
	 * @since 3.6.0
	 */
	public function testPostNameInQuery() {
		$this->ep_factory->post->create(
			array(
				'post_content' => 'findme name in test 1',
				'post_name'    => 'findme-name-in',
			)
		);
		$this->ep_factory->post->create( array( 'post_content' => 'findme name in test 2' ) );
		$this->ep_factory->post->create( array( 'post_content' => 'findme name in test 3' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's' => 'findme name in',
		);

		$args['post_name__in'] = 'findme-name-in';

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );

		$args['post_name__in'] = array( 'findme-name-in' );

		$query2 = new \WP_Query( $args );

		$this->assertTrue( $query2->elasticsearch_success );

		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, $query->found_posts );
		$this->assertEquals( 1, $query2->post_count );
		$this->assertEquals( 1, $query2->found_posts );
	}

	/**
	 * Test Tax Query NOT IN operator
	 *
	 * @since 2.1
	 * @group post
	 */
	public function testTaxQueryNotIn() {
		$this->ep_factory->post->create(
			array(
				'post_content' => 'findme test 1',
				'tags_input'   => array(
					'one',
					'two',
				),
			)
		);
		$this->ep_factory->post->create(
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

		$this->assertTrue( $query->elasticsearch_success );
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

		$this->assertTrue( $query->elasticsearch_success );
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
		$this->ep_factory->post->create(
			array(
				'post_content' => 'findme test 1',
				'tags_input'   => array(
					'one',
					'two',
				),
			)
		);
		$this->ep_factory->post->create(
			array(
				'post_content' => 'findme test 2',
				'tags_input'   => array( 'one' ),
			)
		);
		$this->ep_factory->post->create( array( 'post_content' => 'findme test 3' ) );

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

		$this->assertTrue( $query->elasticsearch_success );
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

		$this->assertTrue( $query->elasticsearch_success );
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
		$this->ep_factory->post->create(
			array(
				'post_content' => 'findme test 1',
				'tags_input'   => array(
					'one',
					'two',
				),
			)
		);
		$this->ep_factory->post->create(
			array(
				'post_content' => 'findme test 2',
				'tags_input'   => array( 'one' ),
			)
		);
		$this->ep_factory->post->create( array( 'post_content' => 'findme test 3' ) );

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

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, $query->found_posts );
	}

	/**
	 * Test post_mime_type query
	 *
	 * @since 2.3
	 */
	public function testPostMimeTypeQuery() {
		$attachment_id_1_jpeg = $this->ep_factory->post->create(
			array(
				'post_type'      => 'attachment',
				'post_mime_type' => 'image/jpeg',
				'post_status'    => 'inherit',
			)
		);
		$attachment_id_2_jpeg = $this->ep_factory->post->create(
			array(
				'post_type'      => 'attachment',
				'post_mime_type' => 'image/jpeg',
				'post_status'    => 'inherit',
			)
		);
		$attachment_id_3_pdf  = $this->ep_factory->post->create(
			array(
				'post_type'      => 'attachment',
				'post_mime_type' => 'application/pdf',
				'post_status'    => 'inherit',
			)
		);
		$attachment_id_4_png  = $this->ep_factory->post->create(
			array(
				'post_type'      => 'attachment',
				'post_mime_type' => 'image/png',
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

		$this->assertTrue( $query->elasticsearch_success );

		$attachment_names = wp_list_pluck( $query->posts, 'post_name' );

		$this->assertEquals( 3, $query->post_count );

		$this->assertContains( get_post_field( 'post_name', $attachment_id_1_jpeg ), $attachment_names );
		$this->assertContains( get_post_field( 'post_name', $attachment_id_2_jpeg ), $attachment_names );
		$this->assertContains( get_post_field( 'post_name', $attachment_id_4_png ), $attachment_names );

		$args = array(
			'ep_integrate'   => true,
			'post_mime_type' => 'image/png',
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );

		$attachment_names = wp_list_pluck( $query->posts, 'post_name' );

		$this->assertEquals( 1, $query->post_count );

		$this->assertContains( get_post_field( 'post_name', $attachment_id_4_png ), $attachment_names );

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

		$this->assertTrue( $query->elasticsearch_success );

		$attachment_names = wp_list_pluck( $query->posts, 'post_name' );

		$this->assertEquals( 3, $query->found_posts );

		$this->assertContains( get_post_field( 'post_name', $attachment_id_1_jpeg ), $attachment_names );
		$this->assertContains( get_post_field( 'post_name', $attachment_id_2_jpeg ), $attachment_names );
		$this->assertContains( get_post_field( 'post_name', $attachment_id_3_pdf ), $attachment_names );

		$args = array(
			'ep_integrate'   => true,
			'post_mime_type' => array(
				'image',
				'application/pdf',
			),
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );

		$attachment_names = wp_list_pluck( $query->posts, 'post_name' );

		$this->assertEquals( 4, $query->found_posts );

		$this->assertContains( get_post_field( 'post_name', $attachment_id_1_jpeg ), $attachment_names );
		$this->assertContains( get_post_field( 'post_name', $attachment_id_2_jpeg ), $attachment_names );
		$this->assertContains( get_post_field( 'post_name', $attachment_id_3_pdf ), $attachment_names );
		$this->assertContains( get_post_field( 'post_name', $attachment_id_4_png ), $attachment_names );

		$args = array(
			'ep_integrate'   => true,
			'post_mime_type' => array(
				'image/png',
			),
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );

		$attachment_names = wp_list_pluck( $query->posts, 'post_name' );

		$this->assertEquals( 1, $query->found_posts );

		$this->assertContains( get_post_field( 'post_name', $attachment_id_4_png ), $attachment_names );
	}

	/**
	 * Test Tax Query IN operator
	 *
	 * @since 2.4
	 * @group post
	 */
	public function testTaxQueryOperatorIn() {
		$this->ep_factory->post->create(
			array(
				'post_content' => 'findme test 1',
				'tags_input'   => array(
					'one',
					'two',
				),
			)
		);
		$this->ep_factory->post->create(
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

		$this->assertTrue( $query->elasticsearch_success );
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

		$this->assertTrue( $query->elasticsearch_success );
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
		$this->ep_factory->post->create(
			array(
				'post_content' => 'findme test 1',
				'tags_input'   => array(
					'one',
					'two',
				),
			)
		);
		$this->ep_factory->post->create(
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

		$this->assertTrue( $query->elasticsearch_success );
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

		$post = $this->ep_factory->post->create_and_get( array( 'post_title' => 'Test Post' ) );

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

		wp_set_object_terms( $post->ID, array( $term1['term_id'] ), $tax_name, true );

		ElasticPress\Indexables::factory()->get( 'post' )->index( $post->ID, true );
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

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( $query->post_count, 1 );
		$this->assertEquals( $query->found_posts, 1 );
		$this->assertTrue( isset( $query->posts[0]->elasticsearch ) );
	}

	/**
	 * If a post is sticky and we are on the home page, it should return at the top.
	 *
	 * @group post
	 * @group post-sticky
	 */
	public function testStickyPostsIncludedOnHome() {
		$this->ep_factory->post->create(
			[
				'post_title' => 'Normal post 1',
			]
		);
		$sticky_id = $this->ep_factory->post->create(
			[
				'post_title' => 'Sticky post',
				'post_date'  => gmdate( 'Y-m-d H:i:s', strtotime( '2 days ago' ) ),
			]
		);
		stick_post( $sticky_id );
		$this->ep_factory->post->create(
			[
				'post_title' => 'Normal post 2',
			]
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$this->go_to( '/' );

		$q = $GLOBALS['wp_query'];

		$this->assertEquals( 'Sticky post', $q->posts[0]->post_title );
	}

	/**
	 * If a post is not sticky and we are not on the home page, it should not return at the top.
	 *
	 * @group post
	 * @group post-sticky
	 */
	public function testStickyPostsExcludedOnNotHome() {
		$this->ep_factory->post->create(
			[
				'post_title' => 'Normal post 1',
			]
		);
		$sticky_id = $this->ep_factory->post->create(
			[
				'post_title' => 'Sticky post',
				'post_date'  => gmdate( 'Y-m-d H:i:s', strtotime( '2 days ago' ) ),
			]
		);
		stick_post( $sticky_id );
		$this->ep_factory->post->create(
			[
				'post_title' => 'Normal post 2',
			]
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		// This used to perform a new WP_Query with "s", but it needs to
		// perform a search request via the URL.
		$this->go_to( '/?s=' );

		$q = $GLOBALS['wp_query'];

		$this->assertNotEquals( 'Sticky post', $q->posts[0]->post_title );
	}

	/**
	 * Test a simple date param search by date and monthnum
	 *
	 * @group post
	 */
	public function testSimpleDateMonthNum() {
		$this->create_date_query_posts();

		$args = array(
			's'              => 'findme',
			'monthnum'       => 12,
			'posts_per_page' => 100,
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 5, $query->post_count );
		$this->assertEquals( 5, $query->found_posts );

		$args = array(
			's'              => 'findme',
			'day'            => 5,
			'posts_per_page' => 100,
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( $query->post_count, 1 );
		$this->assertEquals( $query->found_posts, 1 );
	}

	/**
	 * Test a simple date param search by day number of week
	 *
	 * @group post
	 */
	public function testSimpleDateDay() {
		$this->create_date_query_posts();

		$args = array(
			's'              => 'findme',
			'day'            => 5,
			'posts_per_page' => 100,
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( $query->post_count, 1 );
		$this->assertEquals( $query->found_posts, 1 );
	}

	/**
	 * Test a date query with before and after range
	 *
	 * @group post
	 */
	public function testDateQueryBeforeAfter() {
		$this->create_date_query_posts();

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

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( $query->post_count, 2 );
		$this->assertEquals( $query->found_posts, 2 );
	}

	/**
	 * Test a date query with multiple column range comparison
	 *
	 * @group post
	 */
	public function testDateQueryMultiColumn() {
		$this->create_date_query_posts();

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

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( $query->post_count, 4 );
		$this->assertEquals( $query->found_posts, 4 );
	}

	/**
	 * Test a date query with multiple column range comparison inclusive
	 *
	 * @group post
	 */
	public function testDateQueryMultiColumnInclusive() {
		$this->create_date_query_posts();

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

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( $query->post_count, 1 );
		$this->assertEquals( $query->found_posts, 1 );
	}


	/**
	 * Test a date query with multiple eltries
	 *
	 * @group post
	 */
	public function testDateQueryWorkingHours() {
		$this->create_date_query_posts();

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

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( $query->post_count, 5 );
		$this->assertEquals( $query->found_posts, 5 );
	}

	/**
	 * Test a date query with multiple column range comparison not inclusive
	 *
	 * @group post
	 */
	public function testDateQueryMultiColumnNotInclusive() {
		$this->create_date_query_posts();

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

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( $query->post_count, 0 );
		$this->assertEquals( $query->found_posts, 0 );
	}

	/**
	 * Test a simple date query search by year, monthnum and day of week
	 *
	 * @group post
	 */
	public function testDateQuerySimple() {
		$this->create_date_query_posts();

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

		$this->assertTrue( $query->elasticsearch_success );
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
		$this->create_date_query_posts();

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

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( $query->post_count, 5 );
		$this->assertEquals( $query->found_posts, 5 );
	}

	/**
	 * Test a date query with NOT BETWEEN comparison
	 *
	 * @group post
	 */
	public function testDateQueryNotBetween() {
		$this->create_date_query_posts();

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

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( $query->post_count, 8 );
		$this->assertEquals( $query->found_posts, 8 );
	}

	/**
	 * Test a date query with BETWEEN comparison on 1 day range
	 *
	 * @group post
	 */
	public function testDateQueryShortBetween() {
		$this->create_date_query_posts();

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

		$this->assertTrue( $query->elasticsearch_success );
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
		$this->create_date_query_posts();

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

		$this->assertTrue( $query->elasticsearch_success );
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
		$this->create_date_query_posts();

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

		$this->assertTrue( $query->elasticsearch_success );
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
		$this->create_date_query_posts();

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

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( $query->post_count, 0 );
		$this->assertEquals( $query->found_posts, 0 );
	}

	/**
	 * Test another date query with multiple range comparisons
	 *
	 * @group post
	 */
	public function testDateQueryCompare2() {
		$this->create_date_query_posts();

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

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );
	}

	/**
	 * Test date query where posts are only pulled from weekdays
	 *
	 * @group post
	 */
	public function testDateQueryWeekdayRange() {
		$this->create_date_query_posts();

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

		$this->assertTrue( $query->elasticsearch_success );
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

		$this->assertTrue( $query->elasticsearch_success );

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

		$this->ep_factory->post->create(
			array(
				'post_content'  => 'findme test 1',
				'tags_input'    => array( 'one', 'two' ),
				'post_category' => array( $cat1 ),
			)
		);

		$this->ep_factory->post->create( array( 'post_content' => 'findme test 2' ) );

		$this->ep_factory->post->create(
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

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 2, count( $query->posts ) );
	}

	/**
	 * Test a tag query by slug using array and comma separated string as arguments.
	 *
	 * @group post
	 */
	public function testTagSlugQuery() {
		$post_id_1 = $this->ep_factory->post->create(
			array(
				'post_content' => 'findme test 1',
				'tags_input'   => array( 'slug1', 'slug2' ),
			)
		);
		$post_id_2 = $this->ep_factory->post->create(
			array(
				'post_content' => 'findme test 2',
				'tags_input'   => array( 'slug1', 'slug2', 'slug3', 'slug4' ),
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$query1_args = [
			's'   => 'findme',
			'tag' => 'slug1,slug2',
		];

		$query2_args = [
			's'   => 'findme',
			'tag' => [ 'slug1', 'slug2' ],
		];

		$query1 = new \WP_Query( $query1_args );
		$query2 = new \WP_Query( $query2_args );

		$this->assertTrue( $query1->elasticsearch_success );
		$this->assertTrue( $query2->elasticsearch_success );

		$this->assertTrue( isset( $query1->posts[0]->elasticsearch ) );
		$this->assertTrue( isset( $query2->posts[0]->elasticsearch ) );

		$this->assertEquals( 2, $query1->post_count );
		$this->assertEquals( 2, $query1->found_posts );
		$this->assertEquals( 2, $query2->post_count );
		$this->assertEquals( 2, $query2->found_posts );
	}

	/**
	 * Test a query with tag__and and tag_id params
	 *
	 * @since 2.0
	 * @group post
	 */
	public function testTagQuery() {
		$tag1 = wp_insert_category(
			[
				'cat_name' => 'tag-1',
				'taxonomy' => 'post_tag',
			]
		);
		$tag2 = wp_insert_category(
			[
				'cat_name' => 'tag-2',
				'taxonomy' => 'post_tag',
			]
		);
		$tag3 = wp_insert_category(
			[
				'cat_name' => 'tag-3',
				'taxonomy' => 'post_tag',
			]
		);
		$tag4 = wp_insert_category(
			[
				'cat_name' => 'tag-4',
				'taxonomy' => 'post_tag',
			]
		);
		$tag5 = wp_insert_category(
			[
				'cat_name' => 'tag-5',
				'taxonomy' => 'post_tag',
			]
		);
		$tag6 = wp_insert_category(
			[
				'cat_name' => 'tag-6',
				'taxonomy' => 'post_tag',
			]
		);

		$post_id_1 = $this->ep_factory->post->create(
			array(
				'post_content' => 'findme test 1',
				'tags_input'   => array( $tag1, $tag2 ),
			)
		);
		$post_id_2 = $this->ep_factory->post->create(
			array(
				'post_content' => 'findme test 2',
				'tags_input'   => array( $tag3, $tag4, $tag5, $tag6 ),
			)
		);

		$post_id_3 = $this->ep_factory->post->create(
			array(
				'post_content' => 'findme test 3',
				'tags_input'   => array( $tag1, $tag2, $tag6 ),
			)
		);

		/*
		 *        |  1  |  2  |  3  |  4  |  5  |  6  |
		 * post 1 |  x  |  x  |     |     |     |     |
		 * post 2 |     |     |  x  |  x  |  x  |  x  |
		 * post 3 |  x  |  x  |     |     |     |  x  |
		 */

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		// Should find only posts with both tags 1 AND 2
		$args = array(
			's'         => 'findme',
			'post_type' => 'post',
			'tag__and'  => array( $tag1, $tag2 ),
			'fields'    => 'ids',
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );
		$this->assertEqualsCanonicalizing( [ $post_id_1, $post_id_3 ], $query->posts );

		// Should find only posts with tag 3
		$args = array(
			's'         => 'findme',
			'post_type' => 'post',
			'tag_id'    => $tag3,
			'fields'    => 'ids',
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, $query->found_posts );
		$this->assertEqualsCanonicalizing( [ $post_id_2 ], $query->posts );

		// Should find only posts with tags 1 OR 3
		$args = array(
			's'         => 'findme',
			'post_type' => 'post',
			'tag__in'   => array( $tag1, $tag3 ),
			'fields'    => 'ids',
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 3, $query->post_count );
		$this->assertEquals( 3, $query->found_posts );
		$this->assertEqualsCanonicalizing( [ $post_id_1, $post_id_2, $post_id_3 ], $query->posts );
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

		$post_id = $this->ep_factory->post->create();

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

		$post_id_1 = $this->ep_factory->post->create();
		$post_id_2 = $this->ep_factory->post->create();
		$post_id_3 = $this->ep_factory->post->create();
		$post_id_4 = $this->ep_factory->post->create( [ 'post_password' => '123' ] );

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
				'per_page'                             => 1,
				'ep_indexing_last_processed_object_id' => $post_id_3,
			]
		);

		$post_ids = wp_list_pluck( $results['objects'], 'ID' );
		$this->assertEquals( $post_id_2, $post_ids[0] );
		$this->assertCount( 1, $results['objects'] );
		$this->assertEquals( 3, $results['total_objects'] );

		// A custom upper_limit_object_id was passed in.
		$results = $indexable_post_object->query_db(
			[
				'per_page'                          => 1,
				'ep_indexing_upper_limit_object_id' => $post_id_1,
			]
		);

		$post_ids = wp_list_pluck( $results['objects'], 'ID' );
		$this->assertEquals( $post_id_1, $post_ids[0] );
		$this->assertCount( 1, $results['objects'] );
		$this->assertEquals( 1, $results['total_objects'] );

		// Passing custom start and last post IDs. Second loop.
		$results = $indexable_post_object->query_db(
			[
				'per_page'                             => 1,
				'ep_indexing_upper_limit_object_id'    => $post_id_3,
				'ep_indexing_lower_limit_object_id'    => $post_id_2,
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

		$results = $indexable_post_object->query_db(
			[
				'offset' => 1,
			]
		);

		$post_ids = wp_list_pluck( $results['objects'], 'ID' );
		$this->assertEquals( $post_id_2, $post_ids[0] );
		$this->assertCount( 2, $results['objects'] );
		$this->assertEquals( 3, $results['total_objects'] );

		$results = $indexable_post_object->query_db(
			[
				'offset' => 3,
			]
		);

		$this->assertCount( 0, $results['objects'] );
		$this->assertEquals( 0, $results['total_objects'] );

		$results = $indexable_post_object->query_db(
			[
				'offset' => -1,
			]
		);

		$this->assertCount( 3, $results['objects'] );
		$this->assertEquals( 3, $results['total_objects'] );

		// Test the first loop of the indexing.
		$results = $indexable_post_object->query_db(
			[
				'per_page'     => 1,
				'has_password' => null, // `null` here makes WP ignore passwords completely, bringing everything
			]
		);

		$post_ids = wp_list_pluck( $results['objects'], 'ID' );
		$this->assertEquals( $post_id_4, $post_ids[0] );
		$this->assertCount( 1, $results['objects'] );
		$this->assertEquals( 4, $results['total_objects'] );

		// Test it pulls the post with passwords when password protected feature is enabled.
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$results = $indexable_post_object->query_db(
			[
				'per_page' => 1,
			]
		);
		$this->assertEquals( 4, $results['total_objects'] );
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
		$post_id = $this->ep_factory->post->create();

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

		add_filter( 'ep_sync_taxonomies', '__return_false' );

		$post_args = $post->prepare_document( $post_id );

		remove_filter( 'ep_sync_taxonomies', '__return_false' );

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
		$invalid_taxonomy              = new \stdClass();
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
	 * Tests root taxonomy queries inside format_args.
	 *
	 * @return void
	 * @group post
	 */
	public function testFormatArgsRootLevelTaxonomies() {
		$cat1 = $this->factory->category->create( [ 'name' => 'category one' ] );
		$cat2 = $this->factory->category->create( [ 'name' => 'category two' ] );
		$tag1 = $this->factory->tag->create( [ 'name' => 'tag-1' ] );
		$tag2 = $this->factory->tag->create( [ 'name' => 'tag-2' ] );
		$tag3 = $this->factory->tag->create( [ 'name' => 'tag-3' ] );

		$post1 = $this->ep_factory->post->create(
			array(
				'tags_input'    => array( $tag1, $tag2 ),
				'post_category' => array( $cat1 ),
			)
		);
		$post2 = $this->ep_factory->post->create(
			array(
				'tags_input'    => array( $tag1, $tag2, $tag3 ),
				'post_category' => array( $cat2 ),
			)
		);
		$post3 = $this->ep_factory->post->create(
			array(
				'post_category' => array( $cat1 ),
			)
		);
		$post4 = $this->ep_factory->post->create(
			array(
				'tags_input' => array( $tag1, $tag3 ),
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$query = new \WP_Query(
			[
				'ep_integrate' => true,
				'cat'          => $cat1,
				'tag'          => 'tag-1',
				'fields'       => 'ids',
			]
		);
		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEqualsCanonicalizing( [ $post1 ], $query->posts );
		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, $query->found_posts );

		$query = new \WP_Query(
			[
				'ep_integrate' => true,
				'tag__and'     => [ $tag1, $tag2 ],
				'tag_id'       => $tag1,
				'fields'       => 'ids',
			]
		);
		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEqualsCanonicalizing( [ $post1, $post2 ], $query->posts );
		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );

		$query = new \WP_Query(
			[
				'ep_integrate' => true,
				'tag__and'     => [ $tag1, $tag2 ],
				'tag_id'       => $tag3,
				'fields'       => 'ids',
			]
		);
		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEqualsCanonicalizing( [ $post2 ], $query->posts );
		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, $query->found_posts );

		$query = new \WP_Query(
			[
				'ep_integrate' => true,
				'tag__in'      => [ $tag1, $tag2, $tag3 ],
				'fields'       => 'ids',
			]
		);
		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEqualsCanonicalizing( [ $post1, $post2, $post4 ], $query->posts );
		$this->assertEquals( 3, $query->post_count );
		$this->assertEquals( 3, $query->found_posts );

		$query = new \WP_Query(
			[
				'ep_integrate' => true,
				'category__in' => [ $cat1, $cat2 ],
				'fields'       => 'ids',
			]
		);
		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEqualsCanonicalizing( [ $post1, $post2, $post3 ], $query->posts );
		$this->assertEquals( 3, $query->post_count );
		$this->assertEquals( 3, $query->found_posts );
	}

	/**
	 * Tests post_mime_type in format_args().
	 *
	 * @return void
	 * @group post
	 */
	public function testFormatArgsPostMimeType() {

		$post = new \ElasticPress\Indexable\Post\Post();

		$query = new \WP_Query();

		$args = $post->format_args(
			[
				'post_mime_type' => 'image',
			],
			$query
		);

		$this->assertSame( 'image.*', $args['post_filter']['bool']['must'][0]['regexp']['post_mime_type'] );

		$args = $post->format_args(
			[
				'post_mime_type' => [ 'image/jpeg' ],
			],
			$query
		);

		$this->assertCount( 1, $args['post_filter']['bool']['must'][0]['terms']['post_mime_type'] );
		$this->assertSame( 'image/jpeg', $args['post_filter']['bool']['must'][0]['terms']['post_mime_type'][0] );

		$args = $post->format_args(
			[
				'post_mime_type' => [ 'image/jpeg', 'application/pdf' ],
			],
			$query
		);

		$this->assertCount( 2, $args['post_filter']['bool']['must'][0]['terms']['post_mime_type'] );
		$this->assertContains( 'image/jpeg', $args['post_filter']['bool']['must'][0]['terms']['post_mime_type'] );
		$this->assertContains( 'application/pdf', $args['post_filter']['bool']['must'][0]['terms']['post_mime_type'] );

		$args = $post->format_args(
			[
				'post_mime_type' => [ 'image' ],
			],
			$query
		);

		$this->assertGreaterThan( 1, count( $args['post_filter']['bool']['must'][0]['terms']['post_mime_type'] ) );
		$this->assertContains( 'image/jpeg', $args['post_filter']['bool']['must'][0]['terms']['post_mime_type'] );

		$args = $post->format_args(
			[
				'post_mime_type' => [ 'image', 'application/pdf' ],
			],
			$query
		);

		$this->assertGreaterThan( 2, count( $args['post_filter']['bool']['must'][0]['terms']['post_mime_type'] ) );
		$this->assertContains( 'image/jpeg', $args['post_filter']['bool']['must'][0]['terms']['post_mime_type'] );
		$this->assertContains( 'application/pdf', $args['post_filter']['bool']['must'][0]['terms']['post_mime_type'] );

		$args = $post->format_args(
			[
				'post_mime_type' => [],
			],
			$query
		);

		$this->assertArrayNotHasKey( 'terms', $args['post_filter']['bool']['must'][0] );
	}

	/**
	 * Tests author in format_args().
	 *
	 * @return void
	 * @group post
	 */
	public function testFormatArgsAuthor() {

		$post = new \ElasticPress\Indexable\Post\Post();

		$query = new \WP_Query();

		$args = $post->format_args(
			[
				'author' => 123,
			],
			$query
		);

		$this->assertSame( 123, $args['post_filter']['bool']['must'][0]['term']['post_author.id'] );

		$args = $post->format_args(
			[
				'author_name' => 'Bacon Ipsum',
			],
			$query
		);

		$this->assertSame( 'Bacon Ipsum', $args['post_filter']['bool']['must'][0]['term']['post_author.display_name'] );
	}

	/**
	 * Tests sticky posts in format_args().
	 *
	 * @return void
	 * @group post
	 */
	public function testFormatArgsStickyPosts() {
		global $wp_query;

		// Create a sticky post.
		$sticky_post_id = $this->ep_factory->post->create();
		stick_post( $sticky_post_id );

		$sticky_posts = get_option( 'sticky_posts' );
		$this->assertNotEmpty( $sticky_posts );

		$post = new \ElasticPress\Indexable\Post\Post();

		$this->go_to( home_url( '/' ) );

		$args = $post->format_args(
			[
				'ep_integrate'        => true,
				'ignore_sticky_posts' => false,
			],
			$wp_query
		);

		$this->assertSame( 'desc', $args['sort'][0]['_score']['order'] );
		$this->assertSame( 1, $args['query']['function_score']['query']['match_all']['boost'] );
		$this->assertContains( $sticky_post_id, $args['query']['function_score']['functions'][0]->filter['terms']['_id'] );
		$this->assertSame( 20, $args['query']['function_score']['functions'][0]->weight );
	}

	/**
	 * Tests post statuses for admin in format_args().
	 *
	 * @return void
	 * @group post
	 */
	public function testFormatArgsAdminPostStatuses() {

		set_current_screen( 'edit.php' );
		$this->assertTrue( is_admin() );

		$post = new \ElasticPress\Indexable\Post\Post();

		// This will include statuses besides publish.
		$args = $post->format_args( [], new \WP_Query() );

		$statuses = $args['post_filter']['bool']['must'][1]['terms']['post_status'];

		$this->assertContains( 'publish', $statuses );
		$this->assertContains( 'future', $statuses );
		$this->assertContains( 'draft', $statuses );
		$this->assertContains( 'pending', $statuses );
		$this->assertContains( 'private', $statuses );
	}

	/**
	 * Tests fields in format_args().
	 *
	 * @return void
	 * @group post
	 */
	public function testFormatArgsFields() {

		$post = new \ElasticPress\Indexable\Post\Post();

		$args = $post->format_args(
			[
				'fields' => 'ids',
			],
			new \WP_Query()
		);

		$this->assertContains( 'post_id', $args['_source']['includes'] );

		$args = $post->format_args(
			[
				'fields' => 'id=>parent',
			],
			new \WP_Query()
		);

		$this->assertContains( 'post_id', $args['_source']['includes'] );
		$this->assertContains( 'post_parent', $args['_source']['includes'] );
	}

	/**
	 * Tests aggs in format_args().
	 *
	 * @return void
	 * @group post
	 */
	public function testFormatArgsAggs() {
		// For reference https://www.elasticpress.io/blog/2017/09/aggregations-api-for-grouping-data/.
		$post = new \ElasticPress\Indexable\Post\Post();

		$args = $post->format_args(
			[
				// Triggers $use_filter to be true.
				'post_status' => 'publish',

				'aggs'        => [
					'name'       => 'post_type_stats',
					'use-filter' => true,
					'aggs'       => [
						'terms' => [
							'field' => 'terms.post_type',
						],
					],
				],
			],
			new \WP_Query()
		);

		$this->assertSame( 'publish', $args['aggs']['post_type_stats']['filter']['bool']['must'][1]['term']['post_status'] );
		$this->assertSame( 'terms.post_type', $args['aggs']['post_type_stats']['aggs']['terms']['field'] );

		$args = $post->format_args(
			[
				'aggs' => [
					'aggs' => [
						'terms' => [
							'field' => 'terms.post_type',
						],
					],
				],
			],
			new \WP_Query()
		);

		$this->assertSame( 'terms.post_type', $args['aggs']['aggregation_name']['terms']['field'] );

		// Multiple aggs.
		$args = $post->format_args(
			[
				// Triggers $use_filter to be true.
				'post_status' => 'publish',

				'aggs'        => [
					[
						'name'       => 'taxonomies',
						'use-filter' => true,
						'aggs'       => [
							'terms' => [
								'field' => 'terms.category.slug',
							],
						],
					],
					[
						'aggs' => [
							'terms' => [
								'field' => 'terms.post_type',
							],
						],
					],
				],
			],
			new \WP_Query()
		);

		$this->assertSame( 'publish', $args['aggs']['taxonomies']['filter']['bool']['must'][1]['term']['post_status'] );
		$this->assertSame( 'terms.category.slug', $args['aggs']['taxonomies']['aggs']['terms']['field'] );
		$this->assertSame( 'terms.post_type', $args['aggs']['aggregation_name']['terms']['field'] );
	}

	/**
	 * Tests the `ep_post_filters` filter
	 *
	 * @return void
	 * @group post
	 */
	public function testFormatArgsEpPostFilter() {
		$post = new \ElasticPress\Indexable\Post\Post();

		$test_args  = [];
		$test_query = new \WP_Query( $test_args );

		$add_es_filter = function( $filters, $args, $query ) use ( $test_query, $test_args ) {
			$filters['new_filter'] = [
				'term' => [
					'my_custom_field.raw' => 'my_custom_value',
				],
			];

			// Simple check if the filter additional parameters work.
			$this->assertSame( $test_query, $query );
			$this->assertSame( $test_args, $args );

			return $filters;
		};
		add_filter( 'ep_post_filters', $add_es_filter, 10, 3 );

		$args = $post->format_args( $test_args, $test_query );

		$this->assertNotEmpty( $args['post_filter']['bool']['must'] );

		$last_filter = end( $args['post_filter']['bool']['must'] );
		$this->assertSame( [ 'my_custom_field.raw' => 'my_custom_value' ], $last_filter['term'] );
	}

	/**
	 * Data provider for the testParseOrderby method.
	 *
	 * @since 4.6.0
	 * @return array
	 */
	public function parseOrderbyDataProvider() {
		return [
			[ 'type', 'post_type.raw' ],
			[ 'modified', 'post_modified' ],
			[ 'relevance', '_score' ],
			[ 'date', 'post_date' ],
			[ 'name', 'post_name.raw' ],
			[ 'title', 'post_title.sortable' ],
		];
	}

	/**
	 * Test the parse_orderby() method (without meta values)
	 *
	 * @param string $orderby Orderby value
	 * @param string $es_key  The related ES field
	 * @dataProvider parseOrderbyDataProvider
	 * @group post
	 */
	public function testParseOrderby( $orderby, $es_key ) {
		$method_executed = false;

		$query_args = [
			'ep_integrate' => true,
			'orderby'      => $orderby,
			'order'        => 'asc',
		];

		$assert_callback = function( $args ) use ( &$method_executed, $es_key ) {
			$method_executed = true;

			$this->assertArrayHasKey( $es_key, $args['sort'][0] );
			$this->assertSame( 'asc', $args['sort'][0][ $es_key ]['order'] );

			return $args;
		};

		// Run the tests.
		add_filter( 'ep_formatted_args', $assert_callback );
		$query = new \WP_Query( $query_args );
		remove_filter( 'ep_formatted_args', $assert_callback );

		$this->assertTrue( $method_executed );
		$this->assertGreaterThanOrEqual( 1, did_filter( 'ep_formatted_args' ) );
	}

	/**
	 * Data provider for following methods:
	 *
	 * - testParseOrderbyMetaValueParams
	 * - testParseOrderbyMetaValueWithoutMetaKeyParams
	 * - testParseOrderbyMetaQueryTypes
	 *
	 * @since 4.6.0
	 * @return array
	 */
	public function parseOrderbyMetaDataProvider() {
		$numeric = [ 2, 1, 3 ];
		$char    = [ 'b', 'a', 'c' ];

		$timestamps = [
			strtotime( '2 days ago' ),
			strtotime( '5 days ago' ),
			strtotime( '1 days ago' ),
		];

		$date     = [ gmdate( 'Y-m-d', $timestamps[0] ), gmdate( 'Y-m-d', $timestamps[1] ), gmdate( 'Y-m-d', $timestamps[2] ) ];
		$datetime = [ gmdate( 'Y-m-d 14:00:00', $timestamps[0] ), gmdate( 'Y-m-d 10:00:00', $timestamps[0] ), gmdate( 'Y-m-d 23:30:00', $timestamps[0] ) ];
		$time     = [ '14:00', '10:00', '23:30' ];

		return [
			[ '', 'value.sortable', $char ],
			[ 'NUM', 'long', $numeric ],
			[ 'NUMERIC', 'long', $numeric ],
			[ 'BINARY', 'value.sortable', $char ],
			[ 'CHAR', 'value.sortable', $char ],
			[ 'DATE', 'date', $date ],
			[ 'DATETIME', 'datetime', $datetime ],
			[ 'DECIMAL', 'double', [ 0.2, 0.1, 0.3 ] ],
			[ 'SIGNED', 'long', $numeric ],
			[ 'TIME', 'time', $time ],
			[ 'UNSIGNED', 'long', $numeric ],
		];
	}

	/**
	 * Test the parse_orderby_meta_fields() method when dealing with `'meta_value*'` and `'meta_key'` parameters
	 *
	 * @param string $meta_value_type Meta value type (as in WP)
	 * @param string $es_type         Meta valye type in Elasticsearch
	 * @param array  $meta_values     Meta values for post creation
	 * @since 4.6.0
	 * @dataProvider parseOrderbyMetaDataProvider
	 * @group post
	 */
	public function testParseOrderbyMetaValueParams( $meta_value_type, $es_type, $meta_values ) {
		$method_executed = false;

		$posts = [];
		foreach ( $meta_values as $value ) {
			$posts[] = $this->ep_factory->post->create( [ 'meta_input' => [ 'test_key' => $value ] ] );
		}
		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$query_args = [
			'ep_integrate' => true,
			'fields'       => 'ids',
			'orderby'      => 'meta_value' . ( $meta_value_type ? "_{$meta_value_type}" : '' ),
			'order'        => 'asc',
			'meta_key'     => 'test_key',
		];

		$assert_callback = function( $args ) use ( &$method_executed, $es_type ) {
			$method_executed = true;

			$this->assertArrayHasKey( "meta.test_key.{$es_type}", $args['sort'][0] );
			$this->assertSame( 'asc', $args['sort'][0][ "meta.test_key.{$es_type}" ]['order'] );

			return $args;
		};

		// Run the tests.
		add_filter( 'ep_formatted_args', $assert_callback );
		$query = new \WP_Query( $query_args );
		remove_filter( 'ep_formatted_args', $assert_callback );

		$this->assertTrue( $method_executed );
		$this->assertTrue( $query->elasticsearch_success );
		$this->assertSame( $posts[1], $query->posts[0] );
		$this->assertSame( $posts[0], $query->posts[1] );
		$this->assertSame( $posts[2], $query->posts[2] );
	}

	/**
	 * Test the parse_orderby_meta_fields() method when dealing with `'meta_value*'` parameters
	 *
	 * @param string $meta_value_type Meta value type (as in WP)
	 * @param string $es_type         Meta valye type in Elasticsearch
	 * @since 4.6.0
	 * @dataProvider parseOrderbyMetaDataProvider
	 * @group post
	 */
	public function testParseOrderbyMetaValueWithoutMetaKeyParams( $meta_value_type, $es_type ) {
		$method_executed = false;

		$query_args = [
			'ep_integrate' => true,
			'orderby'      => 'meta_value' . ( $meta_value_type ? "_{$meta_value_type}" : '' ),
			'order'        => 'asc',
			'meta_query'   => [
				[
					'key'     => 'test_key',
					'compare' => 'EXISTS',
				],
			],
		];

		$assert_callback = function( $args ) use ( &$method_executed, $es_type ) {
			$method_executed = true;

			$this->assertArrayHasKey( "meta.test_key.{$es_type}", $args['sort'][0] );
			$this->assertSame( 'asc', $args['sort'][0][ "meta.test_key.{$es_type}" ]['order'] );

			return $args;
		};

		// Run the tests.
		add_filter( 'ep_formatted_args', $assert_callback );
		$query = new \WP_Query( $query_args );
		remove_filter( 'ep_formatted_args', $assert_callback );

		$this->assertTrue( $method_executed );
		$this->assertGreaterThanOrEqual( 1, did_filter( 'ep_formatted_args' ) );
	}

	/**
	 * Test the parse_orderby_meta_fields() method when dealing with named meta queries
	 *
	 * @param string $meta_value_type Meta value type (as in WP)
	 * @param string $es_type         Meta valye type in Elasticsearch
	 * @since 4.6.0
	 * @dataProvider parseOrderbyMetaDataProvider
	 * @group post
	 */
	public function testParseOrderbyMetaQueryTypes( $meta_value_type, $es_type ) {
		$method_executed = false;

		$query_args = [
			'ep_integrate' => true,
			'orderby'      => 'named_clause',
			'order'        => 'asc',
			'meta_query'   => [
				[
					'key'  => 'test_key1',
					'type' => 'NUMERIC',
				],
				'named_clause' => [
					'key'  => 'test_key',
					'type' => $meta_value_type,
				],
			],
		];

		$assert_callback = function( $args ) use ( &$method_executed, $es_type ) {
			$method_executed = true;

			$this->assertArrayHasKey( "meta.test_key.{$es_type}", $args['sort'][0] );
			$this->assertSame( 'asc', $args['sort'][0][ "meta.test_key.{$es_type}" ]['order'] );

			return $args;
		};

		// Run the tests.
		add_filter( 'ep_formatted_args', $assert_callback );
		$query = new \WP_Query( $query_args );
		remove_filter( 'ep_formatted_args', $assert_callback );

		$this->assertTrue( $method_executed );
		$this->assertGreaterThanOrEqual( 1, did_filter( 'ep_formatted_args' ) );
	}

	/**
	 * Test the parse_orderby_meta_fields() method when dealing with multiple meta fields
	 *
	 * @see https://github.com/10up/ElasticPress/issues/3509
	 * @since 4.6.1
	 * @group post
	 */
	public function testParseOrderbyMetaMultiple() {
		$method_executed = false;

		$query_args = [
			'ep_integrate' => true,
			'orderby'      => [
				'meta_field1'             => 'desc',
				'meta.meta_field3.double' => 'asc',
				'meta.meta_field2.double' => 'asc',
			],
			'meta_query'   => [
				'date_clause' => [
					'key'     => 'meta_field2',
					'value'   => '20230622',
					'compare' => '>=',
				],
			],
		];

		$assert_callback = function( $args ) use ( &$method_executed ) {
			$method_executed = true;

			$expected_sort = [
				[ 'meta_field1' => [ 'order' => 'desc' ] ],
				[ 'meta.meta_field3.double' => [ 'order' => 'asc' ] ],
				[ 'meta.meta_field2.double' => [ 'order' => 'asc' ] ],
			];

			$this->assertSame( $expected_sort, $args['sort'] );

			return $args;
		};

		// Run the tests.
		add_filter( 'ep_formatted_args', $assert_callback );
		$query = new \WP_Query( $query_args );
		remove_filter( 'ep_formatted_args', $assert_callback );

		$this->assertTrue( $method_executed );
		$this->assertGreaterThanOrEqual( 1, did_filter( 'ep_formatted_args' ) );
	}

	/**
	 * Tests additional nested tax queries in parse_tax_query().
	 *
	 * @return void
	 * @group post
	 */
	public function testParseNestedTaxQuery() {

		// Post type.
		$query_args = [
			'ep_integrate' => true,
			'tax_query'    => [
				'relation' => 'and',
				[
					'relation' => 'or',
					[
						'taxonomy' => 'category',
						'terms'    => 123,
					],
					[
						'taxonomy' => 'post_tag',
						'terms'    => 456,
					],
				],
				[
					[
						'taxonomy' => 'custom-tax',
						'terms'    => 789,
					],
				],
			],
		];

		$assert_callback = function( $args ) {

			$this->assertSame( 123, $args['post_filter']['bool']['must'][0]['bool']['must'][0]['bool']['should'][0]['terms']['terms.category.term_id'][0] );
			$this->assertSame( 456, $args['post_filter']['bool']['must'][0]['bool']['must'][0]['bool']['should'][1]['terms']['terms.post_tag.term_id'][0] );

			$this->assertSame( 789, $args['post_filter']['bool']['must'][0]['bool']['must'][1]['bool']['must'][0]['terms']['terms.custom-tax.term_id'][0] );

			return $args;
		};

		// Run the tests.
		add_filter( 'ep_formatted_args', $assert_callback );
		$query = new \WP_Query( $query_args );
		remove_filter( 'ep_formatted_args', $assert_callback );
	}

	/**
	 * Tests additional logic in put_mapping().
	 *
	 * @return void
	 * @group post
	 */
	public function testPutMapping() {

		// This lets us trigger the ep_fallback_elasticsearch_version filter.
		add_filter( 'ep_elasticsearch_version', '__return_false' );

		$post = new \ElasticPress\Indexable\Post\Post();

		// Test the mapping files for different ES versions.
		$version_and_file = [
			'5.3' => '5-2.php',
			'7.0' => '7-0.php',
		];

		foreach ( $version_and_file as $version => $file ) {

			$version_callback = function() use ( $version ) {
				return $version;
			};

			// Callback to test the mapping file that was selected.
			$assert_callback = function( $mapping_file ) use ( $file ) {
				$this->assertSame( $file, basename( $mapping_file ) );
				return $mapping_file;
			};

			// Tell EP that we're running a specific ES version.
			add_filter( 'ep_fallback_elasticsearch_version', $version_callback );

			// Turn on the test for the mapping file.
			add_filter( 'ep_post_mapping_file', $assert_callback );

			// Run put_mapping(), which will trigger these filters above
			// and run the tests.
			$post->put_mapping();

			remove_filter( 'ep_fallback_elasticsearch_version', $version_callback );
			remove_filter( 'ep_post_mapping_file', $assert_callback );
		}
	}

	/**
	 * Tests the QueryIntegration constructor.
	 *
	 * @return void
	 * @group  post
	 */
	public function testQueryIntegrationConstructor() {

		// Pretend we're indexing.
		add_filter( 'ep_is_full_reindexing_post', '__return_true' );

		$query_integration = new \ElasticPress\Indexable\Post\QueryIntegration();

		$action_function = [
			'pre_get_posts'   => [ 'add_es_header', 5 ],
			'posts_pre_query' => [ 'get_es_posts', 10 ],
			'loop_end'        => [ 'maybe_restore_blog', 10 ],
			'the_post'        => [ 'maybe_switch_to_blog', 10 ],
			'found_posts'     => [ 'found_posts', 10 ],
		];

		// Make sure these filters are not present if EP is indexing.
		foreach ( $action_function as $action => $function ) {
			$this->assertFalse( has_filter( $action, [ $query_integration, $function[0] ] ) );
		}

		remove_filter( 'ep_is_full_reindexing_post', '__return_true' );

		$query_integration = new \ElasticPress\Indexable\Post\QueryIntegration();

		// Make sure these filters ARE not present since EP is not flagged
		// as indexing.
		foreach ( $action_function as $action => $function ) {
			$this->assertSame( $function[1], has_filter( $action, [ $query_integration, $function[0] ] ) );
		}
	}

	/**
	 * Tests found_posts.
	 *
	 * @return void
	 * @group  post
	 */
	public function testFoundPosts() {

		$query_integration = new \ElasticPress\Indexable\Post\QueryIntegration();

		// Simulate a WP_Query object.
		$query                        = new \stdClass();
		$query->elasticsearch_success = true;
		$query->num_posts             = 123;
		$query->query_vars            = [ 'ep_integrate' => true ];

		$this->assertSame( 123, $query_integration->found_posts( 10, $query ) );
	}

	/**
	 * Tests additional logic in get_es_posts();
	 *
	 * @return void
	 * @group  post
	 */
	public function testGetESPosts() {

		$assert_callback = function( $formatted_args, $args ) {

			$this->assertSame( 'post', $args['post_type'] );

			return $args;
		};

		// Add the tests in the filter and run the query to perform the
		// tests.
		add_filter( 'ep_formatted_args', $assert_callback, 10, 2 );

		// This will default to 'post' by QueryIntegration when 'any' is
		// passed in.
		$query = new \WP_Query(
			[
				'ep_integrate' => true,
				'post_type'    => 'any',
			]
		);

		remove_filter( 'ep_formatted_args', $assert_callback, 10, 2 );

		$post_ids   = [];
		$post_ids[] = $this->ep_factory->post->create();
		$post_ids[] = $this->ep_factory->post->create();
		$post_ids[] = $this->ep_factory->post->create( [ 'post_parent' => $post_ids[1] ] );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		// Now test the fields parameter.
		$assert_callback = function( $new_posts ) use ( $post_ids ) {

			$this->assertContains( $post_ids[0], $new_posts );
			$this->assertContains( $post_ids[1], $new_posts );
			$this->assertContains( $post_ids[2], $new_posts );

			return $new_posts;
		};

		add_filter( 'ep_wp_query', $assert_callback );

		$query = new \WP_Query(
			[
				'ep_integrate' => true,
				'fields'       => 'ids',
				'post__in'     => $post_ids,
			]
		);

		remove_filter( 'ep_wp_query', $assert_callback );

		// Test the id=>parent parameter.
		$assert_callback = function( $new_posts ) use ( $post_ids ) {

			$this->assertSame( $post_ids[0], $new_posts[0]->ID );
			$this->assertSame( $post_ids[1], $new_posts[1]->ID );
			$this->assertSame( $post_ids[2], $new_posts[2]->ID );

			// The last new post should have the parent ID of the second post.
			$this->assertSame( $post_ids[1], $new_posts[2]->post_parent );

			foreach ( $new_posts as $new_post ) {
				$this->assertTrue( $new_post->elasticsearch );
			}

			return $new_posts;
		};

		add_filter( 'ep_wp_query', $assert_callback );

		$query = new \WP_Query(
			[
				'ep_integrate' => true,
				'fields'       => 'id=>parent',
				'post__in'     => $post_ids,
				'orderby'      => 'post_id',
				'order'        => 'asc',
			]
		);

		remove_filter( 'ep_wp_query', $assert_callback );
	}

	/**
	 * Tests logic in maybe_switch_to_blog() and maybe_restore_blog();
	 *
	 * @return void
	 * @group  post
	 */
	public function testMaybeSwitchToBlog() {

		// Do an assert here for both single and multisite tests so we
		// don't get a warning.
		$multisite = defined( 'WP_TESTS_MULTISITE' ) && '1' === WP_TESTS_MULTISITE;

		$this->assertSame( $multisite, is_multisite() );

		// Only continue if this is in multisite.
		if ( ! is_multisite() ) {
			return;
		}

		$sites     = get_sites();
		$blog_1_id = get_current_blog_id();
		$blog_2_id = false;

		// Create a second site if we need one.
		if ( count( $sites ) <= 1 ) {

			$blog_2_id = $this->factory->blog->create_object(
				[
					'domain' => 'example2.org',
					'title'  => 'Example Site 2',
				]
			);

			$this->assertFalse( is_wp_error( $blog_2_id ) );
		} else {
			$blog_2_id = $sites[1]->blog_id;
		}

		$this->assertGreaterThan( 1, $blog_2_id );

		$blog_1_post_id = $this->ep_factory->post->create();

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$query = new \WP_Query(
			[
				'ep_integrate'   => true,
				'post__in'       => [ $blog_1_post_id ],
				'posts_per_page' => 1,
			]
		);

		$this->assertTrue( $query->elasticsearch_success );

		$blog_1_post = $query->posts[0];

		$this->assertSame( $blog_1_id, $blog_1_post->site_id );

		// Switch to the new blog, create a post.
		switch_to_blog( $blog_2_id );

		$blog_2_post_id = $this->ep_factory->post->create();

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$query = new \WP_Query(
			[
				'ep_integrate'   => true,
				'post__in'       => [ $blog_2_post_id ],
				'posts_per_page' => 1,
			]
		);

		$this->assertTrue( $query->elasticsearch_success );

		$blog_2_post = $query->posts[0];

		$this->assertSame( $blog_2_id, $blog_2_post->site_id );

		restore_current_blog();

		// Now we have two different posts in different sites and can
		// test the function. Try accessing the 2nd post from the 1st blog.
		$query_integration = new \ElasticPress\Indexable\Post\QueryIntegration();

		// This should not switch to the 2nd site because the query is not in the loop.
		$query_integration->maybe_switch_to_blog( $blog_2_post, $query );

		$this->assertFalse( $query_integration->get_switched() );

		// To switch sites the query must be in the loop.
		$query->in_the_loop = true;

		// This should switch to the 2nd site.
		$query_integration->maybe_switch_to_blog( $blog_2_post, $query );

		$this->assertSame( $blog_2_post->site_id, $query_integration->get_switched() );

		// Now we're in "switched" mode, try getting the post from the
		// 1st site, should switch back.
		$query_integration->maybe_switch_to_blog( $blog_1_post, $query );

		$this->assertSame( $blog_1_post->site_id, $query_integration->get_switched() );

		restore_current_blog();

		// Verify we're clearing the flag in the class.
		$query_integration->maybe_restore_blog( null );
		$this->assertFalse( $query_integration->get_switched() );

		// Make sure we're back on the first site.
		$this->assertSame( $blog_1_id, get_current_blog_id() );
	}

	/**
	 * Tests additional logic with the post sync queue.
	 *
	 * @return void
	 * @group  post
	 */
	public function testPostSyncQueueEPKill() {

		// Create a post sync it.
		$post_id = $this->ep_factory->post->create();

		$this->assertNotEmpty( ElasticPress\Indexables::factory()->get( 'post' )->sync_manager->get_sync_queue() );

		ElasticPress\Indexables::factory()->get( 'post' )->sync_manager->index_sync_queue();
		ElasticPress\Elasticsearch::factory()->refresh_indices();

		// Make sure we're starting with an empty queue.
		$this->assertEmpty( ElasticPress\Indexables::factory()->get( 'post' )->sync_manager->get_sync_queue() );

		// Turn on the filter to kill syncing.
		add_filter( 'ep_post_sync_kill', '__return_true' );

		update_post_meta( $post_id, 'test_key', 123 );

		// Make sure sync queue is still empty when meta is updated for
		// an existing post.
		$this->assertEmpty( ElasticPress\Indexables::factory()->get( 'post' )->sync_manager->get_sync_queue() );

		wp_insert_post(
			[
				'post_type'   => 'ep_test',
				'post_status' => 'publish',
			]
		);

		// Make sure sync queue is still empty when a new post is added.
		$this->assertEmpty( ElasticPress\Indexables::factory()->get( 'post' )->sync_manager->get_sync_queue() );

		remove_filter( 'ep_post_sync_kill', '__return_true' );

		// Now verify the queue when this filter is not enabled.
		update_post_meta( $post_id, 'test_key', 456 );

		$this->assertNotEmpty( ElasticPress\Indexables::factory()->get( 'post' )->sync_manager->get_sync_queue() );

		// Flush the queues.
		ElasticPress\Indexables::factory()->get( 'post' )->sync_manager->index_sync_queue();
		ElasticPress\Elasticsearch::factory()->refresh_indices();
	}

	/**
	 * Tests additional logic with the post sync queue.
	 *
	 * @return void
	 * @group  post
	 */
	public function testPostSyncQueuePermissions() {

		// Create a post sync it.
		$post_id = $this->ep_factory->post->create();

		ElasticPress\Indexables::factory()->get( 'post' )->sync_manager->index_sync_queue();
		ElasticPress\Elasticsearch::factory()->refresh_indices();

		// Make sure we're starting with an empty queue.
		$this->assertEmpty( ElasticPress\Indexables::factory()->get( 'post' )->sync_manager->get_sync_queue() );

		// Test user permissions. We'll tell WP the user is not allowed
		// to edit the post we created at the top of this function.
		$map_meta_cap_callback = function( $caps, $cap, $user_id, $args ) use ( $post_id ) {

			if ( 'edit_post' === $cap && is_array( $args ) && ! empty( $args ) && $post_id === $args[0] ) {
				$caps = [ 'do_not_allow' ];
			}

			return $caps;
		};

		add_filter( 'map_meta_cap', $map_meta_cap_callback, 10, 4 );

		// Try deleting the post.
		ElasticPress\Indexables::factory()->get( 'post' )->sync_manager->action_delete_post( $post_id );
		ElasticPress\Indexables::factory()->get( 'post' )->sync_manager->index_sync_queue();
		ElasticPress\Elasticsearch::factory()->refresh_indices();

		// Verify we can still get it from ES.
		$document = ElasticPress\Indexables::factory()->get( 'post' )->get( $post_id );

		$this->assertTrue( is_array( $document ) );
		$this->assertSame( $post_id, $document['post_id'] );

		$post_title = $document['post_title'];

		// Try updating the post title.
		wp_update_post(
			[
				'ID'         => $post_id,
				'post_title' => 'New Post Title',
			]
		);
		ElasticPress\Elasticsearch::factory()->refresh_indices();

		// Verify the old title is still there.
		$document = ElasticPress\Indexables::factory()->get( 'post' )->get( $post_id );

		$this->assertTrue( is_array( $document ) );
		$this->assertSame( $post_title, $document['post_title'] );

		// Turn off the map_meta_cap filter and verify everything is flowing
		// through to ES.
		remove_filter( 'map_meta_cap', $map_meta_cap_callback, 10, 4 );

		// Try updating the post title.
		wp_update_post(
			[
				'ID'         => $post_id,
				'post_title' => 'New Post Title',
			]
		);
		ElasticPress\Indexables::factory()->get( 'post' )->sync_manager->index_sync_queue();
		ElasticPress\Elasticsearch::factory()->refresh_indices();

		// Verify the new title is there.
		$document = ElasticPress\Indexables::factory()->get( 'post' )->get( $post_id );

		$this->assertSame( 'New Post Title', $document['post_title'] );

		// Delete it, make sure it's gone.
		ElasticPress\Indexables::factory()->get( 'post' )->sync_manager->action_delete_post( $post_id );
		ElasticPress\Indexables::factory()->get( 'post' )->sync_manager->index_sync_queue();
		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$document = ElasticPress\Indexables::factory()->get( 'post' )->get( $post_id );

		$this->assertEmpty( $document );
	}

	/**
	 * Test prepare_date_terms function
	 *
	 * @return void
	 * @group  post
	 */
	public function testPostPrepareDateTerms() {
		$date = new \DateTime( '2021-04-11 23:58:12' );

		$return_prepare_date_terms = ElasticPress\Indexables::factory()->get( 'post' )->prepare_date_terms( $date->format( 'Y-m-d H:i:s' ) );

		$this->assertIsArray( $return_prepare_date_terms );

		$this->assertArrayHasKey( 'year', $return_prepare_date_terms );
		$this->assertEquals( $date->format( 'Y' ), $return_prepare_date_terms['year'] );

		$this->assertArrayHasKey( 'month', $return_prepare_date_terms );
		$this->assertEquals( $date->format( 'm' ), $return_prepare_date_terms['month'] );

		$this->assertArrayHasKey( 'week', $return_prepare_date_terms );
		$this->assertEquals( $date->format( 'W' ), $return_prepare_date_terms['week'] );

		$this->assertArrayHasKey( 'dayofyear', $return_prepare_date_terms );
		$this->assertEquals( $date->format( 'z' ), $return_prepare_date_terms['dayofyear'] );

		$this->assertArrayHasKey( 'day', $return_prepare_date_terms );
		$this->assertEquals( $date->format( 'd' ), $return_prepare_date_terms['day'] );

		$this->assertArrayHasKey( 'dayofweek', $return_prepare_date_terms );
		$this->assertEquals( $date->format( 'w' ), $return_prepare_date_terms['dayofweek'] );

		$this->assertArrayHasKey( 'dayofweek_iso', $return_prepare_date_terms );
		$this->assertEquals( $date->format( 'N' ), $return_prepare_date_terms['dayofweek_iso'] );

		$this->assertArrayHasKey( 'hour', $return_prepare_date_terms );
		$this->assertEquals( $date->format( 'H' ), $return_prepare_date_terms['hour'] );

		$this->assertArrayHasKey( 'minute', $return_prepare_date_terms );
		$this->assertEquals( $date->format( 'i' ), $return_prepare_date_terms['minute'] );

		$this->assertArrayHasKey( 'second', $return_prepare_date_terms );
		$this->assertEquals( $date->format( 's' ), $return_prepare_date_terms['second'] );

		$this->assertArrayHasKey( 'm', $return_prepare_date_terms );
		$this->assertEquals( $date->format( 'Ym' ), $return_prepare_date_terms['m'] );

		$return_prepare_date_terms = ElasticPress\Indexables::factory()->get( 'post' )->prepare_date_terms( '' );

		$this->assertIsArray( $return_prepare_date_terms );

		$this->assertArrayHasKey( 'year', $return_prepare_date_terms );
		$this->assertArrayHasKey( 'month', $return_prepare_date_terms );
		$this->assertArrayHasKey( 'week', $return_prepare_date_terms );
		$this->assertArrayHasKey( 'dayofyear', $return_prepare_date_terms );
		$this->assertArrayHasKey( 'day', $return_prepare_date_terms );
		$this->assertArrayHasKey( 'dayofweek', $return_prepare_date_terms );
		$this->assertArrayHasKey( 'dayofweek_iso', $return_prepare_date_terms );
		$this->assertArrayHasKey( 'hour', $return_prepare_date_terms );
		$this->assertArrayHasKey( 'minute', $return_prepare_date_terms );
		$this->assertArrayHasKey( 'second', $return_prepare_date_terms );
		$this->assertArrayHasKey( 'm', $return_prepare_date_terms );
	}

	/**
	 * Test when we perform a Tax Query with Id's for the category taxonomy cat id is used and cat slug is not.
	 *
	 * @return void
	 * @group  post
	 */
	public function testTaxQueryWithCategoryId() {
		$cat = wp_create_category( 'test category' );

		$query = new \WP_Query();

		$post = new \ElasticPress\Indexable\Post\Post();

		$args = $post->format_args(
			[
				'post_type'    => 'post',
				'post_status'  => 'public',
				'ep_integrate' => true,
				'tax_query'    => array(
					array(
						'taxonomy' => 'category',
						'terms'    => array( $cat ),
						'field'    => 'term_id',
						'operator' => 'in',
					),
				),
			],
			$query
		);

		$this->assertCount( 1, $args['post_filter']['bool']['must'][0]['bool']['must'] );
		$this->assertArrayHasKey( 'terms.category.term_id', $args['post_filter']['bool']['must'][0]['bool']['must'][0]['terms'] );
		$this->assertContains( $cat, $args['post_filter']['bool']['must'][0]['bool']['must'][0]['terms']['terms.category.term_id'] );
	}

	/**
	 * Test if EP updates all posts when `delete_metadata()` is called with `$delete_all = true`
	 *
	 * @group  post
	 */
	public function testDeleteAllMetadata() {
		$this->ep_factory->post->create(
			array(
				'post_title' => 'one',
				'meta_input' => array(
					'test_key1' => 'lorem',
					'test_key2' => 'ipsum',
				),
			)
		);
		$this->ep_factory->post->create(
			array(
				'post_title' => 'two',
				'meta_input' => array(
					'test_key1' => 'lorem',
					'test_key2' => 'ipsum',
				),
			)
		);

		delete_metadata( 'post', null, 'test_key1', 'lorem', true );

		ElasticPress\Indexables::factory()->get( 'post' )->sync_manager->index_sync_queue();
		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$query = new \WP_Query(
			array(
				'post_type'    => 'post',
				'ep_integrate' => true,
				'meta_key'     => 'test_key1',
				'meta_value'   => 'lorem',
			)
		);

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( $query->found_posts, 0 );

		$query = new \WP_Query(
			array(
				'post_type'    => 'post',
				'ep_integrate' => true,
				'meta_key'     => 'test_key2',
				'meta_value'   => 'ipsum',
			)
		);

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( $query->found_posts, 2 );
	}

	/**
	 * Test integration with Post Queries.
	 */
	public function testIntegrateSearchQueries() {
		$this->assertTrue( $this->get_feature()->integrate_search_queries( true, null ) );
		$this->assertFalse( $this->get_feature()->integrate_search_queries( false, null ) );

		$query = new \WP_Query(
			[
				'ep_integrate' => false,
			]
		);

		$this->assertFalse( $this->get_feature()->integrate_search_queries( true, $query ) );

		$query = new \WP_Query(
			[
				'ep_integrate' => 0,
			]
		);

		$this->assertFalse( $this->get_feature()->integrate_search_queries( true, $query ) );

		$query = new \WP_Query(
			[
				'ep_integrate' => 'false',
			]
		);

		$this->assertFalse( $this->get_feature()->integrate_search_queries( true, $query ) );

		$query = new \WP_Query(
			[
				's' => 'post',
			]
		);

		$this->assertTrue( $this->get_feature()->integrate_search_queries( false, $query ) );
	}

	/**
	 * Test if inserting a post and deleting another one in the thread works as expected.
	 */
	public function testInsertPostAndDeleteAnother() {
		$post_to_be_deleted = $this->ep_factory->post->create( [ 'post_title' => 'To be deleted' ] );

		$new_post_id = wp_insert_post(
			[
				'post_status' => 'publish',
				'post_title'  => 'New Post ' . time(),
			]
		);

		wp_delete_post( $post_to_be_deleted, true );

		\ElasticPress\Indexables::factory()->get( 'post' )->sync_manager->index_sync_queue();
		\ElasticPress\Elasticsearch::factory()->refresh_indices();

		$query = new \WP_Query(
			[
				'ep_integrate' => true,
				'post__in'     => array( $post_to_be_deleted, $new_post_id ),
			]
		);

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, $query->found_posts );
		$this->assertEquals( $query->posts[0]->ID, $new_post_id );
	}

	/**
	 * Tests term deletion applied to posts
	 *
	 * @return void
	 * @group  post
	 */
	public function testPostDeletedTerm() {
		$cat = wp_create_category( 'test category' );
		$tag = wp_insert_category(
			[
				'taxonomy' => 'post_tag',
				'cat_name' => 'test-tag',
			]
		);

		$post_id = $this->ep_factory->post->create(
			array(
				'tags_input'    => array( $tag ),
				'post_category' => array( $cat ),
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$document = ElasticPress\Indexables::factory()->get( 'post' )->get( $post_id );
		$this->assertNotEmpty( $document['terms']['category'] );
		$this->assertNotEmpty( $document['terms']['post_tag'] );

		ElasticPress\Indexables::factory()->get( 'post' )->sync_manager->reset_sync_queue();

		wp_delete_term( $tag, 'post_tag' );
		wp_delete_term( $cat, 'category' );

		ElasticPress\Indexables::factory()->get( 'post' )->sync_manager->index_sync_queue();
		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$document = ElasticPress\Indexables::factory()->get( 'post' )->get( $post_id );
		// Category will fallback to Uncategorized.
		$this->assertNotContains( $cat, wp_list_pluck( $document['terms']['category'], 'term_id' ) );
		$this->assertArrayNotHasKey( 'post_tag', $document['terms'] );
	}

	/**
	 * Tests term edition applied to posts
	 *
	 * @return void
	 * @group  post
	 */
	public function testPostEditedTerm() {
		$post_id = $this->ep_factory->post->create(
			array(
				'tags_input' => array( 'test-tag' ),
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$test_tag = get_term_by( 'name', 'test-tag', 'post_tag' );
		wp_update_term(
			$test_tag->term_id,
			'post_tag',
			[
				'slug' => 'different-tag-slug',
				'name' => 'Different Tag Name',
			]
		);

		ElasticPress\Indexables::factory()->get( 'post' )->sync_manager->index_sync_queue();
		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$document = ElasticPress\Indexables::factory()->get( 'post' )->get( $post_id );
		$this->assertEquals( 'different-tag-slug', $document['terms']['post_tag'][0]['slug'] );
		$this->assertEquals( 'Different Tag Name', $document['terms']['post_tag'][0]['name'] );
	}

	/**
	 * Tests parent term edition when child term is attached to post
	 *
	 * @return void
	 * @group  post
	 */
	public function testParentEditedTerm() {
		$post = $this->ep_factory->post->create_and_get();

		$tax_name = rand_str( 32 );
		register_taxonomy( $tax_name, $post->post_type, array( 'label' => $tax_name ) );
		register_taxonomy_for_object_type( $tax_name, $post->post_type );

		$term_1_name = rand_str( 32 );
		$term_1      = wp_insert_term( $term_1_name, $tax_name );

		$term_2_name = rand_str( 32 );
		$term_2      = wp_insert_term( $term_2_name, $tax_name, array( 'parent' => $term_1['term_id'] ) );

		wp_set_object_terms( $post->ID, array( $term_2['term_id'] ), $tax_name, true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$test_tag = get_term_by( 'id', $term_1['term_id'], $tax_name );

		wp_update_term(
			$test_tag->term_id,
			$tax_name,
			[
				'slug' => 'parent-term',
				'name' => 'Parent Term',
			]
		);

		ElasticPress\Indexables::factory()->get( 'post' )->sync_manager->index_sync_queue();
		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$document = ElasticPress\Indexables::factory()->get( 'post' )->get( $post->ID );
		$this->assertEquals( 'parent-term', $document['terms'][ $tax_name ][1]['slug'] );
		$this->assertEquals( 'Parent Term', $document['terms'][ $tax_name ][1]['name'] );
	}

	/**
	 * Tests post without meta value.
	 *
	 * @return void
	 */
	public function testMetaWithoutValue() {

		$this->ep_factory->post->create_many( 2, array( 'meta_input' => array( 'test_key' => '' ) ) );
		$this->ep_factory->post->create();

		$expected_result = '2';

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		// Make sure WordPress returns only 2 posts.
		$args  = array(
			'meta_query' => array(
				array(
					'key' => 'test_key',
				),
			),
		);
		$query = new \WP_Query( $args );

		$this->assertEquals( $expected_result, $query->post_count );
		$this->assertNull( $query->elasticsearch_success );

		// Make sure ElasticPress returns only 2 posts when meta query is set
		$args  = array(
			'ep_integrate' => true,
			'meta_query'   => array(
				array(
					'key' => 'test_key',
				),
			),
		);
		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( $expected_result, $query->post_count );

		// Make sure ElasticPress returns only 2 posts when meta key is set
		$args  = array(
			'ep_integrate' => true,
			'meta_key'     => 'test_key',
		);
		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( $expected_result, $query->post_count );
	}

	/**
	 * Test the get_search_algorithm implementation
	 */
	public function testGetSearchAlgorithm() {
		/**
		 * Test default search algorithm
		 */
		$version_40 = \ElasticPress\SearchAlgorithms::factory()->get( '4.0' );

		$post_indexable   = \ElasticPress\Indexables::factory()->get( 'post' );
		$search_algorithm = $post_indexable->get_search_algorithm( '', [], [] );

		$this->assertSame( $version_40, $search_algorithm );

		/**
		 * Test setting a diffent algorithm through the `ep_search_algorithm_version` filter
		 */
		$version_35 = \ElasticPress\SearchAlgorithms::factory()->get( '3.5' );

		$set_version_35 = function() {
			return '3.5';
		};

		add_filter( 'ep_search_algorithm_version', $set_version_35 );

		$search_algorithm = $post_indexable->get_search_algorithm( '', [], [] );
		$this->assertSame( $version_35, $search_algorithm );

		remove_filter( 'ep_search_algorithm_version', $set_version_35 );

		/**
		 * Test setting a non-existent algorithm through the `ep_search_algorithm_version` filter
		 * It should use `basic`
		 */
		$basic = \ElasticPress\SearchAlgorithms::factory()->get( 'basic' );

		$set_non_existent_version = function() {
			return 'foobar';
		};

		add_filter( 'ep_search_algorithm_version', $set_non_existent_version );

		$search_algorithm = $post_indexable->get_search_algorithm( '', [], [] );
		$this->assertSame( $basic, $search_algorithm );

		remove_filter( 'ep_search_algorithm_version', $set_non_existent_version );

		/**
		 * Test the `ep_{$indexable_slug}_search_algorithm` filter
		 */
		add_filter( 'ep_post_search_algorithm', $set_version_35 );

		$search_algorithm = $post_indexable->get_search_algorithm( '', [], [] );
		$this->assertSame( $version_35, $search_algorithm );
	}

	/**
	 * Tests is_meta_allowed
	 *
	 * @return void
	 * @group  is_meta_allowed
	 */
	public function testIsMetaAllowed() {
		$meta_not_protected          = 'meta';
		$meta_not_protected_excluded = 'meta_excluded';
		$meta_protected              = '_meta';
		$meta_protected_allowed      = '_meta_allowed';

		add_filter(
			'ep_prepare_meta_allowed_keys',
			function( $allowed_metakeys ) {
				return array_merge( $allowed_metakeys, [ 'meta' ] );
			}
		);
		add_filter(
			'ep_prepare_meta_allowed_protected_keys',
			function () use ( $meta_protected_allowed ) {
				return [ $meta_protected_allowed ];
			}
		);
		add_filter(
			'ep_prepare_meta_excluded_public_keys',
			function () use ( $meta_not_protected_excluded ) {
				return [ $meta_not_protected_excluded ];
			}
		);

		$indexable = \ElasticPress\Indexables::factory()->get( 'post' );
		$post      = new \WP_Post( (object) [ 'post_type' => 'post' ] );

		$this->assertTrue( $indexable->is_meta_allowed( $meta_not_protected, $post ) );
		$this->assertTrue( $indexable->is_meta_allowed( $meta_protected_allowed, $post ) );

		$this->assertFalse( $indexable->is_meta_allowed( $meta_not_protected_excluded, $post ) );
		$this->assertFalse( $indexable->is_meta_allowed( $meta_protected, $post ) );
	}

	/**
	 * Tests get_distinct_meta_field_keys
	 *
	 * @return void
	 * @group  post
	 */
	public function testGetDistinctMetaFieldKeys() {
		$indexable = \ElasticPress\Indexables::factory()->get( 'post' );

		$this->ep_factory->post->create( array( 'meta_input' => array( 'test_key1' => '' ) ) );
		$this->ep_factory->post->create( array( 'meta_input' => array( 'test_key2' => '' ) ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$distinct_meta_field_keys = $indexable->get_distinct_meta_field_keys();

		$this->assertIsArray( $distinct_meta_field_keys );
		$this->assertContains( 'test_key1', $distinct_meta_field_keys );
		$this->assertContains( 'test_key2', $distinct_meta_field_keys );
	}

	/**
	 * Tests get_all_distinct_values
	 *
	 * @return void
	 * @group  post
	 */
	public function testGetAllDistinctValues() {
		$indexable = \ElasticPress\Indexables::factory()->get( 'post' );

		$this->ep_factory->post->create( array( 'meta_input' => array( 'test_key1' => 'foo' ) ) );
		$this->ep_factory->post->create( array( 'meta_input' => array( 'test_key1' => 'bar' ) ) );
		$this->ep_factory->post->create( array( 'meta_input' => array( 'test_key1' => 'foobar' ) ) );

		$this->ep_factory->post->create( array( 'meta_input' => array( 'test_key2' => 'lorem' ) ) );
		$this->ep_factory->post->create( array( 'meta_input' => array( 'test_key2' => 'ipsum' ) ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$distinct_values = $indexable->get_all_distinct_values( 'meta.test_key1.raw' );

		$this->assertCount( 3, $distinct_values );
		$this->assertContains( 'foo', $distinct_values );
		$this->assertContains( 'bar', $distinct_values );
		$this->assertContains( 'foobar', $distinct_values );

		$distinct_values = $indexable->get_all_distinct_values( 'meta.test_key1.raw', 1 );
		$this->assertCount( 1, $distinct_values );
		$this->assertContains( 'bar', $distinct_values );

		$change_bucket_size = function( $count, $field ) {
			return ( 'meta.test_key1.raw' === $field ) ? 1 : $count;
		};
		add_filter( 'ep_post_all_distinct_values', $change_bucket_size, 10, 2 );

		$distinct_values_1 = $indexable->get_all_distinct_values( 'meta.test_key1.raw' );
		$this->assertCount( 1, $distinct_values_1 );
		$this->assertContains( 'bar', $distinct_values_1 );

		$distinct_values_2 = $indexable->get_all_distinct_values( 'meta.test_key2.raw' );
		$this->assertCount( 2, $distinct_values_2 );
		$this->assertContains( 'lorem', $distinct_values_2 );
		$this->assertContains( 'ipsum', $distinct_values_2 );
	}

	/**
	 * Tests search term wrapped in html tags.
	 */
	public function testHighlightTags() {

		ElasticPress\Features::factory()->update_feature(
			'search',
			array(
				'active'            => true,
				'highlight_enabled' => '1',
			)
		);

		$this->ep_factory->post->create(
			array(
				'post_content' => 'test content',
				'post_title'   => 'test title',
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args  = array(
			's' => 'test',
		);
		$query = new \WP_Query( $args );

		$this->assertStringContainsString( '<mark class=\'ep-highlight\'>test</mark>', $query->posts[0]->post_content );
		$this->assertStringContainsString( '<mark class=\'ep-highlight\'>test</mark>', $query->posts[0]->post_title );

		// bypass the highlighting the search term
		add_filter( 'ep_highlight_should_add_clause', '__return_false' );

		$query = new \WP_Query( $args );

		$this->assertEquals( 'test content', $query->posts[0]->post_content );
		$this->assertEquals( 'test title', $query->posts[0]->post_title );
	}

	/**
	 * Tests the `ep_bypass_exclusion_from_search` filter
	 */
	public function testExcludeFromSearchQueryBypassFilter() {
		$this->ep_factory->post->create_many(
			2,
			array(
				'post_content' => 'find me in search',
				'meta_input'   => array( 'ep_exclude_from_search' => false ),
			)
		);
		$this->ep_factory->post->create(
			array(
				'post_content' => 'exlcude from search',
				'meta_input'   => array( 'ep_exclude_from_search' => true ),
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$bypass = function( $should_bypass, $query ) {
			$this->assertInstanceOf( \WP_Query::class, $query );
			return true;
		};
		add_filter( 'ep_bypass_exclusion_from_search', $bypass, 10, 2 );

		$args  = array(
			's' => 'search',
		);
		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 3, $query->post_count );

		remove_filter( 'ep_bypass_exclusion_from_search', $bypass, 10, 2 );

		$args  = array(
			's' => 'search',
		);
		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 2, $query->post_count );
	}

	/**
	 * Tests query doesn't return the post in if `ep_exclude_from_search` meta is set.
	 */
	public function testExcludeFromSearchQuery() {

		$this->ep_factory->post->create_many(
			2,
			array(
				'post_content' => 'find me in search',
				'meta_input'   => array( 'ep_exclude_from_search' => false ),
			)
		);
		$this->ep_factory->post->create(
			array(
				'post_content' => 'exlcude from search',
				'meta_input'   => array( 'ep_exclude_from_search' => true ),
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args  = array(
			's' => 'search',
		);
		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 2, $query->post_count );
	}

	/**
	 * Tests that post meta value should be empty when it is not set.
	 *
	 * @since 4.6.1
	 * @group post
	 */
	public function testMetaValueNotSet() {
		$post_ids    = array();
		$post_ids[0] = $this->ep_factory->post->create(
			array(
				'post_content' => 'find me in search',
			)
		);
		$post_ids[1] = $this->ep_factory->post->create(
			array(
				'post_content' => 'exlcude from search',
				'meta_input'   => array( 'ep_exclude_from_search' => true ),
			)
		);

		$this->assertEmpty( get_post_meta( $post_ids[0], 'ep_exclude_from_search', true ) );
		$this->assertEquals( 1, get_post_meta( $post_ids[1], 'ep_exclude_from_search', true ) );
	}

	/**
	 * Tests search term is wrapped in html tag with custom class
	 */
	public function testHighlightTagsWithCustomClass() {

		ElasticPress\Features::factory()->update_feature(
			'search',
			array(
				'active'            => true,
				'highlight_enabled' => '1',
			)
		);

		$this->ep_factory->post->create(
			array(
				'post_content' => 'test content',
				'post_title'   => 'test title',
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		add_filter(
			'ep_highlighting_class',
			function( $class ) {
				return 'my-custom-class';
			}
		);

		$args  = array(
			's' => 'test',
		);
		$query = new \WP_Query( $args );

		$this->assertStringContainsString( '<mark class=\'my-custom-class\'>test</mark>', $query->posts[0]->post_content );
		$this->assertStringContainsString( '<mark class=\'my-custom-class\'>test</mark>', $query->posts[0]->post_title );

	}

	/**
	 * Tests search term is wrapped in html tag only for tite.
	 */
	public function testHighlightTagsOnlyForTitle() {

		ElasticPress\Features::factory()->update_feature(
			'search',
			array(
				'active'            => true,
				'highlight_enabled' => '1',
			)
		);

		$this->ep_factory->post->create(
			array(
				'post_content' => 'test content',
				'post_title'   => 'test title',
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		add_filter(
			'ep_highlighting_fields',
			function( $fields ) {
				return array( 'post_title' );
			}
		);

		$args  = array(
			's' => 'test',
		);
		$query = new \WP_Query( $args );

		$this->assertStringContainsString( '<mark class=\'ep-highlight\'>test</mark>', $query->posts[0]->post_title );
		$this->assertStringNotContainsString( '<mark class=\'ep-highlight\'>test</mark>', $query->posts[0]->post_content );
	}

	/**
	 * Test get_the_excerpt() has HTML tags when highlight_excerpt is enabled.
	 */
	public function testExcerptHasHiglightHTMLTags() {

		ElasticPress\Features::factory()->update_feature(
			'search',
			array(
				'active'            => true,
				'highlight_enabled' => '1',
				'highlight_excerpt' => '1',
			)
		);

		$this->ep_factory->post->create( array( 'post_excerpt' => 'test excerpt' ) );
		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args  = array(
			's' => 'test',
		);
		$query = new \WP_Query( $args );

		$expected_result = '<mark class=\'ep-highlight\'>test</mark> excerpt';
		$this->assertEquals( $expected_result, $query->posts[0]->post_excerpt );
		$this->assertEquals( $expected_result, get_the_excerpt( $query->posts[0] ) );

		// test post without excerpt
		$this->ep_factory->post->create(
			array(
				'post_content' => 'new post',
				'post_excerpt' => '',
			)
		);
		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args  = array(
			's' => 'new',
		);
		$query = new \WP_Query( $args );

		// using StringContainsString because the_content filter adds the break line.
		$this->assertStringContainsString( '<mark class=\'ep-highlight\'>new</mark> post', get_the_excerpt( $query->posts[0] ) );
	}

	/**
	 * Tests highlight parameters are not added to the query when search term is empty.
	 */
	public function testHighlightTagsNotSetWhenSearchIsEmpty() {

		ElasticPress\Features::factory()->update_feature(
			'search',
			array(
				'active'            => true,
				'highlight_enabled' => '1',
			)
		);

		$this->ep_factory->post->create( array( 'post_content' => 'test content' ) );
		ElasticPress\Elasticsearch::factory()->refresh_indices();

		add_action(
			'pre_http_request',
			function( $preempt, $parsed_args, $url ) {

				$body = json_decode( $parsed_args['body'], true );
				$this->assertArrayNotHasKey( 'highlight', $body );
				return $preempt;
			},
			10,
			3
		);

		$args  = array(
			's'            => '',
			'ep_integrate' => true,
		);
		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
	}

	/**
	 * Tests exclude from search only works on the search query.
	 */
	public function testExcludeFromSearchOnlyRunOnSearchQuery() {

		$this->ep_factory->post->create_many(
			5,
			array(
				'post_content' => 'test post',
				'meta_input'   => array( 'ep_exclude_from_search' => true ),
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args  = array(
			'ep_integrate' => true,
		);
		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 5, $query->post_count );

		$args  = array(
			's' => 'test',
		);
		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 0, $query->post_count );
	}

	/**
	 * Test exclude from search when meta query is set.
	 */
	public function testExcludeFromSearchQueryWithMetaQuery() {

		$this->ep_factory->post->create(
			array(
				'post_content' => 'test post',
				'meta_input'   => array(
					'ep_exclude_from_search' => true,
					'test_key'               => 'test',
				),
			)
		);

		$this->ep_factory->post->create(
			array(
				'post_content' => 'test post',
				'meta_input'   => array(
					'ep_exclude_from_search' => false,
					'test_key'               => 'test',
				),
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args  = array(
			's'          => 'test',
			'meta_query' => array(
				array(
					'key'   => 'test_key',
					'value' => 'test',
				),
			),
		);
		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 1, $query->post_count );

	}

	/**
	 * Test exclude from search filter doesn't apply for admin quries.
	 *
	 * @since 4.4.0
	 */
	public function testExcludeFromSearchFilterDoesNotApplyForAdminQueries() {

		set_current_screen( 'edit.php' );
		$this->assertTrue( is_admin() );

		$this->ep_factory->post->create_many(
			5,
			array(
				'post_content' => 'test post',
				'meta_input'   => array( 'ep_exclude_from_search' => true ),
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args  = array(
			's' => 'test post',
		);
		$query = new \WP_Query( $args );

		$this->assertNull( $query->elasticsearch_success );
		$this->assertEquals( 5, $query->post_count );
	}

	/**
	 * Tests get_distinct_meta_field_keys_db
	 *
	 * @since 4.4.0
	 * @group post
	 */
	public function testGetDistinctMetaFieldKeysDb() {
		global $wpdb;

		$indexable = \ElasticPress\Indexables::factory()->get( 'post' );

		$meta_keys = $wpdb->get_col( "SELECT DISTINCT meta_key FROM {$wpdb->postmeta} ORDER BY meta_key" );
		$this->assertSame( $meta_keys, $indexable->get_distinct_meta_field_keys_db( true ) );

		// Make sure it works if no allowed protected key is found
		add_filter( 'ep_prepare_meta_allowed_protected_keys', '__return_empty_array' );
		$this->assertSame( $meta_keys, $indexable->get_distinct_meta_field_keys_db( true ) );
		$this->assertEmpty( $wpdb->last_error );
		remove_filter( 'ep_prepare_meta_allowed_protected_keys', '__return_empty_array' );

		/**
		 * Test the `ep_post_pre_meta_keys_db` filter
		 */
		$return_custom_array = function() {
			return [ 'totally_custom_key' ];
		};
		add_filter( 'ep_post_pre_meta_keys_db', $return_custom_array );

		// It should not send any new SQL query
		$num_queries = $wpdb->num_queries;
		$this->assertGreaterThan( 0, $num_queries );

		$this->assertSame( [ 'totally_custom_key' ], $indexable->get_distinct_meta_field_keys_db( true ) );
		$this->assertSame( $num_queries, $wpdb->num_queries );

		remove_filter( 'ep_post_pre_meta_keys_db', $return_custom_array );

		/**
		 * Test the `ep_post_pre_meta_keys_db` filter
		 */
		$return_custom_array = function( $meta_keys ) {
			return array_merge( $meta_keys, [ 'custom_key' ] );
		};
		add_filter( 'ep_post_meta_keys_db', $return_custom_array );

		$this->assertSame( array_merge( $meta_keys, [ 'custom_key' ] ), $indexable->get_distinct_meta_field_keys_db( true ) );
	}

	/**
	 * Tests get_distinct_meta_field_keys_db_per_post_type
	 *
	 * @since 4.4.0
	 * @group post
	 * @expectedIncorrectUsage ElasticPress\Indexable\Post\Post::get_distinct_meta_field_keys_db_per_post_type
	 */
	public function testGetDistinctMetaFieldKeysDbPerPostType() {
		global $wpdb;

		$indexable = \ElasticPress\Indexables::factory()->get( 'post' );

		// Without setting the correct screen, this should throw a _doing_it_wrong
		$this->assertSame( [], $indexable->get_distinct_meta_field_keys_db_per_post_type( 'ep_test' ) );

		$this->setupDistinctMetaFieldKeysDbPerPostType();

		$meta_keys = [ '_private_key', 'test_key1', 'test_key2' ];
		$this->assertSame( $meta_keys, $indexable->get_distinct_meta_field_keys_db_per_post_type( 'ep_test' ) );

		/**
		 * Test the `ep_post_pre_meta_keys_db_per_post_type` filter
		 */
		$return_custom_array = function( $meta_keys, $post_type ) {
			$this->assertSame( $post_type, 'ep_test' );
			return [ 'totally_custom_key' ];
		};
		add_filter( 'ep_post_pre_meta_keys_db_per_post_type', $return_custom_array, 10, 2 );

		// It should not send any new SQL query
		$num_queries = $wpdb->num_queries;
		$this->assertGreaterThan( 0, $num_queries );

		$this->assertSame( [ 'totally_custom_key' ], $indexable->get_distinct_meta_field_keys_db_per_post_type( 'ep_test' ) );
		$this->assertSame( $num_queries, $wpdb->num_queries );

		remove_filter( 'ep_post_pre_meta_keys_db_per_post_type', $return_custom_array );

		/**
		 * Test the `ep_post_meta_keys_db_per_post_type` filter
		 */
		$return_custom_array = function( $meta_keys, $post_type ) {
			$this->assertSame( $post_type, 'ep_test' );
			return array_merge( $meta_keys, [ 'custom_key' ] );
		};
		add_filter( 'ep_post_meta_keys_db_per_post_type', $return_custom_array, 10, 2 );

		$this->assertSame( array_merge( $meta_keys, [ 'custom_key' ] ), $indexable->get_distinct_meta_field_keys_db_per_post_type( 'ep_test' ) );
	}


	/**
	 * Tests the filters in get_lazy_post_type_ids
	 *
	 * @since 4.4.0
	 * @group post
	 */
	public function testGetLazyPostTypeIdsFilters() {
		$indexable = \ElasticPress\Indexables::factory()->get( 'post' );

		$this->setupDistinctMetaFieldKeysDbPerPostType();

		/**
		 * Test the `ep_post_meta_by_type_ids_per_page` and `ep_post_meta_by_type_number_of_pages` filters
		 */
		$custom_number_of_ids = function( $per_page, $post_type ) {
			$this->assertSame( 11000, $per_page );
			$this->assertSame( $post_type, 'ep_test' );
			return 1;
		};
		add_filter( 'ep_post_meta_by_type_ids_per_page', $custom_number_of_ids, 10, 2 );

		$custom_number_of_pages = function( $pages, $per_page, $post_type ) {
			$this->assertSame( 1, $per_page );
			$this->assertSame( $post_type, 'ep_test' );
			return 1;
		};
		add_filter( 'ep_post_meta_by_type_number_of_pages', $custom_number_of_pages, 10, 3 );

		// All meta keys from the first post
		$this->assertCount( 3, $indexable->get_distinct_meta_field_keys_db_per_post_type( 'ep_test' ) );
	}

	/**
	 * Tests get_indexable_meta_keys_per_post_type
	 *
	 * @since 4.4.0
	 * @group post
	 */
	public function testGetIndexableMetaKeysPerPostType() {
		ElasticPress\Screen::factory()->set_current_screen( 'status-report' );

		$indexable = \ElasticPress\Indexables::factory()->get( 'post' );

		$this->ep_factory->post->create(
			[
				'post_type'  => 'ep_test',
				'meta_input' => [
					'_private_key' => 'private-meta',
					'test_key1'    => 'meta value 1',
					'test_key2'    => 'meta value 2.1',
				],
			]
		);
		$this->ep_factory->post->create(
			[
				'post_type'  => 'ep_test_2',
				'meta_input' => [
					'test_key2' => 'meta value 2.2',
					'test_key3' => 'meta value 3',
				],
			]
		);

		$meta_keys = [ 'test_key1', 'test_key2' ];
		$this->assertEqualsCanonicalizing( $meta_keys, $indexable->get_indexable_meta_keys_per_post_type( 'ep_test' ) );

		$change_allowed_meta = function () {
			return [ 'test_key1' => 'meta value 1' ];
		};
		add_filter( 'ep_prepare_meta_data', $change_allowed_meta );

		$meta_keys = [ 'test_key1' ];
		$this->assertEqualsCanonicalizing( $meta_keys, $indexable->get_indexable_meta_keys_per_post_type( 'ep_test' ) );
	}

	/**
	 * Tests get_predicted_indexable_meta_keys
	 *
	 * @since 4.4.0
	 * @group post
	 */
	public function testGetPredictedIndexableMetaKeys() {
		$indexable = \ElasticPress\Indexables::factory()->get( 'post' );

		$this->ep_factory->post->create(
			[
				'post_type'  => 'ep_test',
				'meta_input' => [
					'_private_key' => 'private-meta',
					'test_key1'    => 'meta value 1',
					'test_key2'    => 'meta value 2.1',
				],
			]
		);
		$this->ep_factory->post->create(
			[
				'post_type'  => 'ep_test_2',
				'meta_input' => [
					'test_key2' => 'meta value 2.2',
					'test_key3' => 'meta value 3',
				],
			]
		);

		$meta_keys = [ 'test_key1', 'test_key2', 'test_key3' ];
		$this->assertEqualsCanonicalizing( $meta_keys, $indexable->get_predicted_indexable_meta_keys() );

		$change_allowed_meta = function () {
			return [ 'test_key1' => 'meta value 1' ];
		};
		add_filter( 'ep_prepare_meta_data', $change_allowed_meta );

		$meta_keys = [ 'test_key1' ];
		$this->assertEqualsCanonicalizing( $meta_keys, $indexable->get_predicted_indexable_meta_keys() );
	}

	/**
	 * Tests put_mapping method.
	 *
	 * @since 4.4.1
	 */
	public function testPutMappingThrowsError() {

		ElasticPress\Elasticsearch::factory()->delete_all_indices();
		$mapping = ElasticPress\Indexables::factory()->get( 'post' )->put_mapping();

		$this->assertTrue( $mapping );

		// Try to put mapping again to trigger error `resource_already_exists_exception`. Expect false as it defaults to return a bool
		$mapping = ElasticPress\Indexables::factory()->get( 'post' )->put_mapping();
		$this->assertFalse( $mapping );

		$mapping = ElasticPress\Indexables::factory()->get( 'post' )->put_mapping( 'raw' );
		$this->assertInstanceOf( 'WP_Error', $mapping );
		$this->assertEquals( 400, $mapping->get_error_code() );

		// Try to put mapping again to trigger WP_Error by providing an empty host.
		add_filter( 'ep_pre_request_host', '__return_empty_string' );
		$mapping = ElasticPress\Indexables::factory()->get( 'post' )->put_mapping( 'raw' );

		$this->assertInstanceOf( 'WP_Error', $mapping );
		$this->assertEquals( 'http_request_failed', $mapping->get_error_code() );
		remove_filter( 'ep_pre_request_host', '__return_empty_string' );
	}

	/**
	 * Utilitary function to setup data needed by some tests related to the `get_distinct_meta_field_keys_db_per_post_type` method
	 *
	 * @return void
	 */
	protected function setupDistinctMetaFieldKeysDbPerPostType() {
		ElasticPress\Screen::factory()->set_current_screen( 'status-report' );

		$this->ep_factory->post->create(
			[
				'post_type'  => 'ep_test',
				'meta_input' => [
					'_private_key' => 'private-meta',
					'test_key1'    => 'meta value 1',
					'test_key2'    => 'meta value 2.1',
				],
			]
		);
		$this->ep_factory->post->create(
			[
				'post_type'  => 'ep_test_2',
				'meta_input' => [
					'test_key2' => 'meta value 2.2',
					'test_key3' => 'meta value 3',
				],
			]
		);
	}

	/**
	 * Tests that deleting a thumbnail updates the meta value of all the linked indexable posts
	 *
	 * @since 4.5.0
	 */
	public function testDeletingThumbnailUpdateRelatedIndexablePost() {
		$product_id = $this->ep_factory->post->create(
			array(
				'post_type' => 'product',
			)
		);

		$thumbnail_id = $this->factory->attachment->create_object(
			'test.jpg',
			$product_id,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
			)
		);

		set_post_thumbnail( $product_id, $thumbnail_id );

		$thumbnail_id = get_post_thumbnail_id( $product_id );
		$this->assertEquals( $thumbnail_id, get_post_meta( $product_id, '_thumbnail_id', true ) );

		ElasticPress\Indexables::factory()->get( 'post' )->index( $product_id, true );
		ElasticPress\Elasticsearch::factory()->refresh_indices();

		wp_delete_attachment( $thumbnail_id, true );
		$this->assertEquals( '', get_post_meta( $product_id, '_thumbnail_id', true ) );

		$ep_post = ElasticPress\Indexables::factory()->get( 'post' )->get( $product_id );
		$this->assertArrayNotHasKey( '_thumbnail_id', $ep_post['meta'] );
	}


	/**
	 * Tests that deleting a thumbnail does not update the meta value of all the linked non-indexable posts
	 *
	 * @since 4.5.0
	 */
	public function testDeletingThumbnailShouldNotUpdateRelatedNonIndexablePost() {
		$product_id = $this->ep_factory->post->create(
			array(
				'post_type' => 'product',
			)
		);

		$thumbnail_id = $this->factory->attachment->create_object(
			'test.jpg',
			$product_id,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
			)
		);

		set_post_thumbnail( $product_id, $thumbnail_id );

		$thumbnail_id = get_post_thumbnail_id( $product_id );
		$this->assertEquals( $thumbnail_id, get_post_meta( $product_id, '_thumbnail_id', true ) );

		ElasticPress\Indexables::factory()->get( 'post' )->index( $product_id, true );
		ElasticPress\Elasticsearch::factory()->refresh_indices();

		// Remove product from indexable post types.
		add_filter(
			'ep_indexable_post_types',
			function( $post_types ) {
				unset( $post_types['product'] );
				return $post_types;
			}
		);

		wp_delete_attachment( $thumbnail_id, true );
		$this->assertEquals( '', get_post_meta( $product_id, '_thumbnail_id', true ) );

		$ep_post = ElasticPress\Indexables::factory()->get( 'post' )->get( $product_id );
		$this->assertArrayHasKey( '_thumbnail_id', $ep_post['meta'] );
	}

	/**
	 * Test that query with unsupported orderby does not use EP.
	 *
	 * @since 4.5.0
	 */
	public function testQueryWithUnSupportedOrderByDoesNotUseEP() {
		// test for post__in
		$query = new \WP_Query(
			array(
				'orderby'      => 'post__in',
				'ep_integrate' => true,
			)
		);
		$this->assertNull( $query->elasticsearch_success );

		// test for post__in with fallback to a title
		$query = new \WP_Query(
			array(
				'orderby'      => 'post__in title',
				'ep_integrate' => true,
			)
		);
		$this->assertNull( $query->elasticsearch_success );

		// test for post__in with fallback to a title and with different sort orders
		$query = new \WP_Query(
			array(
				'orderby'      => array(
					'post__in' => 'DESC',
					'title'    => 'ASC',
				),
				'ep_integrate' => true,
			)
		);
		$this->assertNull( $query->elasticsearch_success );

		// test for post__in with fallback to a title and without orders.
		$query = new \WP_Query(
			array(
				'orderby'      => array( 'post__in', 'title' ),
				'ep_integrate' => true,
			)
		);
		$this->assertNull( $query->elasticsearch_success );

		// test for post_name__in
		$query = new \WP_Query(
			array(
				'orderby'      => 'post_name__in',
				'ep_integrate' => true,
			)
		);
		$this->assertNull( $query->elasticsearch_success );

		// test for post_parent__in
		$query = new \WP_Query(
			array(
				'orderby'      => 'post_parent__in',
				'ep_integrate' => true,
			)
		);
		$this->assertNull( $query->elasticsearch_success );

		// test for parent
		$query = new \WP_Query(
			array(
				'orderby'      => 'parent',
				'ep_integrate' => true,
			)
		);
		$this->assertNull( $query->elasticsearch_success );
	}

	/**
	 * Test the `add_ngram_analyzer` method
	 *
	 * @todo Move this to a mock, as it is just inherited now
	 * @since 4.5.0
	 * @group post
	 */
	public function testAddNgramAnalyzer() {
		$post_indexable   = ElasticPress\Indexables::factory()->get( 'post' );
		$changed_mapping  = $post_indexable->add_ngram_analyzer( [] );
		$expected_mapping = [
			'settings' => [
				'analysis' => [
					'analyzer' => [
						'edge_ngram_analyzer' => [
							'type'      => 'custom',
							'tokenizer' => 'standard',
							'filter'    => [
								'lowercase',
								'edge_ngram',
							],
						],
					],
				],
			],
		];

		$this->assertSame( $expected_mapping, $changed_mapping );
	}

	/**
	 * Test the `add_term_suggest_field` method with the ES 7 mapping
	 *
	 * @since 4.5.0
	 * @group post
	 */
	public function testAddTermSuggestFieldEs7() {
		$post_indexable = ElasticPress\Indexables::factory()->get( 'post' );

		$original_mapping = [
			'mappings' => [
				'properties' => [
					'post_content' => [ 'type' => 'text' ],
				],
			],
		];
		$changed_mapping  = $post_indexable->add_term_suggest_field( $original_mapping );

		$expected_mapping = [
			'mappings' => [
				'properties' => [
					'post_content' => [ 'type' => 'text' ],
					'term_suggest' => [
						'type'            => 'text',
						'analyzer'        => 'edge_ngram_analyzer',
						'search_analyzer' => 'standard',
					],
				],
			],
		];

		$this->assertSame( $expected_mapping, $changed_mapping );
	}

	/**
	 * Test the `add_term_suggest_field` method with the ES 5 mapping
	 *
	 * @since 4.5.0
	 * @group post
	 */
	public function testAddTermSuggestFieldEs5() {
		$change_es_version = function() {
			return '5.6';
		};
		add_filter( 'ep_elasticsearch_version', $change_es_version );

		$post_indexable = ElasticPress\Indexables::factory()->get( 'post' );

		$original_mapping = [
			'mappings' => [
				'post' => [
					'properties' => [
						'post_content' => [ 'type' => 'text' ],
					],
				],
			],
		];
		$changed_mapping  = $post_indexable->add_term_suggest_field( $original_mapping );

		$expected_mapping = [
			'mappings' => [
				'post' => [
					'properties' => [
						'post_content' => [ 'type' => 'text' ],
						'term_suggest' => [
							'type'            => 'text',
							'analyzer'        => 'edge_ngram_analyzer',
							'search_analyzer' => 'standard',
						],
					],
				],
			],
		];

		$this->assertSame( $expected_mapping, $changed_mapping );
	}

	/**
	 * Test negative `menu_order` values.
	 *
	 * @since 4.6.0
	 * @group post
	 * @see https://github.com/10up/ElasticPress/issues/3440#issuecomment-1545446291
	 */
	public function testNegativeMenuOrder() {
		$post_negative = $this->ep_factory->post->create( array( 'menu_order' => -2 ) );
		$post_positive = $this->ep_factory->post->create( array( 'menu_order' => 1 ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$query = new \WP_Query(
			array(
				'ep_integrate' => true,
				'fields'       => 'ids',
				'post_type'    => 'post',
				'order'        => 'ASC',
				'orderby'      => 'menu_order',
			)
		);

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 2, count( $query->posts ) );
		$this->assertEquals( $post_negative, $query->posts[0] );
		$this->assertEquals( $post_positive, $query->posts[1] );
	}

	/**
	 * Test the `kill_sync_for_password_protected` method
	 *
	 * @since 4.6.0
	 * @group post
	 */
	public function testKillSyncForPasswordProtected() {
		$pw_post    = $this->ep_factory->post->create( [ 'post_password' => 'password' ] );
		$no_pw_post = $this->ep_factory->post->create( [] );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$sync_manager = ElasticPress\Indexables::factory()->get( 'post' )->sync_manager;

		$this->assertTrue( $sync_manager->kill_sync_for_password_protected( false, $pw_post ) );
		$this->assertFalse( $sync_manager->kill_sync_for_password_protected( false, $no_pw_post ) );

		/**
		 * Test the `ep_pre_kill_sync_for_password_protected` filter
		 */
		$dont_kill_pw_post = function ( $short_circuit, $skip, $object_id ) use ( $pw_post ) {
			$this->assertNull( $short_circuit );
			$this->assertFalse( $skip );
			$this->assertSame( $pw_post, $object_id );
			return false;
		};
		add_filter( 'ep_pre_kill_sync_for_password_protected', $dont_kill_pw_post, 10, 3 );
		$this->assertFalse( $sync_manager->kill_sync_for_password_protected( false, $pw_post ) );
	}

	/**
	 * Test if the mapping applies the ep_stop filter correctly
	 *
	 * @since 4.7.0
	 * @group post
	 */
	public function test_mapping_ep_stop_filter() {
		$indexable      = ElasticPress\Indexables::factory()->get( 'post' );
		$index_name     = $indexable->get_index_name();
		$settings       = ElasticPress\Elasticsearch::factory()->get_index_settings( $index_name );
		$index_settings = $settings[ $index_name ]['settings'];

		$this->assertContains( 'ep_stop', $index_settings['index.analysis.analyzer.default.filter'] );
		$this->assertSame( '_english_', $index_settings['index.analysis.filter.ep_stop.stopwords'] );

		$change_lang = function( $lang, $context ) {
			return 'filter_ep_stop' === $context ? '_arabic_' : $lang;
		};
		add_filter( 'ep_analyzer_language', $change_lang, 11, 2 );

		ElasticPress\Elasticsearch::factory()->delete_all_indices();
		$indexable->put_mapping();

		$settings       = ElasticPress\Elasticsearch::factory()->get_index_settings( $index_name );
		$index_settings = $settings[ $index_name ]['settings'];
		$this->assertSame( '_arabic_', $index_settings['index.analysis.filter.ep_stop.stopwords'] );
	}
}
