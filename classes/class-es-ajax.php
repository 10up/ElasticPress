<?php

class ES_AJAX {

	public function __construct() {
		add_action( 'wp_ajax_get_post_types', array( $this, 'action_get_post_types' ) );
	}

	/**
	 * Return post types for current blog
	 *
	 * @since 0.1.0
	 */
	public function action_get_post_types() {
		$output = array();
		$output['success'] = false;

		if ( check_ajax_referer( 'es_post_types_nonce', 'nonce', false ) ) {
			$output['post_types'] = get_post_types( '', 'names' );
			$output['success'] = true;
		}

		wp_send_json( $output );
	}

	/**
	 * Return singleton instance of class
	 *
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