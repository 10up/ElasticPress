<?php
/**
 * Custom Results REST API Controller
 *
 * @since 5.0.0
 * @package elasticpress
 */

namespace ElasticPress\REST;

use ElasticPress\Features;
use ElasticPress\Utils;

/**
 * Custom Results API controller class.
 *
 * @since 5.0.0
 * @package elasticpress
 */
class SearchOrdering {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			'elasticpress/v1',
			'pointer_search',
			[
				'args'                => $this->get_args(),
				'callback'            => [ $this, 'get_posts' ],
				'methods'             => 'GET',
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);

		register_rest_route(
			'elasticpress/v1',
			'pointer_preview',
			[
				'args'                => $this->get_args(),
				'callback'            => [ $this, 'get_preview' ],
				'methods'             => 'GET',
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
	}


	/**
	 * Get args schema.
	 *
	 * @return array
	 */
	public function get_args() {
		return [
			's' => [
				'description'       => __( 'Search query.', 'elasticpress' ),
				'required'          => true,
				'type'              => 'string',
				'validate_callback' => fn( $param ) => ! empty( $param ),
			],
		];
	}

	/**
	 * Check that the request has permission to sync.
	 *
	 * @return boolean
	 */
	public function check_permission() {
		$capability = Utils\get_capability( 'search-ordering' );

		return current_user_can( $capability );
	}

	/**
	 * Handles the search for posts from the admin interface for the post
	 * type.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return array
	 */
	public function get_posts( \WP_REST_Request $request ) {
		$search = $request->get_param( 's' );

		/** Features Class @var Features $features */
		$features = Features::factory();

		/** Search Feature @var Feature\Search\Search $search */
		$search_feature = $features->get_registered_feature( 'search' );

		$post_types = $search_feature->get_searchable_post_types();

		$query = new \WP_Query(
			[
				'post_type'   => $post_types,
				'post_status' => 'publish',
				's'           => $search,
			]
		);

		return $query->posts;
	}

	/**
	 * Handles the search preview on the pointer edit screen.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return array
	 */
	public function get_preview( $request ) {
		$search = $request->get_param( 's' );

		$query = new \WP_Query(
			[
				's'                => $search,
				'exclude_pointers' => true,
			]
		);

		return $query->posts;
	}
}
