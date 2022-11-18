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
		add_filter( 'ep_admin_notices', [ $this, 'maybe_add_notice' ] );

		add_action( 'ep_sync_start_index', [ $this, 'clear_logs' ] );
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

		if ( $keep > 0 && count( $logs ) >= $keep ) {
			return;
		}

		if ( ! $this->should_log_query_type( $query, (string) $type ) ) {
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
		if ( is_wp_error( $query['request'] ) ) {
			return true;
		}

		$response_code = wp_remote_retrieve_response_code( $query['request'] );
		// Bulk index dynamically will eventually fire a 413 (too big) request but will recover from it
		if ( 413 === $response_code && false !== strpos( wp_debug_backtrace_summary(), 'bulk_index_dynamically' ) ) { // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_wp_debug_backtrace_summary
			return false;
		}

		if ( $response_code < 200 || $response_code > 299 ) {
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
		// If the body is too big, trim it down to avoid storing a too big log entry
		if ( strlen( $body ) > 900 * KB_IN_BYTES ) {
			$body = substr( $body, 0, 1000 ) . ' (trimmed)';
		}

		$status = wp_remote_retrieve_response_code( $query['request'] );
		$result = json_decode( wp_remote_retrieve_body( $query['request'] ), true );

		return [
			'wp_url'      => home_url( add_query_arg( [ $_GET ], $wp->request ) ), // phpcs:ignore WordPress.Security.NonceVerification
			'es_req'      => $query['args']['method'] . ' ' . $query['url'],
			'timestamp'   => current_time( 'timestamp' ),
			'query_time'  => $query_time,
			'wp_args'     => $query['query_args'] ?? [],
			'status_code' => $status,
			'body'        => $body,
			'result'      => $result,
		];
	}

	/**
	 * Update the logs array in the transient
	 *
	 * @param array $logs New logs array
	 */
	public function update_logs( array $logs ) {
		$max_cache_size = MB_IN_BYTES;

		$logs_json_str      = wp_json_encode( $logs );
		$logs_json_str_size = strlen( $logs_json_str );

		// If the logs size is too big, remove older entries (except the newest one)
		if ( $logs_json_str_size >= $max_cache_size ) {
			$logs_count = count( $logs );
			for ( $i = 0; $i < ( $logs_count - 1 ); $i++ ) {
				array_pop( $logs );

				$logs_json_str      = wp_json_encode( $logs );
				$logs_json_str_size = strlen( $logs_json_str );

				if ( $logs_json_str_size < $max_cache_size ) {
					break;
				}
			}
		}

		// If even removing older entries, it is still too big, try to limit some of its info
		if ( $logs_json_str_size >= $max_cache_size ) {
			$logs[0]['body'] = '(removed due to its size)';

			$logs_json_str      = wp_json_encode( $logs );
			$logs_json_str_size = strlen( $logs_json_str );

			if ( $logs_json_str_size >= $max_cache_size ) {
				$logs[0]['result'] = '(removed due to its size)';
			}
		}

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			set_site_transient( self::CACHE_KEY, $logs_json_str, DAY_IN_SECONDS );
		} else {
			set_transient( self::CACHE_KEY, $logs_json_str, DAY_IN_SECONDS );
		}
	}

	/**
	 * Conditionally display a notice in the admin
	 *
	 * @param array $notices Current EP notices
	 * @return array
	 */
	public function maybe_add_notice( array $notices ) : array {
		$current_ep_screen = \ElasticPress\Screen::factory()->get_current_screen();
		if ( 'status-report' === $current_ep_screen ) {
			return $notices;
		}

		$logs = $this->get_logs();
		if ( empty( $logs ) ) {
			return $notices;
		}

		$page = 'admin.php?page=elasticpress-status-report';

		$status_report_url = ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) ?
			network_admin_url( $page ) :
			admin_url( $page );

		$notices['has_failed_queries'] = [
			'html'    => sprintf(
				/* translators: Status Report URL */
				__( 'Some ElasticPress queries failed in the last 24 hours. Please visit the <a href="%s">Status Report page</a> for more details.', 'elasticpress' ),
				$status_report_url . '#failed-queries'
			),
			'type'    => 'warning',
			'dismiss' => true,
		];

		return $notices;
	}

	/**
	 * Clear the stored logs
	 */
	public function clear_logs() {
		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			delete_site_transient( self::CACHE_KEY );
		} else {
			delete_transient( self::CACHE_KEY );
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
