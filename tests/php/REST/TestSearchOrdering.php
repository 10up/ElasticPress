<?php
/**
 * Test search ordering REST controller
 *
 * @since 5.0.0
 * @package elasticpress
 */

namespace ElasticPressTest\REST;

use \ElasticPress\Elasticsearch;
use \ElasticPress\Features;
use \ElasticPress\REST\SearchOrdering;

/**
 * SearchOrdering test class
 */
class TestSearchOrdering extends \ElasticPressTest\BaseTestCase {
	/**
	 * Test the `get_posts` method
	 *
	 * @group rest
	 * @group rest-search-ordering
	 * @group search-ordering
	 */
	public function testHandlePointerSearch() {
		$controller = new SearchOrdering();

		Features::factory()->activate_feature( 'search' );
		Features::factory()->setup_features();
		Features::factory()->get_registered_feature( 'search' )->search_setup();

		$post_id_1 = $this->ep_factory->post->create( [ 'post_content' => 'findme test 1' ] );
		$post_id_2 = $this->ep_factory->post->create( [ 'post_content' => 'findme test 2' ] );

		Elasticsearch::factory()->refresh_indices();

		$request = new \WP_REST_Request( 'GET', '/elasticpress/v1/pointer_search' );
		$request->set_param( 's', 'findme' );

		$response = $controller->get_posts( $request );

		$post_ids = wp_list_pluck( $response, 'ID' );

		$this->assertContains( $post_id_1, $post_ids );
		$this->assertContains( $post_id_2, $post_ids );
	}
}
