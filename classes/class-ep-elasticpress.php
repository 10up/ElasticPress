<?php

 if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class EP_ElasticPress {

	/**
	 * Placeholder method
	 *
	 * @since 0.1.0
	 */
	public function __construct() { }

	/**
	 * Setup actions and filters
	 *
	 * @since 0.1.2
	 */
	public function setup() {

	}

	/**
	 * Localize plugin
	 *
	 * @since 0.1.3
	 * @return void
	 */
	public function action_plugins_loaded() {
		load_plugin_textdomain( 'elasticpress', false, basename( dirname( __FILE__ ) ) . '/lang' );
	}

	/**
	 * Return a singleton instance of the class.
	 *
	 * @return EP_ElasticPress
	 */
	public static function factory() {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new self();
			$instance->setup();
		}

		return $instance;
	}

}

EP_ElasticPress::factory();