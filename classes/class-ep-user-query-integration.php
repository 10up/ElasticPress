<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class EP_User_Query_Integration {

	/** @var EP_Object_Index */
	private $user_index;

	/**
	 * EP_User_Query_Integration constructor.
	 *
	 * @param EP_Object_Index $user_index
	 */
	public function __construct( $user_index = null ) {
		$this->user_index = $user_index ? $user_index : ep_get_object_type( 'user' );
	}

	public function setup() {
	}

	/**
	 * @return EP_User_Query_Integration|null
	 */
	public static function factory() {
		static $instance;
		if ( $instance ) {
			return $instance;
		}
		$user = ep_get_object_type( 'user' );
		if ( ! $user ) {
			return null;
		}
		if ( false === $instance ) {
			return null;
		}
		if (
			! method_exists( $user, 'active' ) ||
			! $user->active()
		) {
			$instance = false;

			return null;
		}
		$instance = new self;
		$instance->setup();

		return $instance;
	}

}

add_action( 'plugins_loaded', array( 'EP_User_Query_Integration', 'factory' ), 20 );
