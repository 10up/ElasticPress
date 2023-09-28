<?php
/**
 * Test the Instants Results feature.
 *
 * @since   5.0.0
 * @package elasticpress
 */

namespace ElasticPressTest;

/**
 * Instants Results test class.
 */
class TestInstantResults extends BaseTestCase {

	/**
	 * Test Instants Results settings schema
	 *
	 * @group instant-results
	 */
	public function test_get_settings_schema() {
		$settings_schema = \ElasticPress\Features::factory()->get_registered_feature( 'instant-results' )->get_settings_schema();

		$settings_keys = wp_list_pluck( $settings_schema, 'key' );

		$this->assertSame(
			[ 'active', 'highlight_tag', 'facets', 'match_type', 'term_count', 'per_page', 'search_behavior' ],
			$settings_keys
		);
	}
}
