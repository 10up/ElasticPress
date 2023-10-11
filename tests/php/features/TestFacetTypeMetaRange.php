<?php
/**
 * Test meta range facet type feature
 *
 * @since 4.5.0
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress\Features;

/**
 * Facets\Types\Taxonomy\FacetType test class
 */
class TestFacetTypeMetaRange extends BaseTestCase {

	/**
	 * The facet type instance
	 *
	 * @var null|\ElasticPress\Feature\Facets\Types\MetaRange\FacetType
	 */
	protected $facet_type = null;

	/**
	 * Setup each test.
	 */
	public function set_up() {
		/**
		 * It is too late to use the `ep_facet_types` filter.
		 *
		 * NOTE: This can be removed after the meta range facet type is made available.
		 */
		$facet_feature = Features::factory()->get_registered_feature( 'facets' );
		if ( ! isset( $facet_feature->types['meta-range'] ) && class_exists( '\ElasticPress\Feature\Facets\Types\MetaRange\FacetType' ) ) {
			$facet_feature->types['meta-range'] = new \ElasticPress\Feature\Facets\Types\MetaRange\FacetType();
			$facet_feature->types['meta-range']->setup();
		}

		$facet_feature    = Features::factory()->get_registered_feature( 'facets' );
		$this->facet_type = $facet_feature->types['meta-range'];

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
		$this->assertEquals( 'ep_meta_range_filter_', $this->facet_type->get_filter_name() );

		/**
		 * Test the `ep_facet_meta_filter_name` filter
		 */
		$change_filter_name = function( $filter_name ) {
			return $filter_name . '_';
		};
		add_filter( 'ep_facet_meta_range_filter_name', $change_filter_name );
		$this->assertEquals( 'ep_meta_range_filter__', $this->facet_type->get_filter_name() );
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
		$this->assertEquals( 'meta-range', $this->facet_type->get_filter_type() );

		/**
		 * Test the `ep_facet_filter_type` filter
		 */
		$change_filter_type = function( $filter_type ) {
			return $filter_type . '_';
		};
		add_filter( 'ep_facet_meta_range_filter_type', $change_filter_type );
		$this->assertEquals( 'meta-range_', $this->facet_type->get_filter_type() );
	}

	/**
	 * Test add_query_filters
	 *
	 * @group facets
	 */
	public function testAddQueryFilters() {
		$allow_field = function ( $fields ) {
			$fields[] = 'my_custom_field';
			return $fields;
		};
		add_filter( 'ep_facet_meta_range_fields', $allow_field );

		parse_str( 'ep_meta_range_filter_my_custom_field_min=5&ep_meta_range_filter_my_custom_field_max=25', $_GET );

		$new_filters = $this->facet_type->add_query_filters( [] );
		$expected    = [
			[
				'range' => [
					'meta.my_custom_field.double' => [
						'gte' => floatval( 5 ),
						'lte' => floatval( 25 ),
					],
				],
			],
		];
		$this->assertSame( $expected, $new_filters );

		/**
		 * If applying filters to the aggregations, we do not add the range as that would restrict
		 * min and max to the selected values
		 */
		$new_filters = $this->facet_type->add_query_filters( [], [ 'ep_facet_adding_agg_filters' => true ] );
		$this->assertSame( [], $new_filters );
	}

	/**
	 * Test add_query_filters with not allowed parameters
	 *
	 * @since 4.5.1
	 * @group facets
	 */
	public function testAddQueryFiltersWithNotAllowedParameters() {
		$allow_field = function ( $fields ) {
			$fields[] = 'my_custom_field';
			return $fields;
		};
		add_filter( 'ep_facet_meta_range_fields', $allow_field );

		parse_str( 'ep_meta_range_filter_my_custom_field_min=5&ep_meta_range_filter_my_custom_field_max=25&ep_meta_range_filter_not_allowed_min=5&ep_meta_range_filter_not_allowed_max=25', $_GET );

		// Should not have `not_allowed` yet
		$new_filters = $this->facet_type->add_query_filters( [] );
		$expected    = [
			[
				'range' => [
					'meta.my_custom_field.double' => [
						'gte' => floatval( 5 ),
						'lte' => floatval( 25 ),
					],
				],
			],
		];
		$this->assertSame( $expected, $new_filters );

		add_filter( 'ep_facet_should_check_if_allowed', '__return_false' );

		// As we are not checking, it should have `not_allowed` now
		$new_filters = $this->facet_type->add_query_filters( [] );
		$expected    = [
			[
				'range' => [
					'meta.my_custom_field.double' => [
						'gte' => floatval( 5 ),
						'lte' => floatval( 25 ),
					],
				],
			],
			[
				'range' => [
					'meta.not_allowed.double' => [
						'gte' => floatval( 5 ),
						'lte' => floatval( 25 ),
					],
				],
			],
		];
		$this->assertSame( $expected, $new_filters );
	}

	/**
	 * Test set_wp_query_aggs
	 *
	 * @group facets
	 */
	public function testSetWpQueryAggs() {
		$set_facet_meta_field = function() {
			return [ 'new_meta_key_1', 'new_meta_key_2' ];
		};
		add_filter( 'ep_facet_meta_range_fields', $set_facet_meta_field );

		$with_aggs = $this->facet_type->set_wp_query_aggs( [] );

		/**
		 * Test default behavior
		 */
		$default_min_agg = [
			'min' => [
				'field' => 'meta.new_meta_key_1.double',
			],
		];
		$this->assertSame( $with_aggs['ep_meta_range_filter_new_meta_key_1_min'], $default_min_agg );
		$default_max_agg = [
			'max' => [
				'field' => 'meta.new_meta_key_1.double',
			],
		];
		$this->assertSame( $with_aggs['ep_meta_range_filter_new_meta_key_1_max'], $default_max_agg );

		/**
		 * Test the `ep_facet_meta_use_field` filter
		 */
		$change_meta_facet_field = function( $es_field, $meta_field ) {
			return ( 'new_meta_key_1' === $meta_field ) ? 'long' : $es_field;
		};

		add_filter( 'ep_facet_meta_use_field', $change_meta_facet_field, 10, 2 );

		$with_aggs = $this->facet_type->set_wp_query_aggs( [] );
		$this->assertSame( 'meta.new_meta_key_1.double', $with_aggs['ep_meta_range_filter_new_meta_key_1_min']['min']['field'] );
		$this->assertSame( 'meta.new_meta_key_1.double', $with_aggs['ep_meta_range_filter_new_meta_key_1_max']['max']['field'] );
		$this->assertSame( 'meta.new_meta_key_2.double', $with_aggs['ep_meta_range_filter_new_meta_key_2_min']['min']['field'] );
		$this->assertSame( 'meta.new_meta_key_2.double', $with_aggs['ep_meta_range_filter_new_meta_key_2_max']['max']['field'] );

		remove_filter( 'ep_facet_meta_use_field', $change_meta_facet_field );
	}

	/**
	 * Test the format_selected method.
	 */
	public function testFormatSelected() {
		$original_filters = [ 'custom_type' => [ 'facet' => [ 1, 2, 3 ] ] ];
		$new_filters      = $this->facet_type->format_selected( 'my_meta_min', '5', $original_filters );
		$expected_filters = array_merge(
			$original_filters,
			[
				$this->facet_type->get_filter_type() => [
					'my_meta' => [
						'_min' => '5',
					],
				],
			]
		);

		$this->assertSame( $new_filters, $expected_filters );

		/**
		 * Analyzing tags=slug3,slug4 should ADD tags, keeping the category index.
		 */
		$original_filters = $expected_filters;
		$new_filters      = $this->facet_type->format_selected( 'my_meta_max', '25', $original_filters );

		$expected_filters[ $this->facet_type->get_filter_type() ]['my_meta'] = [
			'_min' => '5',
			'_max' => '25',
		];

		$this->assertSame( $new_filters, $expected_filters );
	}

	/**
	 * Test the add_query_params method.
	 */
	public function testAddQueryParams() {
		$original_query_params = [ 'custom_name' => 'custom_value' ];
		$selected_filters      = [
			[
				'custom_type' => [ 'facet' => [ 1, 2, 3 ] ],
			],
			$this->facet_type->get_filter_type() => [
				'my_meta_1' => [
					'_min' => '5',
					'_max' => '25',
				],
				'my_meta_2' => [
					'_min' => '10',
					'_max' => '50',
				],
			],
		];

		$new_query_params      = $this->facet_type->add_query_params( $original_query_params, $selected_filters );
		$expected_query_params = array_merge(
			$original_query_params,
			[
				$this->facet_type->get_filter_name() . 'my_meta_1_min' => '5',
				$this->facet_type->get_filter_name() . 'my_meta_1_max' => '25',
				$this->facet_type->get_filter_name() . 'my_meta_2_min' => '10',
				$this->facet_type->get_filter_name() . 'my_meta_2_max' => '50',
			]
		);

		$this->assertSame( $new_query_params, $expected_query_params );
	}

	/**
	 * Test get_facets_meta_fields
	 *
	 * @group facets
	 */
	public function testGetFacetsMetaFields() {
		$this->markTestIncomplete();
	}
}
