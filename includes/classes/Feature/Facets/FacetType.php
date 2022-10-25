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

	/**
	 * Get the facet sanitize function.
	 *
	 * @return string The function name.
	 */
	public function get_sanitize_callback() : string {

		/**
		 * Filter the facet filter sanitize callback.
		 *
		 * @hook ep_facet_default_sanitize_callback
		 * @since 4.4.0
		 * @param   {string} Facet filter sanitize callback
		 * @return  {string} New facet filter sanitize callback
		 */
		return apply_filters( 'ep_facet_default_sanitize_callback', 'sanitize_text_field' );
	}

}
