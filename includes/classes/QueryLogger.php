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
	 */
	public function log_query( $query, $type ) {
		$last_sync = Utils\get_option( 'ep_last_sync', false );
		if ( empty( $last_sync ) ) {
			return;
		}

		$logs = $this->get_logs();

		/**
		 * Filter the number of queries to keep in the log
		 *
		 * @since 4.4.0
		 * @hook ep_query_logger_queries_to_keep
		 * @param {int}    $keep  Number of queries to keep in the log
		 * @param {array}  $query Remote request arguments
		 * @param {string} $type  Request type
		 * @return {int} New number
		 */
		$keep = apply_filters( 'ep_query_logger_queries_to_keep', 5, $query, $type );

		if ( $keep > 0 && count( $logs ) >= $keep ) {
			return;
		}

		if ( ! $this->should_log_query_type( $query, (string) $type ) ) {
			return;
		}

		array_unshift( $logs, $this->format_log_entry( $query, $type ) );

		$logs_json_str = $this->update_logs( $logs );

		/**
		 * Perform actions after a new query is logged
		 *
		 * @hook ep_query_logger_logged_query
		 * @since 4.4.0
		 * @param {string} $logs_json_str  The JSON string as stored in the transient
		 * @param {array}  $query          Remote request arguments
		 * @param {string} $type           Request type
		 */
		do_action( 'ep_query_logger_logged_query', $logs_json_str, $query, $type );
	}

	/**
	 * Return logged failed queries.
	 *
	 * @param bool $should_filter_old Whether it should filter out old entries or not. Default to true, only return entries newer than the limit
	 * @return array
	 */
	public function get_logs( bool $should_filter_old = true ) : array {
		$logs = ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) ?
			get_site_transient( self::CACHE_KEY, [] ) :
			get_transient( self::CACHE_KEY, [] );

		$logs = (array) json_decode( (string) $logs, true );

		if ( $should_filter_old ) {
			$current_time = current_time( 'timestamp' );

			/**
			 * Filter the period to keep queried logs. Defaults to DAY_IN_SECONDS
			 *
			 * @since 4.4.0
			 * @hook ep_query_logger_time_to_keep
			 * @param {int} $period_to_keep The period to keep queried logs, in seconds
			 * @return {int} New period
			 */
			$period_to_keep = apply_filters( 'ep_query_logger_time_to_keep', DAY_IN_SECONDS );

			$time_limit = $current_time - $period_to_keep;

			$logs = array_filter(
				(array) $logs,
				function ( $log ) use ( $time_limit ) {
					return ! empty( $log['timestamp'] ) && $log['timestamp'] > $time_limit;
				}
			);
		}

		/**
		 * Filter the logs
		 *
		 * @since 4.4.0
		 * @hook ep_query_logger_logs
		 * @param {int} $logs The logs array
		 * @return {int} New array
		 */
		$logs = apply_filters( 'ep_query_logger_logs', $logs );

		return $logs;
	}

	/**
	 * Update the logs array in the transient
	 *
	 * @param array $logs New logs array
	 */
	public function update_logs( array $logs ) {
		/**
		 * Filter the max cache size. Defaults to MB_IN_BYTES
		 *
		 * @since 4.4.0
		 * @hook ep_query_logger_max_cache_size
		 * @param {int} $max_cache_size The max cache size in bytes
		 * @return {int} New size
		 */
		$max_cache_size = apply_filters( 'ep_query_logger_max_cache_size', MB_IN_BYTES );

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

		\ElasticPress\Utils\delete_option( 'ep_hide_has_failed_queries_notice' );

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			set_site_transient( self::CACHE_KEY, $logs_json_str, DAY_IN_SECONDS );
		} else {
			set_transient( self::CACHE_KEY, $logs_json_str, DAY_IN_SECONDS );
		}

		return $logs_json_str;
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

		/**
		 * Perform actions after clearing the logs
		 *
		 * @hook ep_query_logger_cleared_logs
		 * @since 4.4.0
		 */
		do_action( 'ep_query_logger_cleared_logs' );
	}

	/**
	 * Conditionally display a notice in the admin
	 *
	 * @param array $notices Current EP notices
	 * @return array
	 */
	public function maybe_add_notice( array $notices ) : array {
		if ( ! current_user_can( Utils\get_capability() ) ) {
			return $notices;
		}

		$current_ep_screen = \ElasticPress\Screen::factory()->get_current_screen();
		if ( 'status-report' === $current_ep_screen ) {
			return $notices;
		}

		if ( \ElasticPress\Utils\get_option( 'ep_hide_has_failed_queries_notice' ) ) {
			return $notices;
		}

		$logs = $this->get_logs();
		if ( empty( $logs ) ) {
			return $notices;
		}

		$indices_comparison = Elasticsearch::factory()->get_indices_comparison();
		$present_indices    = count( $indices_comparison['present_indices'] );

		if ( 0 === $present_indices ) {
			$message = sprintf(
				/* translators: %s: Sync page link. */
				esc_html__( 'Your site\'s content is not synced with your %1$s. Please %2$s.', 'elasticpress' ),
				Utils\is_epio() ? __( 'ElasticPress.io account', 'elasticpress' ) : __( 'Elasticsearch server', 'elasticpress' ),
				sprintf(
					'<a href="%1$s">%2$s</a>',
					esc_url( Utils\get_sync_url( true ) ),
					esc_html__( 'sync your content', 'elasticpress' )
				)
			);
		} else {
			$page = 'admin.php?page=elasticpress-status-report';

			$status_report_url = ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) ?
				network_admin_url( $page ) :
				admin_url( $page );

			$message = sprintf(
				/* translators: Status Report URL */
				__( 'Some ElasticPress queries failed in the last 24 hours. Please visit the <a href="%s">Status Report page</a> for more details.', 'elasticpress' ),
				$status_report_url . '#failed-queries'
			);
		}

		$notices['has_failed_queries'] = [
			'html'    => $message,
			'type'    => 'warning',
			'dismiss' => true,
		];

		return $notices;
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

		// If the body is too big, trim it down to avoid storing a too big log entry
		$body = ! empty( $query['args']['body'] ) ? $query['args']['body'] : '';
		if ( strlen( $body ) > 200 * KB_IN_BYTES ) {
			$body = substr( $body, 0, 1000 ) . ' (trimmed)';
		} else {
			$json_body = json_decode( $body, true );
			// Bulk indexes are not "valid" JSON, for example.
			if ( json_last_error() === JSON_ERROR_NONE ) {
				$body = wp_json_encode( $json_body );
			}
		}

		$request_id = ( ! empty( $query['args']['headers'] ) && ! empty( $query['args']['headers']['X-ElasticPress-Request-ID'] ) ) ?
			$query['args']['headers']['X-ElasticPress-Request-ID'] :
			null;

		$status = wp_remote_retrieve_response_code( $query['request'] );
		if ( is_wp_error( $query['request'] ) ) {
			$result = [
				'is_wp_error' => true,
				'code'        => $query['request']->get_error_code(),
				'message'     => $query['request']->get_error_message(),
				'data'        => $query['request']->get_error_data(),
			];
		} else {
			$result = json_decode( wp_remote_retrieve_body( $query['request'] ), true );
		}

		$formatted_log = [
			'wp_url'      => home_url( add_query_arg( [ $_GET ], $wp->request ) ), // phpcs:ignore WordPress.Security.NonceVerification
			'es_req'      => $query['args']['method'] . ' ' . $query['url'],
			'request_id'  => $request_id ?? '',
			'timestamp'   => current_time( 'timestamp' ),
			'query_time'  => $query_time,
			'wp_args'     => $query['query_args'] ?? [],
			'status_code' => $status,
			'body'        => $body,
			'result'      => $result,
		];

		/**
		 * Filter the formatted query log
		 *
		 * @since 4.4.0
		 * @hook ep_query_logger_formatted_query
		 * @param {array}  $formatted_log The log entry
		 * @param {array}  $query         The failed query
		 * @param {string} $type          The query type
		 * @return {array} Changed log entry
		 */
		return apply_filters( 'ep_query_logger_formatted_query', $formatted_log, $query, $type );
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
		 * @hook ep_query_logger_allowed_log_types
		 * @param {array}  $callable_map Array indexed by type and valued by a callable that returns a boolean
		 * @param {array}  $query        Remote request arguments
		 * @param {string} $type         Request type
		 * @return {array} New array
		 */
		$allowed_log_types = apply_filters(
			'ep_query_logger_allowed_log_types',
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

		/**
		 * Filter the formatted query log
		 *
		 * @since 4.4.0
		 * @hook ep_query_logger_should_log_query
		 * @param {bool}   $should_log Whether the query should be logged or not
		 * @param {array}  $query      The failed query
		 * @param {string} $type       The query type
		 * @return {bool} New value of $should_log
		 */
		return apply_filters( 'ep_query_logger_should_log_query', $should_log, $query, $type );
	}

	/**
	 * Check the request body, as usually bulk indexing does not return a status error.
	 *
	 * @param array $query Remote request arguments
	 * @return boolean
	 */
	protected function is_bulk_index_error( $query ) {
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
	 * @return bool
	 */
	protected function maybe_log_delete_index( $query ) {
		$response_code = wp_remote_retrieve_response_code( $query['request'] );

		return ( ( $response_code < 200 || $response_code > 299 ) && 404 !== $response_code );
	}

	/**
	 * Log all non-200 requests
	 *
	 * @param  array $query Remote request arguments
	 * @return bool
	 */
	protected function is_query_error( $query ) {
		if ( is_wp_error( $query['request'] ) ) {
			return true;
		}

		$response_code = wp_remote_retrieve_response_code( $query['request'] );

		return ( $response_code < 200 || $response_code > 299 );
	}
}
