<?php
/**
 * Sync REST API Controller
 *
 * @since 5.0.0
 * @package elasticpress
 */

namespace ElasticPress\REST;

use ElasticPress\IndexHelper;
use ElasticPress\Indexables;
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

		register_rest_route(
			'elasticpress/v1',
			'sync/post-types',
			[
				'args'                => [ 'site_id' => $this->get_args()['site_id'] ],
				'callback'            => [ $this, 'get_post_types' ],
				'methods'             => 'GET',
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
			'site_id'               => [
				'description' => __( 'ID of the network site to sync. Omit to sync all eligible sites.', 'elasticpress' ),
				'type'        => 'integer',
				'minimum'     => 1,
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
	 * @return void|\WP_Error Error when the selected site cannot be synced.
	 */
	public function sync( \WP_REST_Request $request ) {
		nocache_headers();

		$index_meta = Utils\get_indexing_status();

		$has_site_id = $request->has_param( 'site_id' );
		$site_id     = $has_site_id ? $request->get_param( 'site_id' ) : null;

		if ( $has_site_id ) {
			$valid_site = rest_validate_value_from_schema( $site_id, $this->get_args()['site_id'], 'site_id' );

			if ( is_wp_error( $valid_site ) ) {
				return new \WP_Error( 'ep_invalid_sync_site', __( 'Select a valid site to sync.', 'elasticpress' ), [ 'status' => 400 ] );
			}

			$site_id = (int) $site_id;
		}

		if ( false !== $index_meta ) {
			$active_site_id = $index_meta['site_id'] ?? null;
			$has_conflict   = $has_site_id && $site_id !== $active_site_id;

			if ( $has_conflict ) {
				return new \WP_Error( 'ep_sync_site_conflict', __( 'A sync with a different site selection is already in progress. Resume or stop that sync first.', 'elasticpress' ), [ 'status' => 409 ] );
			}

			$site_id = $active_site_id;
		}

		if ( null !== $site_id ) {
			$is_network = defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK;
			$site_ids   = $is_network ? array_map( 'intval', wp_list_pluck( Utils\get_sync_sites(), 'blog_id' ) ) : [];
			$is_allowed = in_array( $site_id, $site_ids, true );

			if ( ! $is_allowed ) {
				return new \WP_Error( 'ep_invalid_sync_site', __( 'The selected site no longer exists or is not eligible for indexing. Reload the sync page and select an eligible site.', 'elasticpress' ), [ 'status' => 400 ] );
			}

			$requested_indexables = $request->get_param( 'indexables' ) ?? [];
			$site_indexables      = Indexables::factory()->get_all( false, true, 'all' );
			$has_no_site_content  = ! empty( $requested_indexables ) && ! array_intersect( $requested_indexables, $site_indexables );

			if ( $has_no_site_content ) {
				return new \WP_Error( 'ep_invalid_sync_indexables', __( 'Select site-specific content to sync, or select All eligible sites to sync shared network content.', 'elasticpress' ), [ 'status' => 400 ] );
			}
		}

		if ( isset( $index_meta['method'] ) && 'cli' === $index_meta['method'] ) {
			$this->get_sync_status( $request );
			exit;
		}

		$use_site_context = defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK;

		if ( $use_site_context && ! empty( $index_meta['site_context'] ) ) {
			$runtime_site_id = IndexHelper::get_sync_site_id( $index_meta );
			$result          = $this->route_to_site( $request, $runtime_site_id, 'sync' );

			if ( null !== $result ) {
				return $result;
			}
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

		if ( null !== $site_id ) {
			$args['site_id']      = $site_id;
			$args['network_wide'] = 0;
		}

		$args['site_context'] = $use_site_context;

		IndexHelper::factory()->full_index( $args );
	}

	/**
	 * Return post-type choices from the selected site's native runtime.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array|\WP_REST_Response|\WP_Error Post types or an error.
	 */
	public function get_post_types( \WP_REST_Request $request ) {
		$site_id = (int) ( $request->get_param( 'site_id' ) ?? get_current_blog_id() );
		$result  = $this->route_to_site( $request, $site_id, 'sync/post-types' );

		if ( null !== $result ) {
			return $result;
		}

		$types = Indexables::factory()->get( 'post' )->get_indexable_post_types();

		return array_values(
			array_map(
				static fn( $type ) => [ $type, get_post_type_object( $type )->labels->name ],
				$types
			)
		);
	}

	/**
	 * Forward a network request through the site's normal WordPress bootstrap.
	 *
	 * Only WordPress's existing login cookie and REST nonce are forwarded. The
	 * receiving route still performs normal authentication and capability checks.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @param int              $site_id Required runtime site.
	 * @param string           $route REST route suffix.
	 * @return null|\WP_REST_Response|\WP_Error Null when already in the right runtime.
	 */
	protected function route_to_site( \WP_REST_Request $request, $site_id, $route ) {
		$is_network = defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK;

		if ( ! $is_network ) {
			return null;
		}

		$site_ids  = array_map( 'intval', wp_list_pluck( Utils\get_sync_sites(), 'blog_id' ) );
		$is_origin = 'sync' === $route && get_main_site_id() === $site_id;
		$allowed   = $is_origin || in_array( $site_id, $site_ids, true );

		if ( ! $allowed ) {
			return new \WP_Error( 'ep_invalid_sync_site', __( 'The selected site no longer exists or is not eligible for indexing. Reload the sync page and select an eligible site.', 'elasticpress' ), [ 'status' => 400 ] );
		}

		$context       = $request->get_header( 'X-ElasticPress-Site' );
		$wrong_context = $context && ( (string) $site_id !== $context || get_current_blog_id() !== $site_id );

		if ( $wrong_context ) {
			return new \WP_Error( 'ep_sync_site_context', __( 'The sync request did not reach the expected site. Check the site URL before resuming.', 'elasticpress' ), [ 'status' => 409 ] );
		}

		if ( get_current_blog_id() === $site_id ) {
			return null;
		}

		$cookie = isset( $_COOKIE[ LOGGED_IN_COOKIE ] ) ? wp_unslash( $_COOKIE[ LOGGED_IN_COOKIE ] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Opaque authentication cookie validated by WordPress on the receiving site.
		$nonce  = $request->get_header( 'X-WP-Nonce' );

		if ( ! $cookie || ! $nonce ) {
			return new \WP_Error( 'ep_sync_site_context', __( 'Reload the sync page to authenticate requests to the selected site.', 'elasticpress' ), [ 'status' => 403 ] );
		}

		$url    = get_rest_url( $site_id, 'elasticpress/v1/' . $route );
		$params = $request->get_params();

		if ( 'GET' === $request->get_method() ) {
			$url    = add_query_arg( $params, $url );
			$params = [];
		}

		$response = wp_remote_request(
			$url,
			[
				'method'      => $request->get_method(),
				'timeout'     => 60,
				'redirection' => 0,
				'headers'     => [
					'X-WP-Nonce'          => $nonce,
					'X-ElasticPress-Site' => (string) $site_id,
				],
				'cookies'     => [
					new \WP_Http_Cookie(
						[
							'name'  => LOGGED_IN_COOKIE,
							'value' => $cookie,
						]
					),
				],
				'body'        => $params,
			]
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'ep_sync_site_context', __( 'Unable to reach the selected site. Check its URL and server loopback requests, then resume the sync.', 'elasticpress' ), [ 'status' => 502 ] );
		}

		$status = wp_remote_retrieve_response_code( $response );
		$body   = wp_remote_retrieve_body( $response );

		if ( 'sync' === $route ) {
			// Match the dashboard client's handling of shortcode output before sync JSON.
			$json_start = strpos( $body, '{"data"' );
			$has_prefix = false !== $json_start && $json_start > 0;

			if ( $has_prefix ) {
				$body = substr( $body, $json_start );
			}
		}

		$data  = json_decode( $body, true );
		$valid = is_array( $data ) && $status >= 200 && $status < 300;

		if ( ! $valid ) {
			return new \WP_Error( 'ep_sync_site_context', __( 'The selected site rejected the sync request. Check its URL and authentication, then resume the sync.', 'elasticpress' ), [ 'status' => 502 ] );
		}

		return new \WP_REST_Response( $data, $status );
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
	 * @return void
	 */
	public function get_sync_status() {
		nocache_headers();

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
	 * @return void
	 */
	public function cancel_sync() {
		nocache_headers();

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
