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
		$transient = 'ep_elasticpress_io_messages';
		$messages  = get_transient( $transient );

		if ( false !== $messages ) {
			return $messages;
		}

		$response = \ElasticPress\Elasticsearch::factory()->remote_request( 'endpoint-messages' );
		$messages = (array) json_decode( wp_remote_retrieve_body( $response ), true );

		set_transient( $transient, $messages, HOUR_IN_SECONDS );

		return $messages;
	}
}
