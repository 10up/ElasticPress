<?php
/**
 * Token REST API Controller
 *
 * @since 5.0.0
 * @package elasticpress
 */

namespace ElasticPress\REST;

use ElasticPress\Elasticsearch;

/**
 * Token API controller class.
 *
 * @since 5.0.0
 * @package elasticpress
 */
class Token {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			'elasticpress/v1',
			'token',
			[
				[
					'callback'            => [ $this, 'get_token' ],
					'permission_callback' => [ $this, 'check_permission' ],
					'methods'             => 'GET',
				],
				[
					'callback'            => [ $this, 'refresh_token' ],
					'permission_callback' => [ $this, 'check_permission' ],
					'methods'             => 'POST',
				],
			]
		);
	}

	/**
	 * Checks if the token API can be used.
	 *
	 * @return boolean
	 */
	public function check_permission() {
		/**
		 * Filters the capability required to use the token API.
		 *
		 * @since 4.5.0
		 * @hook ep_token_capability
		 * @param {string} $capability Required capability.
		 */
		$capability = apply_filters( 'ep_token_capability', 'edit_others_shop_orders' );

		return current_user_can( $capability );
	}

	/**
	 * Get a temporary token.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return string|false
	 */
	public function get_token( \WP_REST_Request $request ) {
		$user_id = get_current_user_id();

		$credentials = get_user_meta( $user_id, 'ep_token', true );

		if ( $credentials ) {
			return $credentials;
		}

		return $this->refresh_token( $request );
	}

	/**
	 * Refresh the temporary token.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return string|false
	 */
	public function refresh_token( \WP_REST_Request $request ) {
		$user_id = get_current_user_id();

		$endpoint = $this->get_token_endpoint();
		$response = Elasticsearch::factory()->remote_request( $endpoint, [ 'method' => 'POST' ] );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}

		$response = wp_remote_retrieve_body( $response );
		$response = json_decode( $response );

		$credentials = base64_encode( "$response->username:$response->clear_password" ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

		update_user_meta( $user_id, 'ep_token', $credentials );

		return $credentials;
	}

	/**
	 * Get the endpoint for temporary tokens.
	 *
	 * @return string
	 */
	protected function get_token_endpoint() {
		/**
		 * Filters the temporary token API endpoint.
		 *
		 * @since 4.5.0
		 * @hook ep_token_endpoint
		 * @param {string} $endpoint Endpoint path.
		 * @returns {string} Token API endpoint.
		 */
		return apply_filters( 'ep_token_endpoint', 'api/v1/token' );
	}
}
