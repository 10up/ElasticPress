<?php

class ES_AJAX {

	/**
	 * Setup actions and filters
	 *
	 * @since 0.1.0
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'setup_rewrites' ) );
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
		$vars[] = 'es_query';
		return $vars;
	}

	/**
	 * Setup rewrite rules for front end AJAX endpoints
	 *
	 * @since 0.1.0
	 */
	public function setup_rewrites() {
		add_rewrite_rule( '^es-post-types/?','index.php?es_query=post_types','top' );
	}

	/**
	 * Return post types for current blog
	 *
	 * @since 0.1.0
	 */
	public function action_get_post_types() {
		$es_query = get_query_var('es_query');

		if ( $es_query == 'post_types' ) {
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
	 * @return ES_AJAX
	 */
	public static function factory() {
		static $instance = false;

		if ( ! $instance  ) {
			$instance = new self();
		}

		return $instance;
	}
}

ES_AJAX::factory();