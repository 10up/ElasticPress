<?php
/**
 * Features REST API Controller
 *
 * @since 5.0.0
 * @package elasticpress
 */

namespace ElasticPress\REST;

use ElasticPress\Features as FeaturesStore;
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

				$type     = $schema['type'] ?? '';
				$property = [ 'description' => $schema['label'] ];

				switch ( $type ) {
					case 'select':
					case 'radio':
						$property['enum'] = array_merge(
							[ false ],
							array_map( fn( $o ) => $o['value'], $schema['options'] )
						);
						$property['type'] = [ 'string', 'boolean' ];
						break;
					case 'toggle':
						$property['type'] = 'boolean';
						break;
					case 'checkbox':
					case 'multiple':
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
	 * Check that the request has permission to save features.
	 *
	 * @return boolean
	 */
	public function check_permission() {
		$capability = Utils\get_capability();

		return current_user_can( $capability );
	}

	/**
	 * Update features settings.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return void
	 */
	public function update_settings( \WP_REST_Request $request ) {
		if ( Utils\is_indexing() ) {
			wp_send_json_error( 'is_syncing', 400 );
			exit;
		}

		$settings = [];

		$features = \ElasticPress\Features::factory()->registered_features;

		foreach ( $features as $slug => $feature ) {
			$param = $request->get_param( $slug );

			if ( ! $param ) {
				continue;
			}

			$settings[ $slug ] = [];

			$schema = $feature->get_settings_schema();

			foreach ( $schema as $schema ) {
				$key = $schema['key'];

				if ( isset( $param[ $key ] ) ) {
					$settings[ $slug ][ $key ] = $param[ $key ];
				}
			}

			FeaturesStore::factory()->update_feature( $slug, $settings[ $slug ] );
		}

		Utils\update_option( 'ep_feature_settings', $settings );

		wp_send_json_success();
	}
}
