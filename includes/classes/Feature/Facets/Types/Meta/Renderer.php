<?php
/**
 * Class responsible for rendering the filters.
 *
 * @since 4.3.0
 * @package elasticpress
 */

namespace ElasticPress\Feature\Facets\Types\Meta;

use ElasticPress\Features as Features;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Facets render class
 */
class Renderer {
	/**
	 * Holds the meta field selected.
	 *
	 * @var string
	 */
	protected $meta_field = '';

	/**
	 * Output the widget or block HTML.
	 *
	 * @param array $args     Widget args
	 * @param array $instance Instance settings
	 */
	public function render( $args, $instance ) {
		$instance = wp_parse_args(
			$instance,
			[
				'title' => '',
			]
		);

		$this->meta_field = $instance['facet'];

		if ( ! $this->should_render() ) {
			return;
		}

		$args = wp_parse_args(
			$args,
			[
				'before_widget' => '',
				'before_title'  => '',
				'after_title'   => '',
				'after_widget'  => '',
			]
		);

		$feature = Features::factory()->get_registered_feature( 'facets' );

		$facet_type = $feature->types['meta'];

		$selected_meta    = $this->get_selected_meta();
		$selected_filters = $feature->get_selected();

		/**
		 * Get all the terms so we know if we should output the widget
		 */
		$raw_values = $facet_type->get_meta_values( $instance['facet'] );

		$values = [];

		foreach ( $raw_values as $raw_value ) {
			$values[ $raw_value ] = [
				'value'       => $raw_value,
				'name'        => $raw_value,
				'count'       => 0,
				'is_selected' => in_array( $raw_value, $selected_meta, true ),
			];

			if ( ! empty( $GLOBALS['ep_facet_aggs'][ $facet_type->get_filter_name() . $this->meta_field ][ $raw_value ] ) ) {
				$values[ $raw_value ]['count'] = (int) $GLOBALS['ep_facet_aggs'][ $facet_type->get_filter_name() . $this->meta_field ][ $raw_value ];
			}
		}

		/**
		 * Filter meta values, their labels, and count.
		 *
		 * If you need to display a value with a different label:
		 * ```
		 * add_filter(
		 *     'ep_facet_meta_all_values',
		 *     function( $values, $meta_field ) {
		 *         if ( 'my_field' !== $meta_field ) {
		 *             return $values;
		 *         }
		 *         if ( isset( $values['unreadable_value'] ) ) {
		 *             $values['unreadable_value']['name'] = 'My Readable Value';
		 *         }
		 *         return $values;
		 *     },
		 *     10,
		 *     2
		 * );
		 * ```
		 *
		 * @hook ep_facet_meta_values_with_count
		 * @since 4.3.0
		 * @param {array}  $values     Values with names and counts
		 * @param {string} $meta_field Meta field
		 * @param {array}  $instance   Block info
		 * @return {array} New values
		 */
		$values = apply_filters( 'ep_facet_meta_all_values', $values, $this->meta_field, $instance );

		echo wp_kses_post( $args['before_widget'] );

		if ( ! empty( $instance['title'] ) ) {
			echo wp_kses_post( $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ) . $args['after_title'] );
		}

		/**
		 * Filter facet search threshold
		 *
		 * @hook ep_facet_search_threshold
		 * @param  {int}    $search_threshold Search threshold
		 * @param  {string} $taxonomy         Current taxonomy
		 * @param  {string} $context          Hint about where the value will be used
		 * @param  {array}  $instance         Block instance
		 * @return  {int} New threshold
		 */
		$search_threshold = apply_filters( 'ep_facet_search_threshold', 15, $this->meta_field, 'meta', $instance );
		?>
		<div class="terms <?php if ( count( $values ) > $search_threshold ) : ?>searchable<?php endif; ?>">
			<?php if ( count( $values ) > $search_threshold ) : ?>
				<input class="facet-search" type="search" placeholder="<?php echo esc_attr( $instance['searchPlaceholder'] ); ?>">
			<?php endif; ?>

			<div class="inner">
				<?php
				$orderby = $instance['orderby'] ?? 'count';
				$order   = $instance['order'] ?? 'desc';

				$values = $this->order_values( $values, $orderby, $order );
				foreach ( $values as $raw_value => $value ) {

					$field_filters = $selected_filters;
					if ( $value['is_selected'] ) {
						unset( $field_filters[ $facet_type->get_filter_type() ][ $this->meta_field ]['terms'][ $raw_value ] );
					} else {
						$field_filters[ $facet_type->get_filter_type() ][ $this->meta_field ]['terms'][ $raw_value ] = 1;
					}

					// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
					echo $this->get_meta_value_html(
						$value,
						$feature->build_query_url( $field_filters )
					);
					// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped					
				}
				?>
			</div>
		</div>
		<?php

		// Enqueue Script & Styles
		wp_enqueue_script( 'elasticpress-facets' );
		wp_enqueue_style( 'elasticpress-facets' );

		echo wp_kses_post( $args['after_widget'] );
	}

	/**
	 * Get the markup for an individual facet item.
	 *
	 * @param array  $value Value.
	 * @param string $url   Filter URL.
	 * @return string HTML for an individual facet term.
	 */
	public function get_meta_value_html( array $value, string $url ) : string {
		$href = sprintf(
			'href="%s"',
			esc_url( $url )
		);

		/**
		 * Filter the label for an individual meta value.
		 *
		 * @since 4.3.0
		 * @hook ep_facet_meta_value_label
		 * @param {string} $label Facet meta value label.
		 * @param {array}  $value Value array. It contains `value`, `name`, `count`, and `is_selected`.
		 * @return {string} Individual facet meta value label.
		 */
		$label = apply_filters( 'ep_facet_meta_value_label', $value['name'], $value );

		/**
		 * Filter the accessible label for an individual facet meta value link.
		 *
		 * Used as the aria-label attribute for filter links. The accessible
		 * label should include additional context around what action will be
		 * performed by visiting the link, such as whether the filter will be
		 * added or removed.
		 *
		 * @since 4.3.0
		 * @hook ep_facet_meta_value_accessible_label
		 * @param {string}  $label Facet meta value accessible label.
		 * @param {array}   $value Value array. It contains `value`, `name`, `count`, and `is_selected`.
		 * @return {string} Individual facet term accessible label.
		 */
		$accessible_label = apply_filters(
			'ep_facet_meta_value_accessible_label',
			$value['is_selected']
				/* translators: %s: Filter term name. */
				? sprintf( __( 'Remove filter: %s', 'elasticpress' ), $label )
				/* translators: %s: Filter term name. */
				: sprintf( __( 'Apply filter: %s', 'elasticpress' ), $label ),
			$value
		);

		$link = sprintf(
			'<a aria-label="%1$s" %2$s rel="nofollow"><div class="ep-checkbox %3$s" role="presentation"></div>%4$s</a>',
			esc_attr( $accessible_label ),
			$value['count'] ? $href : 'aria-role="link" aria-disabled="true"',
			$value['is_selected'] ? 'checked' : '',
			wp_kses_post( $label )
		);

		$html = sprintf(
			'<div class="term level-%1$d %2$s %3$s" data-term-name="%4$s" data-term-slug="%5$s">%6$s</div>',
			0,
			$value['is_selected'] ? 'selected' : '',
			! $value['count'] ? 'empty-term' : '',
			esc_attr( strtolower( $value['value'] ) ),
			esc_attr( strtolower( $value['value'] ) ),
			$link
		);

		/**
		 * Filter the HTML for an individual facet meta value.
		 *
		 * For term search to work correctly the outermost wrapper of the term
		 * HTML must have data-term-name and data-term-slug attributes set to
		 * lowercase versions of the term name and slug respectively.
		 *
		 * @since 4.3.0
		 * @hook ep_facet_meta_value_html
		 * @param {string} $html  Facet meta value HTML.
		 * @param {array}  $value Value array. It contains `value`, `name`, `count`, and `is_selected`.
		 * @param {string} $url   Filter URL.
		 * @return {string} Individual facet meta value HTML.
		 */
		return apply_filters( 'ep_facet_meta_value_html', $html, $value, $url );
	}

	/**
	 * Determine if the block/widget should or not be rendered.
	 *
	 * @return boolean
	 */
	protected function should_render() : bool {
		global $wp_query;

		if ( empty( $this->meta_field ) ) {
			return false;
		}

		$feature = Features::factory()->get_registered_feature( 'facets' );
		if ( $wp_query->get( 'ep_facet', false ) && ! $feature->is_facetable( $wp_query ) ) {
			return false;
		}

		$es_success = ( ! empty( $wp_query->elasticsearch_success ) ) ? true : false;
		if ( ! $es_success ) {
			return false;
		}

		return true;
	}

	/**
	 * Get selected values.
	 *
	 * @return array
	 */
	protected function get_selected_meta() : array {
		$feature = Features::factory()->get_registered_feature( 'facets' );

		$selected_filters = $feature->get_selected();
		$selected_meta    = [];

		if ( isset( $selected_filters['meta'][ $this->meta_field ] ) && isset( $selected_filters['meta'][ $this->meta_field ]['terms'] ) ) {
			$selected_meta = array_map( 'trim', array_keys( $selected_filters['meta'][ $this->meta_field ]['terms'] ) );
		}

		return $selected_meta;
	}

	/**
	 * Given an array of values, reorder them.
	 *
	 * @param array  $values  Multidimentional array of values. Each value should have (string) `name`, (int) `count`, and (bool) `is_selected`.
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
}
