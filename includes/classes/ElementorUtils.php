<?php
/**
 * Elementor Utils class
 *
 * @since 5.3.0
 * @package elasticpress
 */

namespace ElasticPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Elementor Utils class
 */
class ElementorUtils {
	const CACHE_KEY = 'ep_elementor_widgets';

	/**
	 * Hook cache cleanup calls.
	 */
	public function setup() {
		// Bail if Elementor is not installed.
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return;
		}

		add_action( 'save_post_elementor_library', [ $this, 'regenerate_cache' ] );
	}

	/**
	 * Delete and regenerate the cache
	 */
	public function regenerate_cache() {
		delete_transient( self::CACHE_KEY );

		// Simply calling it will reset the transient (if the `ep_elementor_widgets_pre_all_widgets` filter isn't in use.)
		$this->get_all_widgets_in_all_templates();
	}

	/**
	 * Given a widget name, return all its instances across all elementor templates.
	 *
	 * @param string $widget_name The widget name, e.g., `wp-widget-ep-facet-meta`
	 * @return array
	 */
	public function get_specific_widget_in_all_templates( string $widget_name ): array {
		$widgets = array_filter(
			$this->get_all_widgets_in_all_templates(),
			function ( $widget ) use ( $widget_name ) {
				return ( $widget['widgetType'] === $widget_name );
			}
		);

		return $widgets;
	}

	/**
	 * Get all widgets in all elementor templates
	 *
	 * It returns a flat list of all widgets, including innerWidgets.
	 *
	 * @return array
	 */
	public function get_all_widgets_in_all_templates(): array {
		/**
		 * Short-circuits the process of getting all widgets of a template.
		 *
		 * Returning a non-null value will effectively short-circuit the function.
		 *
		 * @since 5.3.0
		 * @hook ep_elementor_widgets_pre_all_widgets
		 * @param {null}   $widgets Widgets array
		 * @return {null|array} Widgets array or `null` to keep default behavior
		 */
		$pre_all_widgets = apply_filters( 'ep_elementor_widgets_pre_all_widgets', null );
		if ( null !== $pre_all_widgets ) {
			return (array) $pre_all_widgets;
		}

		$all_widgets         = [];

		$elementor_templates = get_posts(
			[
				'post_type'      => [ 'elementor_library' ],
				'posts_per_page' => -1,
			]
		);

		foreach ( $elementor_templates as $elementor_template ) {
			$template_content = get_post_meta( $elementor_template->ID, '_elementor_data', true );
			if ( ! $template_content ) {
				continue;
			}
			$template_content = json_decode( $template_content, true );
			$all_widgets      = array_merge(
				$all_widgets,
				$this->recursively_get_inner_widgets( $template_content )
			);
		}
		set_transient( self::CACHE_KEY, $all_widgets, MONTH_IN_SECONDS );
		return $all_widgets;
	}

	/**
	 * Get all inner widgets recursively.
	 *
	 * @param array $template_content Template content to analyze.
	 * @return array
	 */
	protected function recursively_get_inner_widgets( array $template_content ): array {
		$widgets = [];
		foreach ( $template_content as $element ) {
			if ( isset( $element['widgetType'] ) && ! empty( $element['widgetType'] ) ) {
				$widgets[] = $element;
			}
			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$widgets = array_merge( $widgets, $this->recursively_get_inner_widgets( $element['elements'] ) );
			}
		}
		return $widgets;
	}
}
