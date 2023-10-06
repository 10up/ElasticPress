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
		add_filter( 'ep_facet_query_filters', [ $this, 'add_query_filters' ] );
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
		_doing_it_wrong(
			__METHOD__,
			esc_html( 'Aggregation filters related to facet types are now managed by the main Facets class.' ),
			'ElasticPress 4.4.0'
		);

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
	 * Get the facet sanitize function.
	 *
	 * @return string The function name.
	 */
	public function get_sanitize_callback() : string {

		/**
		 * Filter the facet filter sanitize callback.
		 *
		 * @hook ep_facet_meta_sanitize_callback
		 * @since 4.4.0
		 * @param   {string} Facet filter sanitize callback
		 * @return  {string} New facet filter sanitize callback
		 */
		return apply_filters( 'ep_facet_sanitize_callback', 'sanitize_title' );
	}

	/**
	 * Get all taxonomies that could be selected for a facet.
	 *
	 * @return array
	 */
	public function get_facetable_taxonomies() {
		$taxonomies = get_taxonomies(
			array(
				'public'  => true,
				'show_ui' => true,
			),
			'object'
		);

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
	 * DEPRECATED. We enable ElasticPress facet on all archive/search queries as well as non-static home pages. There is no way to know
	 * when a facet widget is used before the main query is executed so we enable EP
	 * everywhere where a facet widget could be used.
	 *
	 * @param  WP_Query $query WP Query
	 */
	public function facet_query( $query ) {
		_doing_it_wrong(
			__METHOD__,
			esc_html( 'Facet selections are now applied directly to the ES Query.' ),
			'ElasticPress 4.4.0'
		);

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

		/** This filter is documented below */
		$special_taxonomies = apply_filters( 'ep_facet_tax_special_slug_taxonomies', [], $selected_filters );

		foreach ( $selected_filters['taxonomies'] as $taxonomy => $filter ) {
			$tax_query[] = [
				'taxonomy' => $special_taxonomies[ $taxonomy ] ?? $taxonomy,
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
	 * Add selected filters to the Facet filter in the ES query
	 *
	 * @since 4.4.0
	 * @param array $filters Current Facet filters
	 * @return array
	 */
	public function add_query_filters( $filters ) {
		$feature = Features::factory()->get_registered_feature( 'facets' );

		$taxonomies = $this->get_facetable_taxonomies();
		if ( empty( $taxonomies ) ) {
			return;
		}

		$selected_filters = $feature->get_selected();

		if ( empty( $selected_filters ) || empty( $selected_filters[ $this->get_filter_type() ] ) ) {
			return;
		}

		/**
		 * Filter for treatment special slugs in taxonomies. This is used in case you need to change the default taxonomy slug.
		 *
		 * @since 4.7.0
		 * @hook ep_facet_tax_special_slug_taxonomies
		 * @param  {array} $special_taxonomies Taxonomies with special slugs.
		 * @param  {array} $selected_filters Selected filters.
		 * @return {array} New taxonomies with special slugs.
		 */
		$special_taxonomies = apply_filters( 'ep_facet_tax_special_slug_taxonomies', [], $selected_filters );

		$match_type = $feature->get_match_type();

		foreach ( $selected_filters['taxonomies'] as $taxonomy => $filter ) {

			$taxonomy_slug = $special_taxonomies[ $taxonomy ] ?? $taxonomy;

			if ( 'any' === $match_type ) {
				$filters[] = [
					'terms' => [
						'terms.' . $taxonomy_slug . '.slug' => array_keys( $filter['terms'] ),
					],
				];
			} else {
				foreach ( $filter['terms'] as $term_slug => $term ) {
					$filters[] = [
						'term' => [
							'terms.' . $taxonomy_slug . '.slug' => $term_slug,
						],
					];
				}
			}
		}

		return $filters;
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
