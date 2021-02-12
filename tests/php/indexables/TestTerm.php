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
	public function setUp() {
		global $wpdb;
		parent::setUp();
		$wpdb->suppress_errors();

		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );

		wp_set_current_user( $admin_id );

		ElasticPress\Features::factory()->activate_feature( 'terms' );
		ElasticPress\Features::factory()->setup_features();

		ElasticPress\Elasticsearch::factory()->delete_all_indices();
		ElasticPress\Indexables::factory()->get( 'term' )->put_mapping();

		ElasticPress\Indexables::factory()->get( 'term' )->sync_manager->sync_queue = [];

		// Need to call this since it's hooked to init.
		ElasticPress\Features::factory()->get_registered_feature( 'terms' )->search_setup();
	}

	/**
	 * Create and index terms for testing
	 *
	 * @since 3.3
	 */
	public function createAndIndexTerms() {

		$term_1 = Functions\create_and_sync_term( 'apple', 'Big Apple', 'The apple fruit term', 'post_tag' );

		$term_2 = Functions\create_and_sync_term( 'banana', 'Yellow Banana', 'The banana fruit term', 'post_tag' );

		$term_3 = Functions\create_and_sync_term( 'mango', 'Green Mango', 'The mango fruit term', 'post_tag' );

		$term_4 = Functions\create_and_sync_term( 'orange', 'Orange', 'The orange fruit term.', 'post_tag' );

		ElasticPress\Elasticsearch::factory()->refresh_indices();
	}

	/**
	 * Clean up after each test. Reset our mocks
	 *
	 * @since 3.3
	 */
	public function tearDown() {
		parent::tearDown();

		$this->deleteAllTerms();

		// Make sure no one attached to this.
		remove_filter( 'ep_sync_terms_allow_hierarchy', array( $this, 'ep_allow_multiple_level_terms_sync' ), 100 );
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
	 * Test the building of index mappings
	 * 
	 * @since 3.6
	 * @group term
	 */
	public function testTermBuildMapping() {
		$mapping_and_settings = ElasticPress\Indexables::factory()->get( 'term' )->build_mapping();

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
	public function testTermBuildSettings() {
		$settings = ElasticPress\Indexables::factory()->get( 'term' )->build_settings();

		$expected_keys = array(
			'index.mapping.total_fields.limit',
			'index.max_result_window',
			'index.max_shingle_diff',
			'analysis',
		);

		$actual_keys = array_keys( $settings );

		$diff = array_diff( $expected_keys, $actual_keys );

		$this->assertEquals( $diff, array() );
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

		ElasticPress\Indexables::factory()->get( 'term' )->sync_manager->sync_queue = [];

		$term = wp_insert_term( 'term name', 'category' );

		$this->assertEquals( 1, count( ElasticPress\Indexables::factory()->get( 'term' )->sync_manager->sync_queue ) );

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

		ElasticPress\Indexables::factory()->get( 'term' )->sync_manager->sync_queue = [];

		update_term_meta( $term['term_id'], 'test_key', true );

		$this->assertEquals( 1, count( ElasticPress\Indexables::factory()->get( 'term' )->sync_manager->sync_queue ) );
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

		foreach ( $term_query->terms as $term ) {
			$this->assertTrue( empty( $term->elasticsearch ) );
		}

		$this->assertEquals( 4, count( $term_query->terms ) );

		// Now try with Elasticsearch.
		$term_query = new \WP_Term_Query(
			[
				'ep_integrate' => true,
				'hide_empty'   => false,
				'taxonomy'     => 'post_tag',
				'number'       => 10,
			]
		);

		foreach ( $term_query->terms as $term ) {
			$this->assertTrue( $term->elasticsearch );
		}

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
				'number'     => 2,
				'hide_empty' => false,
				'taxonomy'   => 'post_tag',
			]
		);

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
				'taxonomy'     => 'post_tag',
				'get'          => 'all',
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

		$term_query = new \WP_Term_Query(
			[
				'number'       => 10,
				'hide_empty'   => false,
				'taxonomy'     => 'post_tag',
				'object_ids'   => [ $post ],
				'ep_integrate' => true,
			]
		);

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

		$term_id = Functions\create_and_sync_term( 'aaa', 'aaa', '', 'post_tag' );

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

		$term_id = Functions\create_and_sync_term( 'aaa', 'aaa', '', 'post_tag' );

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

		$term_id_1 = Functions\create_and_sync_term( 'ff', 'ff', 'aaa', 'post_tag' );
		$term_id_2 = Functions\create_and_sync_term( 'yff', 'ff', 'bbb', 'post_tag' );

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

		$apple = get_term_by( 'slug', 'apple', 'post_tag' );
		$orange = get_term_by( 'slug', 'orange', 'post_tag' );

		$this->assertTrue( is_a( $apple, '\WP_Term' ) );
		$this->assertTrue( is_a( $orange, '\WP_Term' ) );

		$this->assertGreaterThan( $apple->term_id, $orange->term_id );

		$term = wp_insert_term( 'ff', 'post_tag', [ 'parent' => $apple->term_id ] );
		$term_2 = wp_insert_term( 'yff', 'post_tag', [ 'parent' => $orange->term_id ] );

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

		$term = wp_insert_term( 'term name', 'post_tag' );

		wp_set_object_terms( $post, $term['term_id'], 'post_tag', true );

		$term_query = new \WP_Term_Query(
			[
				'number'       => 10,
				'taxonomy'     => 'post_tag',
				'hide_empty'   => true,
				'ep_integrate' => true,
			]
		);

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
}
