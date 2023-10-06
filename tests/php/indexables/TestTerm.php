<?php // phpcs:ignore
/**
 * Test term indexable functionality
 *
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress;

/**
 * Test term indexable class
 */
class TestTerm extends BaseTestCase {
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

		ElasticPress\Features::factory()->activate_feature( 'terms' );
		ElasticPress\Features::factory()->setup_features();

		ElasticPress\Elasticsearch::factory()->delete_all_indices();
		ElasticPress\Indexables::factory()->get( 'term' )->put_mapping();

		ElasticPress\Indexables::factory()->get( 'term' )->sync_manager->reset_sync_queue();

		// Need to call this since it's hooked to init.
		ElasticPress\Features::factory()->get_registered_feature( 'terms' )->search_setup();
	}

	/**
	 * Get Term feature
	 *
	 * @return ElasticPress\Feature\Terms
	 */
	protected function get_feature() {
		return ElasticPress\Features::factory()->get_registered_feature( 'terms' );
	}

	/**
	 * Create and index terms for testing
	 *
	 * @since 3.3
	 */
	public function createAndIndexTerms() {

		$this->ep_factory->term->create(
			array(
				'slug'        => 'apple',
				'name'        => 'Big Apple',
				'description' => 'The apple fruit term',
			)
		);

		$this->ep_factory->term->create(
			array(
				'slug'        => 'banana',
				'name'        => 'Yellow Banana',
				'description' => 'The banana fruit term',
			)
		);

		$this->ep_factory->term->create(
			array(
				'slug'        => 'mango',
				'name'        => 'Green Mango',
				'description' => 'The mango fruit term',
			)
		);

		$this->ep_factory->term->create(
			array(
				'slug'        => 'orange',
				'name'        => 'Orange',
				'description' => 'The orange fruit term',
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();
	}

	/**
	 * Clean up after each test. Reset our mocks
	 *
	 * @since 3.3
	 */
	public function tear_down() {
		parent::tear_down();

		$this->deleteAllTerms();

		$this->fired_actions = array();
	}

	/**
	 * Deletes all terms from the database.
	 *
	 * @return void
	 */
	public function deleteAllTerms() {

		$terms = get_terms(
			[
				'taxonomy'   => [ 'category', 'post_tag' ],
				'hide_empty' => false,
				'get'        => 'all',
			]
		);

		foreach ( $terms as $term ) {
			wp_delete_term( $term->term_id, $term->taxonomy );
		}
	}

	/**
	 * Test a simple term sync
	 *
	 * @since 3.3
	 * @group term
	 */
	public function testTermSync() {
		add_action(
			'ep_sync_term_on_transition',
			function() {
				$this->fired_actions['ep_sync_term_on_transition'] = true;
			}
		);

		ElasticPress\Indexables::factory()->get( 'term' )->sync_manager->reset_sync_queue();

		$term = wp_insert_term( 'term name', 'category' );

		$this->assertEquals( 1, count( ElasticPress\Indexables::factory()->get( 'term' )->sync_manager->get_sync_queue() ) );

		ElasticPress\Indexables::factory()->get( 'term' )->index( $term['term_id'] );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$this->assertTrue( ! empty( $this->fired_actions['ep_sync_term_on_transition'] ) );

		$term = ElasticPress\Indexables::factory()->get( 'term' )->get( $term['term_id'] );

		$this->assertTrue( ! empty( $term ) );
	}

	/**
	 * Test a simple term sync with meta
	 *
	 * @since 3.3
	 * @group term
	 */
	public function testTermSyncMeta() {
		$term = wp_insert_term( 'term name', 'category' );

		update_term_meta( $term['term_id'], 'new_meta', 'test' );

		ElasticPress\Indexables::factory()->get( 'term' )->index( $term['term_id'] );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$term = ElasticPress\Indexables::factory()->get( 'term' )->get( $term['term_id'] );

		$this->assertEquals( 'test', $term['meta']['new_meta'][0]['value'] );
	}

	/**
	 * Test a simple term sync on meta update
	 *
	 * @since 3.3
	 * @group term
	 */
	public function testTermSyncOnMetaUpdate() {
		$term = wp_insert_term( 'term name', 'category' );

		ElasticPress\Indexables::factory()->get( 'term' )->sync_manager->reset_sync_queue();

		update_term_meta( $term['term_id'], 'test_key', true );

		$this->assertEquals( 1, count( ElasticPress\Indexables::factory()->get( 'term' )->sync_manager->get_sync_queue() ) );
		$this->assertTrue( ! empty( ElasticPress\Indexables::factory()->get( 'term' )->sync_manager->add_to_queue( $term['term_id'] ) ) );
	}

	/**
	 * Test term sync kill. Note we can't actually check Elasticsearch here due to how the
	 * code is structured.
	 *
	 * @since 3.3
	 * @group term
	 */
	public function testTermSyncKill() {
		$term            = wp_insert_term( 'term name', 'category' );
		$created_term_id = $term['term_id'];

		add_action(
			'ep_sync_term_on_transition',
			function() {
				$this->fired_actions['ep_sync_term_on_transition'] = true;
			}
		);

		add_filter(
			'ep_term_sync_kill',
			function( $kill, $term_id ) use ( $created_term_id ) {
				if ( $created_term_id === $term_id ) {
					return true;
				}

				return $kill;
			},
			10,
			2
		);

		ElasticPress\Indexables::factory()->get( 'term' )->sync_manager->action_sync_on_update( $created_term_id );

		$this->assertTrue( empty( $this->fired_actions['ep_sync_term_on_transition'] ) );
	}

	/**
	 * Test a basic term query with and without ElasticPress
	 *
	 * @since 3.3
	 * @group term
	 */
	public function testBasicTermQuery() {
		$this->createAndIndexTerms();

		// First try without ES and make sure everything is right.
		$term_query = new \WP_Term_Query(
			[
				'number'     => 10,
				'hide_empty' => false,
				'taxonomy'   => 'post_tag',
			]
		);

		$properties = get_object_vars( $term_query );
		$this->assertArrayNotHasKey( 'elasticsearch_success', $properties );

		$this->assertEquals( 4, count( $term_query->terms ) );

		// Now try with Elasticsearch.
		// *** TODO, need to confirm hierarchical queries with this when hide_empty is false.
		$term_query = new \WP_Term_Query(
			[
				'ep_integrate' => true,
				'hide_empty'   => false,
				'taxonomy'     => 'post_tag',
				'number'       => 10,
			]
		);

		$this->assertTrue( $term_query->elasticsearch_success );

		$this->assertEquals( 4, count( $term_query->terms ) );

		// Test some of the filters and defaults.
		$return_2 = function() {
			return 2;
		};

		add_filter( 'ep_max_results_window', $return_2 );

		$term_query = new \WP_Term_Query(
			[
				'ep_integrate' => true,
				'hide_empty'   => false,
				'taxonomy'     => 'post_tag',
			]
		);

		$this->assertTrue( $term_query->elasticsearch_success );

		$this->assertEquals( 2, count( $term_query->terms ) );
	}


	/**
	 * Test a term query number
	 *
	 * @since 3.3
	 * @group term
	 */
	public function testTermQueryNumber() {
		$this->createAndIndexTerms();

		$term_query = new \WP_Term_Query(
			[
				'ep_integrate' => true,
				'number'       => 2,
				'hide_empty'   => false,
				'taxonomy'     => 'post_tag',
			]
		);

		$this->assertTrue( $term_query->elasticsearch_success );

		$this->assertEquals( 2, count( $term_query->terms ) );
	}

	/**
	 * Test a term query get paramater.
	 *
	 * @since 3.3
	 * @group term
	 */
	public function testTermQueryGet() {
		$this->createAndIndexTerms();

		$apple = get_term_by( 'slug', 'apple', 'post_tag' );

		$term = wp_insert_term( 'apple child', 'post_tag', [ 'parent' => $apple->term_id ] );

		$this->assertTrue( is_array( $term ) );

		ElasticPress\Indexables::factory()->get( 'term' )->index( $term['term_id'], true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		// First, verify this with default functionality.
		$term_query = new \WP_Term_Query(
			[
				'taxonomy' => 'post_tag',
				'get'      => 'all',
			]
		);

		$slugs = wp_list_pluck( $term_query->terms, 'slug' );

		$this->assertEquals( 5, count( $term_query->terms ) );

		$this->assertContains( 'apple-child', $slugs );

		// Then, test it with ES.
		$term_query = new \WP_Term_Query(
			[
				'taxonomy'     => 'post_tag',
				'ep_integrate' => true,
				'get'          => 'all',
			]
		);

		$this->assertTrue( $term_query->elasticsearch_success );

		$slugs = wp_list_pluck( $term_query->terms, 'slug' );

		$this->assertEquals( 5, count( $term_query->terms ) );

		$this->assertContains( 'apple-child', $slugs );
	}

	/**
	 * Test a term query object ids paramater
	 *
	 * @since 3.3
	 * @group term
	 */
	public function testTermQueryObjectIds() {
		$this->createAndIndexTerms();

		$post = wp_insert_post(
			[
				'post_title'  => 'Test',
				'post_status' => 'publish',
				'post_type'   => 'post',
			]
		);

		$term = wp_insert_term( 'term name', 'post_tag' );

		wp_set_object_terms( $post, $term['term_id'], 'post_tag', true );

		ElasticPress\Indexables::factory()->get( 'term' )->sync_manager->index_sync_queue();
		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$term_query = new \WP_Term_Query(
			[
				'number'       => 10,
				'hide_empty'   => false,
				'taxonomy'     => 'post_tag',
				'object_ids'   => [ $post ],
				'ep_integrate' => true,
			]
		);

		$this->assertTrue( $term_query->elasticsearch_success );

		$this->assertEquals( 1, count( $term_query->terms ) );
	}

	/**
	 * Test a term query orderby name
	 *
	 * @since 3.3
	 * @group term
	 */
	public function testTermQueryOrderName() {
		$this->createAndIndexTerms();

		$term_id = $this->ep_factory->term->create( array( 'name' => 'aaa' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$this->assertGreaterThan( 0, $term_id );

		$term_query = new \WP_Term_Query(
			[
				'number'       => 10,
				'hide_empty'   => false,
				'taxonomy'     => 'post_tag',
				'ep_integrate' => true,
			]
		);

		$this->assertTrue( $term_query->elasticsearch_success );

		$this->assertSame( 5, count( $term_query->terms ) );

		$this->assertEquals( $term_id, $term_query->terms[0]->term_id );

		$term_query = new \WP_Term_Query(
			[
				'number'       => 10,
				'hide_empty'   => false,
				'taxonomy'     => 'post_tag',
				'order'        => 'desc',
				'ep_integrate' => true,
			]
		);

		$this->assertTrue( $term_query->elasticsearch_success );

		$this->assertEquals( $term_id, $term_query->terms[ count( $term_query->terms ) - 1 ]->term_id );
	}

	/**
	 * Test a term query orderby slug
	 *
	 * @since 3.3
	 * @group term
	 */
	public function testTermQueryOrderSlug() {
		$this->createAndIndexTerms();

		$term_id = $this->ep_factory->term->create( array( 'name' => 'aaa' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$this->assertGreaterThan( 0, $term_id );

		$term_query = new \WP_Term_Query(
			[
				'number'       => 10,
				'hide_empty'   => false,
				'taxonomy'     => 'post_tag',
				'orderby'      => 'slug',
				'ep_integrate' => true,
			]
		);

		$this->assertTrue( $term_query->elasticsearch_success );

		$this->assertSame( 5, count( $term_query->terms ) );

		$this->assertEquals( $term_id, $term_query->terms[0]->term_id );

		$term_query = new \WP_Term_Query(
			[
				'number'       => 10,
				'hide_empty'   => false,
				'taxonomy'     => 'post_tag',
				'order'        => 'desc',
				'orderby'      => 'slug',
				'ep_integrate' => true,
			]
		);

		$this->assertTrue( $term_query->elasticsearch_success );

		$this->assertEquals( $term_id, $term_query->terms[ count( $term_query->terms ) - 1 ]->term_id );
	}

	/**
	 * Test a term query orderby description
	 *
	 * @since 3.3
	 * @group term
	 */
	public function testTermQueryOrderDescription() {
		$this->createAndIndexTerms();

		$term_id_1 = $this->ep_factory->term->create( array( 'description' => 'aaa' ) );
		$term_id_2 = $this->ep_factory->term->create( array( 'description' => 'bbb' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$this->assertGreaterThan( 0, $term_id_1 );
		$this->assertGreaterThan( 0, $term_id_2 );

		$term_query = new \WP_Term_Query(
			[
				'number'       => 10,
				'hide_empty'   => false,
				'taxonomy'     => 'post_tag',
				'orderby'      => 'description',
				'ep_integrate' => true,
			]
		);

		$this->assertTrue( $term_query->elasticsearch_success );

		// Remove empty descriptions.
		foreach ( $term_query->terms as $key => $term_value ) {
			if ( empty( $term_value->description ) ) {
				unset( $term_query->terms[ $key ] );
			}
		}

		$term_query->terms = array_values( $term_query->terms );

		$this->assertSame( 6, count( $term_query->terms ) );

		$this->assertEquals( $term_id_1, $term_query->terms[0]->term_id );

		$term_query = new \WP_Term_Query(
			[
				'number'       => 10,
				'hide_empty'   => false,
				'taxonomy'     => 'post_tag',
				'order'        => 'desc',
				'orderby'      => 'description',
				'ep_integrate' => true,
			]
		);

		$this->assertTrue( $term_query->elasticsearch_success );

		// Remove empty descriptions.
		foreach ( $term_query->terms as $key => $term_value ) {
			if ( empty( $term_value->description ) ) {
				unset( $term_query->terms[ $key ] );
			}
		}

		$term_query->terms = array_values( $term_query->terms );

		$this->assertSame( 6, count( $term_query->terms ) );

		$this->assertEquals( $term_id_1, $term_query->terms[ count( $term_query->terms ) - 1 ]->term_id );
	}

	/**
	 * Test a term query orderby term id
	 *
	 * @since 3.3
	 * @group term
	 */
	public function testTermQueryOrderTermId() {
		$this->createAndIndexTerms();

		$term_query = new \WP_Term_Query(
			[
				'number'       => 10,
				'hide_empty'   => false,
				'taxonomy'     => 'post_tag',
				'orderby'      => 'term_id',
				'ep_integrate' => true,
			]
		);

		$this->assertTrue( $term_query->elasticsearch_success );

		$this->assertTrue( $term_query->terms[0]->term_id < $term_query->terms[ count( $term_query->terms ) - 1 ]->term_id );

		$term_query = new \WP_Term_Query(
			[
				'number'       => 10,
				'hide_empty'   => false,
				'taxonomy'     => 'post_tag',
				'order'        => 'desc',
				'orderby'      => 'term_id',
				'ep_integrate' => true,
			]
		);

		$this->assertTrue( $term_query->elasticsearch_success );

		$this->assertTrue( $term_query->terms[0]->term_id > $term_query->terms[ count( $term_query->terms ) - 1 ]->term_id );
	}

	/**
	 * Test a term query orderby id
	 *
	 * @since 3.3
	 * @group term
	 */
	public function testTermQueryOrderId() {
		$this->createAndIndexTerms();

		$term_query = new \WP_Term_Query(
			[
				'number'       => 10,
				'hide_empty'   => false,
				'taxonomy'     => 'post_tag',
				'orderby'      => 'id',
				'ep_integrate' => true,
			]
		);

		$this->assertTrue( $term_query->elasticsearch_success );

		$this->assertTrue( $term_query->terms[0]->term_id < $term_query->terms[ count( $term_query->terms ) - 1 ]->term_id );

		$term_query = new \WP_Term_Query(
			[
				'number'       => 10,
				'hide_empty'   => false,
				'taxonomy'     => 'post_tag',
				'order'        => 'desc',
				'orderby'      => 'id',
				'ep_integrate' => true,
			]
		);

		$this->assertTrue( $term_query->elasticsearch_success );

		$this->assertTrue( $term_query->terms[0]->term_id > $term_query->terms[ count( $term_query->terms ) - 1 ]->term_id );
	}

	/**
	 * Test a term query orderby parent
	 *
	 * @since 3.3
	 * @group term
	 */
	public function testTermQueryOrderParent() {
		$this->createAndIndexTerms();

		$apple  = get_term_by( 'slug', 'apple', 'post_tag' );
		$orange = get_term_by( 'slug', 'orange', 'post_tag' );

		$this->assertTrue( is_a( $apple, '\WP_Term' ) );
		$this->assertTrue( is_a( $orange, '\WP_Term' ) );

		$this->assertGreaterThan( $apple->term_id, $orange->term_id );

		$term   = wp_insert_term( 'ff', 'post_tag', [ 'parent' => $apple->term_id ] );
		$term_2 = wp_insert_term( 'yff', 'post_tag', [ 'parent' => $orange->term_id ] );

		ElasticPress\Indexables::factory()->get( 'term' )->sync_manager->index_sync_queue();
		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$this->assertTrue( is_array( $term ) );
		$this->assertTrue( is_array( $term_2 ) );

		$term_query = new \WP_Term_Query(
			[
				'number'       => 10,
				'hide_empty'   => false,
				'taxonomy'     => 'post_tag',
				'orderby'      => 'parent',
				'ep_integrate' => true,
			]
		);

		$this->assertTrue( $term_query->elasticsearch_success );

		// Remove empty parents.
		foreach ( $term_query->terms as $key => $term_value ) {
			if ( empty( $term_value->parent ) ) {
				unset( $term_query->terms[ $key ] );
			}
		}

		$term_query->terms = array_values( $term_query->terms );

		$this->assertNotEmpty( $term_query->terms );

		$this->assertTrue( $term_query->terms[0]->term_id < $term_query->terms[ count( $term_query->terms ) - 1 ]->term_id );

		$term_query = new \WP_Term_Query(
			[
				'number'       => 10,
				'hide_empty'   => false,
				'taxonomy'     => 'post_tag',
				'order'        => 'desc',
				'orderby'      => 'parent',
				'ep_integrate' => true,
			]
		);

		$this->assertTrue( $term_query->elasticsearch_success );

		// Remove empty parents.
		foreach ( $term_query->terms as $key => $term_value ) {
			if ( empty( $term_value->parent ) ) {
				unset( $term_query->terms[ $key ] );
			}
		}

		$term_query->terms = array_values( $term_query->terms );

		$this->assertNotEmpty( $term_query->terms );

		$this->assertTrue( $term_query->terms[0]->term_id > $term_query->terms[ count( $term_query->terms ) - 1 ]->term_id );
	}

	/**
	 * Test a term query parent
	 *
	 * @since 3.6.3
	 * @group term
	 */
	public function testTermQueryParent() {
		$parent_term_id = $this->ep_factory->category->create();
		$child_term_id  = $this->ep_factory->category->create( [ 'parent' => $parent_term_id ] );
		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$term_query = new \WP_Term_Query(
			[
				'hide_empty'   => false,
				'taxonomy'     => 'category',
				'parent'       => $parent_term_id,
				'ep_integrate' => true,
			]
		);

		$this->assertTrue( $term_query->elasticsearch_success );
		$this->assertCount( 1, $term_query->terms );
		$this->assertSame( $term_query->terms[0]->term_id, $child_term_id );

		$term_query = new \WP_Term_Query(
			[
				'hide_empty'   => false,
				'taxonomy'     => 'category',
				'parent'       => 0,
				'ep_integrate' => true,
			]
		);

		$this->assertTrue( $term_query->elasticsearch_success );
		$this->assertCount( 1, $term_query->terms );
		$this->assertContains( $parent_term_id, wp_list_pluck( $term_query->terms, 'term_id' ) );
	}

	/**
	 * Test a term query hide_empty
	 *
	 * @since 3.3
	 * @group term
	 */
	public function testTermQueryHideEmpty() {
		$this->createAndIndexTerms();

		$post = wp_insert_post(
			[
				'post_title'   => 'Test',
				'post_status'  => 'publish',
				'post_type'    => 'post',
				'ep_integrate' => true,
			]
		);

		$term_id = $this->ep_factory->term->create();

		wp_set_object_terms( $post, $term_id, 'post_tag', true );

		ElasticPress\Indexables::factory()->get( 'term' )->index( $term_id, true );
		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$term_query = new \WP_Term_Query(
			[
				'number'       => 10,
				'taxonomy'     => 'post_tag',
				'hide_empty'   => true,
				'ep_integrate' => true,
			]
		);

		$this->assertTrue( $term_query->elasticsearch_success );
		$this->assertEquals( 1, count( $term_query->terms ) );
	}

	/**
	 * Tests prepare_document function.
	 *
	 * @since 3.4
	 * @group term
	 */
	public function testPrepareDocument() {

		$results = ElasticPress\Indexables::factory()->get( 'term' )->prepare_document( 0 );

		$this->assertFalse( $results );
	}

	/**
	 * Test include/exclude logic in format_args().
	 *
	 * @since 3.4
	 * @group term
	 */
	public function testFormatArgsIncludeExclude() {

		$term = new \ElasticPress\Indexable\Term\Term();

		$args = $term->format_args(
			[
				'include' => [ 123 ],
			]
		);

		$this->assertSame( 123, $args['post_filter']['bool']['must'][0]['bool']['must']['terms']['term_id'][0] );

		$args = $term->format_args(
			[
				'exclude' => [ 123 ],
			]
		);

		$this->assertSame( 123, $args['post_filter']['bool']['must'][0]['bool']['must_not']['terms']['term_id'][0] );

		$args = $term->format_args(
			[
				'exclude_tree' => [ 123 ],
			]
		);

		$this->assertSame( 123, $args['post_filter']['bool']['must'][0]['bool']['must_not']['terms']['term_id'][0] );
		$this->assertSame( 123, $args['post_filter']['bool']['must'][1]['bool']['must_not']['terms']['parent'][0] );
	}

	/**
	 * Test name and slug logic in format_args().
	 *
	 * @since 3.4
	 * @group term
	 */
	public function testFormatArgsNameSlug() {

		$term = new \ElasticPress\Indexable\Term\Term();

		$args = $term->format_args(
			[
				'name' => 'Bacon Ipsum',
			]
		);

		$this->assertSame( 'Bacon Ipsum', $args['post_filter']['bool']['must'][0]['terms']['name.raw'][0] );

		$args = $term->format_args(
			[
				'slug' => 'bacon-ipsum',
			]
		);

		$this->assertSame( 'bacon-ipsum', $args['post_filter']['bool']['must'][0]['terms']['slug.raw'][0] );
	}

	/**
	 * Test term_taxonomy_id and hierarchical logic in format_args().
	 *
	 * @since 3.4
	 * @group term
	 */
	public function testFormatArgsTermTaxIdHierarchical() {

		$term = new \ElasticPress\Indexable\Term\Term();

		$args = $term->format_args(
			[
				'term_taxonomy_id' => 123,
			]
		);

		$this->assertSame( 123, $args['post_filter']['bool']['must'][0]['bool']['must']['terms']['term_taxonomy_id'][0] );

		$args = $term->format_args(
			[
				'hierarchical' => false,
				'hide_empty'   => true,
			]
		);

		$this->assertSame( 1, $args['post_filter']['bool']['must'][0]['range']['count']['gte'] );

		$args = $term->format_args(
			[
				'hierarchical' => true,
				'hide_empty'   => true,
			]
		);

		$should = $args['post_filter']['bool']['must'][0]['bool']['should'];

		$this->assertSame( 1, $should[0]['range']['count']['gte'] );
		$this->assertSame( 1, $should[1]['range']['hierarchy.children.count']['gte'] );
	}

	/**
	 * Test hierarchical logic when querying ES.
	 *
	 * @since 3.4
	 * @group term
	 */
	public function testHierarchicalTerms() {

		// testFormatArgsTermTaxIdHierarchical checks for the correct
		// formatting of the hierarchical args. Now we will validate
		// we're only getting terms that have non-empty children.
		// This term is childless and assigned to the post.
		$childless_term_id = $this->ep_factory->category->create(
			[
				'name'        => 'Childless Category',
				'description' => 'The parent category without children',
			]
		);

		// This parent term is not assigned, but the child term is.
		$parent_term_id = $this->ep_factory->category->create(
			[
				'name'        => 'Parent Category',
				'description' => 'Parent/Child Terms',
			]
		);

		$child_term_id = $this->ep_factory->category->create(
			[
				'parent'      => $parent_term_id,
				'name'        => 'Child Category',
				'description' => 'Parent/Child Terms',
			]
		);

		// These two parent/child terms are created, but not assigned to the post.
		$parent_term_id_2 = $this->ep_factory->category->create();
		$child_term_id_2  = $this->ep_factory->category->create(
			[
				'parent' => $parent_term_id_2,
			]
		);

		// Assign the parent term, and the standalone childless term.
		$post_args = [
			'post_type'     => 'post',
			'post_status'   => 'publish',
			'post_category' => [ $child_term_id, $childless_term_id ],
		];

		$this->ep_factory->post->create( $post_args );

		ElasticPress\Indexables::factory()->get( 'post' )->sync_manager->index_sync_queue();
		ElasticPress\Indexables::factory()->get( 'term' )->sync_manager->index_sync_queue();
		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$parent_term = get_term( $parent_term_id, 'category' );
		$child_term  = get_term( $child_term_id, 'category' );

		$this->assertSame( $parent_term->term_id, $child_term->parent );

		$children = get_term_children( $parent_term_id, $parent_term->taxonomy );

		// First, we're going to get the data from WP core so we can match
		// it to the same ES search, four different sets of slugs.
		$wp_slugs = [
			'hierarchical_false_empty_false' => [],
			'hierarchical_false_empty_true'  => [],
			'hierarchical_true_empty_true'   => [],
			'hierarchical_true_empty_false'  => [],
		];

		foreach ( array_keys( $wp_slugs ) as $query_type ) {

			$query_params = explode( '_', $query_type );

			$term_query = new \WP_Term_Query(
				[
					'taxonomy'     => 'category',
					'ep_integrate' => false,
					'hierarchical' => 'true' === $query_params[1],
					'hide_empty'   => 'true' === $query_params[3],
					'fields'       => 'id=>slug',
				]
			);

			$properties = get_object_vars( $term_query );
			$this->assertArrayNotHasKey( 'elasticsearch_success', $properties );

			$wp_slugs[ $query_type ] = array_values( $term_query->terms );

			// Remove uncategorized, not synced to ES.
			$wp_slugs[ $query_type ] = array_filter(
				$wp_slugs[ $query_type ],
				function( $slug ) {
					return 'uncategorized' !== $slug;
				}
			);
		}

		/**
		 * We should have a list list this.
		 *
		 *"hierarchical_false_empty_false": [
		 *     "child-term-post",
		 *     "child-term-2-no-post",
		 *     "childless-term-post",
		 *     "parent-term-no-post",
		 *     "parent-term-2-no-post",
		 * ],
		 * "hierarchical_false_empty_true": [
		 *     "child-term-post",
		 *     "childless-term-post"
		 * ],
		 * "hierarchical_true_empty_true": [
		 *     "child-term-post",
		 *     "childless-term-post",
		 *     "parent-term-no-post"
		 * ],
		 * "hierarchical_true_empty_false": [
		 *     "child-term-post",
		 *     "child-term-2-no-post",
		 *     "childless-term-post",
		 *     "parent-term-no-post",
		 *     "parent-term-2-no-post",
		 *  ]
		 * }
		 */
		// Now run the same queries against ES.
		$es_slugs = [
			'hierarchical_false_empty_false' => [],
			'hierarchical_false_empty_true'  => [],
			'hierarchical_true_empty_true'   => [],
			'hierarchical_true_empty_false'  => [],
		];

		foreach ( array_keys( $es_slugs ) as $query_type ) {

			$query_params = explode( '_', $query_type );

			$term_query = new \WP_Term_Query(
				[
					'taxonomy'     => 'category',
					'ep_integrate' => true,
					'hierarchical' => 'true' === $query_params[1],
					'hide_empty'   => 'true' === $query_params[3],
					'fields'       => 'id=>slug',
				]
			);

			$this->assertTrue( $term_query->elasticsearch_success );

			$es_slugs[ $query_type ] = array_values( $term_query->terms );
		}

		foreach ( array_keys( $es_slugs ) as $key ) {
			$diff = array_diff( $wp_slugs[ $key ], $es_slugs[ $key ] );
			$this->assertEmpty( $diff, "Term slugs from ES don't match term slugs from WP for " . $key . ', diff: ' . wp_json_encode( $diff ) );
		}
	}

	/**
	 * Test search logic in format_args().
	 *
	 * @since 3.4
	 * @group term
	 */
	public function testFormatArgsSearch() {

		$term = new \ElasticPress\Indexable\Term\Term();

		// Default search fields.
		$args = $term->format_args(
			[
				'search' => 'Bacon Ipsum',
			]
		);

		$this->assertSame( 'Bacon Ipsum', $args['query']['bool']['should'][0]['multi_match']['query'] );
		$this->assertCount( 4, $args['query']['bool']['should'][0]['multi_match']['fields'] );
		$this->assertSame( 'name', $args['query']['bool']['should'][0]['multi_match']['fields'][0] );
		$this->assertSame( 'slug', $args['query']['bool']['should'][0]['multi_match']['fields'][1] );
		$this->assertSame( 'taxonomy', $args['query']['bool']['should'][0]['multi_match']['fields'][2] );
		$this->assertSame( 'description', $args['query']['bool']['should'][0]['multi_match']['fields'][3] );

		// Verify the rest of the multi_match fields.
		$this->assertCount( 3, $args['query']['bool']['should'] );
		$this->assertSame( 'Bacon Ipsum', $args['query']['bool']['should'][0]['multi_match']['query'] );
		$this->assertSame( 'Bacon Ipsum', $args['query']['bool']['should'][1]['multi_match']['query'] );
		$this->assertSame( 'Bacon Ipsum', $args['query']['bool']['should'][2]['multi_match']['query'] );

		$this->assertSame( 2, $args['query']['bool']['should'][1]['multi_match']['boost'] );
		$this->assertSame( 0, $args['query']['bool']['should'][1]['multi_match']['fuzziness'] );
		$this->assertSame( 'and', $args['query']['bool']['should'][1]['multi_match']['operator'] );

		$this->assertSame( 1, $args['query']['bool']['should'][2]['multi_match']['fuzziness'] );

		// Custom search fields.
		$args = $term->format_args(
			[
				'search'        => 'Bacon Ipsum',
				'search_fields' => [
					'name',
					'description',
					'meta' => [
						'custom_key',
					],
				],
			]
		);

		$this->assertSame( 'Bacon Ipsum', $args['query']['bool']['should'][0]['multi_match']['query'] );
		$this->assertCount( 3, $args['query']['bool']['should'][0]['multi_match']['fields'] );
		$this->assertSame( 'name', $args['query']['bool']['should'][0]['multi_match']['fields'][0] );
		$this->assertSame( 'description', $args['query']['bool']['should'][0]['multi_match']['fields'][1] );
		$this->assertSame( 'meta.custom_key.value', $args['query']['bool']['should'][0]['multi_match']['fields'][2] );

		$args = $term->format_args(
			[
				'name__like' => 'Bacon Ipsum',
			]
		);

		// Only query the name field.
		$this->assertSame( 'Bacon Ipsum', $args['query']['bool']['should'][0]['multi_match']['query'] );
		$this->assertCount( 1, $args['query']['bool']['should'][0]['multi_match']['fields'] );
		$this->assertSame( 'name', $args['query']['bool']['should'][0]['multi_match']['fields'][0] );

		$args = $term->format_args(
			[
				'description__like' => 'Bacon Ipsum',
			]
		);

		// Only query the name field.
		$this->assertSame( 'Bacon Ipsum', $args['query']['bool']['should'][0]['multi_match']['query'] );
		$this->assertCount( 1, $args['query']['bool']['should'][0]['multi_match']['fields'] );
		$this->assertSame( 'description', $args['query']['bool']['should'][0]['multi_match']['fields'][0] );
	}

	/**
	 * Test search logic in format_args().
	 *
	 * @since 3.4
	 * @group term
	 */
	public function testFormatArgsChildParent() {

		$term = new \ElasticPress\Indexable\Term\Term();

		$args = $term->format_args(
			[
				'child_of' => 123,
				'taxonomy' => 'category',
			]
		);

		$this->assertSame( 'category', $args['post_filter']['bool']['must'][0]['term']['taxonomy.raw'] );
		$this->assertSame( 123, $args['post_filter']['bool']['must'][1]['bool']['must'][0]['match_phrase']['hierarchy.ancestors.terms'] );

		$args = $term->format_args(
			[
				'parent' => 123,
			]
		);

		$this->assertSame( 123, $args['post_filter']['bool']['must'][0]['bool']['must']['term']['parent'] );

		$args = $term->format_args(
			[
				'childless' => true,
			]
		);

		$this->assertSame( 0, $args['post_filter']['bool']['must'][0]['bool']['must']['term']['hierarchy.children.terms'] );
	}

	/**
	 * Test search logic in format_args().
	 *
	 * @since 3.4
	 * @group term
	 */
	public function testFormatArgsMeta() {

		$term = new \ElasticPress\Indexable\Term\Term();

		$args = $term->format_args(
			[
				'meta_key'     => 'custom_key',
				'meta_value'   => 'custom value',
				'meta_compare' => '=',
			]
		);

		$this->assertSame( 'custom value', $args['post_filter']['bool']['must'][0]['bool']['must'][0]['terms']['meta.custom_key.raw'][0] );

		$args = $term->format_args(
			[
				'meta_key'     => 'custom_key',
				'meta_value'   => 'custom value',
				'meta_compare' => '!=',
			]
		);

		$this->assertSame( 'custom value', $args['post_filter']['bool']['must'][0]['bool']['must'][0]['bool']['must_not'][0]['terms']['meta.custom_key.raw'][0] );

		$args = $term->format_args(
			[
				'meta_query' => [
					[
						'key'   => 'custom_key',
						'value' => 'custom value',
					],
				],
			]
		);

		$this->assertSame( 'custom value', $args['post_filter']['bool']['must'][0]['bool']['must'][0]['terms']['meta.custom_key.raw'][0] );
	}

	/**
	 * Test search logic in format_args().
	 *
	 * @since 3.4
	 * @group term
	 */
	public function testFormatArgsFields() {

		$term = new \ElasticPress\Indexable\Term\Term();

		$args = $term->format_args(
			[
				'fields' => 'ids',
			]
		);

		$this->assertSame( 'term_id', $args['_source']['includes'][0] );

		$args = $term->format_args(
			[
				'fields' => 'id=>name',
			]
		);

		$this->assertSame( 'term_id', $args['_source']['includes'][0] );
		$this->assertSame( 'name', $args['_source']['includes'][1] );

		$args = $term->format_args(
			[
				'fields' => 'id=>parent',
			]
		);

		$this->assertSame( 'term_id', $args['_source']['includes'][0] );
		$this->assertSame( 'parent', $args['_source']['includes'][1] );

		$args = $term->format_args(
			[
				'fields' => 'id=>slug',
			]
		);

		$this->assertSame( 'term_id', $args['_source']['includes'][0] );
		$this->assertSame( 'slug', $args['_source']['includes'][1] );

		$args = $term->format_args(
			[
				'fields' => 'tt_ids',
			]
		);

		$this->assertSame( 'term_taxonomy_id', $args['_source']['includes'][0] );

		$args = $term->format_args(
			[
				'fields' => 'names',
			]
		);

		$this->assertSame( 'name', $args['_source']['includes'][0] );
	}

	/**
	 * Tests parse_order and parse_orderby.
	 *
	 * @since 3.4
	 * @group term
	 */
	public function testParseOrder() {

		$term = new \ElasticPress\Indexable\Term\Term();

		$args = $term->format_args(
			[
				'order' => 2,
			]
		);

		$es_version = \ElasticPress\Elasticsearch::factory()->get_elasticsearch_version();
		$field_name = ( version_compare( (string) $es_version, '7.0', '>=' ) ) ? 'name.sortable' : 'name.raw';

		$this->assertSame( 'desc', $args['sort'][0][ $field_name ]['order'] );

		$args = $term->format_args(
			[
				'order' => 'ASC',
			]
		);

		$this->assertSame( 'asc', $args['sort'][0][ $field_name ]['order'] );

		$args = $term->format_args(
			[
				'order' => 'Bacon Ipsum',
			]
		);

		$this->assertSame( 'desc', $args['sort'][0][ $field_name ]['order'] );

		$args = $term->format_args(
			[
				'orderby' => 'term_group',
			]
		);

		$this->assertSame( 'desc', $args['sort'][0]['term_group']['order'] );

		$args = $term->format_args(
			[
				'orderby' => 'term_group',
			]
		);

		$this->assertSame( 'desc', $args['sort'][0]['term_group']['order'] );

		$args = $term->format_args(
			[
				'orderby' => 'count',
			]
		);

		$this->assertSame( 'desc', $args['sort'][0]['count']['order'] );

		$args = $term->format_args(
			[
				'meta_key' => 'custom_key',
				'orderby'  => 'meta_value',
			]
		);

		$this->assertSame( 'desc', $args['sort'][0]['meta.custom_key.value']['order'] );

		$args = $term->format_args(
			[
				'meta_key' => 'custom_key',
				'orderby'  => 'meta_value_num',
			]
		);

		$this->assertSame( 'desc', $args['sort'][0]['meta.custom_key.long']['order'] );

		$args = $term->format_args(
			[
				'orderby' => 'custom',
			]
		);

		$this->assertSame( 'desc', $args['sort'][0]['custom']['order'] );
	}

	/**
	 * Test protected meta key functionality.
	 *
	 * @since 3.4
	 * @group term
	 */
	public function testPrepareMetaProtectedKeys() {

		$term = new \ElasticPress\Indexable\Term\Term();

		$callback = function( $keys ) {
			$keys[] = '_custom_protected_key';
			return $keys;
		};

		$this->assertTrue( is_protected_meta( '_custom_protected_key' ) );

		$term_id = $this->ep_factory->category->create();
		update_term_meta( $term_id, '_custom_protected_key', 123 );

		add_filter( 'ep_prepare_term_meta_allowed_protected_keys', $callback );

		$prepared_meta = $term->prepare_meta( $term_id );

		$this->assertSame( '123', $prepared_meta['_custom_protected_key'][0] );
	}

	/**
	 * Test remap_terms() function.
	 *
	 * @since 3.4
	 * @group term
	 */
	public function testRemapTerms() {

		$term = new \ElasticPress\Indexable\Term\Term();

		$new_term = wp_insert_term(
			'testRemapTerms',
			'category',
			[
				'description' => 'Test remap_terms() function',
				'slug'        => 'test-remap-terms-function',
			]
		);

		$this->assertGreaterThan( 0, $new_term['term_id'] );

		$new_term = get_term( $new_term['term_id'], 'category' );

		$this->assertTrue( is_a( $new_term, '\WP_Term' ) );

		$current_term = $new_term;

		$term->remap_terms( $new_term );

		$properties = get_object_vars( $new_term );
		$this->assertArrayHasKey( 'ID', $properties );
		$this->assertArrayHasKey( 'term_id', $properties );
		$this->assertArrayHasKey( 'name', $properties );
		$this->assertArrayHasKey( 'slug', $properties );
		$this->assertArrayHasKey( 'term_group', $properties );
		$this->assertArrayHasKey( 'term_taxonomy_id', $properties );
		$this->assertArrayHasKey( 'taxonomy', $properties );
		$this->assertArrayHasKey( 'description', $properties );
		$this->assertArrayHasKey( 'parent', $properties );
		$this->assertArrayHasKey( 'count', $properties );

		$this->assertSame( $new_term->ID, $current_term->term_id );
		$this->assertSame( $new_term->term_id, $current_term->term_id );
	}

	/**
	 * Test query_db() function.
	 *
	 * @since 3.4
	 * @group term
	 */
	public function testQueryDb() {

		$this->createAndIndexTerms();

		$term = new \ElasticPress\Indexable\Term\Term();

		$results = $term->query_db(
			[
				'ep_integrate' => false,
				'number'       => 10,
				'per_page'     => 3,
				'offset'       => 0,
				'orderby'      => 'id',
				'order'        => 'desc',
				'taxonomy'     => 'post_tag',
				'hide_empty'   => false,
			]
		);

		$this->assertCount( 3, $results['objects'] );
		$this->assertSame( 4, $results['total_objects'] );

		$term = new \ElasticPress\Indexable\Term\Term();

		$results = $term->query_db(
			[
				'ep_integrate' => false,
				'number'       => 10,
				'per_page'     => 3,
				'offset'       => 20,
				'orderby'      => 'id',
				'order'        => 'desc',
				'taxonomy'     => 'post_tag',
				'hide_empty'   => false,
			]
		);

		$this->assertSame( 0, $results['total_objects'] );
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

		$term = new \ElasticPress\Indexable\Term\Term();

		// Test the mapping files for different ES versions.
		$version_and_file = [
			'4.0' => 'pre-5-0.php',
			'5.1' => 'initial.php',
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
			add_filter( 'ep_term_mapping_file', $assert_callback );

			// Run put_mapping(), which will trigger these filters above
			// and run the tests.
			$term->put_mapping();

			remove_filter( 'ep_fallback_elasticsearch_version', $version_callback );
			remove_filter( 'ep_term_mapping_file', $assert_callback );
		}

	}

	/**
	 * Test term deletion.
	 *
	 * @since 3.6.3
	 * @group term
	 */
	public function testDeleteTerm() {
		$term_id = $this->ep_factory->category->create();
		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$term_query = new \WP_Term_Query(
			[
				'hide_empty'   => false,
				'taxonomy'     => 'category',
				'include'      => [ $term_id ],
				'ep_integrate' => true,
			]
		);

		$this->assertTrue( $term_query->elasticsearch_success );
		$this->assertEquals( 1, $term_query->found_terms );
		$this->assertEquals( $term_id, $term_query->terms[0]->term_id );

		wp_delete_term( $term_id, 'category' );
		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$term_query = new \WP_Term_Query(
			[
				'hide_empty'   => false,
				'taxonomy'     => 'category',
				'include'      => [ $term_id ],
				'ep_integrate' => true,
			]
		);

		$this->assertTrue( $term_query->elasticsearch_success );
		$this->assertEquals( 0, $term_query->found_terms );
	}

	/**
	 * Test integration with Term Queries.
	 */
	public function testIntegrateSearchQueries() {
		$this->assertTrue( $this->get_feature()->integrate_search_queries( true, null ) );
		$this->assertFalse( $this->get_feature()->integrate_search_queries( false, null ) );

		$query = new \WP_Term_Query(
			[
				'ep_integrate' => false,
			]
		);

		$this->assertFalse( $this->get_feature()->integrate_search_queries( true, $query ) );

		$query = new \WP_Term_Query(
			[
				'ep_integrate' => 0,
			]
		);

		$this->assertFalse( $this->get_feature()->integrate_search_queries( true, $query ) );

		$query = new \WP_Term_Query(
			[
				'ep_integrate' => 'false',
			]
		);

		$this->assertFalse( $this->get_feature()->integrate_search_queries( true, $query ) );

		$query = new \WP_Term_Query(
			[
				'search' => 'term',
			]
		);

		$this->assertTrue( $this->get_feature()->integrate_search_queries( false, $query ) );
	}

	/**
	 * Tests terms without meta value.
	 *
	 * @return void
	 */
	public function testMetaWithoutValue() {

		$term_id = $this->ep_factory->category->create();
		update_term_meta( $term_id, 'test_key', 'value' );
		ElasticPress\Indexables::factory()->get( 'term' )->index( $term_id, true );

		$term_id = $this->ep_factory->category->create();
		update_term_meta( $term_id, 'test_key', 'value' );
		ElasticPress\Indexables::factory()->get( 'term' )->index( $term_id, true );

		$this->ep_factory->category->create();

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		// Make sure WP_Term_Query returns only taxonomies for whom meta exists.
		$args = array(
			'taxonomy'     => 'category',
			'meta_key'     => 'test_key',
			'ep_integrate' => true,
			'hide_empty'   => false,

		);
		$query = new \WP_Term_Query( $args );
		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 2, count( $query->terms ) );

		// Make sure get_terms returns only taxonomies for whom meta exists.
		$args  = array(
			'taxonomy'     => 'category',
			'hide_empty'   => false,
			'ep_integrate' => true,
			'meta_query'   => array(
				array(
					'key' => 'test_key',
				),
			),
		);
		$terms = get_terms( $args );
		$this->assertTrue( $terms[0]->elasticsearch );
		$this->assertEquals( 2, count( $terms ) );
	}

	/**
	 * Test term query for non-public taxonomies.
	 *
	 * @return void
	 */
	public function testQueryForNonPublicTaxonomies() {
		register_taxonomy(
			'wptests_tax',
			'post',
			array(
				'public' => false,
			)
		);

		$this->ep_factory->term->create_many( '2', array( 'taxonomy' => 'wptests_tax' ) );
		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$term_query = new \WP_Term_Query(
			[
				'taxonomy'     => 'wptests_tax',
				'ep_integrate' => true,
				'hide_empty'   => false,
			]
		);

		$properties = get_object_vars( $term_query );
		$this->assertArrayNotHasKey( 'elasticsearch_success', $properties );
		$this->assertEquals( 2, count( $term_query->terms ) );

		$this->ep_factory->term->create_many( '2', array( 'taxonomy' => 'post_tag' ) );
		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$term_query = new \WP_Term_Query(
			[
				'taxonomy'     => array( 'wptests_tax', 'post_tag' ),
				'ep_integrate' => true,
				'hide_empty'   => false,
			]
		);

		$properties = get_object_vars( $term_query );
		$this->assertArrayNotHasKey( 'elasticsearch_success', $properties );
		$this->assertEquals( 4, count( $term_query->terms ) );
	}

	/**
	 * Test if the mapping applies the ep_stop filter correctly
	 *
	 * @since 4.7.0
	 * @group term
	 */
	public function test_mapping_ep_stop_filter() {
		$indexable      = ElasticPress\Indexables::factory()->get( 'term' );
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
