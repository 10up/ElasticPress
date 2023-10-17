<?php
/**
 * Meta Range REST API Controller
 *
 * @since 5.0.0
 * @package elasticpress
 */

namespace ElasticPress\REST;

use ElasticPress\Features;

/**
 * Meta Range API controller class.
 *
 * @since 5.0.0
 * @package elasticpress
 */
class MetaRange {

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
			'args'                => $this->get_args(),
			'callback'            => [ $this, 'get_meta_range' ],
			'methods'             => 'GET',
			'permission_callback' => [ $this, 'check_permission' ],
		];

		register_rest_route( 'elasticpress/v1', 'meta-range', $args );
		register_rest_route( 'elasticpress/v1', 'facets/meta-range/block-preview', $args );
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
	 * Get args schema.
	 *
	 * @return array
	 */
	public function get_args() {
		return [
			'facet' => [
				'description' => __( 'Filter to get a value range for.', 'elasticpress' ),
				'required'    => true,
				'type'        => 'string',
			],
		];
	}

	/**
	 * Get the range of values for a given filter.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return void
	 */
	public function get_meta_range( \WP_REST_Request $request ) {
		global $wp_query;

		add_filter( 'ep_is_facetable', '__return_true' );

		$search = Features::factory()->get_registered_feature( 'search' );
		$facets = Features::factory()->get_registered_feature( 'facets' );

		$facet = $request->get_param( 'facet' );

		add_filter(
			'ep_facet_meta_range_fields',
			function ( $meta_fields ) use ( $facet ) {
				$meta_fields = [ $facet ];

				return $meta_fields;
			}
		);

		$args = [
			'post_type'      => $search->get_searchable_post_types(),
			'posts_per_page' => 1,
		];
		$wp_query->query( $args );

		$min_field_name = $facets->types['meta-range']->get_filter_name() . $facet . '_min';
		$max_field_name = $facets->types['meta-range']->get_filter_name() . $facet . '_max';

		if ( empty( $GLOBALS['ep_facet_aggs'][ $min_field_name ] ) || empty( $GLOBALS['ep_facet_aggs'][ $max_field_name ] ) ) {
			wp_send_json_error();
			return;
		}

		$min = $GLOBALS['ep_facet_aggs'][ $min_field_name ];
		$max = $GLOBALS['ep_facet_aggs'][ $max_field_name ];

		wp_send_json_success(
			[
				'min' => $min,
				'max' => $max,
			]
		);
	}
}
