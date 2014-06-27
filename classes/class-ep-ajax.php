<?php

class EP_AJAX {

	/**
	 * Placeholder method
	 *
	 * @since 0.1.0
	 */
	public function __construct() { }

	/**
	 * Setup actions and filters
	 *
	 * @since 0.1.1
	 */
	public function setup() {
		add_filter( 'query_vars', array( $this, 'filter_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'action_get_post_types' ) );
	}

	/**
	 * Setup query vars for front end AJAX endpoints
	 *
	 * @param array $vars
	 * @since 0.1.0
	 * @return array
	 */
	public function filter_query_vars( $vars ){
		$vars[] = 'ep_query';
		return $vars;
	}

	/**
	 * Return post types for current blog
	 *
	 * @since 0.1.0
	 */
	public function action_get_post_types() {
		$ep_query = get_query_var('ep_query');

		if ( $ep_query == 'post_types' ) {
			// Todo: find a better way to do this (WP API!)
			// This probably at the very least needs a nonce to prevent cookie hijacking

			@header( 'Access-Control-Allow-Origin: *' );

			$output = array(
				'post_types' => get_post_types( '', 'names' ),
				'success' => true,
			);

			wp_send_json( $output );
		}
	}

	/**
	 * Return singleton instance of class
	 *
	 * @since 0.1.0
	 * @return EP_AJAX
	 */
	public static function factory() {
		static $instance = false;

		if ( ! $instance  ) {
			$instance = new self();
			$instance->setup();
		}

		return $instance;
	}
}

EP_AJAX::factory();