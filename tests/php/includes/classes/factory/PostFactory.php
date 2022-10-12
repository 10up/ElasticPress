<?php
/**
 * Class for Post factory.
 *
 * @package  elasticpress
 */

namespace ElasticPressTest;

use ElasticPress;

/**
 * Unit test factory for the post.
 *
 * @since 4.4.0
 */
class PostFactory extends \WP_UnitTest_Factory_For_Post {

	/**
	 * Creates a post object.
	 *
	 * @param array $args Array with elements for the post.
	 *
	 * @return int The post ID on success. The value 0 on failure.
	 */
	public function create_object( $args ) {

		$post_id = parent::create_object( $args );

		if ( ! $post_id || is_wp_error( $post_id ) ) {
			return $post_id;
		}

		ElasticPress\Indexables::factory()->get( 'post' )->index( $post_id );
		return $post_id;
	}

}
