<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // exit if accessed directly
}

/**
 * Class EP_User_Index
 *
 * @since 1.7.0
 */
class EP_User_Index extends EP_Abstract_Object_Index {

	/**
	 * {@inheritdoc}
	 */
	protected $name = 'user';

	/**
	 * {@inheritdoc}
	 */
	public function sync_setup() {
		// TODO: Implement sync_setup() method.
	}

	/**
	 * {@inheritdoc}
	 */
	public function sync_teardown() {
		// TODO: Implement sync_teardown() method.
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_settings() {
		$user_mapping = require( $this->get_mapping_file() );

		return isset( $user_mapping['settings'] ) ? (array) $user_mapping['settings'] : array();
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_mappings() {
		$user_mapping = require( $this->get_mapping_file() );

		return isset( $user_mapping['mappings']['user'] ) ? (array) $user_mapping['mappings']['user'] : array();
	}

	/**
	 * {@inheritdoc}
	 */
	public function prepare_object( $object ) {
		// TODO: Implement prepare_object() method.
	}

	/**
	 * {@inheritdoc}
	 */
	protected function get_object_identifier( $object ) {
		if ( $object instanceof WP_User ) {
			return (int) $object->ID;
		}
		if ( is_array( $object ) && ! empty( $object['user_id'] ) ) {
			return (int) $object['user_id'];
		}

		return is_numeric( $object ) ? (int) $object : 0;
	}

	/**
	 * Get the location of the user mapping data
	 *
	 * @since 1.7.0
	 *
	 * @return string
	 */
	private function get_mapping_file() {
		$user_mapping_file = apply_filters(
			'ep_config_user_mapping_file',
			dirname( __FILE__ ) . '/../includes/user-mappings.php'
		);

		return $user_mapping_file;
	}

}
