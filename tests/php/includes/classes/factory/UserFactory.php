<?php
/**
 * Class for User factory.
 *
 * @package  elasticpress
 */

namespace ElasticPressTest;

use ElasticPress;

/**
 * Unit test factory for the user.
 *
 * @since 4.4.0
 */
class UserFactory extends \WP_UnitTest_Factory_For_User {

	/**
	 * Inserts an user.
	 *
	 * @param array $args The user data to insert.
	 * @package array $meta The array of user meta to insert.
	 *
	 * @return int|WP_Error The user ID on success, WP_Error object on failure.
	 */
	public function create_object( $args ) {

		$user_id = parent::create_object( $args );

		if ( ! $user_id || is_wp_error( $user_id ) ) {
			return $user_id;
		}

		ElasticPress\Indexables::factory()->get( 'user' )->index( $user_id, true );
		return $user_id;
	}
}
