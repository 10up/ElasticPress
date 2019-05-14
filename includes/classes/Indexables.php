<?php
/**
 * Handles indexable registration and storage
 *
 * @since  3.0
 * @package elasticpress
 */

namespace ElasticPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class for handling all Indexable instances
 */
class Indexables {

	/**
	 * Array of registered indexables
	 *
	 * @var   array
	 * @since 3.0
	 */
	private $registered_indexables = [];

	/**
	 * Register an indexable instance
	 *
	 * @param  Indexable $indexable Instance of Indexable.
	 * @since 3.0
	 */
	public function register( Indexable $indexable ) {
		$this->registered_indexables[ $indexable->slug ] = $indexable;
	}

	/**
	 * Get an indexable instance given a slug
	 *
	 * @param  string $slug Indexable type slug.
	 * @since  3.0
	 * @return Indexable|boolean
	 */
	public function get( $slug ) {
		return ( ! empty( $this->registered_indexables[ $slug ] ) ) ? $this->registered_indexables[ $slug ] : false;
	}

	/**
	 * Get all indexable instances
	 *
	 * @param  boolean $global If true or false, will only get Indexables with that global property.
	 * @param  boolean $slug_only True returns an array of only string slugs.
	 * @since  3.0
	 * @return array
	 */
	public function get_all( $global = null, $slug_only = false ) {
		$indexables = [];

		foreach ( $this->registered_indexables as $slug => $indexable ) {
			if ( null === $global ) {
				if ( $slug_only ) {
					$indexables[] = $slug;
				} else {
					$indexables[] = $indexable;
				}
			} else {
				if ( $global === $indexable->global ) {
					if ( $slug_only ) {
						$indexables[] = $slug;
					} else {
						$indexables[] = $indexable;
					}
				}
			}
		}

		return $indexables;
	}

	/**
	 * Return singleton instance of class
	 *
	 * @return object
	 */
	public static function factory() {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new self();
		}

		return $instance;
	}
}
