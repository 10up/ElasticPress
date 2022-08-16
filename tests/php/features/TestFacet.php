<?php
/**
 * Test facet feature
 *
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress\Features as Features;

/**
 * Facet test class
 */
class TestFacets extends BaseTestCase {
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
		$this->assertSelectedTax( [ 'dolor' => true, 'sit' => true ], 'taxonomy', $selected );

		parse_str( 'ep_filter_taxonomy=dolor,sit&ep_filter_othertax=amet', $_GET );
		$selected = $facet_feature->get_selected();

		$this->assertIsArray( $selected );
		$this->assertIsArray( $selected['taxonomies'] );
		$this->assertCount( 2, $selected['taxonomies'] );
		$this->assertArrayHasKey( 'taxonomy', $selected['taxonomies'] );
		$this->assertArrayHasKey( 'othertax', $selected['taxonomies'] );
		$this->assertSame( [ 'dolor' => true, 'sit' => true ], $selected['taxonomies']['taxonomy']['terms'] );
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
						'augue' => 1
					]
				]
			]
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
						'consectetur' => 1
					]
				]
			]
		];

		$this->assertEquals( '/?ep_filter_category=augue%2Cconsectetur', $facet_feature->build_query_url( $filters ) );

		$_SERVER['REQUEST_URI'] = 'test/page/1';

		$filters['s'] = 'dolor';
		$this->assertEquals( 'test/?ep_filter_category=augue%2Cconsectetur&s=dolor', $facet_feature->build_query_url( $filters ) );

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
		$change_ep_facet_filter_name = function( $original_name ) {
			$this->assertEquals( 'ep_filter_', $original_name );
			return 'ep_custom_filter_';
		};
		add_filter( 'ep_facet_filter_name', $change_ep_facet_filter_name );
		$this->assertEquals( 'test/?ep_custom_filter_category=augue%2Cconsectetur&s=dolor', $facet_feature->build_query_url( $filters ) );
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
				'terms' => []
			]
		];

		$query_args = [];

		$query = new \WP_Query();
		
		// No `ep_facet` in query_args will make it return the same array.
		$this->assertSame( $args, $facet_feature->set_agg_filters( $args, $query_args, $query ) );

		// No `tax_query` in query_args will make it return the same array.
		$query_args = [
			'ep_facet' => 1,
		];
		$this->assertSame( $args, $facet_feature->set_agg_filters( $args, $query_args, $query ) );

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
		$changed_args = $facet_feature->set_agg_filters( $args, $query_args, $query );
		$this->assertArrayHasKey( 'filter', $changed_args['aggs']['terms'] );
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
