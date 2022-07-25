<?php
/**
 * Handles search algorithms registration and storage
 *
 * @since   4.3.0
 * @package elasticpress
 */

namespace ElasticPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class for handling all SearchAlgorithm
 */
class SearchAlgorithms {

	/**
	 * Array of registered search algorithms
	 *
	 * @var array
	 */
	private $registered_search_algorithms = [];

	/**
	 * Register a search algorithm
	 *
	 * @param SearchAlgorithm $search_algorithm Instance of Search Algorithm.
	 */
	public function register( SearchAlgorithm $search_algorithm ) {
		$this->registered_search_algorithms[ $search_algorithm->get_slug() ] = $search_algorithm;
	}

	/**
	 * Get a search algorithm instance given a slug
	 *
	 * @param  string $slug Search Algorithm slug
	 * @return SearchAlgorithm
	 */
	public function get( string $slug ) {
		return ( ! empty( $this->registered_search_algorithms[ $slug ] ) ) ?
			$this->registered_search_algorithms[ $slug ] :
			$this->registered_search_algorithms['basic'];
	}

	/**
	 * Get all search algorithm instances
	 *
	 * @param boolean $slug_only True returns an array of only string slugs.
	 * @return array
	 */
	public function get_all( $slug_only = false ) {
		if ( $slug_only ) {
			return array_keys( $this->registered_search_algorithms );
		}

		return $this->registered_search_algorithms;
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
