<?php
/**
 * Class for interacting with ElasticPress.io
 *
 * @since 4.5.0
 * @package elasticpress
 */

namespace ElasticPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * ElasticPressIo class
 *
 * @package ElasticPress
 */
class ElasticPressIo {
	/**
	 * Name of the transient that stores EP.io messages
	 */
	const STATUS_TRANSIENT_NAME = 'ep_elasticpress_io_status';

	/**
	 * Return singleton instance of class
	 *
	 * @return object
	 */
	public static function factory() {
		_doing_it_wrong(
			__METHOD__,
			esc_html__( 'ElasticPressIo::factory() is deprecated. Use \ElasticPress\get_container()->get( \ElasticPress\ElasticPressIo::class ) instead.', 'elasticpress' ),
			'ElasticPress 5.3.0'
		);

		return \ElasticPress\get_container()->get( __CLASS__ );
	}

	/**
	 * Get status from ElasticPress.io.
	 *
	 * @since 5.3.0
	 * @param bool $skip_cache Whether to fetch the API or use the cached messages. Defaults to false, i.e., use cache.
	 * @return array ElasticPress.io endpoint status.
	 */
	public function get_endpoint_status( $skip_cache = false ): array {
		static $status = null;

		// Avoid sending multiple requests to the API in the same WP request.
		if ( null !== $status ) {
			return (array) $status;
		}

		if ( ! Utils\is_epio() ) {
			return [];
		}

		$status = get_transient( self::STATUS_TRANSIENT_NAME );
		if ( ! $skip_cache && false !== $status ) {
			return $status;
		}

		$response = \ElasticPress\Elasticsearch::factory()->remote_request( 'endpoint-status' );

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( is_wp_error( $response ) || 200 !== $response_code ) {
			return [];
		}

		$status = (array) json_decode( wp_remote_retrieve_body( $response ), true );

		set_transient( self::STATUS_TRANSIENT_NAME, $status, HOUR_IN_SECONDS );

		return $status;
	}

	/**
	 * Get messages from ElasticPress.io.
	 *
	 * @param bool $skip_cache Whether to use cached messages or not. Defaults to false, i.e., use cache.
	 * @return array ElasticPress.io messages.
	 */
	public function get_endpoint_messages( $skip_cache = false ): array {
		if ( ! Utils\is_epio() ) {
			return [];
		}

		$endpoint_status = $this->get_endpoint_status( $skip_cache );

		return $endpoint_status['messages'] ?? [];
	}

	/**
	 * Get available services from ElasticPress.io.
	 *
	 * @since 5.3.0
	 * @param bool $skip_cache Whether to use cached available services or not. Defaults to false, i.e., use cache.
	 * @return array ElasticPress.io available services.
	 */
	public function get_endpoint_available_services( $skip_cache = false ): array {
		if ( ! Utils\is_epio() ) {
			return [];
		}

		$endpoint_status = $this->get_endpoint_status( $skip_cache );

		return $endpoint_status['avaiableServices'] ?? [];
	}

	/**
	 * Returns true if the service is available.
	 *
	 * @param string $service_name The name of the service to check.
	 * @param bool   $skip_cache   Whether to use cached available services or not. Defaults to false, i.e., use cache.
	 * @return bool True if the service is available, false otherwise.
	 */
	public function is_service_available( $service_name, $skip_cache = false ): bool {
		$available_services = $this->get_endpoint_available_services( $skip_cache );
		return isset( $available_services[ $service_name ] ) && ! empty( $available_services[ $service_name ] );
	}
}
