<?php
/**
 * Highlighting Settings for ElasticPress
 *
 * @package elasticpress
 */

namespace ElasticPress\Feature\Search;

use ElasticPress\Features;
use ElasticPress\Feature as Feature;

/**
 * Highlights Search results.
 *
 * @package ElasticPress\Feature\Search
 */
class Highlighting {
	/**
	 * Default tags allowed to highlight search terms.
	 *
	 * @var array
	 */
	public $default_tags;

	/**
	 * Default settings for the feature.
	 *
	 * @var array
	 */
	public $default_settings;

	/**
	 * Initialize feature setting it's config.
	 */
	public function __construct() {
		$this->default_tags = [
			'mark',
			'span',
			'strong',
			'em',
			'i',
		];

		$this->default_settings = [
			'highlight_enabled' => 'off',
			'highlight_excerpt' => 'off',
			'highlight_tag'     => 'mark',
			'highlight_color'   => '',
		];
	}

	/**
	 * Sets up the highlighting module.
	 */
	public function setup() {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_filter( 'ep_formatted_args', [ $this, 'add_search_highlight_tags' ], 10, 2 );
		add_filter( 'ep_highlighting_tag', [ $this, 'get_highlighting_tag' ] );
		add_filter( 'ep_highlighting_excerpt', [ $this, 'allow_excerpt_html' ], 10, 2 );
	}

	/**
	 * Enqueue styles for highlighting.
	 */
	public function enqueue_scripts() {
		wp_enqueue_style(
			'searchterm-highlighting',
			EP_URL . 'dist/css/highlighting-styles.min.css',
			[],
			EP_VERSION
		);

		// retrieve settings to ge the current color value
		$settings = $this->get_highlighting_configuration();

		// check for value before inlining the style
		if ( ! empty( $settings['highlight_color'] ) ) {
			$inline_color = "
				:root{
					--highlight-color: {$settings['highlight_color']};
				}";
			wp_add_inline_style( 'searchterm-highlighting', $inline_color );
		}
	}

	/**
	 * Set default fields to highlight, and outputs
	 * the tags on the front end.
	 *
	 * @param array $formatted_args ep_formatted_args array
	 * @param array $args WP_Query args
	 * @return array $formatted_args formatted args with search highlight tags
	 */
	public function add_search_highlight_tags( $formatted_args, $args ) {
		apply_filters( 'ep_highlighting_excerpt', [] );

		// get current config
		$settings = $this->get_highlighting_configuration();
		$settings = wp_parse_args( $settings, $this->default_settings );

		if ( '1' !== $settings['highlight_enabled'] ) {
			return $formatted_args;
		}

		if ( empty( $args['s'] ) ) {
			return $formatted_args;
		}

		$fields_to_highlight = array();

		// this should inherit the already-defined search fields.
		// get the search fields as defined by weighting, etc.
		if ( ! empty( $args['search_fields'] ) ) {
			$fields_to_highlight = $args['search_fields'];

		} else {
			// fallback to the fields pre-defined in the query
			$should_match = $formatted_args['query']['bool']['should'];

			// next, check for the the weighted fields, in case any are excluded.
			foreach ( $should_match as $item ) {
				$fields = $item['multi_match']['fields'];
				foreach ( $fields as $field ) {
					array_push( $fields_to_highlight, $field );
				}
			}

			$fields_to_highlight = array_unique( $fields_to_highlight );
		}

		// define the tag to use
		$current_tag   = $settings['highlight_tag'];
		$highlight_tag = apply_filters( 'ep_highlighting_tag', $current_tag );

		// default class
		$highlight_class = 'ep-highlight';

		// tags
		$opening_tag = '<' . $highlight_tag . ' class="' . $highlight_class . '">';
		$closing_tag = '</' . $highlight_tag . '>';

		// only for search query
		if ( ! is_admin() && ! empty( $args['s'] ) ) {
			foreach ( $fields_to_highlight as $field ) {
				$formatted_args['highlight']['fields'][ $field ] = [
					'pre_tags'  => [ $opening_tag ],
					'post_tags' => [ $closing_tag ],
					'type'      => 'plain',
				];
			}
		}
		return $formatted_args;
	}

	/**
	 * Returns the current highlighting configuration.
	 *
	 * @return array
	 */
	public function get_highlighting_configuration() {

		/** Features Class @var Features $features */
		$features = Features::factory();

		/** Search Feature @var Feature\Search\Search $search */
		$search = $features->get_registered_feature( 'search' );
		return $search->get_settings();
	}

	/**
	 * called by ep_highlighting_excerpt filter.
	 *
	 * Replaces the default excerpt with the custom excerpt, allowing
	 * for the selected tag to be displayed in it.
	 */
	public function allow_excerpt_html() {
		if ( is_admin() ) {
			return;
		}

		if ( empty( $_GET['s'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		$settings = $this->get_highlighting_configuration();

		if ( ! empty( $_GET['s'] ) && ! empty( $settings['highlight_excerpt'] ) && '1' === $settings['highlight_excerpt'] ) { // phpcs:ignore WordPress.Security.NonceVerification
			remove_filter( 'get_the_excerpt', 'wp_trim_excerpt' );
			add_filter( 'get_the_excerpt', [ $this, 'ep_highlight_excerpt' ] );
		}
	}

	/**
	 * Called by allow_excerpt_html
	 * logic for the excerpt filter allowing the currently selected tag.
	 *
	 * @param string $text - excerpt string
	 * @return string $text - the new excerpt
	 */
	public function ep_highlight_excerpt( $text ) {

		$settings = $this->get_highlighting_configuration();

		// reproduces wp_trim_excerpt filter, preserving the excerpt_more and excerpt_length filters
		if ( '' === $text ) {
			$text = get_the_content( '' );
			$text = apply_filters( 'the_content', $text );
			$text = str_replace( '\]\]\>', ']]&gt;', $text );
			$text = strip_tags( $text, '<' . esc_html( $settings['highlight_tag'] ) . '>' );

			// use the defined length, if already applied...
			$excerpt_length = apply_filters( 'excerpt_length', 55 );

			// use defined excerpt_more filter if it is used
			$excerpt_more = apply_filters( 'excerpt_more', $text );

			$excerpt_more = $excerpt_more !== $text ? $excerpt_more : '[&hellip;]';

			$words = explode( ' ', $text, $excerpt_length + 1 );
			if ( count( $words ) > $excerpt_length ) {
				array_pop( $words );
				array_push( $words, $excerpt_more );
				$text = implode( ' ', $words );
			}
		}

		return $text;
	}

	/**
	 * Helper filter to check if the tag is allowed.
	 *
	 * @param string $tag - html tag
	 * @return string
	 */
	public function get_highlighting_tag( $tag ) {
		$this->highlighting_tag = $tag;

		$options = $this->default_tags;

		if ( ! in_array( $tag, $options, true ) ) {
			$this->highlighting_tag = 'mark';
		}

		return $this->highlighting_tag;
	}

}
