<?php
/**
 * Test taxonomy facet type feature
 *
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress\Features as Features;

/**
 * Facets\Types\Taxonomy\FacetType test class
 */
class TestFacetTypeTaxonomy extends BaseTestCase {

	/**
	 * Test build query URL
	 *
	 * @since 4.3.0
	 * @group facets
	 */
	public function testBuildQueryUrl() {
		$facet_feature = Features::factory()->get_registered_feature( 'facets' );
		$facet_type    = $facet_feature->types['taxonomy'];

		$filters = [
			'taxonomies' => [
				'category' => [
					'terms' => [
						'augue' => 1
					]
				]
			]
		];

		$this->assertEquals( '/?ep_filter_category=augue', $facet_type->build_query_url( $filters ) );

		$filters['s'] = 'dolor';
		$this->assertEquals( '/?ep_filter_category=augue&s=dolor', $facet_type->build_query_url( $filters ) );

		unset( $filters['s'] );
		$filters = [
			'taxonomies' => [
				'category' => [
					'terms' => [
						'augue'       => 1,
						'consectetur' => 1
					]
				]
			]
		];

		$this->assertEquals( '/?ep_filter_category=augue%2Cconsectetur', $facet_type->build_query_url( $filters ) );

		$_SERVER['REQUEST_URI'] = 'test/page/1';

		$filters['s'] = 'dolor';
		$this->assertEquals( 'test/?ep_filter_category=augue%2Cconsectetur&s=dolor', $facet_type->build_query_url( $filters ) );

		/**
		 * Test the `ep_facet_query_string` filter.
		 */
		$change_facet_query_string = function ( $query_string, $query_params ) {
			$this->assertIsArray( $query_params );
			$query_string .= '&foobar';
			return $query_string;
		};
		add_filter( 'ep_facet_query_string', $change_facet_query_string, 10, 2 );
		$this->assertStringEndsWith( '&foobar', $facet_type->build_query_url( $filters ) );
		remove_filter( 'ep_facet_query_string', $change_facet_query_string, 10, 2 );

		/**
		 * (Indirectly) test the `ep_facet_filter_name` filter
		 */
		$change_ep_facet_filter_name = function( $original_name ) {
			$this->assertEquals( 'ep_filter_', $original_name );
			return 'ep_custom_filter_';
		};
		add_filter( 'ep_facet_filter_name', $change_ep_facet_filter_name );
		$this->assertEquals( 'test/?ep_custom_filter_category=augue%2Cconsectetur&s=dolor', $facet_type->build_query_url( $filters ) );
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
		$facet_type    = $facet_feature->types['taxonomy'];

		$args = [
			'aggs' => [
				'terms' => []
			]
		];

		$query_args = [];

		$query = new \WP_Query();
		
		// No `ep_facet` in query_args will make it return the same array.
		$this->assertSame( $args, $facet_type->set_agg_filters( $args, $query_args, $query ) );

		// No `tax_query` in query_args will make it return the same array.
		$query_args = [
			'ep_facet' => 1,
		];
		$this->assertSame( $args, $facet_type->set_agg_filters( $args, $query_args, $query ) );

		$query_args = [
			'ep_facet'  => 1,
			'tax_query' => [
				[
					'taxonomy' => 'category',
					'field'    => 'slug',
					'terms'    => [ 'foo', 'bar' ],

				],
			],
		];
		$changed_args = $facet_type->set_agg_filters( $args, $query_args, $query );
		$this->assertArrayHasKey( 'filter', $changed_args['aggs']['terms'] );
	}
}
