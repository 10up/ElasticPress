<?php
/**
 * Taxonomy facet type
 *
 * @since 4.3.0
 * @package elasticpress
 */

namespace ElasticPress\Feature\Facets\Types\Taxonomy;

use \ElasticPress\Features;

/**
 * Taxonomy facet type class
 */
class FacetType extends \ElasticPress\Feature\Facets\FacetType {

	/**
	 * Block instance.
	 *
	 * @var Block
	 */
	public $block;

	/**
	 * Setup hooks and filters for feature
	 */
	public function setup() {
		add_action( 'widgets_init', [ $this, 'register_widgets' ] );
		add_filter( 'ep_facet_agg_filters', [ $this, 'agg_filters' ] );
		add_action( 'pre_get_posts', [ $this, 'facet_query' ] );
		add_filter( 'ep_facet_wp_query_aggs_facet', [ $this, 'set_wp_query_aggs' ] );

		$this->block = new Block();
		$this->block->setup();
	}

	/**
	 * If we are doing or matches, we need to remove filters from aggs
	 *
	 * @param  array $query_args Query arguments
	 * @return array
	 */
	public function agg_filters( $query_args ) {
		// Without taxonomies there is nothing to do here.
		if ( empty( $query_args['tax_query'] ) ) {
			return $query_args;
		}

		$feature  = Features::factory()->get_registered_feature( 'facets' );
		$settings = wp_parse_args(
			$feature->get_settings(),
			array(
				'match_type' => 'all',
			)
		);

		if ( 'any' === $settings['match_type'] ) {
			foreach ( $query_args['tax_query'] as $key => $taxonomy ) {
				if ( is_array( $taxonomy ) ) {
					unset( $query_args['tax_query'][ $key ] );
				}
			}
		}

		// @todo For some reason these are appearing in the query args, need to investigate
		$unwanted_args = [ 'category_name', 'cat', 'tag', 'tag_id', 'taxonomy', 'term' ];
		foreach ( $unwanted_args as $unwanted_arg ) {
			unset( $query_args[ $unwanted_arg ] );
		}

		return $query_args;
	}

	/**
	 * Register facet widget(s)
	 */
	public function register_widgets() {
		register_widget( __NAMESPACE__ . '\Widget' );
	}

	/**
	 * Get the facet filter name.
	 *
	 * @return string The filter name.
	 */
	public function get_filter_name() : string {
		/**
		 * Filter the facet filter name that's added to the URL
		 *
		 * @hook ep_facet_filter_name
		 * @since 4.0.0
		 * @param   {string} Facet filter name
		 * @return  {string} New facet filter name
		 */
		return apply_filters( 'ep_facet_filter_name', 'ep_filter_' );
	}

	/**
	 * Get the facet filter type.
	 *
	 * @return string The filter name.
	 */
	public function get_filter_type() : string {
		/**
		 * Filter the facet filter type. Used by the Facet feature to organize filters.
		 *
		 * @hook ep_facet_filter_type
		 * @since 4.3.0
		 * @param   {string} Facet filter type
		 * @return  {string} New facet filter type
		 */
		return apply_filters( 'ep_facet_filter_type', 'taxonomies' );
	}

	/**
	 * Get all taxonomies that could be selected for a facet.
	 *
	 * @return array
	 */
	public function get_facetable_taxonomies() {
		$taxonomies = get_taxonomies( array( 'public' => true ), 'object' );
		/**
		 * Filter taxonomies made available for faceting
		 *
		 * @hook ep_facet_include_taxonomies
		 * @param  {array} $taxonomies Taxonomies
		 * @return  {array} New taxonomies
		 */
		return apply_filters( 'ep_facet_include_taxonomies', $taxonomies );
	}

	/**
	 * We enable ElasticPress facet on all archive/search queries as well as non-static home pages. There is no way to know
	 * when a facet widget is used before the main query is executed so we enable EP
	 * everywhere where a facet widget could be used.
	 *
	 * @param  WP_Query $query WP Query
	 */
	public function facet_query( $query ) {
		$feature = Features::factory()->get_registered_feature( 'facets' );

		if ( ! $feature->is_facetable( $query ) ) {
			return;
		}

		$taxonomies = $this->get_facetable_taxonomies();

		if ( empty( $taxonomies ) ) {
			return;
		}

		$selected_filters = $feature->get_selected();

		$settings = $feature->get_settings();

		$settings = wp_parse_args(
			$settings,
			array(
				'match_type' => 'all',
			)
		);

		$tax_query = $query->get( 'tax_query', [] );

		// Account for taxonomies that should be woocommerce attributes, if WC is enabled
		$attribute_taxonomies = [];
		if ( function_exists( 'wc_attribute_taxonomy_name' ) ) {
			$all_attr_taxonomies = wc_get_attribute_taxonomies();

			foreach ( $all_attr_taxonomies as $attr_taxonomy ) {
				$attribute_taxonomies[ $attr_taxonomy->attribute_name ] = wc_attribute_taxonomy_name( $attr_taxonomy->attribute_name );
			}
		}

		foreach ( $selected_filters['taxonomies'] as $taxonomy => $filter ) {
			$tax_query[] = [
				'taxonomy' => isset( $attribute_taxonomies[ $taxonomy ] ) ? $attribute_taxonomies[ $taxonomy ] : $taxonomy,
				'field'    => 'slug',
				'terms'    => array_keys( $filter['terms'] ),
				'operator' => ( 'any' === $settings['match_type'] ) ? 'or' : 'and',
			];
		}

		if ( ! empty( $selected_filters['taxonomies'] ) && 'any' === $settings['match_type'] ) {
			$tax_query['relation'] = 'or';
		}

		$query->set( 'tax_query', $tax_query );
	}

	/**
	 * Add taxonomies to facets aggs
	 *
	 * @param array $facet_aggs Facet Aggs array.
	 * @since 4.3.0
	 * @return array
	 */
	public function set_wp_query_aggs( $facet_aggs ) {
		$taxonomies = $this->get_facetable_taxonomies();

		if ( empty( $taxonomies ) ) {
			return $facet_aggs;
		}

		foreach ( $taxonomies as $slug => $taxonomy ) {
			/**
			 * Retrieve aggregations based on a custom field. This field must exist on the mapping.
			 * Values available out-of-the-box are:
			 *  - slug (default)
			 *  - term_id
			 *  - name
			 *  - parent
			 *  - term_taxonomy_id
			 *  - term_order
			 *  - facet (retrieves a JSON representation of the term object)
			 *
			 * @since 3.6.0, 4.3.0 added $taxonomy
			 * @hook ep_facet_use_field
			 * @param  {string}      $field    The term field to use
			 * @param  {WP_Taxonomy} $taxonomy The taxonomy
			 * @return  {string} The chosen term field
			 */
			$facet_field = apply_filters( 'ep_facet_use_field', 'slug', $taxonomy );

			$facet_aggs[ $slug ] = array(
				'terms' => array(
					'size'  => apply_filters( 'ep_facet_taxonomies_size', 10000, $taxonomy ),
					'field' => 'terms.' . $slug . '.' . $facet_field,
				),
			);
		}

		return $facet_aggs;
	}
}
