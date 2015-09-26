<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // exit if accessed directly
}

class EP_User_API {

	/** @var EP_API */
	protected $api;

	/**
	 * Set up object
	 *
	 * @param EP_API $api
	 */
	public function __construct( $api = null ) {
		if ( ! $api instanceof EP_API ) {
			$api = EP_API::factory();
		}
		$this->api = $api;
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
