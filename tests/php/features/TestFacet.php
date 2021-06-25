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

		set_query_var( 's', 'dolor' );
		$this->assertEquals( '/?s=dolor&filter_category=augue', $facet_feature->build_query_url( $filters ) );

		set_query_var( 's', '' );
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

		set_query_var( 's', 'dolor' );
		$this->assertEquals( 'test/?s=dolor&filter_category=augue%2Cconsectetur', $facet_feature->build_query_url( $filters ) );

	}

}
