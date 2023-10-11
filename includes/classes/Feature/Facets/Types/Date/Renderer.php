<?php
/**
 * Class responsible for rendering the filters.
 *
 * @since 5.0.0
 * @package elasticpress
 */

namespace ElasticPress\Feature\Facets\Types\Date;

use ElasticPress\Features;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Facets render class
 */
class Renderer extends \ElasticPress\Feature\Facets\Renderer {

	/**
	 * Whether to display the custom date filter.
	 *
	 * @var bool
	 */
	protected $display_custom_date;

	/**
	 * The number of instances of the class.
	 *
	 * This is used to generate unique IDs for the HTML elements.
	 *
	 * @var int
	 */
	public static $instance_count = 1;

	/**
	 * Output the widget or block HTML.
	 *
	 * @param array $args     Widget args
	 * @param array $instance Instance settings
	 */
	public function render( $args, $instance ) {
		$this->display_custom_date = $instance['displayCustomDate'];
		$feature                   = Features::factory()->get_registered_feature( 'facets' );

		$facet_type       = $feature->types['date'];
		$selected_filters = $feature->get_selected();
		$is_custom_date   = $this->is_custom_date();
		$applied_dates    = isset( $selected_filters[ $facet_type->get_filter_type() ]['terms'] ) ? array_keys( $selected_filters[ $facet_type->get_filter_type() ]['terms'] ) : [];
		?>

		<form class="ep-facet-date-form">
			<?php
			foreach ( $facet_type->get_facet_options() as $date ) :
				$is_selected   = false;
				$field_filters = $selected_filters;

				if ( isset( $field_filters[ $facet_type->get_filter_type() ]['terms'][ $date['url-param'] ] ) ) {
					unset( $field_filters[ $facet_type->get_filter_type() ]['terms'][ $date['url-param'] ] );
					$is_selected = true;
				} else {
					$field_filters[ $facet_type->get_filter_type() ]['terms']                       = [];
					$field_filters[ $facet_type->get_filter_type() ]['terms'][ $date['url-param'] ] = $date['url-param'];
				}

				$item = [
					'label'       => $date['label'],
					'is_selected' => $is_selected,
					'value'       => $date['url-param'],
				];
				?>

				<?php
				// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $this->get_facet_item_value_html( $item, $feature->build_query_url( $field_filters ) );
				// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			<?php endforeach ?>

			<?php if ( $this->display_custom_date ) : ?>
				<?php
				// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $this->get_facet_custom_date_item( $is_custom_date, $applied_dates );
				// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			<?php endif; ?>

			<?php
			// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $this->get_facet_action_item( $applied_dates );
			// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
		</form>
		<?php

		// Enqueue Script & Styles
		wp_enqueue_script( 'elasticpress-facets' );
		wp_enqueue_style( 'elasticpress-facets' );
		self::$instance_count++;
	}

	/**
	 * Returns the HTML for a single facet item value.
	 *
	 * @param array  $item The facet item to render.
	 * @param string $url  The URL to apply the filter.
	 *
	 * @return string The HTML for the facet item value.
	 */
	public function get_facet_item_value_html( $item, string $url ): string {
		$checked = $item['is_selected'] ? 'checked' : '';
		$id      = sprintf( 'option-%s-%s', esc_attr( $item['value'] ), self::$instance_count );

		$html = sprintf(
			'<div class="ep-facet-date-option"><input type="radio" value="%1$s" class="ep-radio" name="%2$s" %3$s id="%4$s" autocomplete="off"><label class="ep-radio-label" for="%4$s">%5$s</label></div>',
			esc_attr( $item['value'] ),
			esc_attr( $this->get_filter_name() ),
			esc_attr( $checked ),
			$id,
			wp_kses_post( $item['label'] ),
		);

		/**
		 * Filter the HTML for an individual facet value.
		 *
		 * @since 5.0.0
		 * @hook ep_facet_date_value_html
		 * @param {string} $html  Facet value HTML.
		 * @param {array}  $item Value array. It contains `value`, `label` and `is_selected`.
		 * @return {string} Individual facet value HTML.
		 */
		return apply_filters( 'ep_facet_date_value_html', $html, $item );
	}

	/**
	 * Returns the HTML for the facet action item.
	 *
	 * @param array $applied_dates Applied dates.
	 *
	 * @return string The HTML for the facet action item.
	 */
	public function get_facet_action_item( $applied_dates ) {
		$filter_button = sprintf(
			'<button type="submit" class="wp-element-button ep-facet-date-form__action-submit">%s</button>',
			esc_html__( 'Filter', 'elasticpress' ),
		);

		$clear_filter_link = sprintf(
			'<a aria-label="Clear" href="%s" rel="nofollow" class="ep-facet-date-form__action-clear">%s</a>',
			esc_url( $this->get_clear_filter_url() ),
			esc_html__( 'Clear', 'elasticpress' ),
		);

		$html = sprintf(
			'<div class="ep-facet-date-form__action">%s%s</div>',
			$filter_button,
			$applied_dates ? $clear_filter_link : '',
		);

		/**
		 * Filter the HTML for the facet action.
		 *
		 * @since 5.0.0
		 * @hook ep_facet_date_action_html
		 * @param {string} $html  Facet action item HTML.
		 * @param {array}  $selected_terms Selected terms.
		 * @return {string} Individual facet action item HTML.
		 */
		return apply_filters( 'ep_facet_date_action_html', $html, $applied_dates );
	}

	/**
	 * Returns the HTML for the facet custom date item.
	 *
	 * This method generates the HTML for the custom date range option in the date facet.
	 * It includes a radio button to select the custom date range option and a date picker to select the date range.
	 *
	 * @since 5.0.0
	 *
	 * @param bool  $is_custom_date   Whether the selected date filter is custom.
	 * @param array $applied_dates    Applied dates.
	 * @return string                  The HTML for the facet custom date item.
	 */
	public function get_facet_custom_date_item( $is_custom_date, $applied_dates ) {
		$radio_button = sprintf(
			'<div class="ep-facet-date-option"><input class="ep-radio ep-date-range-custom-radio" type="radio" name="%1$s" value="custom" id="%2$s" class="ep-date-range-custom-radio" %3$s /><label for="%2$s">%4$s</label></div>',
			esc_attr( $this->get_filter_name() ),
			sprintf( 'custom-%s-%s', esc_attr( $this->get_filter_name() ), esc_attr( self::$instance_count ) ),
			$is_custom_date ? 'checked' : '',
			esc_html__( 'Custom', 'elasticpress' )
		);

		$date_picker = sprintf(
			'<div class="ep-date-range-picker %1$s"><div class="ep-date-range-picker__from"><label>%2$s</label><input type="date" name="%3$s_from" value="%4$s"></div><div class="ep-date-range-picker__to"><label>%5$s</label><input type="date" name="%3$s_to" value="%6$s"></div></div>',
			! $is_custom_date ? 'is-hidden' : '',
			esc_html__( 'From:', 'elasticpress' ),
			esc_attr( $this->get_filter_name() ),
			esc_attr( $applied_dates[0] ?? '' ),
			esc_html__( 'To:', 'elasticpress' ),
			esc_attr( $applied_dates[1] ?? '' )
		);

		$html = sprintf(
			'%s%s',
			$radio_button,
			$date_picker,
		);

		/**
		 * Filter the HTML for the facet custom date.
		 *
		 * @since 5.0.0
		 * @hook ep_facet_date_custom_date_html
		 * @param {string} $html  Facet custom date item HTML.
		 * @param {bool}  $is_custom_date Whether the selected date filter is custom.
		 * @param {array}  $applied_dates Applied dates.
		 * @return {string} Individual facet custom date item HTML.
		 */
		return apply_filters( 'ep_facet_date_custom_date_html', $html, $is_custom_date, $applied_dates );
	}

	/**
	 * Returns the URL to clear the selected date filter.
	 *
	 * @return string The URL to clear the selected date filter.
	 */
	public function get_clear_filter_url(): string {
		$feature    = Features::factory()->get_registered_feature( 'facets' );
		$facet_type = $feature->types['date'];

		$selected_filters = $feature->get_selected();
		unset( $selected_filters[ $facet_type->get_filter_type() ] );

		return $feature->build_query_url( $selected_filters );
	}

	/**
	 * Checks if the selected date filter is custom. If the selected date filter has more than one term, it is considered as custom
	 *
	 * @return bool True if the selected date filter is custom, false otherwise.
	 */
	protected function is_custom_date(): bool {
		$feature = Features::factory()->get_registered_feature( 'facets' );

		$facet_type       = $feature->types['date'];
		$selected_filters = $feature->get_selected();

		if ( empty( $selected_filters[ $facet_type->get_filter_type() ] ) ) {
			return false;
		}

		$selected_dates  = array_keys( $selected_filters[ $facet_type->get_filter_type() ]['terms'] );
		$default_options = array_column( $facet_type->get_facet_options(), 'url-param' );

		$selected_dates = array_diff( $selected_dates, $default_options );
		return count( $selected_dates ) > 0;
	}

	/**
	 * Get the filter name for the date facet type.
	 *
	 * @return string The filter name.
	 */
	protected function get_filter_name() : string {
		$feature    = Features::factory()->get_registered_feature( 'facets' );
		$facet_type = $feature->types['date'];
		return $facet_type->get_filter_name();
	}
}
