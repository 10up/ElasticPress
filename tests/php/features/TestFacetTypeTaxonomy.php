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
