<?php
/**
 * Query Logger class
 *
 * phpcs:disable WordPress.DateTime.CurrentTimeTimestamp.Requested
 *
 * @since 4.4.0
 * @package elasticpress
 */

namespace ElasticPress;

defined( 'ABSPATH' ) || exit;

/**
 * Query Logger class
 *
 * @package ElasticPress
 */
class QueryLogger {
	/**
	 * String used to get and update the transient.
	 */
	const CACHE_KEY = 'ep_query_log';

	/**
	 * Setup the logging functionality
	 */
	public function setup() {
		add_action( 'ep_remote_request', [ $this, 'log_query' ], 10, 2 );
	}

	/**
	 * Conditionally save a query to the log which is stored in options. This is a big performance hit so be careful.
	 *
	 * @param array  $query Remote request arguments
	 * @param string $type  Request type
	 * @since 1.3
	 */
	public function log_query( $query, $type ) {
		$logs = $this->get_logs();
		$keep = 5;

		if ( $keep > 0 && count( $logs ) > $keep ) {
			return;
		}

		if ( ! $this->should_log_query_type( $query, $type ) ) {
			return;
		}

		array_unshift( $logs, $this->format_log_entry( $query, $type ) );

		$this->update_logs( $logs );
	}

	/**
	 * Check the request body, as usually bulk indexing does not return a status error.
	 *
	 * @since 2.1.0
	 * @param array $query Remote request arguments
	 * @return boolean
	 */
	public function is_bulk_index_error( $query ) {
		if ( $this->is_query_error( $query ) ) {
			return true;
		}

		$request_body = json_decode( wp_remote_retrieve_body( $query['request'] ), true );
		return ! empty( $request_body['errors'] );
	}

	/**
	 * Only log delete index error if not 2xx AND not 404
	 *
	 * @param  array $query Remote request arguments
	 * @since  1.3
	 * @return bool
	 */
	public function maybe_log_delete_index( $query ) {
		$response_code = wp_remote_retrieve_response_code( $query['request'] );

		return ( ( $response_code < 200 || $response_code > 299 ) && 404 !== $response_code );
	}

	/**
	 * Log all non-200 requests
	 *
	 * @param  array $query Remote request arguments
	 * @since  1.3
	 * @return bool
	 */
	public function is_query_error( $query ) {
		if ( is_wp_error( $query['request'] ) ) {
			return true;
		}

		$response_code = wp_remote_retrieve_response_code( $query['request'] );

		return ( $response_code < 200 || $response_code > 299 );
	}

	/**
	 * Return logged failed queries.
	 *
	 * @return array
	 */
	public function get_logs() : array {
		$current_time   = current_time( 'timestamp' );
		$period_to_keep = DAY_IN_SECONDS;
		$time_limit     = $current_time - $period_to_keep;

		$logs = ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) ?
			get_site_transient( self::CACHE_KEY, [] ) :
			get_transient( self::CACHE_KEY, [] );

		$logs = json_decode( $logs, true );

		$logs = array_filter(
			(array) $logs,
			function ( $log ) use ( $time_limit ) {
				return ! empty( $log['timestamp'] ) && $log['timestamp'] > $time_limit;
			}
		);

		return $logs;
	}

	/**
	 * Given a query, return a formatted log entry
	 *
	 * @param array  $query The failed query
	 * @param string $type  The query type
	 * @return array
	 */
	protected function format_log_entry( array $query, string $type ) : array {
		global $wp;

		$query_time = ( ! empty( $query['time_start'] ) && ! empty( $query['time_finish'] ) ) ?
			( $query['time_finish'] - $query['time_start'] ) * 1000 :
			false;

		// Bulk indexes are not "valid" JSON, for example.
		$body = '';
		if ( ! empty( $query['args']['body'] ) ) {
			$body = json_decode( $query['args']['body'], true );
			if ( json_last_error() === JSON_ERROR_NONE ) {
				$body = wp_json_encode( $body );
			} else {
				$body = $query['args']['body'];
			}
		}

		$result = json_decode( wp_remote_retrieve_body( $query['request'] ), true );

		return [
			'wp_url'     => home_url( add_query_arg( [ $_GET ], $wp->request ) ), // phpcs:ignore WordPress.Security.NonceVerification
			'es_url'     => $query['args']['method'] . ' ' . $query['url'],
			'timestamp'  => current_time( 'timestamp' ),
			'query_time' => $query_time,
			'wp_args'    => $query['query_args'] ?? [],
			'body'       => $body,
			'result'     => $result,
		];
	}

	/**
	 * Update the logs array in the transient
	 *
	 * @param array $logs New logs array
	 */
	public function update_logs( array $logs ) {
		$logs = wp_json_encode( $logs );

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			set_site_transient( self::CACHE_KEY, $logs, DAY_IN_SECONDS );
		} else {
			set_transient( self::CACHE_KEY, $logs, DAY_IN_SECONDS );
		}
	}

	/**
	 * Given a query and its type, check if it should be logged
	 *
	 * @param array  $query The failed query
	 * @param string $type  The query type
	 * @return boolean
	 */
	protected function should_log_query_type( array $query, string $type ) : bool {
		/**
		 * Filter the array with a map from query types to callables. If the callable returns true,
		 * the query will be logged.
		 *
		 * @since 4.4.0
		 * @hook ep_allowed_log_types
		 * @param {array}  $callable_map Array indexed by type and valued by a callable that returns a boolean
		 * @param {array}  $query        Remote request arguments
		 * @param {string} $type         Request type
		 * @return {array} New array
		 */
		$allowed_log_types = apply_filters(
			'ep_allowed_log_types',
			array(
				'put_mapping'          => array( $this, 'is_query_error' ),
				'delete_network_alias' => array( $this, 'is_query_error' ),
				'create_network_alias' => array( $this, 'is_query_error' ),
				'bulk_index'           => array( $this, 'is_bulk_index_error' ),
				'bulk_index_posts'     => array( $this, 'is_query_error' ),
				'delete_index'         => array( $this, 'maybe_log_delete_index' ),
				'create_pipeline'      => array( $this, 'is_query_error' ),
				'get_pipeline'         => array( $this, 'is_query_error' ),
				'query'                => array( $this, 'is_query_error' ),
			),
			$query,
			$type
		);

		$should_log = isset( $allowed_log_types[ $type ] ) ?
			call_user_func( $allowed_log_types[ $type ], $query ) :
			false;

		return $should_log;
	}
}
