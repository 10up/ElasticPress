<?php
/**
 * Vector Embedding Configuration REST API Controller.
 *
 * @since 5.4.0
 * @package ElasticPress
 */

namespace ElasticPress\REST;

use ElasticPress\Feature\VectorEmbeddings\Settings;

/**
 * Vector Embedding Configuration API controller class.
 */
class VectorEmbeddingSettings {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			'elasticpress/v1',
			'vector-embeddings',
			[
				'callback'            => [ $this, 'update_settings' ],
				'methods'             => 'POST',
				'permission_callback' => '__return_true',
				'args'                => $this->get_endpoint_args_for_item_schema( true ),
			]
		);
	}

	/**
	 * Get the endpoint arguments for the item schema.
	 *
	 * @return array
	 */
	protected function get_endpoint_args_for_item_schema() {
		$schema = $this->get_item_schema();

		$args = [];

		foreach ( $schema['properties'] as $property_id => $property_schema ) {
			$args[ $property_id ] = [
				'required'          => ! empty( $property_schema['required'] ),
				'type'              => $property_schema['type'],
				'description'       => $property_schema['description'],
				'validate_callback' => 'rest_validate_request_arg',
				'sanitize_callback' => 'rest_sanitize_request_arg',
			];
		}

		return $args;
	}

	/**
	 * Get the item schema.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		return [
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'vector-embeddings',
			'type'       => 'object',
			'properties' => [
				'chunking'       => [
					'description' => __( 'Chunking settings.', 'elasticpress' ),
					'type'        => 'object',
				],
				'mode'           => [
					'description' => __( 'Mode of the embeddings.', 'elasticpress' ),
					'type'        => 'string',
				],
				'postTypeConfig' => [
					'description' => __( 'Post type configurations.', 'elasticpress' ),
					'type'        => 'array',
					'items'       => [
						'type'       => 'object',
						'properties' => [
							'key'        => [
								'description' => __( 'Post type key.', 'elasticpress' ),
								'type'        => 'string',
							],
							'taxonomies' => [
								'description' => __( 'Taxonomies settings.', 'elasticpress' ),
								'type'        => 'object',
							],
						],
					],
				],
			],
		];
	}

	/**
	 * Update vector embeddings.
	 *
	 * @param \WP_REST_Request $request The REST request.
	 * @return \WP_REST_Response
	 */
	public function update_settings( \WP_REST_Request $request ) {
		$settings = $request->get_params();

		// unset locale
		unset( $settings['_locale'] );

		// Validate settings
		$valid = rest_validate_value_from_schema( $settings, $this->get_item_schema(), 'vector-embeddings' );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$sanitized = rest_sanitize_value_from_schema( $settings, $this->get_item_schema(), 'vector-embeddings' );
		if ( is_wp_error( $sanitized ) ) {
			return $sanitized;
		}

		update_option( SETTINGS::SETTINGS_KEY, $sanitized );

		return rest_ensure_response( [ 'success' => true ] );
	}
}
