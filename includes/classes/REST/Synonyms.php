<?php
/**
 * Features REST API Controller
 *
 * @since 5.0.0
 * @package elasticpress
 */

namespace ElasticPress\REST;

use ElasticPress\Features;
use ElasticPress\Utils;

/**
 * Features API controller class.
 *
 * @since 5.0.0
 * @package elasticpress
 */
class Synonyms {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			'elasticpress/v1',
			'synonyms',
			[
				'args'                => $this->get_args(),
				'callback'            => [ $this, 'update_synonyms' ],
				'methods'             => 'PUT',
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
		$feature = Features::factory()->get_registered_feature( 'search' )->synonyms;

		$args = [
			'mode' => [
				'default'     => 'simple',
				'description' => __( 'Synonyms editor mode.', 'elasticpress' ),
				'enum'        => [ 'advanced', 'simple' ],
			],
			'solr' => [
				'default'           => $feature->example_synonym_list( false ),
				'description'       => __( 'Synonyms in Solr format.', 'elasticpress' ),
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => [ $this, 'sanitize_solr' ],
			],
		];

		return $args;
	}

	/**
	 * Check that the request has permission to save synonyms.
	 *
	 * @return boolean
	 */
	public function check_permission() {
		$capability = Utils\get_capability();

		return current_user_can( $capability );
	}

	/**
	 * Sanitize Solr synonyms.
	 *
	 * @param string $value Solr synonyms,
	 * @return string
	 */
	public function sanitize_solr( $value ) {
		$solr = trim( $value );
		$solr = sanitize_textarea_field( $value );

		return $solr;
	}

	/**
	 * Update synonyms settings.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return array
	 */
	public function update_synonyms( \WP_REST_Request $request ) {
		$feature = Features::factory()->get_registered_feature( 'search' )->synonyms;

		$mode = $request->get_param( 'mode' );
		$solr = $request->get_param( 'solr' );

		$post_id = $feature->update_synonym_post( $solr );

		if ( ! $post_id || is_wp_error( $post_id ) ) {
			return new \WP_Error( 'error-update-post' );
		}

		$updated = $feature->update_synonyms();

		if ( ! $updated ) {
			return new \WP_Error( 'error-update-index' );
		}

		$feature->save_editor_mode( $mode );

		return [
			'data'    => $solr,
			'success' => true,
		];
	}
}
