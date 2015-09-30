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
		$user = $this->get_wp_user( $object );

		if ( ! $user || ! $user->exists() ) {
			return array();
		}

		$user_registered = $user->user_registered;
		if ( apply_filters( 'ep_ignore_invalid_dates', true, $user->ID, $user ) ) {
			if ( ! strtotime( $user_registered ) || $user_registered === '0000-00-00 00:00:00' ) {
				$user_registered = null;
			}
		}

		$data = array(
			'user_id'         => absint( $user->ID ),
			'user_login'      => $user->user_login,
			'user_nicename'   => $user->user_nicename,
			'nickname'        => $user->nickname,
			'user_email'      => $user->user_email,
			'description'     => $user->description,
			'first_name'      => $user->first_name,
			'last_name'       => $user->last_name,
			'user_url'        => $user->user_url,
			'display_name'    => $user->display_name,
			'user_registered' => $user_registered,
			'role'            => $user->roles,
			'terms'           => $this->prepare_terms( $user ),
			'user_meta'       => $this->prepare_meta( $user ),
		);

		return $data;
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

	/**
	 * Get a WordPress user object
	 *
	 * @since 1.7.0
	 *
	 * @param mixed $object
	 *
	 * @return false|WP_User
	 */
	private function get_wp_user( $object ) {
		return get_user_by( 'id', $this->get_object_identifier( $object ) );
	}

}
