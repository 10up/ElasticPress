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
		add_filter( 'ep_facet_query_filters', [ $this, 'add_query_filters' ], 9999 );
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
		return apply_filters( 'ep_facet_post_type_filter_name', 'ep_post_type_filter_' );
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
	 * @since 4.6.0
	 * @return array
	 */
	public function set_wp_query_aggs( $facet_aggs ) {
		$post_types = $this->get_facetable_post_types();

		if ( empty( $post_types ) ) {
			return $facet_aggs;
		}

		$facet_aggs['post_type'] = array(
			'terms' => array(
				'size'  => apply_filters( 'ep_facet_post_type_size', 10000, 'post_type' ),
				'field' => 'post_type.raw',
			),
		);

		return $facet_aggs;
	}

	/**
	 * Add selected filters to the Facet filter in the ES query
	 *
	 * @since 4.6.0
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

		foreach ( $selected_filters['ep_post_type']['post_type']['terms'] as $post_type => $value ) {
			if ( $value ) {
				$filters[0]['terms']['post_type.raw'][] = $post_type;
			}
		}

		return $filters;
	}

	/**
	 * Get the post types that are facetable.
	 *
	 * @return array Array of post types.
	 */
	public function get_facetable_post_types() {
		$indexable_post_types = \ElasticPress\Indexables::factory()->get( 'post' )->get_indexable_post_types();

		/**
		 * Filter post types that are facetable.
		 *
		 * @since 4.6.0
		 * @hook ep_facetable_post_types
		 * @param {array} $indexable_post_types Array of post indexable types.
		 * @return {array} The array of facetable post types.
		 */
		return apply_filters( 'ep_facetable_post_types', $indexable_post_types );
	}
}
