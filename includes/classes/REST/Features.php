<?php
/**
 * Features REST API Controller
 *
 * @since 5.0.0
 * @package elasticpress
 */

namespace ElasticPress\REST;

use ElasticPress\IndexHelper;
use ElasticPress\Utils;

/**
 * Features API controller class.
 *
 * @since 5.0.0
 * @package elasticpress
 */
class Features {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			'elasticpress/v1',
			'features',
			[
				'args'                => $this->get_args(),
				'callback'            => [ $this, 'update_settings' ],
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
		$args = [];

		$features = \ElasticPress\Features::factory()->registered_features;

		foreach ( $features as $feature ) {
			$properties = [];

			$schema = $feature->get_settings_schema();

			foreach ( $schema as $schema ) {
				if ( ! isset( $schema['label'] ) ) {
					continue;
				}

				$property = [ 'description' => $schema['label'] ];

				switch ( $schema['type'] ) {
					case 'select':
					case 'radio':
						$property['enum'] = array_map( fn( $o ) => $o['value'], $schema['options'] );
						break;
					case 'multiple':
						$property['type'] = 'string';
						break;
					case 'checkbox':
						$property['type'] = 'boolean';
						break;
					default:
						$property['type'] = 'string';
						break;
				}

				$properties[ $schema['key'] ] = $property;
			}

			$args[ $feature->slug ] = [
				'description' => $feature->get_title(),
				'properties'  => $properties,
				'type'        => 'object',
			];
		}

		return $args;
	}

	/**
	 * Check that the request has permission to sync.
	 *
	 * @return boolean
	 */
	public function check_permission() {
		$capability = Utils\get_capability();

		return current_user_can( $capability );
	}

	/**
	 * Start or continue a sync.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return void
	 */
	public function update_settings( \WP_REST_Request $request ) {
		$settings = $request->get_params();

		Utils\update_option( 'ep_feature_settings', $settings );

		wp_send_json_success();
	}
}
