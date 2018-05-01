<?php
/**
 * Indexable loader
 *
 * @since  2.1
 * @package elasticpress
 */

namespace ElasticPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Indexables {

	private $registered_indexables = [];

	public function register( Indexable $indexable ) {
		$this->registered_indexables[ $indexable->indexable_type ] = $indexable;
	}

	public function get( $slug = null ) {
		if ( null === $slug ) {
			return $this->registered_indexables;
		}

		return $this->registered_indexables[ $slug ];
	}

	/**
	 * Return singleton instance of class
	 *
	 * @return object
	 */
	public static function factory() {
		static $instance = false;

		if ( ! $instance  ) {
			$instance = new self();
		}

		return $instance;
	}
}
