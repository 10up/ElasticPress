<?php

class EP_API {

	/**
	 * Status of Elasticsearch connection
	 *
	 * @var bool
	 */
	private $is_alive = array();

	/*
	 * Placeholder method
	 *
	 * @since 0.1.0
	 */
	public function __construct() { }

	/**
	 * Return singleton instance of class
	 *
	 * @return EP_API
	 */
	public static function factory() {
		static $instance = false;

		if ( ! $instance  ) {
			$instance = new self();
		}

		return $instance;
	}

	/**
	 * Index a post under a given site index or the global index ($site_id = 0)
	 *
	 * @param array $post
	 * @param int $site_id
	 * @return array|bool|mixed
	 */
	public function index_post( $post, $site_id = null ) {

		$index_url = ep_get_index_url( $site_id );

		$url = $index_url . '/post/';

		if ( ! empty( $post['site_id'] ) && $post['site_id'] > 1 ) {
			$url .= (int) $post['site_id'] . 'ms' . (int) $post['post_id'];
		} else {
			$url .= (int) $post['post_id'];
		}

		$request = wp_remote_request( $url, array( 'body' => json_encode( $post ), 'method' => 'PUT' ) );

		if ( ! is_wp_error( $request ) ) {
			$response_body = wp_remote_retrieve_body( $request );

			return json_decode( $response_body );
		}

		return false;
	}

	/**
	 * Search for posts under a specific site index or the global index ($site_id = 0).
	 *
	 * @param array $args
	 * @param int $site_id
	 * @since 0.1.0
	 * @return array
	 */
	public function search( $args, $site_id = null ) {
		$index_url = ep_get_index_url( $site_id );

		$url = $index_url . '/post/_search';

		$request = wp_remote_request( $url, array( 'body' => json_encode( $args ), 'method' => 'POST' ) );

		if ( ! is_wp_error( $request ) ) {
			$response_body = wp_remote_retrieve_body( $request );

			$response = json_decode( $response_body, true );

			if ( $this->is_empty_search( $response ) ) {
				return array( 'found_posts' => 0, 'posts' => array() );
			}

			$hits = $response['hits']['hits'];

			return array( 'found_posts' => $response['hits']['total'], 'posts' => wp_list_pluck( $hits, '_source' ) );
		}

		return array( 'found_posts' => 0, 'posts' => array() );
	}

	/**
	 * Check if a response array contains results or not
	 *
	 * @param array $response
	 * @return bool
	 */
	public function is_empty_search( $response ) {

		if ( ! is_array( $response ) ) {
			return true;
		}

		if ( isset( $response['error'] ) ) {
			return true;
		}

		if ( empty( $response['hits'] ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Delete a post from the ES server given a site ID and a host site ID which
	 * is used to determine the index to delete from.
	 *
	 * @param int $post_id
	 * @param int $site_id
	 * @param int $host_site_id
	 * @since 0.1.0
	 * @return bool
	 */
	public function delete_post( $post_id, $site_id = null, $host_site_id = null ) {
		$index_url = ep_get_index_url( $host_site_id );

		$url = $index_url . '/post/';

		if ( ! empty( $site_id ) && $site_id > 1 ) {
			$url .= (int) $site_id . 'ms' . (int) $post_id;
		} else {
			$url .= (int) $post_id;
		}

		$request = wp_remote_request( $url, array( 'method' => 'DELETE' ) );

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
	 * Check if a post is indexed given a $site_id and a $host_site_id
	 *
	 * @param int $post_id
	 * @param int $site_id
	 * @param int $host_site_id
	 * @since 0.1.0
	 * @return bool
	 */
	public function post_indexed( $post_id, $site_id = null, $host_site_id = null ) {
		$index_url = ep_get_index_url( $host_site_id );

		$url = $index_url . '/post/';

		if ( ! empty( $site_id ) && $site_id > 1 ) {
			$url .= (int) $site_id . 'ms' . (int) $post_id;
		} else {
			$url .= (int) $post_id;
		}

		$request = wp_remote_request( $url, array( 'method' => 'GET' ) );

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
	 * Ping the server to ensure the Elasticsearch server is operating and the index exists
	 *
	 * @param int $site_id
	 * @since 0.1.1
	 * @return bool
	 */
	public function is_alive( $site_id = 0 ) {
		// If we've already determined what our connection is, we can finish early!
		if ( isset( $this->is_alive[ $site_id ] ) ) {
			return $this->is_alive[ $site_id ];
		}

		// Otherwise, let's proceed with the check
		$is_alive = false;

		// Get main site options which are stored in location 0
		$index_url = ep_get_index_url( $site_id );

		$url = $index_url . '/_status';

		$request = wp_remote_request( $url );

		if ( ! is_wp_error( $request ) ) {
			if ( isset( $request['response']['code'] ) && 200 === $request['response']['code'] ) {
				$is_alive = true;
			}
		}

		// Return our status and cache it
		return $this->is_alive[ $site_id ] = $is_alive;
	}
}

EP_API::factory();

/**
 * Accessor functions for methods in above class. See doc blocks above for function details.
 */

function ep_index_post( $post, $site_id = null ) {
	return EP_API::factory()->index_post( $post, $site_id );
}

function ep_search( $args, $site_id = null ) {
	return EP_API::factory()->search( $args, $site_id );
}

function ep_post_indexed( $post_id, $site_id = null, $host_site_id = null ) {
	return EP_API::factory()->post_indexed( $post_id, $site_id, $host_site_id );
}

function ep_delete_post( $post_id, $site_id = null, $host_site_id = null ) {
	return EP_API::factory()->delete_post( $post_id, $site_id, $host_site_id );
}

function ep_is_alive( $site_id ) {
	return EP_API::factory()->is_alive( $site_id );
}