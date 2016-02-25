<?php

/**
 * Registers a WP-API endpoint to fetch post_types.
 *
 * @package elasticpress
 *
 * @since   1.9
 *
 */
class EP_Endpoint {

	/**
	 * Adds action to register endpoint routes (if WP-API is installed or WP 4.4 +.
	 * 
	 * @since 1.9
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Registers the WP-API routes.
	 * 
	 * Example: http://example.org/wp-json/elasticpress/v1/posttypes/
	 * 
	 * @since 1.9
	 * 
	 */
	function register_routes() {
		register_rest_route( 'elasticpress/v1', '/posttypes', array(
			'methods'	 => 'GET',
			'callback'	 => array( $this, 'post_types' ),
		) );
	}

	/**
	 * Fetches indexable post_types.
	 * 
	 * @since 1.9
	 * 
	 * @url https://github.com/10up/ElasticPress/issues/334
	 * 
	 * @return array
	 */
	function post_types() {
		return ep_get_indexable_post_types();
	}

}

new EP_Endpoint();
