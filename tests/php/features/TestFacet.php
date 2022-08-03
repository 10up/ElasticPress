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
	 * @expectedDeprecated ElasticPress\Feature\Facets\Facets::get_selected
	 */
	public function testGetSelected() {
		Features::factory()->get_registered_feature( 'facets' )->get_selected();
	}

	/**
	 * Test build query URL
	 *
	 * @since 3.6.0
	 * @group facets
	 * @expectedDeprecated ElasticPress\Feature\Facets\Facets::build_query_url
	 */
	public function testBuildQueryUrl() {
		$filters = [
			'taxonomies' => [
				'category' => [
					'terms' => [
						'augue' => 1
					]
				]
			]
		];
		Features::factory()->get_registered_feature( 'facets' )->build_query_url( $filters );
	}
}
