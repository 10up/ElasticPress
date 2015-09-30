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
		// TODO: Implement get_settings() method.
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_mappings() {
		// TODO: Implement get_mappings() method.
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

}
