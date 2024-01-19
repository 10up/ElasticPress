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

	/**
	 * Format selected values.
	 *
	 * @since 4.5.0
	 * @param string $facet   Facet name
	 * @param mixed  $value   Facet value
	 * @param array  $filters Selected filters
	 * @return array
	 */
	public function format_selected( string $facet, $value, array $filters ) {
		$terms = explode( ',', trim( $value, ',' ) );
		$filters[ $this->get_filter_type() ][ $facet ] = [
			'terms' => array_fill_keys( array_map( $this->get_sanitize_callback(), $terms ), true ),
		];
		return $filters;
	}

	/**
	 * Add selected filters to the query string.
	 *
	 * @since 4.5.0
	 * @param array $query_params Existent query parameters
	 * @param array $filters      Selected filters
	 * @return array
	 */
	public function add_query_params( array $query_params, array $filters ) : array {
		$selected = $filters[ $this->get_filter_type() ];

		foreach ( $selected as $facet => $filter ) {
			if ( ! empty( $filter['terms'] ) ) {
				$query_params[ $this->get_filter_name() . $facet ] = implode( ',', array_keys( $filter['terms'] ) );
			}
		}

		return $query_params;
	}

	/**
	 * Get all facet fields selected across all blocks of the current facet type.
	 *
	 * Given a block name, e.g., `elasticpress/facet-meta` this method returns all meta fields
	 * selected in all blocks.
	 *
	 * @param string $block_name The block name
	 * @return array
	 */
	protected function block_template_meta_fields( string $block_name ) : array {
		$block_template_utils = \ElasticPress\get_container()->get( '\ElasticPress\BlockTemplateUtils' );
		$ep_blocks            = $block_template_utils->get_specific_block_in_all_templates( $block_name );

		return array_filter(
			array_reduce(
				$ep_blocks,
				function( $acc, $block ) use ( $block_name ) {
					if ( 0 !== strpos( $block['blockName'], $block_name ) ) {
						return $acc;
					}

					if ( empty( $block['attrs']['facet'] ) ) {
						return $acc;
					}

					$acc[] = $block['attrs']['facet'];
					return $acc;
				},
				[]
			)
		);
	}
}
