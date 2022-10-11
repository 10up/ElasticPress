<?php
/**
 * Class for category factory.
 */

namespace ElasticPressTest;

use ElasticPress;

/**
 * Unit test factory for the category.
 *
 * @since 4.4.0
 */
class CategoryFactory extends \WP_UnitTest_Factory_For_Term {

	/**
	 * Inserts a comment and "sync" it to Elasticsearch
	 *
	 * @param array $args The category details.
	 *
	 * @return int|false The category's ID on success, false on failure.
	 */
	public function create_object( $args ) {

		$args['taxonomy'] = 'category';
		$id               = parent::create_object( $args );

		ElasticPress\Indexables::factory()->get( 'term' )->index( $id, true );

		return $id;
	}

}
