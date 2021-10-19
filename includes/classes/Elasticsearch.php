<?php
/**
 * ElasticPress-Elasticsearch API functions
 *
 * @since  3.0
 * @package elasticpress
 */

namespace ElasticPress;

use ElasticPress\Utils as Utils;
use \WP_Error as WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Elasticsearch API class
 */
class Elasticsearch {

	/**
	 * Logged queries for debugging
	 *
	 * @since  1.8
	 * @var  array
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
	 * @return object
	 * @since 0.1.0
	 */
	public static function factory() {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new self();
		}

		return $instance;
	}

	/**
	 * Index a document in Elasticsearch.
	 *
	 * We require $document to have ID set
	 *
	 * @param  string  $index Index name.
	 * @param  string  $type Index type. Previously this was used for index type. Now it's just passed to hooks for legacy reasons.
	 * @param  array   $document Formatted Elasticsearch document.
	 * @param  boolean $blocking Blocking HTTP request or not.
	 * @since  3.0
	 * @return boolean|array
	 */
	public function index_document( $index, $type, $document, $blocking = true ) {
		/**
		 * Filter Elasticsearch index document request path
		 *
		 * @hook ep_index_{document_type}_request_path
		 * @param {string} $path Path to index document
		 * @param  {int} $document_id Document ID
		 * @param  {array} $document Document to index
		 * @param  {string} $type Type of document
		 * @return  {string} New path
		 * @since  3.0
		 */
		if ( version_compare( $this->get_elasticsearch_version(), '7.0', '<' ) ) {
			$path = apply_filters( 'ep_index_' . $type . '_request_path', $index . '/' . $type . '/' . $document['ID'], $document, $type );
		} else {
			$path = apply_filters( 'ep_index_' . $type . '_request_path', $index . '/_doc/' . $document['ID'], $document, $type );
		}

		$path = apply_filters( 'ep_index_request_path', $path, $document, $type );

		if ( function_exists( 'wp_json_encode' ) ) {
			$encoded_document = wp_json_encode( $document );
		} else {
			// phpcs:disable
			$encoded_document = json_encode( $document );
			// phpcs:enable
		}

		$request_args = array(
			'body'     => $encoded_document,
			'method'   => 'POST',
			'timeout'  => apply_filters( 'ep_index_document_timeout', 15 ),
			'blocking' => $blocking,
		);

		$request = $this->remote_request( $path, $request_args, [], 'index' );

		/**
		 * Backwards compat for pre-3.0
		 */

		/**
		 * Fires after indexing document
		 *
		 * @hook ep_index_post_retrieve_raw_response
		 * @param  {array} $request Remote request response
		 * @param {array} $document Current document
		 * @param  {string} $path Elasticsearch request path
		 */
		do_action( 'ep_index_post_retrieve_raw_response', $request, $document, $path );

		/**
		 * Fires after indexing document
		 *
		 * @hook ep_index_retrieve_raw_response
		 * @param  {array} $request Remote request response
		 * @param {array} $document Current document
		 * @param  {string} $path Elasticsearch request path
		 */
		do_action( 'ep_index_retrieve_raw_response', $request, $document, $path );

		if ( ! is_wp_error( $request ) ) {
			$response_body = wp_remote_retrieve_body( $request );

			$return = json_decode( $response_body );
		} else {
			$return = false;
		}

		/**
		 * Backwards compat for pre-3.0
		 */

		/**
		 * Fires after indexing document and body decoding
		 *
		 * @hook ep_index_index_post
		 * @param {array} $document Current document
		 * @param  {array|boolean} $return Elasticsearch response. False on error.
		 */
		do_action( 'ep_after_index_post', $document, $return );

		/**
		 * Fires after indexing document and body decoding
		 *
		 * @hook ep_index_index
		 * @param {array} $document Current document
		 * @param  {array|boolean} $return Elasticsearch response. False on error.
		 */
		do_action( 'ep_after_index', $document, $return );

		return $return;
	}

	/**
	 * Pull the site id from the index name
	 *
	 * @param string $index_name Index name.
	 * @since 0.9.0
	 * @return int
	 */
	public function parse_site_id( $index_name ) {
		return (int) preg_replace( '#^.*\-([0-9]+)$#', '$1', $index_name );
	}

	/**
	 * Refresh all index. Sometimes useful if you need changes to show up instantly.
	 *
	 * @since  3.0
	 * @return bool
	 */
	public function refresh_indices() {

		$request_args = array( 'method' => 'POST' );

		$request = $this->remote_request( '_refresh', $request_args, [], 'refresh_indices' );

		if ( ! is_wp_error( $request ) ) {
			if ( isset( $request['response']['code'] ) && 200 === $request['response']['code'] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get Elasticsearch version. We cache this so we don't have to do it every time.
	 *
	 * @param  bool $force Bust cache or not.
	 * @since  2.1.2
	 * @return string|bool
	 */
	public function get_elasticsearch_version( $force = false ) {

		$info = $this->get_elasticsearch_info( $force );

		/**
		 * Filter Elasticsearch version
		 *
		 * @hook ep_elasticsearch_version
		 * @param {string} $version Version
		 * @return  {string} New version
		 * @since  2.1.2
		 */
		return apply_filters( 'ep_elasticsearch_version', $info['version'] );
	}

	/**
	 * Get Elasticsearch plugins. We cache this so we don't have to do it every time.
	 *
	 * @param  bool $force Force cache refresh or not.
	 * @since  2.2
	 * @return string|bool
	 */
	public function get_elasticsearch_plugins( $force = false ) {

		$info = $this->get_elasticsearch_info( $force );

		/**
		 * Filter Elasticsearch plugins
		 *
		 * @hook ep_elasticsearch_plugins
		 * @param {array} $plugins Elasticsearch plugins
		 * @return  {array} New plugins
		 * @since  2.2
		 */
		return apply_filters( 'ep_elasticsearch_plugins', $info['plugins'] );
	}

	/**
	 * Run a query on Elasticsearch
	 *
	 * @param  string $index Index name.
	 * @param  string $type Index type. Previously this was used for index type. Now it's just passed to hooks for legacy reasons.
	 * @param  array  $query Prepared ES query.
	 * @param  array  $query_args WP query args.
	 * @param  mixed  $query_object Could be WP_Query, WP_User_Query, etc.
	 * @since  3.0
	 * @return bool|array
	 */
	public function query( $index, $type, $query, $query_args, $query_object = null ) {
		if ( version_compare( $this->get_elasticsearch_version(), '7.0', '<' ) ) {
			$path = $index . '/' . $type . '/_search';
		} else {
			$path = $index . '/_search';
		}

		// For backwards compat
		/**
		 * Filter Elasticsearch query request path
		 *
		 * @hook ep_search_request_path
		 * @param {string} $path Request path
		 * @param  {string} $index Index name
		 * @param  {string} $type Index type
		 * @param  {array} $query Prepared Elasticsearch query
		 * @param  {array} $query_args Query arguments
		 * @param  {mixed} $query_object Could be WP_Query, WP_User_Query, etc.
		 * @return  {string} New path
		 */
		$path = apply_filters( 'ep_search_request_path', $path, $index, $type, $query, $query_args, $query_object );

		/**
		 * Filter Elasticsearch query request path
		 *
		 * @hook ep_query_request_path
		 * @param {string} $path Request path
		 * @param  {string} $index Index name
		 * @param  {string} $type Index type
		 * @param  {array} $query Prepared Elasticsearch query
		 * @param  {array} $query_args Query arguments
		 * @param  {mixed} $query_object Could be WP_Query, WP_User_Query, etc.
		 * @return  {string} New path
		 */
		$path = apply_filters( 'ep_query_request_path', $path, $index, $type, $query, $query_args, $query_object );

		$request_args = array(
			'body'    => wp_json_encode( $query ),
			'method'  => 'POST',
			'headers' => array(
				'Content-Type' => 'application/json',
			),
		);

		/**
		 * Filter whether to send the EP-Search-Term header or not.
		 *
		 * @todo Evaluate if we should remove tests for is_admin() and empty post types.
		 *
		 * @since  3.5.2
		 * @hook ep_query_send_ep_search_term_header
		 * @param  {bool}  $send_header True means send the EP-Search-Term header
		 * @param  {array} $query_args  WP query args
		 * @return {bool}  New $send_header value
		 */
		$send_ep_search_term_header = apply_filters(
			'ep_query_send_ep_search_term_header',
			(
				Utils\is_epio() &&
				! empty( $query_args['s'] ) &&
				Utils\is_integrated_request( 'search' ) &&
				! isset( $_GET['post_type'] ) // phpcs:ignore WordPress.Security.NonceVerification
			),
			$query_args
		);

		// If needed, send the search term as a header to ES so the backend understands what a normal query looks like
		if ( $send_ep_search_term_header ) {
			$request_args['headers']['EP-Search-Term'] = rawurlencode( $query_args['s'] );
		}

		/**
		 * Filter Elasticsearch query request arguments
		 *
		 * @hook ep_query_request_args
		 * @since 3.6.4
		 * @param {array}  $request_args Request arguments
		 * @param {string} $path         Request path
		 * @param {string} $index        Index name
		 * @param {string} $type         Index type
		 * @param {array}  $query        Prepared Elasticsearch query
		 * @param {array}  $query_args   Query arguments
		 * @param {mixed}  $query_object Could be WP_Query, WP_User_Query, etc.
		 * @return {array} New request arguments
		 */
		$request_args = apply_filters( 'ep_query_request_args', $request_args, $path, $index, $type, $query, $query_args, $query_object );

		$request = $this->remote_request( $path, $request_args, $query_args, 'query' );

		$remote_req_res_code = absint( wp_remote_retrieve_response_code( $request ) );

		$is_valid_res = ( $remote_req_res_code >= 200 && $remote_req_res_code <= 299 );

		/**
		 * Filter whether Elasticsearch remote request response code is valid
		 *
		 * @hook ep_remote_request_is_valid_res
		 * @param {boolean} $is_valid_res Whether response code is valid or not
		 * @param  {array} $request Remote request response
		 * @return  {string} New value
		 */
		if ( ! is_wp_error( $request ) && apply_filters( 'ep_remote_request_is_valid_res', $is_valid_res, $request ) ) {

			$response_body = wp_remote_retrieve_body( $request );

			$response = json_decode( $response_body, true );

			$hits       = $this->get_hits_from_query( $response );
			$total_hits = $this->get_total_hits_from_query( $response );

			if ( ! empty( $response['aggregations'] ) ) {
				/**
				 * Deprecated way to retrieve aggregations.
				 *
				 * @hook ep_retrieve_aggregations
				 * @param {array} $aggregations Elasticsearch aggregations
				 * @param  {array} $query Prepared Elasticsearch query
				 * @param {string} $scope Backwards compat for scope parameter.
				 * @param  {array} $query_args Current WP Query arguments
				 */
				do_action( 'ep_retrieve_aggregations', $response['aggregations'], $query, '', $query_args );
			}

			/**
			 * Fires after valid Elasticsearch query
			 *
			 * @hook ep_valid_response
			 * @param {array} $response Elasticsearch decoded response
			 * @param  {array} $query Prepared Elasticsearch query
			 * @param  {array} $query_args Current WP Query arguments
			 * @param  {mixed} $query_object Could be WP_Query, WP_User_Query, etc.
			 */
			do_action( 'ep_valid_response', $response, $query, $query_args, $query_object );

			// Backwards compat
			/**
			 * Fires after valid Elasticsearch query
			 *
			 * @hook ep_retrieve_raw_response
			 * @param {array} $response Elasticsearch request
			 * @param  {array} $query Prepared Elasticsearch query
			 * @param  {array} $query_args Current WP Query arguments
			 * @param  {mixed} $query_object Could be WP_Query, WP_User_Query, etc.
			 */
			do_action( 'ep_retrieve_raw_response', $request, $query, $query_args, $query_object );

			$documents = [];

			foreach ( $hits as $hit ) {
				$document            = $hit['_source'];
				$document['site_id'] = $this->parse_site_id( $hit['_index'] );

				if ( ! empty( $hit['highlight'] ) ) {
					$document['highlight'] = $hit['highlight'];
				}

				/**
				 * Filter Elasticsearch retrieved document
				 *
				 * @hook ep_retrieve_the_{index_type}
				 * @param  {array} $document Document retrieved from Elasticsearch
				 * @param  {array} $hit Raw Elasticsearch hit
				 * @param  {string} $index Index name
				 * @return  {array} New document
				 */
				$documents[] = apply_filters( 'ep_retrieve_the_' . $type, $document, $hit, $index );
			}

			/**
			 * Filter Elasticsearch query results
			 *
			 * @hook ep_es_query_results
			 * @param {array} $results Results from Elasticsearch
			 * @param  {response} $response Raw response from Elasticsearch
			 * @param  {array} $query Raw Elasticsearch query
			 * @param  {array} $query_args Query arguments
			 * @param  {mixed} $query_object Could be WP_Query, WP_User_Query, etc.
			 * @return  {array} New results
			 */
			return apply_filters(
				'ep_es_query_results',
				[
					'found_documents' => $total_hits,
					'documents'       => $documents,
				],
				$response,
				$query,
				$query_args,
				$query_object
			);
		}

		/**
		 * Fires after invalid Elasticsearch query
		 *
		 * @hook ep_invalid_response
		 * @param  {array} $request Remote request response
		 * @param  {array} $query Prepared Elasticsearch query
		 * @param  {array} $query_args Current WP Query arguments
		 * @param  {mixed} $query_object Could be WP_Query, WP_User_Query, etc.
		 */
		do_action( 'ep_invalid_response', $request, $query, $query_args, $query_object );

		return false;
	}

	/**
	 * Returns the number of total results that ElasticSearch found for the given query
	 *
	 * @param array $response Response to get total hits from.
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
	 * @param array $response Response to get hits from.
	 * @since  2.5
	 * @return array
	 */
	public function get_hits_from_query( $response ) {

		if ( $this->is_empty_query( $response ) ) {
			return [];
		}

		/**
		 * Filter Elasticsearch allows to flatten hits, if searched hits are come within aggregations.
		 *
		 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/search-aggregations-metrics-top-hits-aggregation.html
		 *
		 * @hook ep_get_hits_from_query
		 * @param {array} $hits from Elasticsearch
		 * @param {response} $response Raw response from Elasticsearch
		 * @return {array} hits
		 */
		return apply_filters( 'ep_get_hits_from_query', $response['hits']['hits'], $response );
	}

	/**
	 * Check if a response array contains results or not
	 *
	 * @param array $response Response to check.
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

		if ( isset( $response['hits']['total'] ) && 0 === (int) $response['hits']['total'] ) {
			return true;
		}

		return false;
	}

	/**
	 * Delete an Elasticsearch document
	 *
	 * @param  string  $index Index name.
	 * @param  string  $type Index type. Previously this was used for index type. Now it's just passed to hooks for legacy reasons.
	 * @param  int     $document_id Document id to delete.
	 * @param  boolean $blocking Blocking HTTP request or not.
	 * @since  3.0
	 * @return boolean
	 */
	public function delete_document( $index, $type, $document_id, $blocking = true ) {
		if ( version_compare( $this->get_elasticsearch_version(), '7.0', '<' ) ) {
			$path = $index . '/' . $type . '/' . $document_id;
		} else {
			$path = $index . '/_doc/' . $document_id;
		}

		$request_args = [
			'method'   => 'DELETE',
			'timeout'  => 15,
			'blocking' => $blocking,
		];

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
	 * Add appropriate headers to request
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
		 * ES Shield info
		 *
		 * @since 1.9
		 */
		$shield = Utils\get_shield_credentials();

		if ( ! empty( $shield ) ) {
			// phpcs:disable
			$headers['Authorization'] = 'Basic ' . base64_encode( $shield );
			// phpcs:enable
		}

		/**
		 * Filter Elasticsearch request headers
		 *
		 * @hook ep_format_request_headers
		 * @param {array} $headers Current headers
		 * @return  {array} New headers
		 */
		$headers = apply_filters( 'ep_format_request_headers', $headers );

		return $headers;
	}

	/**
	 * Get a document from Elasticsearch given an id
	 *
	 * @param  string $index Index name.
	 * @param  string $type Index type. Previously this was used for index type. Now it's just passed to hooks for legacy reasons.
	 * @param  int    $document_id Document id to get.
	 * @since  3.0
	 * @return boolean|array
	 */
	public function get_document( $index, $type, $document_id ) {
		if ( version_compare( $this->get_elasticsearch_version(), '7.0', '<' ) ) {
			$path = $index . '/' . $type . '/' . $document_id;
		} else {
			$path = $index . '/_doc/' . $document_id;
		}

		$request_args = [ 'method' => 'GET' ];

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

	/**
	 * Delete the network alias.
	 *
	 * Network aliases are used to query documents across blogs in a network.
	 *
	 * @param  string $alias Alias to use.
	 * @since  3.0
	 * @return array|boolean
	 */
	public function delete_network_alias( $alias ) {
		$path = '*/_alias/' . $alias;

		$request_args = [ 'method' => 'DELETE' ];

		$request = $this->remote_request( $path, $request_args, [], 'delete_network_alias' );

		if ( ! is_wp_error( $request ) && ( 200 >= wp_remote_retrieve_response_code( $request ) && 300 > wp_remote_retrieve_response_code( $request ) ) ) {
			$response_body = wp_remote_retrieve_body( $request );

			return json_decode( $response_body );
		}

		return false;
	}

	/**
	 * Get multiple documents from Elasticsearch given an array of ids
	 *
	 * @param  string $index Index name.
	 * @param  string $type Index type. Previously this was used for index type. Now it's just passed to hooks for legacy reasons.
	 * @param  array  $document_ids Array of document ids to get.
	 * @since  3.6.0
	 * @return boolean|array
	 */
	public function get_documents( $index, $type, $document_ids ) {
		if ( version_compare( $this->get_elasticsearch_version(), '7.0', '<' ) ) {
			$path = apply_filters( 'ep_index_' . $type . '_request_path', $index . '/' . $type . '/_mget', $document_ids, $type );
		} else {
			$path = apply_filters( 'ep_index_' . $type . '_request_path', $index . '/_mget', $document_ids, $type );
		}

		$request_args = [
			'method' => 'POST',
			'body'   => wp_json_encode(
				array(
					'ids' => $document_ids,
				)
			),
		];

		$request = $this->remote_request( $path, $request_args, [], 'post' );

		if ( is_wp_error( $request ) ) {
			return false;
		}

		$response_body = wp_remote_retrieve_body( $request );

		$response = json_decode( $response_body, true );

		$docs = [];

		if ( isset( $response['docs'] ) && is_array( $response['docs'] ) ) {
			foreach ( $response['docs'] as $doc ) {
				if ( ! empty( $doc['exists'] ) || ! empty( $doc['found'] ) ) {
					$docs[ $doc['_id'] ] = $doc['_source'];
				}
			}
		}

		/**
		 * Filter documents found by Elasticsearch through the /_mget endpoint.
		 *
		 * @hook ep_get_documents
		 * @since 3.6.0
		 * @param {array} $docs Documents found indexed by ID
		 * @param  {string} $index Index name
		 * @param  {string} $type Index type
		 * @param  {array} $document_ids Array of document ids
		 * @return  {array} Documents to be returned
		 */
		$docs = apply_filters( 'ep_get_documents', $docs, $index, $type, $document_ids );

		return $docs;
	}

	/**
	 * Create the network alias.
	 *
	 * Network aliases are used to query documents across blogs in a network.
	 *
	 * @param  array  $indexes       Indexes to group under alias.
	 * @param  string $network_alias Name of network alias.
	 * @since  3.0
	 * @return boolean
	 */
	public function create_network_alias( $indexes, $network_alias ) {

		$path = '_aliases';

		$args = array(
			'actions' => [],
		);

		foreach ( $indexes as $index ) {
			if ( empty( $index ) ) {
				continue;
			}

			$args['actions'][] = array(
				'add' => array(
					'index' => $index,
					'alias' => $network_alias,
				),
			);
		}

		$request_args = array(
			'body'    => wp_json_encode( $args ),
			'method'  => 'POST',
			'timeout' => 25,
		);

		$request = $this->remote_request( $path, $request_args, [], 'create_network_alias' );

		if ( ! is_wp_error( $request ) && ( 200 >= wp_remote_retrieve_response_code( $request ) && 300 > wp_remote_retrieve_response_code( $request ) ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Put a mapping into Elasticsearch
	 *
	 * @param  string $index Index name.
	 * @param  array  $mapping Mapping array.
	 * @since  3.0
	 * @return boolean
	 */
	public function put_mapping( $index, $mapping ) {
		/**
		 * Filter Elasticsearch mapping before put mapping
		 *
		 * @hook ep_config_mapping
		 * @param {array} $mapping Elasticsearch mapping
		 * @param  {string} $index Index name
		 * @return  {array} New mapping
		 */
		$mapping = apply_filters( 'ep_config_mapping', $mapping, $index );

		$request_args = [
			'body'    => wp_json_encode( $mapping ),
			'method'  => 'PUT',
			'timeout' => 30,
		];

		$request = $this->remote_request( $index, $request_args, [], 'put_mapping' );

		/**
		 * Filter Elasticsearch put mapping response
		 *
		 * @hook ep_config_mapping_request
		 * @param {array} $request Elasticsearch response
		 * @param  {string} $index Elasticsearch index name
		 * @param  {array} $mapping Mapping sent to Elasticsearch
		 * @return  {array} New response
		 */
		$request = apply_filters( 'ep_config_mapping_request', $request, $index, $mapping );

		$response_body = wp_remote_retrieve_body( $request );

		if ( ! is_wp_error( $request ) && 200 === wp_remote_retrieve_response_code( $request ) ) {
			$response_body = wp_remote_retrieve_body( $request );

			return true;
		}

		return false;
	}

	/**
	 * Get current index mapping from Elasticsearch.
	 *
	 * @param  string $index The index name.
	 * @since  3.5
	 * @return array
	 */
	public function get_mapping( $index ) {
		$request_args = [
			'method'  => 'GET',
			'timeout' => 30,
		];

		$request = $this->remote_request( $index, $request_args, [], 'get_mapping' );

		if ( is_wp_error( $request ) || 200 !== wp_remote_retrieve_response_code( $request ) ) {
			return [];
		}

		$body = wp_remote_retrieve_body( $request );

		if ( ! $body ) {
			return [];
		}

		$mapping = json_decode( $body, true );

		return is_array( $mapping ) ? $mapping : [];
	}

	/**
	 * Close an open index.
	 *
	 * @param  string $index Index name.
	 * @since  3.5
	 * @return boolean
	 */
	public function close_index( $index ) {
		$request_args = [
			'method'  => 'POST',
			'timeout' => 30,
		];

		$close   = trailingslashit( $index ) . '_close';
		$request = $this->remote_request( $close, $request_args, [], 'close_index' );

		return ( ! is_wp_error( $request ) && 200 === wp_remote_retrieve_response_code( $request ) );
	}

	/**
	 * Open a closed index.
	 *
	 * @param  string $index Index name.
	 * @since  3.5
	 * @return boolean
	 */
	public function open_index( $index ) {
		$request_args = [
			'method'  => 'POST',
			'timeout' => 30,
		];

		$open    = trailingslashit( $index ) . '_open';
		$request = $this->remote_request( $open, $request_args, [], 'open_index' );

		return ( ! is_wp_error( $request ) && 200 === wp_remote_retrieve_response_code( $request ) );
	}

	/**
	 * Update index settings.
	 *
	 * @param  string  $index       Index name.
	 * @param  array   $settings    Setting update array.
	 * @param  boolean $close_first Optional. True if index must be closed prior to update.
	 *                              Dynamic settings can be updated on open indices. Static
	 *                              settings must be closed.  Default false.
	 * @since  3.5
	 * @return boolean
	 */
	public function update_index_settings( $index, $settings, $close_first = false ) {
		$request_args = [
			'body'    => wp_json_encode( $settings ),
			'method'  => 'PUT',
			'timeout' => 30,
		];

		$closed = false;
		if ( $close_first ) {
			$closed = $this->close_index( $index );
		}

		if ( ! $close_first || $closed ) {
			$settings = trailingslashit( $index ) . '_settings';
			$request  = $this->remote_request( $settings, $request_args, [], 'update_index_settings' );
		} else {
			return false;
		}

		$updated = ( ! is_wp_error( $request ) && 200 === wp_remote_retrieve_response_code( $request ) );

		if ( $closed ) {
			$opened = $this->open_index( $index );
			return ( $updated && $opened );
		}

		return $updated;
	}

	/**
	 * Delete an Elasticsearch index
	 *
	 * @param  string $index Index name.
	 * @since  3.0
	 * @return boolean
	 */
	public function delete_index( $index ) {

		$request_args = [
			'method'  => 'DELETE',
			'timeout' => 30,
		];

		$request = $this->remote_request( $index, $request_args, [], 'delete_index' );

		// 200 means the delete was successful
		// 404 means the index was non-existent, but we should still pass this through as we will occasionally want to delete an already deleted index
		if ( ! is_wp_error( $request ) && ( 200 === wp_remote_retrieve_response_code( $request ) || 404 === wp_remote_retrieve_response_code( $request ) ) ) {
			$response_body = wp_remote_retrieve_body( $request );

			return json_decode( $response_body );
		}

		return false;
	}

	/**
	 * Delete all indices
	 *
	 * @since  3.0
	 * @return boolean
	 */
	public function delete_all_indices() {
		return $this->delete_index( '*' );
	}

	/**
	 * Check if an ES index exists
	 *
	 * @param  string $index Index name.
	 * @since  3.0
	 * @return boolean
	 */
	public function index_exists( $index ) {

		$request_args = [
			'method' => 'HEAD',
		];

		$request = $this->remote_request( $index, $request_args, [], 'index_exists' );

		// 200 means the index exists.
		// 404 means the index was non-existent.
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

	/**
	 * Bulk index Elasticsearch documents
	 *
	 * @param  string $index Index name.
	 * @param  string $type Index type. Previously this was used for index type. Now it's just passed to hooks for legacy reasons.
	 * @param  string $body  Encoded JSON.
	 * @since  3.0
	 * @return WP_Error|array
	 */
	public function bulk_index( $index, $type, $body ) {
		/**
		 * Filter Elasticsearch bulk index request path
		 *
		 * @hook ep_bulk_index_request_path
		 * @param {string} Request path
		 * @param  {string} $body Bulk index request body
		 * @param  {string} $type Index type
		 * @return  {string} New path
		 */
		if ( version_compare( $this->get_elasticsearch_version(), '7.0', '<' ) ) {
			$path = apply_filters( 'ep_bulk_index_request_path', $index . '/' . $type . '/_bulk', $body, $type );
		} else {
			$path = apply_filters( 'ep_bulk_index_request_path', $index . '/_bulk', $body, $type );
		}

		$request_args = array(
			'method'  => 'POST',
			'body'    => $body,
			'timeout' => apply_filters( 'ep_bulk_index_timeout', 30 ),
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
	 * @param array  $query_args Optional. The query args originally passed to WP_Query.
	 * @param string $type Type of request, used for debugging.
	 *
	 * @return WP_Error|array The response or WP_Error on failure.
	 */
	public function remote_request( $path, $args = [], $query_args = [], $type = null ) {

		if ( empty( $args['method'] ) ) {
			$args['method'] = 'GET';
		}

		// Checks for any previously set headers
		$existing_headers = isset( $args['headers'] ) ? (array) $args['headers'] : [];

		// Add the API Header.
		// Note that the "User Agent" header will be changed via WordPress's `http_headers_useragent` filter later.
		$new_headers = $this->format_request_headers();

		$args['headers'] = array_merge( $existing_headers, $new_headers );

		/**
		 * Filter Elasticsearch args prior to remote request
		 *
		 * @hook ep_pre_request_args
		 * @since 3.6.4
		 * @param {array}       $args       Request args
		 * @param {string}      $path       Site URL to retrieve
		 * @param {array}       $query_args The query args originally passed to WP_Query.
		 * @param {string|null} $type       Type of request, used for debugging.
		 * @return {array} New request args
		 */
		$args = apply_filters( 'ep_pre_request_args', $args, $path, $query_args, $type );

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

		$request  = false;
		$failures = 0;

		add_filter( 'http_headers_useragent', [ $this, 'add_elasticpress_version_to_user_agent' ] );

		// Optionally let us try back up hosts and account for failures.
		while ( true ) {
			/**
			 * Filter Elasticsearch host prior to remote request
			 *
			 * @hook ep_pre_request_host
			 * @param {string} Request host
			 * @param  {int} $failures Number of current failures
			 * @param  {string} $path Request path
			 * @param  {array} $args Request arguments
			 * @return {string} New host
			 */
			$query['host'] = apply_filters( 'ep_pre_request_host', $query['host'], $failures, $path, $args );

			/**
			 * Filter Elasticsearch url prior to remote request
			 *
			 * @hook ep_pre_request_url
			 * @param {string} Request url
			 * @param  {int} $failures Number of current failures
			 * @param  {string} $host Request host
			 * @param  {string} $path Request path
			 * @param  {array} $args Request arguments
			 * @return {string} New url
			 */
			$query['url'] = apply_filters( 'ep_pre_request_url', esc_url( trailingslashit( $query['host'] ) . $path ), $failures, $query['host'], $path, $args );

			/**
			 * Filter whether remote request should be intercepted
			 *
			 * @hook ep_intercept_remote_request
			 * @param {boolean} $intercept True to intercept
			 * @return {boolean} New value
			 */
			if ( true === apply_filters( 'ep_intercept_remote_request', false ) ) {
				/**
				 * Filter intercepted request
				 *
				 * @hook ep_do_intercept_request
				 * @param {array} $request New remote request response
				 * @param  {array} $query Remote request arguments
				 * @param  {args} $args Request arguments
				 * @param  {int} $failures Number of failures
				 * @return {array} New request
				 */
				$request = apply_filters( 'ep_do_intercept_request', new WP_Error( 400, 'No Request defined' ), $query, $args, $failures );
			} else {
				$request = wp_remote_request( $query['url'], $args ); // try the existing host to avoid unnecessary calls.
			}

			$request_response_code = (int) wp_remote_retrieve_response_code( $request );

			$is_valid_res            = ( $request_response_code >= 200 && $request_response_code <= 299 );
			$is_non_blocking_request = ( 0 === $request_response_code );

			if ( false === $request || is_wp_error( $request ) || ( ! $is_valid_res && ! $is_non_blocking_request ) ) {
				$failures++;

				/**
				 * Filter max number of times to attempt remote requests
				 *
				 * @hook ep_max_remote_request_tries
				 * @param {int} $tries Number of times to try
				 * @param  {path} $path Request path
				 * @param  {args} $args Request arguments
				 * @return {int} New number of tries
				 */
				if ( $failures >= apply_filters( 'ep_max_remote_request_tries', 1, $path, $args ) ) {
					break;
				}
			} else {
				break;
			}
		}

		remove_filter( 'http_headers_useragent', [ $this, 'add_elasticpress_version_to_user_agent' ] );

		// Return now if we're not blocking, since we won't have a response yet.
		if ( isset( $args['blocking'] ) && false === $args['blocking'] ) {
			$query['blocking'] = true;
			$query['request']  = $request;
			$this->add_query_log( $query );

			return $request;
		}

		$query['time_finish'] = microtime( true );
		$query['request']     = $request;
		$this->add_query_log( $query );

		/**
		 * Fires after Elasticsearch remote request
		 *
		 * @hook ep_remote_request
		 * @param  {array} $query Remote request arguments
		 * @param  {string} $type Request type
		 */
		do_action( 'ep_remote_request', $query, $type );

		return $request;

	}

	/**
	 * Parse response from Elasticsearch
	 *
	 * Determines if there is an issue or if the response is valid.
	 *
	 * @since 1.9
	 * @param object $response JSON decoded response from Elasticsearch.
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

		return array(
			'status' => true,
			'data'   => $response->_all->primaries->indexing,
		);

	}

	/**
	 * Get ES plugins and version, cache everything
	 *
	 * @param  bool $force Bust cache or not.
	 * @since 2.2
	 * @return array
	 */
	public function get_elasticsearch_info( $force = false ) {

		if ( $force || null === $this->elasticsearch_version || null === $this->elasticsearch_plugins ) {

			// Get ES info from cache if available. If we are forcing, then skip cache check.
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
				// Set ES info from cache.
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
						$response      = json_decode( $response_body, true );

						try {
							$this->elasticsearch_version = $response['version']['number'];
						} catch ( Exception $e ) {
							// Do nothing.
						}
					}
				} else {
					$response = json_decode( wp_remote_retrieve_body( $request ), true );

					$this->elasticsearch_plugins = [];
					$this->elasticsearch_version = false;

					if ( isset( $response['nodes'] ) ) {

						foreach ( $response['nodes'] as $node ) {
							// Save version of last node. We assume all nodes are same version.
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

				/**
				 * Filter elasticsearch info cache expiration
				 *
				 * @hook ep_es_info_cache_expiration
				 * @param {int} $time Cache time in seconds
				 * @return {int} New cache time
				 */
				if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
					set_site_transient(
						'ep_es_info',
						array(
							'version' => $this->elasticsearch_version,
							'plugins' => $this->elasticsearch_plugins,
						),
						apply_filters( 'ep_es_info_cache_expiration', ( 5 * MINUTE_IN_SECONDS ) )
					);
				} else {
					set_transient(
						'ep_es_info',
						array(
							'version' => $this->elasticsearch_version,
							'plugins' => $this->elasticsearch_plugins,
						),
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
	 * Get an Elasticsearch pipeline
	 *
	 * @param  string $id Id of pipeline.
	 * @since  2.3
	 * @return WP_Error|bool|array
	 */
	public function get_pipeline( $id ) {
		$path = '_ingest/pipeline/' . $id;

		$request_args = array(
			'method' => 'GET',
		);

		/**
		 * Filter get pipeline request arguments
		 *
		 * @hook ep_get_pipeline_args
		 * @param  {array} $request_args Request arguments
		 * @return {array} New arguments
		 */
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
	 * Put an Elasticsearch pipeline
	 *
	 * @param  string $id Pipeline id.
	 * @param array  $args Args to send to ES.
	 * @since  2.3
	 * @return WP_Error|bool
	 */
	public function create_pipeline( $id, $args ) {
		$path = '_ingest/pipeline/' . $id;

		$request_args = array(
			'body'   => wp_json_encode( $args ),
			'method' => 'PUT',
		);

		/**
		 * Filter create pipeline request arguments
		 *
		 * @hook ep_create_pipeline_args
		 * @param  {array} $request_args Request arguments
		 * @return {array} New arguments
		 */
		$request = $this->remote_request( $path, apply_filters( 'ep_create_pipeline_args', $request_args ), [], 'create_pipeline' );

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

	/**
	 * Conditionally add the ElasticPress version to the User Agent string.
	 *
	 * @since 3.6.1
	 * @param string $user_agent Original User Agent.
	 * @return string
	 */
	public function add_elasticpress_version_to_user_agent( $user_agent ) {
		/**
		 * Filter the User Agent header when submitting requests to Elasticsearch.
		 *
		 * @hook ep_remote_request_add_ep_user_agent
		 * @param  {bool} $should_add_ep_verion Whether the ElasticPress version should be added to the User Agent string.
		 * @return {bool} New value
		 * @since  3.6.1
		 */
		if ( apply_filters( 'ep_remote_request_add_ep_user_agent', Utils\is_epio() ) ) {
			$end_part   = '; ' . get_bloginfo( 'url' );
			$user_agent = str_replace(
				$end_part,
				' (ElasticPress/' . EP_VERSION . ')' . $end_part,
				$user_agent
			);
		}
		return $user_agent;
	}

	/**
	 * Query logging. Don't log anything to the queries property when
	 * WP_DEBUG is not enabled. Calls action 'ep_add_query_log' if you
	 * want to access the query outside of the ElasticPress plugin. This
	 * runs regardless of debufg settings.
	 *
	 * @param array $query Query to log.
	 */
	protected function add_query_log( $query ) {
		if ( ( defined( 'WP_DEBUG' ) && WP_DEBUG ) || ( defined( 'WP_EP_DEBUG' ) && WP_EP_DEBUG ) ) {
			$this->queries[] = $query;
		}

		/**
		 * Fires after item is added to the query log
		 *
		 * @hook ep_add_query_log
		 * @param {array} $query Query to log
		 */
		do_action( 'ep_add_query_log', $query );
	}

}
