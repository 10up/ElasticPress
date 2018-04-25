<?php
/**
 * ElasticPress-Elasticsearch API functions
 *
 * @since  2.6
 * @package elasticpress
 */

namespace ElasticPress;

use ElasticPress\Utils as Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Elasticsearch {

	/**
	 * Placeholder method
	 *
	 * @since 0.1.0
	 */
	public function __construct() { }

	/**
	 * Logged queries for debugging
	 *
	 * @since  1.8
	 */
	private $queries = [];

	/**
	 * ES plugins
	 *
	 * @var array
	 * @since  2.2
	 */
	public $elasticsearch_plugins = null;

	/**
	 * ES version number
	 *
	 * @var string
	 * @since  2.2
	 */
	public $elasticsearch_version = null;

	/**
	 * Return singleton instance of class
	 *
	 * @return EP_API
	 * @since 0.1.0
	 */
	public static function factory() {
		static $instance = false;

		if ( ! $instance  ) {
			$instance = new self();
		}

		return $instance;
	}

	public function index_document( $index, $type, $document_id, $document, $blocking = true ) {

		$path = $index . '/' . $type . '/' . $document_id;

		if ( function_exists( 'wp_json_encode' ) ) {
			$encoded_document = wp_json_encode( $document );
		} else {
			$encoded_document = json_encode( $document );
		}

		$request_args = array(
			'body'    => $encoded_document,
			'method'  => 'PUT',
			'timeout' => 15,
			'blocking' => $blocking,
		);

		if ( ! is_wp_error( $request ) ) {
			$response_body = wp_remote_retrieve_body( $request );

			$return = json_decode( $response_body );
		} else {
			$return = false;
		}

		return $return;
	}

	/**
	 * Pull the site id from the index name
	 *
	 * @param string $index_name
	 * @since 0.9.0
	 * @return int
	 */
	public function parse_site_id( $index_name ) {
		return (int) preg_replace( '#^.*\-([0-9]+)$#', '$1', $index_name );
	}

	public function refresh_index( $path ) {

		$request_args = array( 'method' => 'POST' );

		$request = $this->remote_request( '_refresh', apply_filters( 'ep_refresh_index_request_args', $request_args ), [], 'refresh_index' );

		if ( ! is_wp_error( $request ) ) {
			if ( isset( $request['response']['code'] ) && 200 === $request['response']['code'] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get Elasticsearch version
	 *
	 * @param  bool $force
	 * @since  2.1.2
	 * @return string|bool
	 */
	public function get_elasticsearch_version( $force = false ) {

		$info = $this->get_elasticsearch_info( $force );

		return apply_filters( 'ep_elasticsearch_version', $info['version'] );
	}

	/**
	 * Get Elasticsearch plugins
	 *
	 * @param  bool $force
	 * @since  2.2
	 * @return string|bool
	 */
	public function get_elasticsearch_plugins( $force = false ) {

		$info = $this->get_elasticsearch_info( $force );

		return apply_filters( 'ep_elasticsearch_plugins', $info['plugins'] );
	}

	public function query( $index, $type, $args, $query_args ) {
		$path = $index . '/' . $type . '/_search';

		$request_args = array(
			'body'    => json_encode( apply_filters( 'ep_query_args', $args, $query_args ) ),
			'method'  => 'POST',
			'headers' => array(
				'Content-Type' => 'application/json',
			),
		);

		$request = $this->remote_request( $path, $request_args, $query_args, 'query' );

		$remote_req_res_code = intval( wp_remote_retrieve_response_code( $request ) );

		$is_valid_res = ( $remote_req_res_code >= 200 && $remote_req_res_code <= 299 );

		if ( ! is_wp_error( $request ) && apply_filters( '$this->remote_request_is_valid_res', $is_valid_res, $request ) ) {

			// Allow for direct response retrieval
			do_action( 'ep_retrieve_raw_response', $request, $args, $scope, $query_args );

			$response_body = wp_remote_retrieve_body( $request );

			$response = json_decode( $response_body, true );

			$hits = $this->get_hits_from_query( $response );
			$total_hits = $this->get_total_hits_from_query( $response );

			// Check for and store aggregations
			if ( ! empty( $response['aggregations'] ) ) {
				do_action( 'ep_retrieve_aggregations', $response['aggregations'], $args, $scope, $query_args );
			}

			$documents = [];

			foreach ( $hits as $hit ) {
				$document = $hit['_source'];
				$document['site_id'] = $this->parse_site_id( $hit['_index'] );
				$documents[] = apply_filters( 'ep_retrieve_the_post', $post, $hit );
			}

			return apply_filters( 'ep_query_results_array', array( 'found_documents' => $total_hits, 'documents' => $documents ), $response, $args );
		}

		return false;
	}

    /**
     * Returns the number of total results that ElasticSearch found for the given query
     *
     * @param array $response
     * @since  2.5
     * @return int
     */
	public function get_total_hits_from_query( $response ) {

	    if ( $this->is_empty_query( $response ) ) {
	        return 0;
        }

        return $response['hits']['total'];
    }

    /**
     * Returns array containing hits returned from query, if such exist
     *
     * @param array $response
     * @since  2.5
     * @return array
     */
	public function get_hits_from_query( $response ) {

        if ( $this->is_empty_query( $response ) ) {
            return [];
        }

        return $response['hits']['hits'];
    }

	/**
	 * Check if a response array contains results or not
	 *
	 * @param array $response
	 * @since 0.1.2
	 * @return bool
	 */
	public function is_empty_query( $response ) {

		if ( ! is_array( $response ) ) {
			return true;
		}

		if ( isset( $response['error'] ) ) {
			return true;
		}

		if ( empty( $response['hits'] ) ) {
			return true;
		}

		if ( isset( $response['hits']['total'] ) && 0 === (int)$response['hits']['total'] ) {
			return true;
		}

		return false;
	}

	public function delete_document( $index, $type, $document_id, $blocking = true  ) {

		$path = $index . '/' . $type . '/' . $document_id;

		$request_args = array( 'method' => 'DELETE', 'timeout' => 15, 'blocking' => $blocking );

		$request = $this->remote_request( $path, $request_args, [], 'delete' );

		if ( ! is_wp_error( $request ) ) {
			$response_body = wp_remote_retrieve_body( $request );

			$response = json_decode( $response_body, true );

			if ( ! empty( $response['found'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Add appropriate request headers
	 *
	 * @since 1.4
	 * @return array
	 */
	public function format_request_headers() {
		$headers = array(
			'Content-Type' => 'application/json',
		);

		// Check for ElasticPress API key and add to header if needed.
		if ( defined( 'EP_API_KEY' ) && EP_API_KEY ) {
			$headers['X-ElasticPress-API-Key'] = EP_API_KEY;
		}

		/**
		 * ES Shield Username & Password
		 * Adds username:password basic authentication headers
		 *
		 * Define the constant ES_SHIELD in your wp-config.php
		 * Format: 'username:password' (colon separated)
		 * Example: define( 'ES_SHIELD', 'es_admin:password' );
		 *
		 * @since 1.9
		 */
		if ( defined( 'ES_SHIELD' ) && ES_SHIELD ) {
			$headers['Authorization'] = 'Basic ' . base64_encode( ES_SHIELD );
		}

		$headers = apply_filters( 'ep_format_request_headers', $headers );

		return $headers;
	}

	public function get_document( $index, $type, $document_id ) {
		$path = $index . '/' . $type . '/' . $document_id;

		$request_args = array( 'method' => 'GET' );

		$request = $this->remote_request( $path, $request_args, [], 'get' );

		if ( ! is_wp_error( $request ) ) {
			$response_body = wp_remote_retrieve_body( $request );

			$response = json_decode( $response_body, true );

			if ( ! empty( $response['exists'] ) || ! empty( $response['found'] ) ) {
				return $response['_source'];
			}
		}

		return false;
	}

	public function delete_network_alias( $alias ) {
		$path = '*/_alias/' . $alias;

		$request_args = array( 'method' => 'DELETE' );

		$request = $this->remote_request( $path, $request_args, [], 'delete_network_alias' );

		if ( ! is_wp_error( $request ) && ( 200 >= wp_remote_retrieve_response_code( $request ) && 300 > wp_remote_retrieve_response_code( $request ) ) ) {
			$response_body = wp_remote_retrieve_body( $request );

			return json_decode( $response_body );
		}

		return false;
	}

	public function create_network_alias( $indexes, $network_alias ) {

		$path = '_aliases';

		$args = array(
			'actions' => [],
		);

		foreach ( $indexes as $index ) {
			$args['actions'][] = array(
				'add' => array(
					'index' => $index,
					'alias' => $network_alias,
				),
			);
		}

		$request_args = array(
			'body'    => json_encode( $args ),
			'method'  => 'POST',
			'timeout' => 25,
		);

		$request = $this->remote_request( $path, apply_filters( 'ep_create_network_alias_request_args', $request_args, $args, $indexes ), [], 'create_network_alias' );

		if ( ! is_wp_error( $request ) && ( 200 >= wp_remote_retrieve_response_code( $request ) && 300 > wp_remote_retrieve_response_code( $request ) ) ) {
			$response_body = wp_remote_retrieve_body( $request );

			return json_decode( $response_body );
		}

		return false;
	}

	public function put_mapping( $index, $mapping ) {
		$mapping = apply_filters( 'ep_config_mapping', $mapping );

		$request_args = array(
			'body'    => json_encode( $mapping ),
			'method'  => 'PUT',
			'timeout' => 30,
		);

		$request = $this->remote_request( $index, apply_filters( 'ep_put_mapping_request_args', $request_args ), [], 'put_mapping' );

		$request = apply_filters( 'ep_config_mapping_request', $request, $index, $mapping );

		if ( ! is_wp_error( $request ) && 200 === wp_remote_retrieve_response_code( $request ) ) {
			$response_body = wp_remote_retrieve_body( $request );

			return json_decode( $response_body );
		}

		return false;
	}

	public function delete_index( $index ) {

		$request_args = array( 'method' => 'DELETE', 'timeout' => 30, );

		$request = $this->remote_request( $index, apply_filters( 'ep_delete_index_request_args', $request_args ), [], 'delete_index' );

		// 200 means the delete was successful
		// 404 means the index was non-existent, but we should still pass this through as we will occasionally want to delete an already deleted index
		if ( ! is_wp_error( $request ) && ( 200 === wp_remote_retrieve_response_code( $request ) || 404 === wp_remote_retrieve_response_code( $request ) ) ) {
			$response_body = wp_remote_retrieve_body( $request );

			return json_decode( $response_body );
		}

		return false;
	}

	public function index_exists( $index_name ) {

		$request_args = array( 'method' => 'HEAD' );

		$request = $this->remote_request( $index, apply_filters( 'ep_index_exists_request_args', $request_args, $index_name ), [], 'index_exists' );

		// 200 means the index exists
		// 404 means the index was non-existent
		if ( ! is_wp_error( $request ) && ( 200 === wp_remote_retrieve_response_code( $request ) || 404 === wp_remote_retrieve_response_code( $request ) ) ) {

			if ( 404 === wp_remote_retrieve_response_code( $request ) ) {
				return false;
			}

			if ( 200 === wp_remote_retrieve_response_code( $request ) ) {
				return true;
			}
		}

		return false;
	}

	public function bulk_index( $index, $type, $body ) {
		$path = $index . '/' . $type . '/_bulk';

		$request_args = array(
			'method'  => 'POST',
			'body'    => $body,
			'timeout' => 30,
		);

		$request = $this->remote_request( $path, $request_args, [], 'bulk_index' );

		if ( is_wp_error( $request ) ) {
			return $request;
		}

		$response = wp_remote_retrieve_response_code( $request );

		if ( 200 !== $response ) {
			return new WP_Error( $response, wp_remote_retrieve_response_message( $request ), $request );
		}

		return json_decode( wp_remote_retrieve_body( $request ), true );
	}

	/**
	 * Return queries for debugging
	 *
	 * @since  1.8
	 * @return array
	 */
	public function get_query_log() {
		return $this->queries;
	}

	/**
	 * Wrapper for wp_remote_request
	 *
	 * This is a wrapper function for wp_remote_request to account for request failures.
	 *
	 * @since 1.6
	 *
	 * @param string $path Site URL to retrieve.
	 * @param array  $args Optional. Request arguments. Default empty array.
	 * @param array  $query_args Optional. The query args originally passed to WP_Query
	 * @param string Type of request, used for debugging
	 *
	 * @return WP_Error|array The response or WP_Error on failure.
	 */
	public function remote_request( $path, $args = [], $query_args = [], $type = null ) {

		if ( empty( $args['method'] ) ) {
			$args['method'] = 'GET';
		}

		$query = array(
			'time_start'   => microtime( true ),
			'time_finish'  => false,
			'args'         => $args,
			'blocking'     => true,
			'failed_hosts' => [],
			'request'      => false,
			'host'         => Utils\get_host(),
			'query_args'   => $query_args,
		);

		//Add the API Header
		$args['headers'] = $this->format_request_headers();

		$request = false;
		$failures = 0;

		// Optionally let us try back up hosts and account for failures
		while ( true ) {
			$query['host'] = apply_filters( 'ep_pre_request_host', $query['host'], $failures, $path, $args );
			$query['url'] = apply_filters( 'ep_pre_request_url', esc_url( trailingslashit( $query['host'] ) . $path ), $failures, $query['host'], $path, $args );

			$request = wp_remote_request( $query['url'], $args ); //try the existing host to avoid unnecessary calls

			$request_response_code = (int) wp_remote_retrieve_response_code( $request );

			$is_valid_res = ( $request_response_code >= 200 && $request_response_code <= 299 );

			if ( false === $request || is_wp_error( $request ) || ! $is_valid_res ) {
				$failures++;

				if ( $failures >= apply_filters( 'ep_max_remote_request_tries', 1, $path, $args ) ) {
					break;
				}
			} else {
				break;
			}
		}

		// Return now if we're not blocking, since we won't have a response yet
		if ( isset( $args['blocking'] ) && false === $args['blocking' ] ) {
			$query['blocking'] = true;
			$query['request']  = $request;
			$this->_add_query_log( $query );

			return $request;
		}

		$query['time_finish'] = microtime( true );
		$query['request'] = $request;
		$this->_add_query_log( $query );

		do_action( '$this->remote_request', $query, $type );

		return $request;

	}

	/**
	 * Parse response from Elasticsearch
	 *
	 * Determines if there is an issue or if the response is valid.
	 *
	 * @since 1.9
	 *
	 * @param object $response JSON decoded response from Elasticsearch.
	 *
	 * @return array Contains the status message or the returned statistics.
	 */
	public function parse_api_response( $response ) {

		if ( null === $response ) {

			return array(
				'status' => false,
				'msg'    => esc_html__( 'Invalid response from ElasticPress server. Please contact your administrator.' ),
			);

		} elseif (
			isset( $response->error ) &&
			(
				( is_string( $response->error ) && stristr( $response->error, 'IndexMissingException' ) ) ||
				( isset( $response->error->reason ) && stristr( $response->error->reason, 'no such index' ) )
			)
		) {

			if ( is_multisite() ) {

				$error = __( 'Site not indexed. <p>Please run: <code>wp elasticpress index --setup --network-wide</code> using WP-CLI. Or use the index button on the left of this screen.</p>', 'elasticpress' );

			} else {

				$error = __( 'Site not indexed. <p>Please run: <code>wp elasticpress index --setup</code> using WP-CLI. Or use the index button on the left of this screen.</p>', 'elasticpress' );

			}

			return array(
				'status' => false,
				'msg'    => $error,
			);

		}

		return array( 'status' => true, 'data' => $response->_all->primaries->indexing );

	}

	/**
	 * Get ES plugins and version, cache everything
	 *
	 * @param  bool $force
	 * @since 2.2
	 * @return array
	 */
	public function get_elasticsearch_info( $force = false ) {

		if ( $force || null === $this->elasticsearch_version || null === $this->elasticsearch_plugins ) {

			// Get ES info from cache if available. If we are forcing, then skip cache check
			if ( $force ) {
				$es_info = false;
			} else {
				if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
					$es_info = get_site_transient( 'ep_es_info' );
				} else {
					$es_info = get_transient( 'ep_es_info' );
				}
			}

			if ( ! empty( $es_info ) ) {
				// Set ES info from cache
				$this->elasticsearch_version = $es_info['version'];
				$this->elasticsearch_plugins = $es_info['plugins'];
			} else {
				$path = '_nodes/plugins';

				$request = $this->remote_request( $path, array( 'method' => 'GET' ) );

				if ( is_wp_error( $request ) || 200 !== wp_remote_retrieve_response_code( $request ) ) {
					$this->elasticsearch_version = false;
					$this->elasticsearch_plugins = false;

					/**
					 * Try a different endpoint in case the plugins url is restricted
					 *
					 * @since 2.2.1
					 */

					$request = $this->remote_request( '', array( 'method' => 'GET' ) );

					if ( ! is_wp_error( $request ) && 200 === wp_remote_retrieve_response_code( $request ) ) {
						$response_body = wp_remote_retrieve_body( $request );
						$response = json_decode( $response_body, true );

						try {
							$this->elasticsearch_version = $response['version']['number'];
						} catch ( Exception $e ) {
							// Do nothing
						}
					}
				} else {
					$response = json_decode( wp_remote_retrieve_body( $request ), true );

					$this->elasticsearch_plugins = [];
					$this->elasticsearch_version = false;

					if ( isset( $response['nodes'] ) ) {

						foreach ( $response['nodes'] as $node ) {
							// Save version of last node. We assume all nodes are same version
							$this->elasticsearch_version = $node['version'];

							if ( isset( $node['plugins'] ) && is_array( $node['plugins'] ) ) {

								foreach ( $node['plugins'] as $plugin ) {

									$this->elasticsearch_plugins[ $plugin['name'] ] = $plugin['version'];
								}

								break;
							}
						}
					}
				}

				/**
				 * Cache ES info
				 *
				 * @since  2.3.1
				 */
				if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
					set_site_transient(
						'ep_es_info',
						array( 'version' => $this->elasticsearch_version, 'plugins' => $this->elasticsearch_plugins, ),
						apply_filters( 'ep_es_info_cache_expiration', ( 5 * MINUTE_IN_SECONDS ) )
					);
				} else {
					set_transient(
						'ep_es_info',
						array( 'version' => $this->elasticsearch_version, 'plugins' => $this->elasticsearch_plugins, ),
						apply_filters( 'ep_es_info_cache_expiration', ( 5 * MINUTE_IN_SECONDS ) )
					);
				}
			}
		}

		return array(
			'plugins' => $this->elasticsearch_plugins,
			'version' => $this->elasticsearch_version,
		);
	}

	/**
	 * Get cluster status
	 *
	 * Retrieves cluster stats from Elasticsearch.
	 *
	 * @since 1.9
	 *
	 * @return array Contains the status message or the returned statistics.
	 */
	public function get_cluster_status() {

		if ( is_wp_error( Utils\get_host() ) ) {

			return array(
				'status' => false,
				'msg'    => esc_html__( 'Elasticsearch Host is not available.', 'elasticpress' ),
			);

		} else {

			$request = $this->remote_request( '_cluster/stats', array( 'method' => 'GET' ) );

			if ( ! is_wp_error( $request ) ) {

				$response = json_decode( wp_remote_retrieve_body( $request ) );

				return $response;

			}

			return array(
				'status' => false,
				'msg'    => $request->get_error_message(),
			);

		}
	}

	/**
	 * Get a pipeline
	 *
	 * @param  string $id
	 * @since  2.3
	 * @return WP_Error|bool|array
	 */
	public function get_pipeline( $id ) {
		$path = '_ingest/pipeline/' . $id;

		$request_args = array(
			'method'  => 'GET',
		);

		$request = $this->remote_request( $path, apply_filters( 'ep_get_pipeline_args', $request_args ), [], 'get_pipeline' );

		if ( is_wp_error( $request ) ) {
			return $request;
		}

		$response = wp_remote_retrieve_response_code( $request );

		if ( 200 !== $response ) {
			return new WP_Error( $response, wp_remote_retrieve_response_message( $request ), $request );
		}

		$body = json_decode( wp_remote_retrieve_body( $request ), true );

		if ( empty( $body ) ) {
			return false;
		}

		return $body;
	}

	/**
	 * Put a pipeline
	 *
	 * @param  string $id
	 * @param array $args
	 * @since  2.3
	 * @return WP_Error|bool
	 */
	public function create_pipeline( $id, $args ) {
		$path = '_ingest/pipeline/' . $id;

		$request_args = array(
			'body'    => json_encode( $args ),
			'method'  => 'PUT',
		);

		$request = $this->remote_request( $path, apply_filters( 'ep_get_pipeline_args', $request_args ), [], 'create_pipeline' );

		if ( is_wp_error( $request ) ) {
			return $request;
		}

		$response = wp_remote_retrieve_response_code( $request );

		if ( 200 > $response || 300 <= $response ) {
			return new WP_Error( $response, wp_remote_retrieve_response_message( $request ), $request );
		}

		$body = json_decode( wp_remote_retrieve_body( $request ), true );

		if ( empty( $body ) ) {
			return false;
		}

		return true;
	}

	public function get_index_status( $index, $network_alias ) {

		if ( is_wp_error( Utils\get_host( true ) ) ) {

			return array(
				'status' => false,
				'msg'    => esc_html__( 'Elasticsearch Host is not available.', 'elasticpress' ),
			);

		} else {

			if ( is_multisite() && null === $blog_id && defined( 'EP_IS_NETWORK' ) && true == EP_IS_NETWORK ) {

				$path = $network_alias . '/_stats/indexing/';

			} else {

				$path = $index . '/_stats/indexing/';

			}

			$request = $this->remote_request( $path, array( 'method' => 'GET' ), [], 'get_index_status' );

		}

		if ( ! is_wp_error( $request ) ) {

			$response = json_decode( wp_remote_retrieve_body( $request ) );

			return ep_parse_api_response( $response );

		}

		return array(
			'status' => false,
			'msg'    => $request->get_error_message(),
		);

	}

	/**
	 * Retrieves search stats from Elasticsearch.
	 *
	 * Retrieves various search statistics from the ES server.
	 *
	 * @since 1.9
	 *
	 * @param int $blog_id Id of blog to get stats.
	 *
	 * @return array Contains the status message or the returned statistics.
	 */
	public function get_search_status( $blog_id = null ) {

		if ( is_wp_error( Utils\get_host() ) ) {

			return array(
				'status' => false,
				'msg'    => esc_html__( 'Elasticsearch Host is not available.', 'elasticpress' ),
			);

		} else {

			if ( is_multisite() && null === $blog_id ) {

				$path = ep_get_network_alias() . '/_stats/search/';

			} else {

				$path = ep_get_index_name( $blog_id ) . '/_stats/search/';

			}

			$request = $this->remote_request( $path, array( 'method' => 'GET' ), [], 'get_search_status' );

		}

		if ( ! is_wp_error( $request ) ) {

			$stats = json_decode( wp_remote_retrieve_body( $request ) );

			if ( isset( $stats->_all ) ) {
				return $stats->_all->primaries->search;
			}

			return false;

		}

		return array(
			'status' => false,
			'msg'    => $request->get_error_message(),
		);

	}

	/**
	 * Query logging. Don't log anything to the queries property when
	 * WP_DEBUG is not enabled. Calls action 'ep_add_query_log' if you
	 * want to access the query outside of the ElasticPress plugin. This
	 * runs regardless of debufg settings.
	 *
	 * @param array $query Query.
	 *
	 * @return void Method does not return.
	 */
	protected function _add_query_log( $query ) {
		if ( ( defined( 'WP_DEBUG' ) && WP_DEBUG ) || ( defined( 'WP_EP_DEBUG' ) && WP_EP_DEBUG ) ) {
			$this->queries[] = $query;
		}

		do_action( 'ep_add_query_log', $query );
	}

}

