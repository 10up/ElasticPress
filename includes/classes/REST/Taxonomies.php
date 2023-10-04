<?php
/**
 * Taxonomies REST API Controller
 *
 * @since 5.0.0
 * @package elasticpress
 */

namespace ElasticPress\REST;

use ElasticPress\Features;

/**
 * Taxonomies API controller class.
 *
 * @since 5.0.0
 * @package elasticpress
 */
class Taxonomies {

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
			'callback'            => [ $this, 'get_taxonomies' ],
			'methods'             => 'GET',
			'permission_callback' => [ $this, 'check_permission' ],
		];

		register_rest_route( 'elasticpress/v1', 'taxonomies', $args );
		register_rest_route( 'elasticpress/v1', 'facets/taxonomies', $args );
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
	 * Get filterable taxonomies.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return array
	 */
	public function get_taxonomies( \WP_REST_Request $request ) {
		$filterable_taxonomies = Features::factory()->get_registered_feature( 'facets' )->types['taxonomy']->get_facetable_taxonomies();

		$taxonomies = [];

		foreach ( $filterable_taxonomies as $slug => $taxonomy ) {
			$terms_sample = get_terms(
				[
					'taxonomy' => $slug,
					'number'   => 20,
				]
			);
			if ( is_array( $terms_sample ) ) {
				// This way we make sure it will be an array in the outputted JSON.
				$terms_sample = array_values( $terms_sample );
			} else {
				$terms_sample = [];
			}

			$taxonomies[ $slug ] = [
				'label'  => $taxonomy->labels->singular_name,
				'plural' => $taxonomy->labels->name,
				'terms'  => $terms_sample,
			];
		}

		return $taxonomies;
	}
}
