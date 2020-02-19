<?php
/**
 * Highlighting Settings for ElasticPress
 *
 * @package elasticpress
 */

namespace ElasticPress\Feature\Search;

use ElasticPress\Features;
use ElasticPress\Indexable\Post\Post;
use ElasticPress\Feature as Feature;

/**
 * Controls search weighting and search fields dashboard
 *
 * @package ElasticPress\Feature\Search
 */
class Highlighting {

	/**
	 * Initialize feature setting it's config
	 *
	 * @since  VERSION
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
			'highlight_enabled'	=> 'off',
			'highlight_excerpt' => 'off',
			'highlight_tag'     => 'mark',
			'highlight_color'   => '',
		];
	}

	/**
	 * Sets up the weighting module
	 */
	public function setup() {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_filter( 'ep_formatted_args', [ $this, 'add_search_highlight_tags' ], 10, 2 );
		add_filter( 'ep_highlighting_tag', [ $this, 'get_highlighting_tag' ] );
		add_filter( 'ep_highlighting_excerpt', [ $this, 'allow_excerpt_html' ], 10, 2 );
	}


	/**
	 * Enqueue styles for highlighting
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
	 * Set default fields to highilight, and outputs
	 * the tags on the front end
	 *
	 * @since VERSION
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

		if ( $settings['highlight_enabled'] !== '1' ) {
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
	 * Returns the current highlighting configuration
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
	 * called by ep_highlighting_excerpt filter
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
	 * called by allow_excerpt_html
	 * logic for the excerpt filter allowing the currentlty selected tag
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
	 * helper filter to check if the tag is allowed
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


	/**
	 * Display highlight settings on dashboard.
	 *
	 * @since VERSION
	 */
	public function output_feature_box_settings() {
		$settings = $this->get_highlighting_configuration();

		if ( ! $settings ) {
			$settings = [];
		}

		// $settings = wp_parse_args( $settings, $this->default_settings );

		$tag_options     = $this->default_tags;
		$highlight_color = ( ! empty( $settings['highlight_color'] ) ) ? $settings['highlight_color'] : null;

		?>
			<div class="field" data-feature="<?php echo esc_attr( $this->slug ); ?>">
				<div class="field-group">
					<div class="fields">
						<label for="highlight-tag" class="field-name status"><?php echo esc_html_e( 'Highlight Tag: ', 'elasticpress' ); ?></label>
						<select id="highlight-tag" name="highlight-tag" class="setting-field" data-field-name="highlight_tag">
							<?php
							foreach ( $tag_options as $option ) :
								echo '<option value="' . esc_attr( $option ) . '" ' . selected( $option, $settings['highlight_tag'] ) . '>' . esc_html( $option ) . '</option>';
							endforeach;
							?>
						</select>
					</div>
				</div>
			</div>
			<div class="field" data-feature="<?php echo esc_attr( $this->slug ); ?>">
				<div class="field-group">
					<label for="highlight-color"><?php echo esc_html( 'Highlight Color: ' ); ?>
					<input type="text" id="highlight-color" name="highlight-color" class="ep-highlight-color-select setting-field" value="<?php echo esc_attr( $highlight_color ); ?>" data-field-name="highlight_color" />
				</div>
			</div>
			<div class="field js-toggle-feature" data-feature="<?php echo esc_attr( $this->slug ); ?>">
				<p><?php esc_html_e( 'By default, WordPress strips HTML from content excerpts. Enable or disable the highlight tag in excerpts: ', 'elasticpress' ); ?></p>
				<div class="field-name status"><?php esc_html_e( 'Excerpt status', 'elasticpress' ); ?></div>
				<div class="input-wrap">
					<label for="highlight_excerpt_enabled"><input name="highlight_excerpt" id="highlight_excerpt_enabled" class="setting-field" type="radio" <?php checked( 1 === $settings['highlight_excerpt'] ); ?> value="1" data-field-name="highlight_excerpt"><?php esc_html_e( 'Enabled', 'elasticpress' ); ?></label><br>
					<label for="highlight_excerpt_disabled"><input name="highlight_excerpt" id="highlight_excerpt_disabled" class="setting-field" type="radio" <?php checked( 0 === $settings['highlight_excerpt'] ); ?> value="0" data-field-name="highlight_excerpt"><?php esc_html_e( 'Disabled', 'elasticpress' ); ?></label>
				</div>
			</div>

		<?php
	}

}
