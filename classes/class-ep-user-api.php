<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // exit if accessed directly
}

class EP_User_API {

	/**
	 * Empty constructor
	 */
	public function __construct() {
	}

	/**
	 * Factory method to get singleton
	 *
	 * @return EP_User_API
	 */
	public static function factory() {
		static $instance;
		if ( ! $instance ) {
			$instance = new self();
		}

		return $instance;
	}

}

EP_User_API::factory();
