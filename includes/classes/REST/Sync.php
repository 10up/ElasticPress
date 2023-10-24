<?php
/**
 * Sync REST API Controller
 *
 * @since 5.0.0
 * @package elasticpress
 */

namespace ElasticPress\REST;

use ElasticPress\IndexHelper;
use ElasticPress\Utils;

/**
 * Sync API controller class.
 *
 * @since 5.0.0
 * @package elasticpress
 */
class Sync {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			'elasticpress/v1',
			'sync',
			[
				'args'                => $this->get_args(),
				'callback'            => [ $this, 'sync' ],
				'methods'             => 'POST',
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);

		register_rest_route(
			'elasticpress/v1',
			'sync',
			[
				'callback'            => [ $this, 'get_sync_status' ],
				'methods'             => 'GET',
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);

		register_rest_route(
			'elasticpress/v1',
			'sync',
			[
				'callback'            => [ $this, 'cancel_sync' ],
				'methods'             => 'DELETE',
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
			'include'               => [
				'description' => __( 'IDs of objects to sync.', 'elasticpress' ),
				'items'       => [
					'type' => 'integer',
				],
				'type'        => 'array',
			],
			'indexables'            => [
				'description' => __( 'Indexables to sync', 'elasticpress' ),
				'items'       => [
					'type' => 'string',
				],
				'required'    => false,
				'type'        => 'array',
			],
			'lower_limit_object_id' => [
				'description' => __( 'Start of object ID range to sync,', 'elasticpress' ),
				'type'        => 'integer',
				'required'    => false,
			],
			'offset'                => [
				'description' => __( 'Number of objects to skip.', 'elasticpress' ),
				'required'    => false,
				'type'        => 'integer',
			],
			'post_type'             => [
				'description' => __( 'Post type to sync.', 'elasticpress' ),
				'items'       => [
					'type' => 'string',
				],
				'type'        => 'array',
			],
			'put_mapping'           => [
				'default'     => false,
				'description' => __( 'Whether to clear the index and send mapping before syncing.', 'elasticpress' ),
				'type'        => 'boolean',
				'required'    => false,
			],
			'trigger'               => [
				'enum'     => [ 'features', 'install', 'manual', 'upgrade' ],
				'required' => false,
			],
			'upper_limit_object_id' => [
				'description' => __( 'End of object ID range to sync.', 'elasticpress' ),
				'type'        => 'integer',
				'required'    => false,
			],
		];
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
	public function sync( \WP_REST_Request $request ) {
		$index_meta = Utils\get_indexing_status();

		if ( isset( $index_meta['method'] ) && 'cli' === $index_meta['method'] ) {
			$this->get_sync_status( $request );
			exit;
		}

		$args = array_merge(
			[
				'method'        => 'dashboard',
				'network_wide'  => 0,
				'output_method' => [ $this, 'output' ],
				'show_errors'   => true,
			],
			$request->get_params()
		);

		IndexHelper::factory()->full_index( $args );
	}

	/**
	 * Output the result of indexing.
	 *
	 * @param array $message Index details.
	 * @return void
	 */
	public function output( array $message ) {
		switch ( $message['status'] ) {
			case 'success':
				wp_send_json_success( $message );
				break;
			case 'error':
				wp_send_json_error( $message );
				break;
			default:
				wp_send_json( [ 'data' => $message ] );
				break;
		}
	}

	/**
	 * Get the status of a sync in progress.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return void
	 */
	public function get_sync_status( \WP_REST_Request $request ) {
		$index_meta = Utils\get_indexing_status();

		if ( isset( $index_meta['method'] ) && 'cli' === $index_meta['method'] ) {
			wp_send_json_success(
				[
					'message'    => sprintf(
						/* translators: 1. Number of objects indexed, 2. Total number of objects, 3. Last object ID. */
						esc_html__( 'Processed %1$d/%2$d. Last Object ID: %3$d', 'elasticpress' ),
						$index_meta['offset'],
						$index_meta['found_items'],
						$index_meta['current_sync_item']['last_processed_object_id']
					),
					'index_meta' => $index_meta,
				]
			);
		}

		wp_send_json_success(
			[
				'is_finished' => true,
				'totals'      => IndexHelper::factory()->get_last_sync(),
			]
		);
	}

	/**
	 * Cancel a sync in progress.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return void
	 */
	public function cancel_sync( \WP_REST_Request $request ) {
		$index_meta = Utils\get_indexing_status();

		if ( isset( $index_meta['method'] ) && 'cli' === $index_meta['method'] ) {
			set_transient( 'ep_wpcli_sync_interrupted', true, MINUTE_IN_SECONDS );
			wp_send_json_success();
			exit;
		}

		$index_meta = IndexHelper::factory()->get_index_meta();

		if ( $index_meta ) {
			IndexHelper::factory()->clear_index_meta();

			wp_send_json_success(
				IndexHelper::factory()->get_last_sync()
			);

			return;
		}

		wp_send_json_success();
	}
}
