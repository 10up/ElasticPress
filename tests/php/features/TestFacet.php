<?php
/**
 * Test facet feature
 *
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress;
use ElasticPress\Features;

/**
 * Facet test class
 */
class TestFacet extends BaseTestCase {

	/**
	 * Setup each test.
	 *
	 * @since 5.3.0
	 */
	public function set_up() {
		parent::set_up();

		ElasticPress\Elasticsearch::factory()->delete_all_indices();
		ElasticPress\Indexables::factory()->get( 'post' )->put_mapping();

		ElasticPress\Indexables::factory()->get( 'post' )->sync_manager->reset_sync_queue();

		$this->setup_test_post_type();
	}

	/**
	 * Clean up after each test.
	 *
	 * @since 5.1.0
	 */
	public function tear_down() {
		parent::tear_down();

		$GLOBALS['pagenow'] = '';
	}

	/**
	 * Test the setup method
	 *
	 * @since 5.1.0
	 * @group facets
	 */
	public function test_setup() {
		$facet_feature = Features::factory()->get_registered_feature( 'facets' );
		$facet_feature->setup();

		$this->assertSame( 10, has_action( 'rest_api_init', [ $facet_feature, 'setup_endpoints' ] ) );
	}

	/**
	 * Test the ep_facet_enabled_in_editor filter
	 *
	 * @since 5.1.0
	 * @group facets
	 */
	public function test_setup_ep_facet_enabled_in_editor() {
		add_filter( 'ep_facet_enabled_in_editor', '__return_true' );

		$GLOBALS['pagenow'] = 'post-new.php';
		set_current_screen( 'post-new.php' );

		$facet_feature = Features::factory()->get_registered_feature( 'facets' );
		$facet_feature->tear_down();
		$facet_feature->setup();

		$this->assertSame( 10, has_action( 'rest_api_init', [ $facet_feature, 'setup_endpoints' ] ) );
	}

	/**
	 * Test facet type registration
	 *
	 * @since 4.3.0
	 * @group facets
	 */
	public function testFacetTypeRegistration() {
		$facet_type = $this->getMockForAbstractClass( '\ElasticPress\Feature\Facets\FacetType' );
		$facet_type->expects( $this->exactly( 1 ) )->method( 'setup' );

		$register_facet_type = function ( $types ) use ( $facet_type ) {
			$types['test_custom'] = get_class( $facet_type );
			return $types;
		};

		add_filter( 'ep_facet_types', $register_facet_type );

		$facets = new \ElasticPress\Feature\Facets\Facets();

		$this->assertArrayHasKey( 'test_custom', $facets->types );
		$this->assertInstanceOf( get_class( $facet_type ), $facets->types['test_custom'] );

		// Make sure it uses our instance
		$facets->types['test_custom'] = $facet_type;

		$facets->setup();
	}

	/**
	 * Test the `get_selected` method
	 *
	 * @since 3.6.0
	 * @group facets
	 */
	public function testGetSelected() {
		$facet_feature = Features::factory()->get_registered_feature( 'facets' );

		parse_str( 'ep_filter_taxonomy=dolor', $_GET );
		$selected = $facet_feature->get_selected();
		$this->assertSelectedTax( [ 'dolor' => true ], 'taxonomy', $selected );

		parse_str( 'ep_filter_taxonomy=dolor,sit', $_GET );
		$selected = $facet_feature->get_selected();
		$this->assertSelectedTax(
			[
				'dolor' => true,
				'sit'   => true,
			],
			'taxonomy',
			$selected
		);

		parse_str( 'ep_filter_taxonomy=dolor,sit&ep_filter_othertax=amet', $_GET );
		$selected = $facet_feature->get_selected();

		$this->assertIsArray( $selected );
		$this->assertIsArray( $selected['taxonomies'] );
		$this->assertCount( 2, $selected['taxonomies'] );
		$this->assertArrayHasKey( 'taxonomy', $selected['taxonomies'] );
		$this->assertArrayHasKey( 'othertax', $selected['taxonomies'] );
		$this->assertSame(
			[
				'dolor' => true,
				'sit'   => true,
			],
			$selected['taxonomies']['taxonomy']['terms']
		);
		$this->assertSame( [ 'amet' => true ], $selected['taxonomies']['othertax']['terms'] );

		parse_str( 's=searchterm&ep_filter_taxonomy=dolor', $_GET );
		$selected = $facet_feature->get_selected();
		$this->assertSelectedTax( [ 'dolor' => true ], 'taxonomy', $selected );
		$this->assertArrayHasKey( 's', $selected );
		$this->assertSame( 'searchterm', $selected['s'] );

		parse_str( 'post_type=posttype&ep_filter_taxonomy=dolor', $_GET );
		$selected = $facet_feature->get_selected();
		$this->assertSelectedTax( [ 'dolor' => true ], 'taxonomy', $selected );
		$this->assertArrayHasKey( 'post_type', $selected );
		$this->assertSame( 'posttype', $selected['post_type'] );

		// test for a term having accents characters in it.
		$term = $this->factory->category->create_and_get(
			array(
				'name' => 'غير-مصنف',
			)
		);
		parse_str( "post_type=posttype&ep_filter_taxonomy={$term->slug}", $_GET );
		$selected = $facet_feature->get_selected();
		$this->assertSelectedTax( array( $term->slug => true ), 'taxonomy', $selected );
		$this->assertArrayHasKey( 'post_type', $selected );
		$this->assertSame( 'posttype', $selected['post_type'] );

		// test when filter value is empty.
		parse_str( 'ep_filter_category=&ep_filter_othertax=amet&s=', $_GET );
		$selected = $facet_feature->get_selected();
		$this->assertArrayNotHasKey( 'category', $selected['taxonomies'] );
		$this->assertArrayHasKey( 's', $selected );
	}

	/**
	 * Test build query URL
	 *
	 * @since 3.6.0
	 * @group facets
	 */
	public function testBuildQueryUrl() {
		$facet_feature = Features::factory()->get_registered_feature( 'facets' );

		$filters = [
			'taxonomies' => [
				'category' => [
					'terms' => [
						'augue' => 1,
					],
				],
			],
		];

		$this->assertEquals( '/?ep_filter_category=augue', $facet_feature->build_query_url( $filters ) );

		$filters['s'] = 'dolor';
		$this->assertEquals( '/?ep_filter_category=augue&s=dolor', $facet_feature->build_query_url( $filters ) );

		unset( $filters['s'] );
		$filters = [
			'taxonomies' => [
				'category' => [
					'terms' => [
						'augue'       => 1,
						'consectetur' => 1,
					],
				],
			],
		];

		$this->assertEquals( '/?ep_filter_category=augue,consectetur', $facet_feature->build_query_url( $filters ) );

		// test when search parameter is empty.
		$filters['s'] = '';
		$this->assertEquals( '/?ep_filter_category=augue,consectetur&s=', $facet_feature->build_query_url( $filters ) );

		$_SERVER['REQUEST_URI'] = 'test/page/1';

		$filters['s'] = 'dolor';
		$this->assertEquals( 'test/?ep_filter_category=augue,consectetur&s=dolor', $facet_feature->build_query_url( $filters ) );

		/**
		 * Test the `ep_facet_query_string` filter.
		 */
		$change_facet_query_string = function ( $query_string, $query_params ) {
			$this->assertIsArray( $query_params );
			$query_string .= '&foobar';
			return $query_string;
		};
		add_filter( 'ep_facet_query_string', $change_facet_query_string, 10, 2 );
		$this->assertStringEndsWith( '&foobar', $facet_feature->build_query_url( $filters ) );
		remove_filter( 'ep_facet_query_string', $change_facet_query_string, 10, 2 );

		/**
		 * (Indirectly) test the `ep_facet_filter_name` filter
		 */
		$change_ep_facet_filter_name = function ( $original_name ) {
			$this->assertEquals( 'ep_filter_', $original_name );
			return 'ep_custom_filter_';
		};
		add_filter( 'ep_facet_filter_name', $change_ep_facet_filter_name );
		$this->assertEquals( 'test/?ep_custom_filter_category=augue,consectetur&s=dolor', $facet_feature->build_query_url( $filters ) );
		remove_filter( 'ep_facet_filter_name', $change_ep_facet_filter_name );
	}

	/**
	 * Test set_agg_filters
	 *
	 * @since 4.3.0
	 * @group facets
	 */
	public function testSetAggFilter() {
		$facet_feature = Features::factory()->get_registered_feature( 'facets' );

		$args = [
			'aggs' => [
				'terms' => [],
			],
		];

		$query_args = [];

		$query = new \WP_Query();

		// No `ep_facet` in query_args will make it return the same array.
		$this->assertSame( $args, $facet_feature->set_agg_filters( $args, $query_args, $query ) );

		/**
		 * Without any function hooked to `ep_facet_agg_filters` we expect
		 * aggregation filters to match exactly the filter applied to the main
		 * query.
		 */
		remove_all_filters( 'ep_facet_agg_filters' );
		$query_args = [
			'ep_facet'    => 1,
			'post_type'   => 'post',
			'post_status' => 'publish',
		];
		// Get the ES query args.
		$formatted_args = \ElasticPress\Indexables::factory()->get( 'post' )->format_args( $query_args, $query );
		// Get the ES query args after applying the changes to aggs filters.
		$formatted_args_with_args = $facet_feature->set_agg_filters( $formatted_args, $query_args, $query );

		$this->assertSame( $formatted_args['post_filter'], $formatted_args_with_args['aggs']['terms']['filter'] );
	}

	/**
	 * Test if the `ep_facet_adding_agg_filters` flag is set in `set_agg_filters`
	 *
	 * @since 4.5.0
	 * @group facets
	 */
	public function testSetAggFilterAddingAggFiltersFlag() {
		$facet_feature = Features::factory()->get_registered_feature( 'facets' );

		$query_args = [
			'ep_facet'    => 1,
			'post_type'   => 'post',
			'post_status' => 'publish',
		];

		$check_flag = function ( $query_args ) {
			$this->assertTrue( $query_args['ep_facet_adding_agg_filters'] );
			return $query_args;
		};
		add_filter( 'ep_facet_agg_filters', $check_flag );

		$previous_filter_count = did_filter( 'ep_facet_agg_filters' );
		$facet_feature->set_agg_filters( [], $query_args, new \WP_Query() );
		$current_filter_count = did_filter( 'ep_facet_agg_filters' );

		$this->assertGreaterThan( $previous_filter_count, $current_filter_count );
	}

	/**
	 * Test apply_facets_filters
	 *
	 * @since 4.4.0
	 * @group facets
	 */
	public function testApplyFacetsFilters() {
		$facet_feature = Features::factory()->get_registered_feature( 'facets' );

		$new_filters = $facet_feature->apply_facets_filters( [], [], new \WP_Query( [] ) );
		$this->assertSame( [], $new_filters );

		/**
		 * Test the `ep_facet_query_filters` filter
		 */
		$add_filter = function ( $filters, $args, $query ) {
			$filters[] = [
				'terms' => [
					'post_type' => [ 'post', 'page' ],
				],
			];

			$this->assertInstanceOf( '\WP_Query', $query );

			return $filters;
		};
		add_filter( 'ep_facet_query_filters', $add_filter, 10, 3 );

		$new_filters     = $facet_feature->apply_facets_filters( [], [], new \WP_Query( [ 'ep_is_facetable' => true ] ) );
		$expected_filter = [
			'facets' => [
				'bool' => [
					'must' => [
						[
							'terms' => [
								'post_type' => [ 'post', 'page' ],
							],
						],
					],
				],
			],
		];
		$this->assertSame( $expected_filter, $new_filters );

		/**
		 * Changing the match type should change from `must` to `should`
		 */
		$change_match_type = function () {
			return 'any';
		};
		add_filter( 'ep_facet_match_type', $change_match_type );

		$new_filters     = $facet_feature->apply_facets_filters( [], [], new \WP_Query( [ 'ep_is_facetable' => true ] ) );
		$expected_filter = [
			'facets' => [
				'bool' => [
					'should' => [
						[
							'terms' => [
								'post_type' => [ 'post', 'page' ],
							],
						],
					],
				],
			],
		];
		$this->assertSame( $expected_filter, $new_filters );
	}

	/**
	 * Test get_allowed_query_args
	 *
	 * @since 4.5.1
	 * @group facets
	 */
	public function testGetAllowedQueryArgs() {
		$facet_feature = Features::factory()->get_registered_feature( 'facets' );

		$default_allowed_args = [
			// Default:
			's',
			'post_type',
			'orderby',
			// Taxonomy related:
			'cat',
			'category_name',
			'post_format',
			'product_brand',
			'product_cat',
			'product_tag',
			'tag',
			'taxonomy',
			'term',
		];

		$this->assertEqualsCanonicalizing( $default_allowed_args, $facet_feature->get_allowed_query_args() );

		/**
		 * Test the `ep_facet_allowed_query_args` filter
		 */
		$add_allowed_query_arg = function ( $allowed ) {
			$allowed[] = 'test';
			return $allowed;
		};
		add_filter( 'ep_facet_allowed_query_args', $add_allowed_query_arg );

		$this->assertEqualsCanonicalizing( array_merge( $default_allowed_args, [ 'test' ] ), $facet_feature->get_allowed_query_args() );
	}

	/**
	 * Test Facets settings schema
	 *
	 * @since 5.0.0
	 * @group facets
	 */
	public function test_get_settings_schema() {
		$settings_schema = Features::factory()->get_registered_feature( 'facets' )->get_settings_schema();

		$settings_keys = wp_list_pluck( $settings_schema, 'key' );

		$this->assertSame(
			[ 'active', 'match_type' ],
			$settings_keys
		);
	}

	/**
	 * Test ep_facet_selected_filters filter.
	 *
	 * @since 5.1.4
	 * @group facets
	 */
	public function test_ep_facet_selected_filters() {
		$facet_feature = Features::factory()->get_registered_feature( 'facets' );

		parse_str( 'ep_filter_taxonomy=dolor,sit', $_GET );

		$add_prefix_with_terms = function ( $filters ) {
			$new_terms = [];
			foreach ( $filters['taxonomies']['taxonomy']['terms'] as $key => $value ) {
				$new_terms[ 'cap-' . $key ] = $value;
			}

			$filters['taxonomies']['taxonomy']['terms'] = $new_terms;
			return $filters;
		};
		add_filter( 'ep_facet_selected_filters', $add_prefix_with_terms );

		$selected = $facet_feature->get_selected();
		foreach ( $selected['taxonomies']['taxonomy']['terms'] as $key => $value ) {
			$this->assertStringStartsWith( 'cap-', $key );
		}
	}

	/**
	 * Test if the query has aggregations
	 *
	 * @since 5.3.0
	 * @group facets
	 */
	public function test_query_has_aggregations() {
		$this->ep_factory->post->create();
		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$query = new \WP_Query( [ 'ep_is_facetable' => true ] );
		$this->assertNotEmpty( $query->ep_aggregations );
	}

	/**
	 * Test if the query does not have aggregations
	 *
	 * @since 5.3.0
	 * @group facets
	 */
	public function test_query_does_not_have_aggregations() {
		$query = new \WP_Query( [ 'ep_is_facetable' => false ] );
		$this->assertEmpty( $query->ep_aggregations );
	}

	/**
	 * Test get_query_aggregations method
	 *
	 * @since 5.3.0
	 * @group facets
	 */
	public function test_get_query_aggregations() {
		$this->ep_factory->post->create_many( 5 );
		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$query = new \WP_Query( [ 'ep_is_facetable' => true ] );

		$expected_aggregations = [
			'post_type' => [
				'post' => 5,
			],
			'category'  => [
				'uncategorized' => 5,
			],
		];
		$facet_feature         = Features::factory()->get_registered_feature( 'facets' );

		$this->assertSame( $expected_aggregations, $facet_feature->get_query_aggregations( $query ) );
	}

	/**
	 * Test get_facet_aggregation method
	 *
	 * @since 5.3.0
	 * @group facets
	 */
	public function test_get_facet_aggregation() {
		$this->ep_factory->post->create_many( 5, [ 'post_type' => 'page' ] );
		$this->ep_factory->post->create_many( 5, [ 'post_type' => 'post' ] );
		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$query = new \WP_Query(
			[
				'ep_is_facetable' => true,
				'post_type'       => [ 'page', 'post' ],
			]
		);

		$expected_aggregations = [
			'post_type' => [
				'page' => 5,
				'post' => 5,
			],
		];

		$facet_feature = Features::factory()->get_registered_feature( 'facets' );
		$aggregation   = $facet_feature->get_facet_aggregation( $query, 'post_type' );

		$this->assertSame( $expected_aggregations['post_type'], $aggregation );
	}

	/**
	 * Test ep_is_facetable filter throws deprecated warning.
	 *
	 * @since 5.3.0
	 * @group facets
	 *
	 * @expectedDeprecated ep_is_facetable
	 */
	public function test_ep_is_facetable_throws_deprecated_warning() {
		Features::factory()->get_registered_feature( 'facets' );

		$this->ep_factory->post->create_many( 5 );
		ElasticPress\Elasticsearch::factory()->refresh_indices();

		add_filter( 'ep_is_facetable', '__return_true' );

		$query = new \WP_Query( [ 'ep_integrate' => true ] );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertNotEmpty( $query->ep_aggregations );
	}

	/**
	 * Test ep_facet_sync_aggregations_to_global filter
	 *
	 * @expectedIncorrectUsage ElasticPress\Feature\Facets\Facets::set_query_aggregations
	 *
	 * @since 5.3.0
	 * @group facets
	 */
	public function test_ep_facet_sync_aggregations_to_global_filter() {
		global $wp_the_query, $wp_query;

		Features::factory()->get_registered_feature( 'facets' );

		add_filter( 'ep_facet_sync_aggregations_to_global', '__return_true' );

		$this->ep_factory->post->create_many( 5 );
		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args  = [
			'ep_integrate'    => true,
			'ep_is_facetable' => true,
		];
		$query = new \WP_Query( $args );

		// mock the query as main query
		$wp_the_query = $query;
		$wp_query     = $query;

		$this->assertTrue( $query->elasticsearch_success );

		$query->query( $args );

		$this->assertNotEmpty( $GLOBALS['ep_facet_aggs'] );
		$this->assertEquals( $query->ep_aggregations, $GLOBALS['ep_facet_aggs'] );
	}

	/**
	 * Utilitary function for the testGetSelected test.
	 *
	 * Private as it is super specific and not likely to be extended.
	 *
	 * @param array  $terms    Array of terms as they should be in the $selected array.
	 * @param string $taxonomy Taxonomy slug
	 * @param array  $selected Array of selected terms.
	 */
	private function assertSelectedTax( $terms, $taxonomy, $selected ) {
		$this->assertIsArray( $selected );
		$this->assertIsArray( $selected['taxonomies'] );
		$this->assertCount( 1, $selected['taxonomies'] );
		$this->assertArrayHasKey( $taxonomy, $selected['taxonomies'] );
		$this->assertSame( $terms, $selected['taxonomies'][ $taxonomy ]['terms'] );
	}
}
