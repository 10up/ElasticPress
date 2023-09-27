<?php
/**
 * Facets REST API Controller
 *
 * @since 5.0.0
 * @package elasticpress
 */

namespace ElasticPress\REST;

use ElasticPress\Features;
use ElasticPress\Indexables;

/**
 * Facets API controller class.
 *
 * @since 5.0.0
 * @package elasticpress
 */
class Facets {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			'elasticpress/v1',
			'facets/meta/keys',
			[
				'callback'            => [ $this, 'get_meta_keys' ],
				'methods'             => 'GET',
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);

		register_rest_route(
			'elasticpress/v1',
			'facets/meta-range/block-preview',
			[
				'args'                => $this->get_meta_range_args(),
				'callback'            => [ $this, 'get_meta_range' ],
				'methods'             => 'GET',
				'permission_callback' => [ $this, 'check_permission' ],

			]
		);

		register_rest_route(
			'elasticpress/v1',
			'facets/taxonomies',
			[
				'callback'            => [ $this, 'get_taxonomies' ],
				'methods'             => 'GET',
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
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
	public function get_meta_range_args() {
		$meta_keys = $this->get_facetable_meta_keys();

		return [
			'facet' => [
				'description' => __( 'Facet to get a value range for.', 'elasticpress' ),
				'enum'        => $meta_keys,
				'required'    => true,
			],
		];
	}

	/**
	 * Get meta keys.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return array
	 */
	public function get_meta_keys( \WP_REST_Request $request ) {
		$meta_keys = $this->get_facetable_meta_keys();

		return $meta_keys;
	}

	/**
	 * Get the range of values for a given facet.
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

	/**
	 * Get taxonomies.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return array
	 */
	public function get_taxonomies( \WP_REST_Request $request ) {
		$taxonomies_raw = $this->get_facetable_taxonomies();

		$taxonomies = [];

		foreach ( $taxonomies_raw as $slug => $taxonomy ) {
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

	/**
	 * Get facetable meta keys.
	 *
	 * @return array
	 */
	protected function get_facetable_meta_keys() {
		$post_indexable = Indexables::factory()->get( 'post' );

		try {
			$meta_keys = $post_indexable->get_distinct_meta_field_keys();
		} catch ( \Throwable $th ) {
			$meta_keys = [];
		}

		return $meta_keys;
	}

	/**
	 * Get facetable tasxonomies.
	 *
	 * @return array Taxonomies.
	 */
	protected function get_facetable_taxonomies() {
		return Features::factory()->get_registered_feature( 'facets' )->types['taxonomy']->get_facetable_taxonomies();
	}
}