<?php
/**
 * Test meta facet type feature
 *
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress\Features;

/**
 * Facets\Types\Taxonomy\FacetType test class
 */
class TestFacetTypeMeta extends BaseTestCase {

	/**
	 * Setup each test.
	 */
	public function set_up() {
		/**
		 * It is too late to use the `ep_facet_types` filter.
		 *
		 * NOTE: This can be removed after the meta facet type is made available.
		 */
		$facet_feature = Features::factory()->get_registered_feature( 'facets' );
		if ( ! isset( $facet_feature->types['meta'] ) && class_exists( '\ElasticPress\Feature\Facets\Types\Meta\FacetType' ) ) {
			$facet_feature->types['meta'] = new \ElasticPress\Feature\Facets\Types\Meta\FacetType();
			$facet_feature->types['meta']->setup();
		}

		parent::set_up();
	}

	/**
	 * Test get_filter_name
	 *
	 * @since 4.3.0
	 * @group facets
	 */
	public function testGetFilterName() {
		$facet_feature = Features::factory()->get_registered_feature( 'facets' );
		$facet_type    = $facet_feature->types['meta'];

		/**
		 * Test default behavior
		 */
		$this->assertEquals( 'ep_meta_filter_', $facet_type->get_filter_name() );

		/**
		 * Test the `ep_facet_meta_filter_name` filter
		 */
		$change_filter_name = function( $filter_name ) {
			return $filter_name . '_';
		};
		add_filter( 'ep_facet_meta_filter_name', $change_filter_name );
		$this->assertEquals( 'ep_meta_filter__', $facet_type->get_filter_name() );
	}

	/**
	 * Test get_filter_type
	 *
	 * @since 4.3.0
	 * @group facets
	 */
	public function testGetFilterType() {
		$facet_feature = Features::factory()->get_registered_feature( 'facets' );
		$facet_type    = $facet_feature->types['meta'];

		/**
		 * Test default behavior
		 */
		$this->assertEquals( 'meta', $facet_type->get_filter_type() );

		/**
		 * Test the `ep_facet_filter_type` filter
		 */
		$change_filter_type = function( $filter_type ) {
			return $filter_type . '_';
		};
		add_filter( 'ep_facet_meta_filter_type', $change_filter_type );
		$this->assertEquals( 'meta_', $facet_type->get_filter_type() );
	}

	/**
	 * Test set_wp_query_aggs
	 *
	 * @since 4.3.0
	 * @group facets
	 */
	public function testSetWpQueryAggs() {
		$facet_feature = Features::factory()->get_registered_feature( 'facets' );
		$facet_type    = $facet_feature->types['meta'];

		$set_facet_meta_field = function() {
			return [ 'new_meta_key_1', 'new_meta_key_2' ];
		};
		add_filter( 'ep_facet_meta_fields', $set_facet_meta_field );

		$with_aggs = $facet_type->set_wp_query_aggs( [] );

		/**
		 * Test default behavior
		 */
		$default_cat_agg = [
			'terms' => [
				'size'  => 10000,
				'field' => 'meta.new_meta_key_1.raw',
			],
		];
		$this->assertSame( $with_aggs['ep_meta_filter_new_meta_key_1'], $default_cat_agg );

		/**
		 * Test the `ep_facet_meta_use_field` filter
		 */
		$change_meta_facet_field = function( $es_field, $meta_field ) {
			return ( 'new_meta_key_1' === $meta_field ) ? 'boolean' : $es_field;
		};

		add_filter( 'ep_facet_meta_use_field', $change_meta_facet_field, 10, 2 );

		$with_aggs = $facet_type->set_wp_query_aggs( [] );
		$this->assertSame( 'meta.new_meta_key_1.boolean', $with_aggs['ep_meta_filter_new_meta_key_1']['terms']['field'] );
		$this->assertSame( 'meta.new_meta_key_2.raw', $with_aggs['ep_meta_filter_new_meta_key_2']['terms']['field'] );

		remove_filter( 'ep_facet_meta_use_field', $change_meta_facet_field );

		/**
		 * Test the `ep_facet_meta_size` filter
		 */
		$change_meta_bucket_size = function( $size, $meta_field ) {
			return ( 'new_meta_key_1' === $meta_field ) ? 5 : $size;
		};

		add_filter( 'ep_facet_meta_size', $change_meta_bucket_size, 10, 2 );

		$with_aggs = $facet_type->set_wp_query_aggs( [] );
		$this->assertSame( 5, $with_aggs['ep_meta_filter_new_meta_key_1']['terms']['size'] );
		$this->assertSame( 10000, $with_aggs['ep_meta_filter_new_meta_key_2']['terms']['size'] );
	}

	/**
	 * Test get_meta_values
	 *
	 * @since 4.3.0
	 * @group facets
	 */
	public function testGetMetaValues() {
		$facet_feature = Features::factory()->get_registered_feature( 'facets' );
		$facet_type    = $facet_feature->types['meta'];

		$this->ep_factory->post->create( array( 'meta_input' => array( 'new_meta_key_1' => 'foo' ) ) );
		$this->ep_factory->post->create( array( 'meta_input' => array( 'new_meta_key_1' => 'bar' ) ) );
		$this->ep_factory->post->create( array( 'meta_input' => array( 'new_meta_key_1' => 'foobar' ) ) );

		$this->ep_factory->post->create( array( 'meta_input' => array( 'new_meta_key_2' => 'lorem' ) ) );
		$this->ep_factory->post->create( array( 'meta_input' => array( 'new_meta_key_2' => 'ipsum' ) ) );

		\ElasticPress\Elasticsearch::factory()->refresh_indices();

		/**
		 * Test default behavior
		 */
		$meta_values = $facet_type->get_meta_values( 'new_meta_key_1' );
		$this->assertEqualsCanonicalizing( [ 'foo', 'bar', 'foobar' ], $meta_values );

		// Make sure it is using the cached value and is not going to Elasticsearch.
		$ep_remote_request_count = did_action( 'ep_remote_request' );

		$meta_values = $facet_type->get_meta_values( 'new_meta_key_1' );
		$this->assertSame( $ep_remote_request_count, did_action( 'ep_remote_request' ) );

		/**
		 * Test the `ep_facet_meta_custom_meta_values` filter
		 */
		$change_meta_values = function( $meta_values, $meta_key ) {
			return ( 'new_meta_key_1' === $meta_key ) ? [ '123' ] : $meta_values;
		};
		add_filter( 'ep_facet_meta_custom_meta_values', $change_meta_values, 10, 2 );
		$meta_values_1 = $facet_type->get_meta_values( 'new_meta_key_1' );
		$meta_values_2 = $facet_type->get_meta_values( 'new_meta_key_2' );

		$this->assertSame( [ '123' ], $meta_values_1 );
		$this->assertEqualsCanonicalizing( [ 'lorem', 'ipsum' ], $meta_values_2 );

		remove_filter( 'ep_facet_meta_custom_meta_values', $change_meta_values );

		delete_transient( get_class( $facet_type )::TRANSIENT_PREFIX . 'new_meta_key_1' );
		delete_transient( get_class( $facet_type )::TRANSIENT_PREFIX . 'new_meta_key_2' );

		/**
		 * Test the `ep_facet_meta_value_max_strlen` filter
		 */
		$change_max_str_len = function( $length, $meta_key ) {
			return ( 'new_meta_key_2' === $meta_key ) ? 4 : $length;
		};
		add_filter( 'ep_facet_meta_value_max_strlen', $change_max_str_len, 10, 2 );
		$meta_values_1 = $facet_type->get_meta_values( 'new_meta_key_1' );
		$meta_values_2 = $facet_type->get_meta_values( 'new_meta_key_2' );

		$this->assertEqualsCanonicalizing( [ 'foo', 'bar', 'foobar' ], $meta_values_1 );
		$this->assertEqualsCanonicalizing( [ 'lore', 'ipsu' ], $meta_values_2 );
	}

	/**
	 * Test add_query_filters
	 *
	 * @since 4.4.0
	 * @group facets
	 */
	public function testAddQueryFilters() {
		$facet_feature = Features::factory()->get_registered_feature( 'facets' );
		$facet_type    = $facet_feature->types['meta'];

		$allow_field = function ( $fields ) {
			$fields[] = 'my_custom_field';
			return $fields;
		};
		add_filter( 'ep_facet_meta_fields', $allow_field );

		parse_str( 'ep_meta_filter_my_custom_field=dolor,amet', $_GET );

		$new_filters = $facet_type->add_query_filters( [] );
		$expected    = [
			[
				'term' => [
					'meta.my_custom_field.raw' => 'dolor',
				],
			],
			[
				'term' => [
					'meta.my_custom_field.raw' => 'amet',
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
					'meta.my_custom_field.raw' => [ 'dolor', 'amet' ],
				],
			],
		];
		$this->assertSame( $expected, $new_filters );
	}

	/**
	 * Test add_query_filters with not allowed parameters
	 *
	 * @since 4.5.1
	 * @group facets
	 */
	public function testAddQueryFiltersWithNotAllowedParameters() {
		$facet_feature = Features::factory()->get_registered_feature( 'facets' );
		$facet_type    = $facet_feature->types['meta'];

		$allow_field = function ( $fields ) {
			$fields[] = 'my_custom_field';
			return $fields;
		};
		add_filter( 'ep_facet_meta_fields', $allow_field );

		parse_str( 'ep_meta_filter_my_custom_field=dolor,amet&ep_meta_filter_not_allowed=1', $_GET );

		$new_filters = $facet_type->add_query_filters( [] );
		$expected    = [
			[
				'term' => [
					'meta.my_custom_field.raw' => 'dolor',
				],
			],
			[
				'term' => [
					'meta.my_custom_field.raw' => 'amet',
				],
			],
		];
		$this->assertSame( $expected, $new_filters );

		add_filter( 'ep_facet_should_check_if_allowed', '__return_false' );

		$new_filters = $facet_type->add_query_filters( [] );
		$expected    = [
			[
				'term' => [
					'meta.my_custom_field.raw' => 'dolor',
				],
			],
			[
				'term' => [
					'meta.my_custom_field.raw' => 'amet',
				],
			],
			[
				'term' => [
					'meta.not_allowed.raw' => 1,
				],
			],
		];
		$this->assertSame( $expected, $new_filters );
	}

	/**
	 * Test get_facets_meta_fields
	 *
	 * @since 4.3.0
	 * @group facets
	 */
	public function testGetFacetsMetaFields() {
		$this->markTestIncomplete();
	}

	/**
	 * Test invalidate_meta_values_cache
	 *
	 * @since 4.3.0
	 * @group facets
	 */
	public function testInvalidateMetaValuesCache() {
		$this->markTestIncomplete();
	}

	/**
	 * Test invalidate_meta_values_cache_after_bulk
	 *
	 * @since 4.3.0
	 * @group facets
	 */
	public function testInvalidateMetaValuesCacheAfterBulk() {
		$this->markTestIncomplete();
	}

	/**
	 * Test get_sanitize_callback method.
	 *
	 * @since 4.4.0
	 * @group facets
	 */
	public function testGetSanitizeCallback() {

		$facet_feature = Features::factory()->get_registered_feature( 'facets' );
		$test_meta     = 'This is a test meta';

		parse_str( "ep_meta_filter_new_meta_key_1={$test_meta}", $_GET );
		$selected = $facet_feature->get_selected();

		// test sanitize_text_field runs by default on taxonomy facets
		$expected_result = sanitize_text_field( $test_meta );
		$this->assertArrayHasKey( $expected_result, $selected['meta']['new_meta_key_1']['terms'] );

		$sanitize_function = function( $function ) {

			$this->assertSame( 'sanitize_text_field', $function );

			return 'sanitize_title';
		};

		// modify the sanitize callback.
		add_filter( 'ep_facet_default_sanitize_callback', $sanitize_function );

		$selected = $facet_feature->get_selected();

		// test sanitize_text_field runs when filter is applied.
		$expected_result = sanitize_title( $test_meta );
		$this->assertArrayHasKey( $expected_result, $selected['meta']['new_meta_key_1']['terms'] );
	}
}
