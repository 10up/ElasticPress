<?php
/**
 * Test post type facet type feature
 *
 * @since 4.6.0
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress\Features;

/**
 * Facets\Types\PostType\FacetType test class
 */
class TestFacetTypePostType extends BaseTestCase {
	/**
	 * The facet type instance
	 *
	 * @var null|\ElasticPress\Feature\Facets\Types\PostType\FacetType
	 */
	protected $facet_type = null;

	/**
	 * Setup each test.
	 */
	public function set_up() {
		$facet_feature    = Features::factory()->get_registered_feature( 'facets' );
		$this->facet_type = $facet_feature->types['post-type'];

		parent::set_up();
	}

	/**
	 * Test get_filter_name
	 *
	 * @group facets
	 */
	public function testGetFilterName() {
		/**
		 * Test default behavior
		 */
		$this->assertEquals( 'ep_post_type_filter', $this->facet_type->get_filter_name() );

		/**
		 * Test the `ep_facet_post_type_filter_name` filter
		 */
		$change_filter_name = function( $filter_name ) {
			return $filter_name . '_';
		};
		add_filter( 'ep_facet_post_type_filter_name', $change_filter_name );
		$this->assertEquals( 'ep_post_type_filter_', $this->facet_type->get_filter_name() );
	}

	/**
	 * Test get_filter_type
	 *
	 * @group facets
	 */
	public function testGetFilterType() {
		/**
		 * Test default behavior
		 */
		$this->assertEquals( 'ep_post_type', $this->facet_type->get_filter_type() );

		/**
		 * Test the `ep_facet_post_type_filter_type` filter
		 */
		$change_filter_type = function( $filter_type ) {
			return $filter_type . '_';
		};
		add_filter( 'ep_facet_post_type_filter_type', $change_filter_type );
		$this->assertEquals( 'ep_post_type_', $this->facet_type->get_filter_type() );
	}

	/**
	 * Test set_wp_query_aggs
	 *
	 * @group facets
	 */
	public function testSetWpQueryAggs() {
		$initial_aggs = [ 'initial' ];

		add_filter( 'ep_facetable_post_types', '__return_empty_array' );
		$this->assertSame( $initial_aggs, $this->facet_type->set_wp_query_aggs( $initial_aggs ) );

		remove_filter( 'ep_facetable_post_types', '__return_empty_array' );

		$new_aggs = $this->facet_type->set_wp_query_aggs( $initial_aggs );

		$expected_agg = [
			'terms' => [
				'size'  => 10000,
				'field' => 'post_type.raw',
			],
		];
		$this->assertArrayHasKey( 'post_type', $new_aggs );
		$this->assertSame( $expected_agg, $new_aggs['post_type'] );

		/**
		 * Test the `ep_facet_post_type_size` filter
		 */
		$change_size = function ( $size, $post_types ) {
			$searchable_post_types = Features::factory()->get_registered_feature( 'search' )->get_searchable_post_types();
			$this->assertSame( $searchable_post_types, $post_types );
			return 5;
		};
		add_filter( 'ep_facet_post_type_size', $change_size, 10, 2 );

		$new_aggs = $this->facet_type->set_wp_query_aggs( $initial_aggs );
		$this->assertSame( 5, $new_aggs['post_type']['terms']['size'] );
	}

	/**
	 * Test add_query_filters
	 *
	 * @group facets
	 */
	public function testAddQueryFilters123() {
		parse_str( 'ep_post_type_filter=test1,test2', $_GET );

		$expected = [
			[
				'terms' => [
					'post_type.raw' => [ 'test1', 'test2' ],
				],
			],
		];
		$this->assertSame( $expected, $this->facet_type->add_query_filters( [] ) );
	}

	/**
	 * Test get_facetable_post_types
	 *
	 * @group facets
	 */
	public function testGetFacetablePostTypes() {
		$searchable_post_types = Features::factory()->get_registered_feature( 'search' )->get_searchable_post_types();

		$this->assertSame( $searchable_post_types, $this->facet_type->get_facetable_post_types() );

		/**
		 * Test the `ep_facetable_post_types` filter
		 */
		$change_filter_type = function( $post_types ) {
			$post_types['test'] = 'test';
			return $post_types;
		};
		add_filter( 'ep_facetable_post_types', $change_filter_type );
		$this->assertArrayHasKey( 'test', $this->facet_type->get_facetable_post_types() );
	}

	/**
	 * Test format_selected
	 *
	 * @group facets
	 */
	public function testFormatSelected() {
		$filters  = $this->facet_type->format_selected( '', 'test1,test2', [] );
		$expected = [
			'ep_post_type' => [
				'terms' => [
					'test1' => true,
					'test2' => true,
				],
			],
		];

		$this->assertSame( $expected, $filters );
	}

	/**
	 * Test add_query_params
	 *
	 * @group facets
	 */
	public function testAddQueryParams() {
		$new_filters = [
			'ep_post_type' => [
				'terms' => [
					'test1' => true,
					'test2' => true,
				],
			],
		];
		$filters     = $this->facet_type->add_query_params( [ 's' => 'test' ], $new_filters );
		$expected    = [
			's'                   => 'test',
			'ep_post_type_filter' => 'test1,test2',
		];

		$this->assertSame( $expected, $filters );
	}
}
