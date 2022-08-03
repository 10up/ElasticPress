<?php
/**
 * Taxonomy facet type
 *
 * @since 4.3.0
 * @package elasticpress
 */

namespace ElasticPress\Feature\Facets\Types\Taxonomy;

use \ElasticPress\Features;
use \ElasticPress\Indexables;

/**
 * Taxonomy facet type class
 */
class FacetType {

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
		add_filter( 'ep_post_formatted_args', [ $this, 'set_agg_filters' ], 10, 3 );
		add_action( 'pre_get_posts', [ $this, 'facet_query' ] );

		$this->block = new Block();
		$this->block->setup();
	}

	/**
	 * If we are doing or matches, we need to remove filters from aggs
	 *
	 * @param  array    $args ES arguments
	 * @param  array    $query_args Query arguments
	 * @param  WP_Query $query WP Query instance
	 * @return array
	 */
	public function set_agg_filters( $args, $query_args, $query ) {
		// Not a facetable query
		if ( empty( $query_args['ep_facet'] ) ) {
			return $args;
		}

		// Without taxonomies there is nothing to do here.
		if ( empty( $query_args['tax_query'] ) ) {
			return $args;
		}

		$feature  = Features::factory()->get_registered_feature( 'facets' );
		$settings = wp_parse_args(
			$feature->get_settings(),
			array(
				'match_type' => 'all',
			)
		);

		$facet_query_args = $query_args;

		if ( 'any' === $settings['match_type'] ) {
			foreach ( $facet_query_args['tax_query'] as $key => $taxonomy ) {
				if ( is_array( $taxonomy ) ) {
					unset( $facet_query_args['tax_query'][ $key ] );
				}
			}
		}

		// @todo For some reason these are appearing in the query args, need to investigate
		$unwanted_args = [ 'category_name', 'cat', 'tag', 'tag_id', 'taxonomy', 'term' ];
		foreach ( $unwanted_args as $unwanted_arg ) {
			unset( $facet_query_args[ $unwanted_arg ] );
		}

		remove_filter( 'ep_post_formatted_args', [ $this, 'set_agg_filters' ], 10, 3 );
		$facet_formatted_args = Indexables::factory()->get( 'post' )->format_args( $facet_query_args, $query );
		add_filter( 'ep_post_formatted_args', [ $this, 'set_agg_filters' ], 10, 3 );

		$args['aggs']['terms']['filter'] = $facet_formatted_args['post_filter'];

		return $args;
	}

	/**
	 * Get currently selected facets from query args
	 *
	 * @return array
	 */
	public function get_selected() {
		$feature = Features::factory()->get_registered_feature( 'facets' );

		$filters = array(
			'taxonomies' => [],
		);

		$allowed_args = $feature->get_allowed_query_args();
		$filter_name  = $this->get_filter_name();

		$escaped_get_keys = array_map( 'sanitize_key', array_keys( $_GET ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$escaped_get_keys = array_map( 'sanitize_key', array_keys( $_GET ) ); // phpcs:ignore WordPress.Security.NonceVerification
		foreach ( $_GET as $key => $value ) { // phpcs:ignore WordPress.Security.NonceVerification
			if ( 0 === strpos( $key, $filter_name ) ) {
				$taxonomy = str_replace( $filter_name, '', $key );

				$filters['taxonomies'][ $taxonomy ] = array(
					'terms' => array_fill_keys( array_map( 'trim', explode( ',', trim( $value, ',' ) ) ), true ),
				);
			}

			if ( in_array( $key, $allowed_args, true ) ) {
				$filters[ $key ] = $value;
			}
		}

		return $filters;
	}

	/**
	 * Build query url
	 *
	 * @param  array $filters Facet filters
	 * @return string
	 */
	public function build_query_url( $filters ) {
		$query_param = array();

		if ( ! empty( $filters['taxonomies'] ) ) {
			$tax_filters = $filters['taxonomies'];

			foreach ( $tax_filters as $taxonomy => $filter ) {
				if ( ! empty( $filter['terms'] ) ) {
					$query_param[ $this->get_filter_name() . $taxonomy ] = implode( ',', array_keys( $filter['terms'] ) );
				}
			}
		}

		$feature      = Features::factory()->get_registered_feature( 'facets' );
		$allowed_args = $feature->get_allowed_query_args();

		if ( ! empty( $filters ) ) {
			foreach ( $filters as $filter => $value ) {
				if ( ! empty( $value ) && in_array( $filter, $allowed_args, true ) ) {
					$query_param[ $filter ] = $value;
				}
			}
		}

		$query_string = http_build_query( $query_param );

		/**
		 * Filter facet query string
		 *
		 * @hook ep_facet_query_string
		 * @param  {string} $query_string Current query string
		 * @param  {array} $query_param Query parameters
		 * @return  {string} New query string
		 */
		$query_string = apply_filters( 'ep_facet_query_string', $query_string, $query_param );

		$url        = $_SERVER['REQUEST_URI'];
		$pagination = strpos( $url, '/page' );
		if ( false !== $pagination ) {
			$url = substr( $url, 0, $pagination );
		}

		return strtok( trailingslashit( $url ), '?' ) . ( ( ! empty( $query_string ) ) ? '?' . $query_string : '' );
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
	protected function get_filter_name() {
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

		$taxonomies = get_taxonomies( array( 'public' => true ), 'object' );

		/**
		 * Filter taxonomies made available for faceting
		 *
		 * @hook ep_facet_include_taxonomies
		 * @param  {array} $taxonomies Taxonomies
		 * @return  {array} New taxonomies
		 */
		$taxonomies = apply_filters( 'ep_facet_include_taxonomies', $taxonomies );

		if ( empty( $taxonomies ) ) {
			return;
		}

		$query->set( 'ep_integrate', true );
		$query->set( 'ep_facet', true );

		$facets = [];

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
		 * @since 3.6.0
		 * @hook ep_facet_use_field
		 * @param  {string} $field The term field to use
		 * @return  {string} The chosen term field
		 */
		$facet_field = apply_filters( 'ep_facet_use_field', 'slug' );

		foreach ( $taxonomies as $slug => $taxonomy ) {
			$facets[ $slug ] = array(
				'terms' => array(
					'size'  => apply_filters( 'ep_facet_taxonomies_size', 10000, $taxonomy ),
					'field' => 'terms.' . $slug . '.' . $facet_field,
				),
			);
		}

		$aggs = array(
			'name'       => 'terms',
			'use-filter' => true,
			'aggs'       => $facets,
		);

		$query->set( 'aggs', $aggs );

		$selected_filters = $this->get_selected();

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
}
