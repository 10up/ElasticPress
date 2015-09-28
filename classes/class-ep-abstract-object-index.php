<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // exit if accessed directly
}

abstract class EP_Abstract_Object_Index implements EP_Object_Index {

	/** @var EP_API */
	protected $api = '';

	protected $name = '';

	public function __construct( $name, $api = null ) {
		$this->name = $name;
		$this->api  = $api ? $api : EP_API::factory();
	}

	public function get_name() {
		return $this->name;
	}

	public function set_name( $name ) {
		$this->name = $name;
	}

	public function index_document( $object ) {
		/**
		 * Filter the object prior to indexing
		 *
		 * Allows for last minute indexing of object information.
		 *
		 * @since 1.7
		 *
		 * @param         array Array of post information to index.
		 */
		$object = apply_filters( "ep_pre_index_{$this->name}", $object );

		$index = untrailingslashit( ep_get_index_name() );

		$path = implode( '/', array( $index, $this->name, $this->get_object_identifier( $object ) ) );

		$request_args = array(
			'body'    => json_encode( $object ),
			'method'  => 'PUT',
			'timeout' => 15,
		);

		$request = ep_remote_request(
			$path,
			apply_filters( "ep_index_{$this->name}_request_args", $request_args, $object )
		);

		do_action( "ep_index_{$this->name}_retrieve_raw_response", $request, $object, $path );

		if ( ! is_wp_error( $request ) ) {
			$response_body = wp_remote_retrieve_body( $request );

			return json_decode( $response_body );
		}

		return false;
	}

	public function get_document( $object ) {
		$index = untrailingslashit( ep_get_index_name() );

		$path = implode( '/', array( $index, $this->name, $object ) );

		$request_args = array( 'method' => 'GET' );

		$request = ep_remote_request(
			$path,
			apply_filters( "ep_get_{$this->name}_request_args", $request_args, $object )
		);

		if ( ! is_wp_error( $request ) ) {
			$response_body = wp_remote_retrieve_body( $request );

			$response = json_decode( $response_body, true );

			if ( ! empty( $response['exists'] ) || ! empty( $response['found'] ) ) {
				return $response['_source'];
			}
		}

		return false;
	}

	public function delete_document( $object ) {
		$index = untrailingslashit( ep_get_index_name() );

		$path = implode( '/', array( $index, $this->name, $object ) );

		$request_args = array( 'method' => 'DELETE', 'timeout' => 15 );

		$request = ep_remote_request(
			$path,
			apply_filters( "ep_delete_{$this->name}_request_args", $request_args, $object )
		);

		if ( ! is_wp_error( $request ) ) {
			$response_body = wp_remote_retrieve_body( $request );

			$response = json_decode( $response_body, true );

			if ( ! empty( $response['found'] ) ) {
				return true;
			}
		}

		return false;
	}

	public function search( $args, $scope = 'current' ) {
		$index = null;

		if ( 'all' === $scope ) {
			$index = ep_get_network_alias();
		} elseif ( is_int( $scope ) ) {
			$index = ep_get_index_name( $scope );
		} elseif ( is_array( $scope ) ) {
			$index = array();

			foreach ( $scope as $site_id ) {
				$index[] = ep_get_index_name( $site_id );
			}

			$index = implode( ',', $index );
		} else {
			$index = ep_get_index_name();
		}

		$path = $index . "/{$this->name}/_search";

		$request_args = array(
			'body'    => json_encode( apply_filters( 'ep_search_args', $args, $scope ) ),
			'method'  => 'POST',
		);

		$request = ep_remote_request( $path, apply_filters( 'ep_search_request_args', $request_args, $args, $scope ) );

		if ( ! is_wp_error( $request ) ) {

			// Allow for direct response retrieval
			do_action( 'ep_retrieve_raw_response', $request, $args, $scope, $this->name );

			$response_body = wp_remote_retrieve_body( $request );

			$response = json_decode( $response_body, true );

			if ( $this->api->is_empty_search( $response ) ) {
				return array( 'found_objects' => 0, 'objects' => array() );
			}

			$hits = $response['hits']['hits'];

			// Check for and store aggregations
			if ( ! empty( $response['aggregations'] ) ) {
				do_action( 'ep_retrieve_aggregations', $response['aggregations'], $args, $scope, $this->name );
			}

			$posts = array();

			foreach ( $hits as $hit ) {
				$post = $hit['_source'];
				$post['site_id'] = $this->parse_site_id( $hit['_index'] );
				$posts[] = apply_filters( 'ep_retrieve_the_post', $post, $hit );
			}

			/**
			 * Filter search results.
			 *
			 * Allows more complete use of filtering request variables by allowing for filtering of results.
			 *
			 * @since 1.6.0
			 *
			 * @param array  $results  The unfiltered search results.
			 * @param object $response The response body retrieved from ElasticSearch.
			 */

			return apply_filters( 'ep_search_results_array', array( 'found_objects' => $response['hits']['total'], 'objects' => $posts ), $response );
		}

		return false;
	}

	/**
	 * Get the primary identifier for an object
	 *
	 * This could be a slug, or an ID, or something else. It will be used as a canonical
	 * lookup for the document.
	 *
	 * @param mixed $object
	 *
	 * @return int|string
	 */
	abstract protected function get_object_identifier( $object );

}
