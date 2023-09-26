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
	 * Array of active indexables
	 *
	 * @var   array
	 * @since 4.5.0
	 */
	private $active_indexables = [];

	/**
	 * Register an indexable instance
	 *
	 * @param Indexable $indexable Instance of Indexable
	 * @param bool      $activate  If the indexable should also be activated. Defaults to true.
	 * @since 3.0, 4.5.0 added $activate
	 */
	public function register( Indexable $indexable, bool $activate = true ) {
		$this->registered_indexables[ $indexable->slug ] = $indexable;
		if ( $activate ) {
			$this->active_indexables[ $indexable->slug ] = $indexable;
		}
	}

	/**
	 * Unregister an indexable instance
	 *
	 * @param  string $slug Indexable type slug.
	 * @since 4.4.1
	 */
	public function unregister( $slug ) {
		unset( $this->active_indexables[ $slug ] );
		unset( $this->registered_indexables[ $slug ] );
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
	 * Activate an indexable functionality
	 *
	 * @param string $slug The indexable slug
	 * @since 4.5.0
	 */
	public function activate( string $slug ) {
		$indexable = $this->get( $slug );
		if ( $indexable ) {
			$this->active_indexables[ $indexable->slug ] = $indexable;
			if ( method_exists( $indexable, 'setup' ) ) {
				$indexable->setup();
			}
		}
	}

	/**
	 * Deactivate an indexable
	 *
	 * @param string $slug The indexable slug
	 * @since 4.5.0
	 */
	public function deactivate( string $slug ) {
		unset( $this->active_indexables[ $slug ] );
	}

	/**
	 * Deactivate all indexables
	 *
	 * @since 4.5.0
	 */
	public function deactivate_all() {
		$this->active_indexables = [];
	}

	/**
	 * Return whether an Indexable is active or not.
	 *
	 * @since 4.5.0
	 * @param string $slug Indexable slug
	 * @return boolean
	 */
	public function is_active( string $slug ) : bool {
		return ! empty( $this->active_indexables[ $slug ] );
	}

	/**
	 * Get all indexable instances
	 *
	 * @param  boolean $global    If true or false, will only get Indexables with that global property.
	 * @param  boolean $slug_only True returns an array of only string slugs.
	 * @param  string  $status    Whether to return active indexables or all registered.
	 * @since  3.0, 4.5.0 Added $status
	 * @return array
	 */
	public function get_all( $global = null, $slug_only = false, $status = 'active' ) {
		$indexables = [];
		$list       = ( 'active' === $status ) ? $this->active_indexables : $this->registered_indexables;

		foreach ( $list as $slug => $indexable ) {
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
	 * @return self
	 */
	public static function factory() {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new self();
		}

		return $instance;
	}
}
