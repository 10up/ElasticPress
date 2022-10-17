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
	 * Test get_filter_name
	 *
	 * @since 4.3.0
	 * @group facets
	 */
	public function testGetFilterName() {
		$facet_feature = Features::factory()->get_registered_feature( 'facets' );
		$facet_type    = $facet_feature->types['taxonomy'];

		/**
		 * Test default behavior
		 */
		$this->assertEquals( 'ep_filter_', $facet_type->get_filter_name() );

		/**
		 * Test the `ep_facet_filter_name` filter
		 */
		$change_filter_name = function( $filter_name ) {
			return $filter_name . '_';
		};
		add_filter( 'ep_facet_filter_name', $change_filter_name );
		$this->assertEquals( 'ep_filter__', $facet_type->get_filter_name() );
		remove_filter( 'ep_facet_filter_name', $change_filter_name );
	}

	/**
	 * Test get_filter_type
	 *
	 * @since 4.3.0
	 * @group facets
	 */
	public function testGetFilterType() {
		$facet_feature = Features::factory()->get_registered_feature( 'facets' );
		$facet_type    = $facet_feature->types['taxonomy'];

		/**
		 * Test default behavior
		 */
		$this->assertEquals( 'taxonomies', $facet_type->get_filter_type() );

		/**
		 * Test the `ep_facet_filter_type` filter
		 */
		$change_filter_type = function( $filter_type ) {
			return $filter_type . '_';
		};
		add_filter( 'ep_facet_filter_type', $change_filter_type );
		$this->assertEquals( 'taxonomies_', $facet_type->get_filter_type() );
		remove_filter( 'ep_facet_filter_type', $change_filter_type );
	}

	/**
	 * Test get_facetable_taxonomies
	 *
	 * @since 4.3.0
	 * @group facets
	 */
	public function testGetFacetableTaxonomies() {
		$facet_feature = Features::factory()->get_registered_feature( 'facets' );
		$facet_type    = $facet_feature->types['taxonomy'];

		$public_taxonomies    = array_keys( get_taxonomies( array( 'public' => true, 'show_ui' => true ), 'names' ) );
		$facetable_taxonomies = array_keys( $facet_type->get_facetable_taxonomies() );

		/**
		 * Test default behavior
		 */
		$this->assertEqualsCanonicalizing( $public_taxonomies, $facetable_taxonomies );
		$this->assertContains( 'category', $facetable_taxonomies );

		/**
		 * Test the `ep_facet_include_taxonomies` filter
		 */
		$change_facetable_taxonomies = function( $taxonomies ) {
			unset( $taxonomies['category'] );
			return $taxonomies;
		};
		add_filter( 'ep_facet_include_taxonomies', $change_facetable_taxonomies );

		$facetable_taxonomies = array_keys( $facet_type->get_facetable_taxonomies() );
		$this->assertNotContains( 'category', $facetable_taxonomies );

		remove_filter( 'ep_facet_include_taxonomies', $change_facetable_taxonomies );
	}

	/**
	 * Test set_wp_query_aggs
	 *
	 * @since 4.3.0
	 * @group facets
	 */
	public function testSetWpQueryAggs() {
		$facet_feature = Features::factory()->get_registered_feature( 'facets' );
		$facet_type    = $facet_feature->types['taxonomy'];

		$with_aggs = $facet_type->set_wp_query_aggs( [] );

		/**
		 * Test default behavior
		 */
		$default_cat_agg = [
			'terms' => [
				'size'  => 10000,
				'field' => 'terms.category.slug',
			],
		];
		$this->assertSame( $with_aggs['category'], $default_cat_agg );

		/**
		 * Test the `ep_facet_use_field` filter
		 */
		$change_cat_facet_field = function( $field, $taxonomy ) {
			return ( 'category' === $taxonomy->name ) ? 'term_id' : $field;
		};

		add_filter( 'ep_facet_use_field', $change_cat_facet_field, 10, 2 );

		$with_aggs = $facet_type->set_wp_query_aggs( [] );
		$this->assertSame( 'terms.category.term_id', $with_aggs['category']['terms']['field'] );
		$this->assertSame( 'terms.post_tag.slug', $with_aggs['post_tag']['terms']['field'] );

		remove_filter( 'ep_facet_use_field', $change_cat_facet_field );

		/**
		 * Test the `ep_facet_taxonomies_size` filter
		 */
		$change_tax_bucket_size = function( $size, $taxonomy ) {
			return ( 'category' === $taxonomy->name ) ? 5 : $size;
		};

		add_filter( 'ep_facet_taxonomies_size', $change_tax_bucket_size, 10, 2 );

		$with_aggs = $facet_type->set_wp_query_aggs( [] );
		$this->assertSame( 5, $with_aggs['category']['terms']['size'] );
		$this->assertSame( 10000, $with_aggs['post_tag']['terms']['size'] );

		remove_filter( 'ep_facet_taxonomies_size', $change_tax_bucket_size );
	}

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
