<?php
/**
 * Class for Term factory.
 *
 * @package  elasticpress
 */

namespace ElasticPressTest;

use ElasticPress;

/**
 * Unit test factory for the term.
 *
 * @since 4.4.0
 */
class TermFactory extends \WP_UnitTest_Factory_For_Term {

	/**
	 * Creates a term object.
	 *
	 * @param array $args Array or string of arguments for inserting a term.
	 * @return Int|WP_Error
	 */
	public function create_object( $args ) {

		$term_id = parent::create_object( $args );

		if ( is_wp_error( $term_id ) ) {
			return $term_id;
		}

		ElasticPress\Indexables::factory()->get( 'term' )->index( $term_id, true );

		return $term_id;
	}

}
