<?php
/**
 * Post Type facet type
 *
 * @since 4.6.0
 * @package elasticpress
 */

namespace ElasticPress\Feature\Facets\Types\PostType;

use \ElasticPress\Features;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Post Type facet type class
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
		add_filter( 'ep_facet_query_filters', [ $this, 'add_query_filters' ] );
		add_filter( 'ep_facet_wp_query_aggs_facet', [ $this, 'set_wp_query_aggs' ] );

		$this->block = new Block();
		$this->block->setup();
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
		 * @hook ep_facet_post_type_filter_name
		 * @since 4.6.0
		 * @param   {string} Facet filter name
		 * @return  {string} New facet filter name
		 */
		return apply_filters( 'ep_facet_post_type_filter_name', 'ep_post_type_filter' );
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
		 * Note: Do not set is as `post_type`, as it will conflict with the post_type query parameter if set.
		 *
		 * @hook ep_facet_post_type_filter_type
		 * @since 4.6.0
		 * @param   {string} Facet filter type
		 * @return  {string} New facet filter type
		 */
		return apply_filters( 'ep_facet_post_type_filter_type', 'ep_post_type' );
	}

	/**
	 * Add post type fields to facets aggs
	 *
	 * @param array $facet_aggs Facet Aggs array.
	 * @return array
	 */
	public function set_wp_query_aggs( $facet_aggs ) {
		$post_types = $this->get_facetable_post_types();

		if ( empty( $post_types ) ) {
			return $facet_aggs;
		}

		$facet_aggs['post_type'] = array(
			'terms' => array(
				/**
				 * Filter the number of different values (and their count) for post types returned by Elasticsearch.
				 *
				 * @since 4.6.0
				 * @hook ep_facet_post_type_size
				 * @param {int}    $size       The number of different values. Default: 10000
				 * @param {array} $post_types Post types
				 * @return {int} The new number of different values
				 */
				'size'  => apply_filters( 'ep_facet_post_type_size', 10000, $post_types ),
				'field' => 'post_type.raw',
			),
		);

		return $facet_aggs;
	}

	/**
	 * Add selected filters to the Facet filter in the ES query
	 *
	 * @param array $filters Current Facet filters
	 * @return array
	 */
	public function add_query_filters( $filters ) {
		if ( ! empty( $filters['terms']['post_type.raw'] ) ) {
			return $filters;
		}

		$feature = Features::factory()->get_registered_feature( 'facets' );

		$post_types = $this->get_facetable_post_types();

		if ( empty( $post_types ) ) {
			return $filters;
		}

		$selected_filters = $feature->get_selected();
		if ( empty( $selected_filters ) || empty( $selected_filters[ $this->get_filter_type() ] ) ) {
			return $filters;
		}

		/**
		 * As there is no content with two post types,
		 * if we have a list of types we want *any* content that matches *any* type.
		 */
		$filters[] = [
			'terms' => [
				'post_type.raw' => array_keys( $selected_filters[ $this->get_filter_type() ]['terms'] ),
			],
		];

		return $filters;
	}

	/**
	 * Get the post types that are facetable.
	 *
	 * @return array Array of post types.
	 */
	public function get_facetable_post_types() {
		$searchable_post_types = \ElasticPress\Features::factory()->get_registered_feature( 'search' )->get_searchable_post_types();

		/**
		 * Filter post types that are facetable.
		 *
		 * @since 4.6.0
		 * @hook ep_facetable_post_types
		 * @param {array} $searchable_post_types Array of searchable post types.
		 * @return {array} The array of facetable post types.
		 */
		return apply_filters( 'ep_facetable_post_types', $searchable_post_types );
	}

	/**
	 * Format selected values.
	 *
	 * @param string $facet   Facet name
	 * @param mixed  $value   Facet value
	 * @param array  $filters Selected filters
	 * @return array
	 */
	public function format_selected( string $facet, $value, array $filters ) {
		$terms = explode( ',', trim( $value, ',' ) );

		$filters[ $this->get_filter_type() ] = [
			'terms' => array_fill_keys( array_map( $this->get_sanitize_callback(), $terms ), true ),
		];

		return $filters;
	}

	/**
	 * Add selected filters to the query string.
	 *
	 * @param array $query_params Existent query parameters
	 * @param array $filters      Selected filters
	 * @return array
	 */
	public function add_query_params( array $query_params, array $filters ) : array {
		$selected = $filters[ $this->get_filter_type() ] ?? [];

		if ( ! empty( $selected['terms'] ) ) {
			$query_params[ $this->get_filter_name() ] = implode( ',', array_keys( $selected['terms'] ) );
		}

		return $query_params;
	}
}
