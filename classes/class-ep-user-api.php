<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // exit if accessed directly
}

class EP_User_API {

	/** @var EP_API */
	protected $api;

	/**
	 * Factory method to get singleton
	 *
	 * @return EP_User_API
	 */
	public static function factory() {
		static $instance;
		if ( ! $instance ) {
			$instance = new self();
			$instance->setup();
		}

		return $instance;
	}

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
	 * Set this object up
	 *
	 * Actions and filters should get set up here
	 */
	public function setup() {
		if ( ! $this->active() ) {
			return;
		}
		add_filter( 'ep_config_mapping', array( $this, 'add_user_to_mapping' ) );
	}

	/**
	 * Check if user search is active
	 *
	 * User search defaults to being inactive. This cannot be active if the main plugin isn't.
	 *
	 * @return bool
	 */
	public function active() {
		if ( ! defined( 'EP_USER_SEARCH_ACTIVE' ) ) {
			$active = apply_filters( 'ep_user_search_is_active', false );
			define( 'EP_USER_SEARCH_ACTIVE', $active );
		}

		return $this->api->is_activated() && (bool) EP_USER_SEARCH_ACTIVE;
	}

	/**
	 * @param array $mapping
	 *
	 * @return array
	 */
	public function add_user_to_mapping( $mapping ) {
		return $mapping;
	}

}

add_action( 'plugins_loaded', array( 'EP_User_API', 'factory' ) );
