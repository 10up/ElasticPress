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
	 * Test the `get_selected` method
	 *
	 * @since 4.3.0
	 * @group facets
	 */
	public function testGetSelected() {
		$facet_feature = Features::factory()->get_registered_feature( 'facets' );
		$facet_type    = $facet_feature->types['taxonomy'];

		parse_str( 'ep_filter_taxonomy=dolor', $_GET );
		$selected = $facet_type->get_selected();
		$this->assertSelectedTax( [ 'dolor' => true ], 'taxonomy', $selected );

		parse_str( 'ep_filter_taxonomy=dolor,sit', $_GET );
		$selected = $facet_type->get_selected();
		$this->assertSelectedTax( [ 'dolor' => true, 'sit' => true ], 'taxonomy', $selected );

		parse_str( 'ep_filter_taxonomy=dolor,sit&ep_filter_othertax=amet', $_GET );
		$selected = $facet_type->get_selected();

		$this->assertIsArray( $selected );
		$this->assertIsArray( $selected['taxonomies'] );
		$this->assertCount( 2, $selected['taxonomies'] );
		$this->assertArrayHasKey( 'taxonomy', $selected['taxonomies'] );
		$this->assertArrayHasKey( 'othertax', $selected['taxonomies'] );
		$this->assertSame( [ 'dolor' => true, 'sit' => true ], $selected['taxonomies']['taxonomy']['terms'] );
		$this->assertSame( [ 'amet' => true ], $selected['taxonomies']['othertax']['terms'] );

		parse_str( 's=searchterm&ep_filter_taxonomy=dolor', $_GET );
		$selected = $facet_type->get_selected();
		$this->assertSelectedTax( [ 'dolor' => true ], 'taxonomy', $selected );
		$this->assertArrayHasKey( 's', $selected );
		$this->assertSame( 'searchterm', $selected['s'] );

		parse_str( 'post_type=posttype&ep_filter_taxonomy=dolor', $_GET );
		$selected = $facet_type->get_selected();
		$this->assertSelectedTax( [ 'dolor' => true ], 'taxonomy', $selected );
		$this->assertArrayHasKey( 'post_type', $selected );
		$this->assertSame( 'posttype', $selected['post_type'] );
	}

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
