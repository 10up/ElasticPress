<?php
/**
 * Log Request trait
 *
 * This trait enables logging of requests.
 *
 * @since 5.4.0
 * @package ElasticPress
 */

namespace ElasticPress\Traits;

trait LogRequest {

	/**
	 * Formatted request
	 *
	 * @var array $formatted_request.
	 */
	protected $formatted_request;

	/**
	 * Send the request and log it.
	 *
	 * @param string $url                The URL to send the request to.
	 * @param array  $options            The options for the request.
	 * @param string $ep_query_type      The type of query.
	 * @param string $ep_query_type_slug The slug of the query type.
	 * @return array The response.
	 */
	public function send_request_and_log( $url, $options, $ep_query_type, $ep_query_type_slug ) {
		$formatted_request = [
			'time_start'   => microtime( true ),
			'time_finish'  => false,
			'args'         => $options,
			'blocking'     => true,
			'failed_hosts' => [],
			'request'      => false,
			'host'         => $url,
			'url'          => $url,
			'query_args'   => [],
		];

		// Make our API request.
		$response = wp_remote_post( $url, $options );

		$formatted_request['args']['method']        = 'POST';
		$formatted_request['args']['ep_query_type'] = $ep_query_type;
		$formatted_request['time_finish']           = microtime( true );
		$formatted_request['request']               = $response;

		$this->formatted_request = $formatted_request;
		add_filter( 'ep_get_query_log', [ $this, 'add_vector_embeddings_to_query_log' ] );
		do_action( 'ep_remote_request', $formatted_request, $ep_query_type_slug );

		return $response;
	}

	/**
	 * Add the formatted request to the query log.
	 *
	 * @param array $query_log The query log.
	 * @return array The query log.
	 */
	public function add_vector_embeddings_to_query_log( $query_log ) {
		$query_log[] = $this->formatted_request;
		return $query_log;
	}
}
