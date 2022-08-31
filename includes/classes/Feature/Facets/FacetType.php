<?php
/**
 * Taxonomy facet type
 *
 * @since 4.3.0
 * @package elasticpress
 */

namespace ElasticPress\Feature\Facets;

/**
 * Abstract Facet type class
 *
 * Keeping this as an abstract class (and not an interface) to make
 * the implementation of common methods easier.
 */
abstract class FacetType {

	/**
	 * Setup hooks and filters for feature
	 */
	abstract public function setup();

	/**
	 * Get the facet filter name.
	 *
	 * @return string The filter name.
	 */
	abstract public function get_filter_name() : string;

	/**
	 * Get the facet filter type.
	 *
	 * @return string The filter name.
	 */
	abstract public function get_filter_type() : string;
}
