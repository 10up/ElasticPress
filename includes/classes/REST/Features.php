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
				$property = [
					'description' => $schema['label'],
					'type'        => 'string',
				];

				switch ( $type ) {
					case 'select':
					case 'radio':
						$property['enum'] = array_map( fn( $o ) => $o['value'], $schema['options'] );
						break;
					case 'toggle':
						$property['type'] = 'boolean';
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
	 * @return array
	 */
	public function update_settings( \WP_REST_Request $request ) {
		if ( Utils\is_indexing() ) {
			wp_send_json_error( 'is_syncing', 400 );
			exit;
		}

		$current_settings = FeaturesStore::factory()->get_feature_settings();
		$new_settings     = $current_settings;

		$features = \ElasticPress\Features::factory()->registered_features;

		$settings_that_requires_features = [];

		foreach ( $features as $slug => $feature ) {
			$param = $request->get_param( $slug );

			if ( ! $param ) {
				continue;
			}

			if ( empty( $current_settings[ $slug ] ) ) {
				$current_settings[ $slug ] = [];
				$new_settings[ $slug ]     = [];
			}

			$schema = $feature->get_settings_schema();

			foreach ( $schema as $schema ) {
				$key = $schema['key'];

				if ( isset( $param[ $key ] ) ) {
					$new_settings[ $slug ][ $key ] = $param[ $key ];

					// Only apply to the current settings if does not require a sync
					if ( ! empty( $schema['requires_sync'] ) ) {
						continue;
					}

					/*
					 * If a setting requires another feature, we have to check for it after running through everything,
					 * as it is possible that the feature will be active after this foreach.
					 */
					if ( empty( $schema['requires_feature'] ) ) {
						$current_settings[ $slug ][ $key ] = $param[ $key ];
					} else {
						if ( ! isset( $settings_that_requires_features[ $slug ] ) ) {
							$settings_that_requires_features[ $slug ] = [];
						}
						$settings_that_requires_features[ $slug ][ $key ] = [
							'required_feature' => $schema['requires_feature'],
							'value'            => $param[ $key ],
						];
					}
				}
			}

			FeaturesStore::factory()->update_feature( $slug, $new_settings[ $slug ], true, 'draft' );
		}

		foreach ( $settings_that_requires_features as $feature => $fields ) {
			foreach ( $fields as $field_key => $field_data ) {
				if ( ! empty( $current_settings[ $field_data['required_feature'] ]['active'] ) ) {
					$current_settings[ $feature ][ $field_key ] = $field_data['value'];
				}
			}
		}

		foreach ( $current_settings as $slug => $feature ) {
			FeaturesStore::factory()->update_feature( $slug, $feature );
		}

		return [
			'data'    => $current_settings,
			'success' => true,
		];
	}
}
