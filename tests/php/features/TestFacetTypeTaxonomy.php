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
	 * Test agg_filters
	 *
	 * @since 4.3.0
	 * @group facets
	 */
	public function testAggFilters() {
		$facet_feature = Features::factory()->get_registered_feature( 'facets' );
		$facet_type    = $facet_feature->types['taxonomy'];

		$query_args = [];
		$this->assertSame( $query_args, $facet_type->agg_filters( $query_args ) );

		$query_args = [
			'tax_query' => [
				[
					'taxonomy' => 'category',
					'terms'    => [ 1, 2, 3 ],
				],
				[
					'taxonomy' => 'post_tag',
					'terms'    => [ 4, 5, 6 ],
				],
			],
		];

		/**
		 * Test when `match_type` is `all`. In this case, all the filters applied to the
		 * main query should be applied to aggregations as well.
		 */
		$set_facet_match_type_all = function() {
			return [
				'facets' => [
					'match_type' => 'all',
				],
			];
		};
		add_filter( 'pre_site_option_ep_feature_settings', $set_facet_match_type_all );
		add_filter( 'pre_option_ep_feature_settings', $set_facet_match_type_all );

		$this->assertSame( $query_args, $facet_type->agg_filters( $query_args ) );

		remove_filter( 'pre_site_option_ep_feature_settings', $set_facet_match_type_all );
		remove_filter( 'pre_option_ep_feature_settings', $set_facet_match_type_all );

		/**
		 * Test when `match_type` is `any`. In this case, the code should remove
		 * from the aggregations filter the taxonomy filters applied to the main query.
		 */
		$set_facet_match_type_any = function() {
			return [
				'facets' => [
					'match_type' => 'any',
				],
			];
		};
		add_filter( 'pre_site_option_ep_feature_settings', $set_facet_match_type_any );
		add_filter( 'pre_option_ep_feature_settings', $set_facet_match_type_any );

		$this->assertSame( [ 'tax_query' => [] ], $facet_type->agg_filters( $query_args ) );

		remove_filter( 'pre_site_option_ep_feature_settings', $set_facet_match_type_any );
		remove_filter( 'pre_option_ep_feature_settings', $set_facet_match_type_any );

		/**
		 * Test the removal of unwanted parameters.
		 */
		$query_args = [
			'category_name' => 'lorem',
			'cat'           => 'lorem',
			'tag'           => 'lorem',
			'tag_id'        => 'lorem',
			'taxonomy'      => 'lorem',
			'term'          => 'lorem',
			'tax_query'     => [ [] ],
		];
		$this->assertSame( [ 'tax_query' => [ [] ] ], $facet_type->agg_filters( $query_args ) );
	}
}
