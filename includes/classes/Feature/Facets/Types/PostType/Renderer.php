<?php
/**
 * Class responsible for rendering the post type filters.
 *
 * @since 4.6.0
 * @package elasticpress
 */

namespace ElasticPress\Feature\Facets\Types\PostType;

use ElasticPress\Features;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Facets render class
 */
class Renderer extends \ElasticPress\Feature\Facets\Renderer {
	/**
	 * Whether the term count should be displayed or not.
	 *
	 * @var bool
	 */
	protected $display_count = false;

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

		$this->display_count = $instance['displayCount'];

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

		$facet_type = $feature->types['post-type'];

		$selected_filters = $feature->get_selected();

		$facetable_post_types = $facet_type->get_facetable_post_types();

		$values = [];

		foreach ( $facetable_post_types as $post_type ) {
			$values[ $post_type ] = [
				'value'       => $post_type,
				'name'        => ucfirst( $post_type ),
				'count'       => 0,
				'is_selected' => ! empty( $selected_filters[ $facet_type->get_filter_type() ]['terms'][ $post_type ] ) ?
					$selected_filters[ $facet_type->get_filter_type() ]['terms'][ $post_type ] :
					false,
			];

			if ( ! empty( $GLOBALS['ep_facet_aggs']['post_type'][ $post_type ] ) ) {
				$values[ $post_type ]['count'] = (int) $GLOBALS['ep_facet_aggs']['post_type'][ $post_type ];
			}
		}

		echo wp_kses_post( $args['before_widget'] );

		if ( ! empty( $instance['title'] ) ) {
			echo wp_kses_post( $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ) . $args['after_title'] );
		}

		/**
		 * Filter facet search threshold
		 *
		 * @hook ep_facet_search_threshold
		 * @param  {int}    $search_threshold Search threshold
		 * @param  {string} $type             Facet type
		 * @param  {string} $context          Hint about where the value will be used
		 * @param  {array}  $instance         Block instance
		 * @return  {int} New threshold
		 */
		$search_threshold = apply_filters( 'ep_facet_search_threshold', 15, 'post-type', 'post-type', $instance );
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
				foreach ( $values as $facetable_post_type_data ) {
					$field_filters = $selected_filters;

					$field_filters = $selected_filters;
					if ( $facetable_post_type_data['is_selected'] ) {
						unset( $field_filters[ $facet_type->get_filter_type() ]['terms'][ $facetable_post_type_data['value'] ] );
					} else {
						$field_filters[ $facet_type->get_filter_type() ]['terms'][ $facetable_post_type_data['value'] ] = 1;
					}
					// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
					echo $this->get_facet_item_value_html( $facetable_post_type_data, $feature->build_query_url( $field_filters ) );
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
	 * @param array  $item Facet item.
	 * @param string $url   Filter URL.
	 * @return string HTML for an individual facet term.
	 */
	public function get_facet_item_value_html( $item, $url ) : string {
		$href = sprintf(
			'href="%s"',
			esc_url( $url )
		);

		$label = $item['name'];
		if ( $this->display_count ) {
			$label .= ' <span>(' . esc_html( $item['count'] ) . ')</span>';
		}

		/**
		 * Filter the label for an individual post-type value.
		 *
		* @since 4.6.0
		 * @hook ep_facet_post_type_value_label
		 * @param {string} $label Facet post-type value label.
		 * @param {array}  $item Value array. It contains `value`, `name`, `count`, and `is_selected`.
		 * @return {string} Individual facet post-type value label.
		 */
		$label = apply_filters( 'ep_facet_post_type_value_label', $label, $item );

		/**
		 * Filter the accessible label for an individual facet post-type value link.
		 *
		 * Used as the aria-label attribute for filter links. The accessible
		 * label should include additional context around what action will be
		 * performed by visiting the link, such as whether the filter will be
		 * added or removed.
		 *
		 * @since 4.6.0
		 * @hook ep_facet_post_type_value_accessible_label
		 * @param {string}  $label Facet post-type value accessible label.
		 * @param {array}   $item Value array. It contains `value`, `name`, `count`, and `is_selected`.
		 * @return {string} Individual facet term accessible label.
		 */
		$accessible_label = apply_filters(
			'ep_facet_post_type_value_accessible_label',
			$item['is_selected']
				/* translators: %s: Filter term name. */
				? sprintf( __( 'Remove filter: %s', 'elasticpress' ), $label )
				/* translators: %s: Filter term name. */
				: sprintf( __( 'Apply filter: %s', 'elasticpress' ), $label ),
			$item
		);

		$link = sprintf(
			'<a aria-label="%1$s" %2$s rel="nofollow"><div class="ep-checkbox %3$s" role="presentation"></div>%4$s</a>',
			esc_attr( $accessible_label ),
			$item['count'] ? $href : 'aria-role="link" aria-disabled="true"',
			$item['is_selected'] ? 'checked' : '',
			wp_kses_post( $label )
		);

		$html = sprintf(
			'<div class="term level-%1$d %2$s %3$s" data-term-name="%4$s" data-term-slug="%5$s">%6$s</div>',
			0,
			$item['is_selected'] ? 'selected' : '',
			! $item['count'] ? 'empty-term' : '',
			esc_attr( strtolower( $item['value'] ) ),
			esc_attr( strtolower( $item['value'] ) ),
			$link
		);

		/**
		 * Filter the HTML for an individual facet post-type value.
		 *
		 * For term search to work correctly the outermost wrapper of the term
		 * HTML must have data-term-name and data-term-slug attributes set to
		 * lowercase versions of the term name and slug respectively.
		 *
		 * @since 4.6.0
		 * @hook ep_facet_post_type_value_html
		 * @param {string} $html  Facet post-type value HTML.
		 * @param {array}  $item Value array. It contains `value`, `name`, `count`, and `is_selected`.
		 * @param {string} $url   Filter URL.
		 * @return {string} Individual facet post-typ value HTML.
		 */
		return apply_filters( 'ep_facet_post_type_value_html', $html, $item, $url );
	}

	/**
	 * Determine if the block/widget should or not be rendered.
	 *
	 * @return boolean
	 */
	protected function should_render() : bool {
		global $wp_query;

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
}
