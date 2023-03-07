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
	const MESSAGES_TRANSIENT_NAME = 'ep_elasticpress_io_messages';

	/**
	 * Return singleton instance of class
	 *
	 * @return object
	 */
	public static function factory() {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new self();
		}

		return $instance;
	}

	/**
	 * Get messages from ElasticPress.io.
	 *
	 * @return array ElasticPress.io messages.
	 */
	public function get_endpoint_messages() {
		if ( ! Utils\is_epio() ) {
			return [];
		}

		$messages = get_transient( self::MESSAGES_TRANSIENT_NAME );
		if ( false !== $messages ) {
			return $messages;
		}

		$response = \ElasticPress\Elasticsearch::factory()->remote_request( 'endpoint-messages' );

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( is_wp_error( $response ) || 200 !== $response_code ) {
			return [];
		}

		$messages = (array) json_decode( wp_remote_retrieve_body( $response ), true );

		set_transient( self::MESSAGES_TRANSIENT_NAME, $messages, HOUR_IN_SECONDS );

		return $messages;
	}

	/**
	 * Delete cached messages.
	 */
	public function delete_endpoint_messages() {
		delete_transient( self::MESSAGES_TRANSIENT_NAME );
	}
}
