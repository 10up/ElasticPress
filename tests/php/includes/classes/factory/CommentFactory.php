<?php
/**
 * Class for comment factory.
 *
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress;

/**
 * Unit test factory for the comment.
 *
 * @since 4.4.0
 */
class CommentFactory extends \WP_UnitTest_Factory_For_Comment {

	/**
	 * Inserts a comment.
	 *
	 * @param array $args The comment details.
	 *
	 * @return int|false The comment's ID on success, false on failure.
	 */
	public function create_object( $args ) {
		$id = wp_insert_comment( $this->addslashes_deep( $args ) );

		ElasticPress\Indexables::factory()->get( 'comment' )->index( $id );

		return $id;
	}

}
