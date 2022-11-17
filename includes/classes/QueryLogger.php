<?php
/**
 * Query Logger class
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

		if ( isset( $allowed_log_types[ $type ] ) ) {
			$do_log = call_user_func( $allowed_log_types[ $type ], $query );

			if ( ! $do_log ) {
				return;
			}
		} else {
			return;
		}

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			$log = get_site_transient( 'ep_query_log', [] );
		} else {
			$log = get_transient( 'ep_query_log', [] );
		}

		$log[] = array(
			'query' => $query,
			'type'  => $type,
		);

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			set_site_transient( 'ep_query_log', $log, DAY_IN_SECONDS );
		} else {
			set_transient( 'ep_query_log', $log, DAY_IN_SECONDS );
		}
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
}
