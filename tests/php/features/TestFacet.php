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
	 * Setup each test.
	 *
	 * @since 3.6.0
	 */
	public function setUp() {
		parent::setUp();
	}

	/**
	 * Clean up after each test.
	 *
	 * @since 3.6.0
	 */
	public function tearDown() {
		parent::tearDown();
	}


	/**
	 * Test the `get_selected` method
	 *
	 * @since 3.6.0
	 * @group facets
	 *
	 */
	public function testGetSelected() {
		$facet_feature = Features::factory()->get_registered_feature( 'facets' );

		parse_str( 'filter_taxonomy=dolor', $_GET );
		$selected = $facet_feature->get_selected();
		$this->assertSelectedTax( [ 'dolor' => true ], 'taxonomy', $selected );

		parse_str( 'filter_taxonomy=dolor,sit', $_GET );
		$selected = $facet_feature->get_selected();
		$this->assertSelectedTax( [ 'dolor' => true, 'sit' => true ], 'taxonomy', $selected );

		parse_str( 'filter_taxonomy=dolor,sit&filter_othertax=amet', $_GET );
		$selected = $facet_feature->get_selected();

		$this->assertIsArray( $selected );
		$this->assertIsArray( $selected['taxonomies'] );
		$this->assertCount( 2, $selected['taxonomies'] );
		$this->assertArrayHasKey( 'taxonomy', $selected['taxonomies'] );
		$this->assertArrayHasKey( 'othertax', $selected['taxonomies'] );
		$this->assertSame( [ 'dolor' => true, 'sit' => true ], $selected['taxonomies']['taxonomy']['terms'] );
		$this->assertSame( [ 'amet' => true ], $selected['taxonomies']['othertax']['terms'] );

		parse_str( 's=searchterm&filter_taxonomy=dolor', $_GET );
		$selected = $facet_feature->get_selected();
		$this->assertSelectedTax( [ 'dolor' => true ], 'taxonomy', $selected );
		$this->assertArrayHasKey( 's', $selected );
		$this->assertSame( 'searchterm', $selected['s'] );

		parse_str( 'post_type=posttype&filter_taxonomy=dolor', $_GET );
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
	 *
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

		$this->assertEquals( '/?filter_category=augue', $facet_feature->build_query_url( $filters ) );

		$filters['s'] = 'dolor';
		$this->assertEquals( '/?filter_category=augue&s=dolor', $facet_feature->build_query_url( $filters ) );

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

		$this->assertEquals( '/?filter_category=augue%2Cconsectetur', $facet_feature->build_query_url( $filters ) );

		$_SERVER['REQUEST_URI'] = 'test/page/1';

		$filters['s'] = 'dolor';
		$this->assertEquals( 'test/?filter_category=augue%2Cconsectetur&s=dolor', $facet_feature->build_query_url( $filters ) );
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
