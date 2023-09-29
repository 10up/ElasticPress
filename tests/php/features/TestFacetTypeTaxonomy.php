<?php
/**
 * Test taxonomy facet type feature
 *
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress\Features;

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

		$public_taxonomies    = array_keys(
			get_taxonomies(
				array(
					'public'  => true,
					'show_ui' => true,
				),
				'names'
			)
		);
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
	}

	/**
	 * Test add_query_filters
	 *
	 * @since 4.4.0
	 * @group facets
	 */
	public function testAddQueryFilters() {
		$facet_feature = Features::factory()->get_registered_feature( 'facets' );
		$facet_type    = $facet_feature->types['taxonomy'];

		parse_str( 'ep_filter_taxonomy=dolor,amet', $_GET );

		$new_filters = $facet_type->add_query_filters( [] );
		$expected    = [
			[
				'term' => [
					'terms.taxonomy.slug' => 'dolor',
				],
			],
			[
				'term' => [
					'terms.taxonomy.slug' => 'amet',
				],
			],
		];
		$this->assertSame( $expected, $new_filters );

		/**
		 * Changing the match type should change from `term` to `terms`
		 */
		$change_match_type = function () {
			return 'any';
		};
		add_filter( 'ep_facet_match_type', $change_match_type );

		$new_filters = $facet_type->add_query_filters( [] );
		$expected    = [
			[
				'terms' => [
					'terms.taxonomy.slug' => [ 'dolor', 'amet' ],
				],
			],
		];
		$this->assertSame( $expected, $new_filters );
	}

	/**
	 * Test get_sanitize_callback method.
	 *
	 * @since 4.4.0
	 * @group facets
	 */
	public function testGetSanitizeCallback() {
		$facet_feature = Features::factory()->get_registered_feature( 'facets' );
		$test_taxonomy = 'This is a test taxonomy';

		parse_str( "ep_filter_taxonomy={$test_taxonomy}", $_GET );
		$selected = $facet_feature->get_selected();

		// test sanitize_title runs by default on taxonomy facets
		$expected_result = sanitize_title( $test_taxonomy );
		$this->assertArrayHasKey( $expected_result, $selected['taxonomies']['taxonomy']['terms'] );

		$sanitize_function = function( $function ) {

			$this->assertSame( 'sanitize_title', $function );

			return 'sanitize_text_field';
		};

		// modify the sanitize callback.
		add_filter( 'ep_facet_sanitize_callback', $sanitize_function );

		$selected = $facet_feature->get_selected();

		// test sanitize_text_field runs when filter is applied.
		$expected_result = sanitize_text_field( $test_taxonomy );
		$this->assertArrayHasKey( $expected_result, $selected['taxonomies']['taxonomy']['terms'] );
	}

	/**
	 * Test the format_selected method.
	 *
	 * @todo Move this to a mock, as it is just inherited now
	 * @since 4.5.0
	 */
	public function testFormatSelected() {
		$facet_feature = Features::factory()->get_registered_feature( 'facets' );
		$facet_type    = $facet_feature->types['taxonomy'];

		$original_filters = [ 'custom_type' => [ 'facet' => [ 1, 2, 3 ] ] ];
		$new_filters      = $facet_type->format_selected( 'category', 'slug1,slug2', $original_filters );
		$expected_filters = array_merge(
			$original_filters,
			[
				$facet_type->get_filter_type() => [
					'category' => [
						'terms' => [
							'slug1' => true,
							'slug2' => true,
						],
					],
				],
			]
		);

		$this->assertSame( $new_filters, $expected_filters );

		/**
		 * Analyzing tags=slug3,slug4 should ADD tags, keeping the category index.
		 */
		$original_filters = $expected_filters;
		$new_filters      = $facet_type->format_selected( 'tags', 'slug3,slug4', $original_filters );

		$expected_filters[ $facet_type->get_filter_type() ]['tags'] = [
			'terms' => [
				'slug3' => true,
				'slug4' => true,
			],
		];

		$this->assertSame( $new_filters, $expected_filters );
	}

	/**
	 * Test the add_query_params method.
	 *
	 * @todo Move this to a mock, as it is just inherited now
	 * @since 4.5.0
	 */
	public function testAddQueryParams() {
		$facet_feature = Features::factory()->get_registered_feature( 'facets' );
		$facet_type    = $facet_feature->types['taxonomy'];

		$original_query_params = [ 'custom_name' => 'custom_value' ];
		$selected_filters      = [
			[
				'custom_type' => [ 'facet' => [ 1, 2, 3 ] ],
			],
			$facet_type->get_filter_type() => [
				'category' => [
					'terms' => [
						'slug1' => true,
						'slug2' => true,
					],
				],
				'tags'     => [
					'terms' => [
						'slug3' => true,
						'slug4' => true,
					],
				],
			],
		];

		$new_query_params      = $facet_type->add_query_params( $original_query_params, $selected_filters );
		$expected_query_params = array_merge(
			$original_query_params,
			[
				$facet_type->get_filter_name() . 'category' => 'slug1,slug2',
				$facet_type->get_filter_name() . 'tags' => 'slug3,slug4',
			]
		);

		$this->assertSame( $new_query_params, $expected_query_params );
	}

	/**
	 * Test the ep_facet_tax_special_slug_taxonomies filter runs.
	 *
	 * @since 4.7.0
	 * @return void
	 */
	public function test_ep_facet_special_slug_taxonomies_filter() {
		add_filter(
			'ep_facet_tax_special_slug_taxonomies',
			function( $special_slug_taxonomies ) {
				$special_slug_taxonomies['testmyfilter'] = 'testmyfilterchangedfilter';
				return $special_slug_taxonomies;
			},
			99999
		);

		$facet_feature = Features::factory()->get_registered_feature( 'facets' );
		$facet_type    = $facet_feature->types['taxonomy'];

		parse_str( 'ep_filter_taxonomy=dolor,amet&ep_filter_testmyfilter=dolor,amet', $_GET );

		$query_filters = $facet_type->add_query_filters( [] );

		$sample_test[0]['term']['terms.taxonomy.slug']                  = 'dolor';
		$sample_test[1]['term']['terms.taxonomy.slug']                  = 'amet';
		$sample_test[2]['term']['terms.testmyfilterchangedfilter.slug'] = 'dolor';
		$sample_test[3]['term']['terms.testmyfilterchangedfilter.slug'] = 'amet';

		$this->assertEquals( $sample_test, $query_filters );
		$this->assertGreaterThanOrEqual( 1, did_filter( 'ep_facet_tax_special_slug_taxonomies' ) );
	}
}
