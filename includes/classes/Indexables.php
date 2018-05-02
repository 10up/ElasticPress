<?php
/**
 * Handles indexable registration and storage
 *
 * @since  2.6
 * @package elasticpress
 */

namespace ElasticPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Indexables {

	/**
	 * Array of registered indexables
	 *
	 * @var   array
	 * @since 2.6
	 */
	private $registered_indexables = [];

	/**
	 * Register an indexable instance
	 *
	 * @param  Indexable $indexable
	 * @since 2.6
	 */
	public function register( Indexable $indexable ) {
		$this->registered_indexables[ $indexable->indexable_type ] = $indexable;
	}

	/**
	 * Get an indexable instance given a slug
	 *
	 * @param  string $slug
	 * @since  2.6
	 * @return Indexable
	 */
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
