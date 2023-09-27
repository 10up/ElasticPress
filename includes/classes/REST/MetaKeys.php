<?php
/**
 * Meta Keys REST API Controller
 *
 * @since 5.0.0
 * @package elasticpress
 */

namespace ElasticPress\REST;

use ElasticPress\Features;
use ElasticPress\Indexables;

/**
 * Meta Keys API controller class.
 *
 * @since 5.0.0
 * @package elasticpress
 */
class MetaKeys {

	/**
	 * Register routes.
	 *
	 * Registers the route using its own endpoint and the previous facets
	 * endpoint, for backwards compatibility.
	 *
	 * @return void
	 */
	public function register_routes() {
		$args = [
			'callback'            => [ $this, 'get_meta_keys' ],
			'methods'             => 'GET',
			'permission_callback' => [ $this, 'check_permission' ],
		];

		register_rest_route( 'elasticpress/v1', 'meta-keys', $args );
		register_rest_route( 'elasticpress/v1', 'facets/meta/keys', $args );
	}

	/**
	 * Check that the request has permission.
	 *
	 * @return boolean
	 */
	public function check_permission() {
		return current_user_can( 'edit_theme_options' );
	}

	/**
	 * Get indexed meta keys.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return array
	 */
	public function get_meta_keys( \WP_REST_Request $request ) {
		$post_indexable = Indexables::factory()->get( 'post' );

		try {
			$meta_keys = $post_indexable->get_distinct_meta_field_keys();
		} catch ( \Throwable $th ) {
			$meta_keys = [];
		}

		return $meta_keys;
	}
}
