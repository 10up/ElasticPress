<?php
/**
 * Vector Embeddings Request
 *
 * Utilitary class that holds all methods needed to request vector embeddings.
 *
 * @since 5.4.0
 * @package ElasticPress
 */

namespace ElasticPress\Feature\VectorEmbeddings;

use ElasticPress\Feature\VectorEmbeddings\VectorEmbeddings;
use ElasticPress\Traits\LogRequest;


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Vector Embeddings feature
 */
class VectorEmbeddingsRequest {
	use LogRequest;

	/**
	 * Vector Embeddings feature
	 *
	 * @var VectorEmbeddings
	 */
	protected $vector_embeddings;

	/**
	 * Constructor
	 *
	 * @param VectorEmbeddings $vector_embeddings The Vector Embeddings feature.
	 */
	public function __construct( VectorEmbeddings $vector_embeddings ) {
		$this->vector_embeddings = $vector_embeddings;
	}

	/**
	 * Get results from the response.
	 *
	 * @param object $response The API response.
	 * @return array|WP_Error
	 */
	public function get_result( $response ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$headers      = wp_remote_retrieve_headers( $response );
		$content_type = false;

		if ( ! empty( $headers ) ) {
			$content_type = isset( $headers['content-type'] ) ? $headers['content-type'] : false;
		}

		$body = wp_remote_retrieve_body( $response );
		$code = wp_remote_retrieve_response_code( $response );

		if ( false === $content_type || false !== strpos( $content_type, 'application/json' ) ) {
			$json = json_decode( $body, true );

			if ( json_last_error() === JSON_ERROR_NONE ) {
				if ( empty( $json['error'] ) ) {
					return $json;
				} else {
					$message = $json['error']['message'] ?? esc_html__( 'An error occured', 'elasticpress' );
					return new \WP_Error( $code, $message );
				}
			} else {
				return new \WP_Error( 'Invalid JSON: ' . json_last_error_msg(), $body );
			}
		} elseif ( $content_type && false !== strpos( $content_type, 'audio/mpeg' ) ) {
			return $response;
		} else {
			return new \WP_Error( 'Invalid content type', $body, [ 'full_response' => $response ] );
		}
	}

	/**
	 * Add the headers.
	 *
	 * @param array $options The header options, passed by reference.
	 */
	public function add_headers( array &$options = [] ) {
		if ( empty( $options['headers'] ) ) {
			$options['headers'] = [];
		}

		if ( ! isset( $options['headers']['Authorization'] ) ) {
			$options['headers']['Authorization'] = $this->get_auth_header();
		}

		if ( ! isset( $options['headers']['Content-Type'] ) ) {
			$options['headers']['Content-Type'] = 'application/json';
		}
	}

	/**
	 * Get the auth header.
	 *
	 * @return string
	 */
	public function get_auth_header() {
		return 'Bearer ' . $this->vector_embeddings->get_setting( 'ep_embeddings_api_key' );
	}

	/**
	 * Send the request to the embeddings API.
	 *
	 * @param string $text The text to get embeddings for.
	 * @return array|WP_Error
	 */
	public function send_request( $text ) {
		/**
		 * Filter the URL for the post request.
		 *
		 * @hook ep_embeddings_api_url
		 * @since 5.4.0
		 *
		 * @param {string} $url The URL for the request.
		 *
		 * @return {string} The URL for the request.
		 */
		$url = apply_filters( 'ep_embeddings_api_url', $this->vector_embeddings->get_setting( 'ep_embeddings_api_url' ) );

		/**
		 * Filter the request body before sending to OpenAI.
		 *
		 * @hook ep_embeddings_request_body
		 * @since 5.4.0
		 *
		 * @param {array} $body Request body that will be sent to OpenAI.
		 * @param {string} $text Text we are getting embeddings for.
		 *
		 * @return {array} Request body.
		 */
		$body = apply_filters(
			'ep_embeddings_request_body',
			[
				'model'      => $this->vector_embeddings->get_setting( 'ep_embeddings_embedding_model' ),
				'input'      => (array) $text,
				'dimensions' => $this->vector_embeddings->get_dimensions(),
			],
			$text
		);

		/**
		 * Filter the options for the post request.
		 *
		 * @hook ep_embeddings_options
		 * @since 5.4.0
		 *
		 * @param {array} $options The options for the request.
		 * @param {string} $url The URL for the request.
		 *
		 * @return {array} The options for the request.
		 */
		$options = apply_filters(
			'ep_embeddings_options',
			[
				'body'    => wp_json_encode( $body ),
				'timeout' => 60, // phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout
			],
			$url
		);

		$this->add_headers( $options );

		$response = $this->send_request_and_log( $url, $options, 'Vector Embeddings', 'vector_embeddings' );

		return $this->get_result( $response );
	}
}
