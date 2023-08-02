<?php
/**
 * Meta range facet type
 *
 * @since 4.5.0
 * @package elasticpress
 */

namespace ElasticPress\Feature\Facets\Types\MetaRange;

use \ElasticPress\Features;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Meta facet type class
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
		add_filter( 'ep_facet_query_filters', [ $this, 'add_query_filters' ], 10, 2 );
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
		 * @hook ep_facet_meta_range_filter_name
		 * @since 4.5.0
		 * @param   {string} Facet filter name
		 * @return  {string} New facet filter name
		 */
		return apply_filters( 'ep_facet_meta_range_filter_name', 'ep_meta_range_filter_' );
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
		 * @hook ep_facet_meta_range_filter_type
		 * @since 4.5.0
		 * @param   {string} Facet filter type
		 * @return  {string} New facet filter type
		 */
		return apply_filters( 'ep_facet_meta_range_filter_type', 'meta-range' );
	}

	/**
	 * Add selected filters to the Facet filter in the ES query
	 *
	 * @param array $filters    Current Facet filters
	 * @param array $query_args WP_Query arguments
	 * @return array
	 */
	public function add_query_filters( $filters, $query_args = [] ) {
		/**
		 * We do not want to apply the range filter to the aggregations because then
		 * the minimum and maximum values will be the filter already applied.
		 *
		 * @see https://github.com/10up/ElasticPress/issues/3341
		 */
		if ( ! empty( $query_args['ep_facet_adding_agg_filters'] ) ) {
			return $filters;
		}

		$feature = Features::factory()->get_registered_feature( 'facets' );

		$all_selected_filters = $feature->get_selected();
		if ( empty( $all_selected_filters ) || empty( $all_selected_filters[ $this->get_filter_type() ] ) ) {
			return $filters;
		}

		/**
		 * Filter if EP should only filter by fields selected in facets. Defaults to true.
		 *
		 * @since 4.5.1
		 * @hook ep_facet_should_check_if_allowed
		 * @param {bool} $should_check Whether it should or not check fields
		 * @return {string} New value
		 */
		$should_check_if_allowed = apply_filters( 'ep_facet_should_check_if_allowed', true );
		if ( $should_check_if_allowed ) {
			$allowed_meta_fields = $this->get_facets_meta_fields();

			$selected_range_filters = array_filter(
				$all_selected_filters[ $this->get_filter_type() ],
				function ( $meta_field ) use ( $allowed_meta_fields ) {
					return in_array( $meta_field, $allowed_meta_fields, true );
				},
				ARRAY_FILTER_USE_KEY
			);
		} else {
			$selected_range_filters = $all_selected_filters[ $this->get_filter_type() ];
		}

		$range_filters = [];
		foreach ( $selected_range_filters as $field_name => $values ) {
			foreach ( $values as $min_or_max => $value ) {
				$operator = '_min' === $min_or_max ? 'gte' : 'lte';

				$range_filters[ $field_name ][ $operator ] = floatval( $value );
			}
		}

		foreach ( $range_filters as $field_name => $range_filter ) {
			$filters[] = [
				'range' => [
					'meta.' . $field_name . '.double' => $range_filter,
				],
			];
		}

		return $filters;
	}

	/**
	 * Add meta fields to facets aggs
	 *
	 * @param array $facet_aggs Facet Aggs array.
	 * @return array
	 */
	public function set_wp_query_aggs( $facet_aggs ) {
		$facets_meta_fields = $this->get_facets_meta_fields();

		foreach ( $facets_meta_fields as $meta_field ) {
			/**
			 * Retrieve aggregations based on a custom field. This field must exist on the mapping and be numeric
			 * so ES can apply min and max to it.
			 *
			 * `meta.<field>.value` is *not* available, as that throws a `Fielddata is disabled on text fields by default` error.
			 *
			 * @since 4.5.0
			 * @hook ep_facet_meta_range_use_field
			 * @param {string} $es_field   The Elasticsearch field to use for this meta field
			 * @param {string} $meta_field The meta field key
			 * @return {string} The chosen ES field
			 */
			$facet_field = apply_filters( 'ep_facet_meta_range_use_field', 'double', $meta_field );

			$facet_aggs[ $this->get_filter_name() . $meta_field . '_min' ] = array(
				'min' => array(
					'field' => 'meta.' . $meta_field . '.' . $facet_field,
				),
			);

			$facet_aggs[ $this->get_filter_name() . $meta_field . '_max' ] = array(
				'max' => array(
					'field' => 'meta.' . $meta_field . '.' . $facet_field,
				),
			);
		}

		return $facet_aggs;
	}

	/**
	 * Format selected values.
	 *
	 * For meta range facets, we will have `[ 'facet_name' => [ '_min' => X, '_max' => Y ] ]`;
	 *
	 * @since 4.5.0
	 * @param string $facet   Facet name
	 * @param mixed  $value   Facet value
	 * @param array  $filters Selected filters
	 * @return array
	 */
	public function format_selected( string $facet, $value, array $filters ) {
		$min_or_max = substr( $facet, -4 );
		$field_name = substr( $facet, 0, -4 );

		$filters[ $this->get_filter_type() ][ $field_name ][ $min_or_max ] = $value;

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

		foreach ( $selected as $facet => $values ) {
			foreach ( $values as $min_or_max => $value ) {
				$query_params[ $this->get_filter_name() . $facet . $min_or_max ] = $value;
			}
		}

		return $query_params;
	}

	/**
	 * Get all fields selected in all Facet blocks
	 *
	 * @return array
	 */
	public function get_facets_meta_fields() {
		$facets_meta_fields = [];

		$widget_block_instances = ( new \WP_Widget_Block() )->get_settings();
		foreach ( $widget_block_instances as $instance ) {
			if ( ! isset( $instance['content'] ) ) {
				continue;
			}

			if ( false === strpos( $instance['content'], 'elasticpress/facet-meta-range' ) ) {
				continue;
			}

			if ( ! preg_match_all( '/"facet":"(.*?)"/', $instance['content'], $matches ) ) {
				continue;
			}

			$facets_meta_fields = array_merge( $facets_meta_fields, $matches[1] );
		}

		if ( current_theme_supports( 'block-templates' ) ) {
			$facets_meta_fields = array_merge(
				$facets_meta_fields,
				$this->block_template_meta_fields( 'elasticpress/facet-meta-range' )
			);
		}

		/**
		 * Filter meta fields to be used in aggregations related to meta range blocks.
		 *
		 * @since 4.5.0
		 * @hook ep_facet_meta_range_fields
		 * @param {string} $facets_meta_fields Array of meta field keys
		 * @return {string} The array of meta field keys
		 */
		return apply_filters( 'ep_facet_meta_range_fields', $facets_meta_fields );
	}
}
