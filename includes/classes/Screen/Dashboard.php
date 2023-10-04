<?php
/**
 * Dashboard screen class.
 *
 * @since 5.0.0
 * @package elasticpress
 */

namespace ElasticPress\Screen;

use ElasticPress\REST;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Dashboard screen.
 *
 * @since 5.0.0
 * @package ElasticPress
 */
class Dashboard {
	/**
	 * Initialize class
	 */
	public function setup() {
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
	}

	/**
	 * Register REST API routes.
	 *
	 * @return void
	 */
	public function register_rest_routes() {
		$controller = new REST\Features();
		$controller->register_routes();
	}
}
