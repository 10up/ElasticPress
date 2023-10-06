<?php
/**
 * Abstract Facet Renderer class.
 *
 * @since 4.7.0
 * @package elasticpress
 */

namespace ElasticPress\Feature\Facets;

/**
 * Abstract Facet Renderer class.
 */
abstract class Renderer {
	/**
	 * Whether the term count should be displayed or not.
	 *
	 * @var bool
	 */
	protected $display_count;

	/**
	 * Method to render the facet.
	 *
	 * @param array $args     Widget args
	 * @param array $instance Instance settings
	 */
	abstract public function render( $args, $instance );

	/**
	 * Whether the facet should be rendered or not.
	 *
	 * @return bool
	 */
	protected function should_render() : bool {
		return true;
	}

	/**
	 * Given an array of values, reorder them.
	 *
	 * @param array  $values  Multidimensional array of values. Each value should have (string) `name`, (int) `count`, and (bool) `is_selected`.
	 * @param string $orderby Key to be used to order.
	 * @param string $order   ASC or DESC.
	 * @return array
	 */
	protected function order_values( array $values, string $orderby = 'count', $order = 'desc' ) : array {
		$orderby = strtolower( $orderby );
		$orderby = in_array( $orderby, [ 'name', 'count' ], true ) ? $orderby : 'count';

		$order = strtoupper( $order );
		$order = in_array( $order, [ 'ASC', 'DESC' ], true ) ? $order : 'DESC';

		$values = wp_list_sort( $values, $orderby, $order, true );

		$selected = [];
		foreach ( $values as $key => $value ) {
			if ( $value['is_selected'] ) {
				$selected[ $key ] = $value;
				unset( $values[ $key ] );
			}
		}
		$values = $selected + $values;

		return $values;
	}

	/**
	 * Get the markup for an individual facet item.
	 *
	 * @param array|object $item        Facet item.
	 * @param string       $url         URL for the facet item.
	 * @return string|null
	 */
	public function get_facet_item_value_html( $item, string $url ) {
		return null;
	}
}
