<?php
/**
 * Search feature
 *
 * @since  1.9
 * @package  elasticpress
 */

namespace ElasticPress\Feature\Search;

use ElasticPress\Feature as Feature;
use ElasticPress\Indexables as Indexables;
use ElasticPress\Utils as Utils;

/**
 * Search feature class
 */
class Search extends Feature {
	/**
	 * Synonyms Class (Sub Feature)
	 *
	 * @var Synonyms
	 */
	public $synonyms;

	/**
	 * Weighting Class (Sub Feature)
	 *
	 * @var Weighting
	 */
	public $weighting;

	/**
	 * Highlighting tags
	 *
	 * @var array
	 */
	public static $default_highlight_tags = [
		'mark',
		'span',
		'strong',
		'em',
		'i',
	];

	/**
	 * Initialize feature setting it's config
	 *
	 * @since  3.0
	 */
	public function __construct() {
		$this->slug = 'search';

		$this->title = esc_html__( 'Post Search', 'elasticpress' );

		$this->requires_install_reindex = false;
		$this->default_settings         = [
			'decaying_enabled'     => true,
			'synonyms_editor_mode' => 'simple',
			'highlight_enabled'    => false,
			'highlight_excerpt'    => false,
			'highlight_tag'        => 'mark',
		];

		parent::__construct();
	}

	/**
	 * We need to delay search setup up since it will fire after protected content and protected
	 * content filters into the search setup
	 *
	 * @since 2.2
	 */
	public function setup() {
		add_action( 'init', [ $this, 'search_setup' ] );
		add_filter( 'ep_sanitize_feature_settings', [ $this, 'sanitize_highlighting_settings' ] );

		// Set up weighting sub-module
		$this->weighting = new Weighting();
		$this->weighting->setup();

		$this->synonyms = new Synonyms();
		$this->synonyms->setup();
	}

	/**
	 * Setup feature on each page load
	 *
	 * @since  3.0
	 */
	public function search_setup() {
		add_filter( 'ep_elasticpress_enabled', [ $this, 'integrate_search_queries' ], 10, 2 );
		add_filter( 'ep_formatted_args', [ $this, 'weight_recent' ], 11, 2 );
		add_filter( 'ep_query_post_type', [ $this, 'filter_query_post_type_for_search' ], 10, 2 );

		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_filter( 'ep_formatted_args', [ $this, 'add_search_highlight_tags' ], 10, 2 );
		add_filter( 'ep_highlighting_tag', [ $this, 'get_highlighting_tag' ] );
		add_action( 'ep_highlighting_pre_add_highlight', [ $this, 'allow_excerpt_html' ] );
	}


	/**
	 * Enqueue styles for highlighting.
	 */
	public function enqueue_scripts() {
		$settings = $this->get_settings();

		if ( ! $settings ) {
			$settings = [];
		}

		$settings = wp_parse_args( $settings, $this->default_settings );

		if ( true !== $settings['highlight_enabled'] ) {
			return;
		}

		wp_enqueue_style(
			'searchterm-highlighting',
			EP_URL . 'dist/css/highlighting-styles.min.css',
			[],
			EP_VERSION
		);
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

		/**
		 * Fires before the highlighting clause is added to the Elasticsearch query
		 *
		 * @since  3.5.1
		 * @hook ep_highlighting_pre_add_highlight
		 * @param  {array} $formatted_args ep_formatted_args array
		 * @param  {string} $args WP_Query args
		 */
		do_action( 'ep_highlighting_pre_add_highlight', $formatted_args, $args );

		// get current config
		$settings = $this->get_settings();

		if ( ! $settings ) {
			$settings = [];
		}

		$settings = wp_parse_args( $settings, $this->default_settings );

		if ( true !== $settings['highlight_enabled'] ) {
			return $formatted_args;
		}

		if ( empty( $args['s'] ) ) {
			return $formatted_args;
		}

		/**
		 * Filter whether to add the `highlight` clause in the query or not.
		 *
		 * @since  3.5.6
		 * @hook ep_highlight_should_add_clause
		 * @param  {bool}  $add_highlight_clause True means the clause should be added.
		 * @param  {array} $formatted_args  ep_formatted_args array
		 * @param  {array} $args  WP query args
		 * @return {bool}  New $add_highlight_clause value
		 */
		$add_highlight_clause = apply_filters(
			'ep_highlight_should_add_clause',
			Utils\is_integrated_request( 'highlighting', [ 'public' ] ),
			$formatted_args,
			$args
		);

		if ( ! $add_highlight_clause ) {
			return $formatted_args;
		}

		/**
		 * Filter the fields that should be highlighted.
		 *
		 * @since 3.5.1
		 * @hook ep_highlighting_fields
		 * @param  {array} $fields Highlighting fields
		 * @param  {array} $formatted_args array
		 * @param  {array} $args WP_Query args
		 * @return  {array} New Highlighting fields
		 */
		$fields_to_highlight = apply_filters(
			'ep_highlighting_fields',
			[ 'post_title', 'post_content' ],
			$formatted_args,
			$args
		);

		// define the tag to use
		$current_tag = $settings['highlight_tag'];

		/**
		 * Filter the tag that wraps the search highlighted term
		 *
		 * @since 3.5
		 * @hook ep_highlighting_tag
		 * @param  {string} $current_tag Highlighting tag
		 * @return  {string} New highlighting tag
		 */
		$highlight_tag = apply_filters( 'ep_highlighting_tag', $current_tag );

		/**
		 * Filter class applied to search highlight tags
		 *
		 * @since 3.5
		 * @hook ep_highlighting_class
		 * @param  {string} $class Highlighting class
		 * @return  {string} New highlighting class
		 */
		$highlight_class = apply_filters( 'ep_highlighting_class', 'ep-highlight' );

		// tags
		$opening_tag = '<' . $highlight_tag . ' class="' . $highlight_class . '">';
		$closing_tag = '</' . $highlight_tag . '>';

		foreach ( $fields_to_highlight as $field ) {
			$formatted_args['highlight']['fields'][ $field ] = [
				'pre_tags'            => [ $opening_tag ],
				'post_tags'           => [ $closing_tag ],
				'type'                => 'plain',
				'number_of_fragments' => 0,
			];
		}

		return $formatted_args;
	}

	/**
	 * Called by ep_highlighting_pre_add_highlight action.
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

		$settings = $this->get_settings();

		if ( ! $settings ) {
			$settings = [];
		}

		$settings = wp_parse_args( $settings, $this->default_settings );

		if ( ! empty( $settings['highlight_excerpt'] ) && true === $settings['highlight_excerpt'] ) {
			remove_filter( 'get_the_excerpt', 'wp_trim_excerpt' );
			add_filter( 'get_the_excerpt', [ $this, 'ep_highlight_excerpt' ] );
			add_filter( 'ep_highlighting_fields', [ $this, 'ep_highlight_add_excerpt_field' ] );
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

		$settings = $this->get_settings();

		if ( ! $settings ) {
			$settings = [];
		}

		$settings = wp_parse_args( $settings, $this->default_settings );

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
	 * Add `post_content` to the list of fields to highlight.
	 *
	 * @since 3.5.1
	 * @param array $fields_to_highlight The list of fields to highlight.
	 * @return array
	 */
	public function ep_highlight_add_excerpt_field( $fields_to_highlight ) {
		$fields_to_highlight[] = 'post_excerpt';
		return $fields_to_highlight;
	}

	/**
	 * Helper filter to check if the tag is allowed.
	 *
	 * @param string $tag - html tag
	 * @return string
	 */
	public function get_highlighting_tag( $tag ) {
		if ( ! in_array( $tag, self::$default_highlight_tags, true ) ) {
			$tag = 'mark';
		}

		return $tag;
	}

	/**
	 * Sanitizes our highlighting settings.
	 *
	 * @param array $settings Array of current settings
	 * @return mixed
	 */
	public function sanitize_highlighting_settings( $settings ) {
		if ( ! empty( $settings['search']['highlight_excerpt'] ) ) {
			$settings['search']['highlight_excerpt'] = (bool) $settings['search']['highlight_excerpt'];
		}

		if ( ! empty( $settings['search']['highlight_enabled'] ) ) {
			$settings['search']['highlight_enabled'] = (bool) $settings['search']['highlight_enabled'];
		}

		return $settings;
	}

	/**
	 * Returns searchable post types for the current site
	 *
	 * @since 1.9
	 * @return mixed|void
	 */
	public function get_searchable_post_types() {
		$post_types = get_post_types( array( 'exclude_from_search' => false ) );

		/**
		 * Don't search attachments by default
		 *
		 * @since  3.0
		 */
		unset( $post_types['attachment'] );

		/**
		 * Filter searchable post types
		 *
		 * @hook ep_searchable_post_types
		 * @param  {array} $post_types Post types
		 * @return  {array} New post types
		 */
		return apply_filters( 'ep_searchable_post_types', $post_types );
	}

	/**
	 * Make sure we don't search for "any" on a search query
	 *
	 * @param  string   $post_type Post type
	 * @param  WP_Query $query WP Query
	 * @return string|array
	 */
	public function filter_query_post_type_for_search( $post_type, $query ) {
		if ( 'any' === $post_type && $query->is_search() ) {
			$searchable_post_types = $this->get_searchable_post_types();

			// If we have no searchable post types, there's no point going any further
			if ( empty( $searchable_post_types ) ) {

				// Have to return something or it improperly calculates the found_posts
				return false;
			}

			// Conform the post types array to an acceptable format for ES
			$post_types = [];

			foreach ( $searchable_post_types as $type ) {
				$post_types[] = $type;
			}

			// These are now the only post types we will search
			$post_type = $post_types;
		}

		return $post_type;
	}

	/**
	 * Returns true/false if decaying is/isn't enabled
	 *
	 * @return bool
	 */
	public function is_decaying_enabled() {
		$settings = $this->get_settings();

		$settings = wp_parse_args(
			$settings,
			[
				'decaying_enabled' => true,
			]
		);

		return (bool) $settings['decaying_enabled'];
	}

	/**
	 * Weight more recent content in searches
	 *
	 * @param  array $formatted_args Formatted ES args
	 * @param  array $args WP_Query args
	 * @since  2.1
	 * @return array
	 */
	public function weight_recent( $formatted_args, $args ) {
		if ( ! empty( $args['s'] ) ) {
			if ( $this->is_decaying_enabled() ) {
				/**
				 * Filter search date weighting scale
				 *
				 * @hook epwr_decay_function
				 * @param  {string} $decay_function Current decay function
				 * @param  {array} $formatted_args Formatted Elasticsearch arguments
				 * @param  {array} $args WP_Query arguments
				 * @return  {string} New decay function
				 */
				$decay_function = apply_filters( 'epwr_decay_function', 'exp', $formatted_args, $args );
				$date_score     = array(
					'function_score' => array(
						'query'      => $formatted_args['query'],
						'functions'  => array(
							array(
								$decay_function => array(
									'post_date_gmt' => array(
										/**
										 * Filter search date weighting scale
										 *
										 * @hook epwr_scale
										 * @param  {string} $scale Current scale
										 * @param  {array} $formatted_args Formatted Elasticsearch arguments
										 * @param  {array} $args WP_Query arguments
										 * @return  {string} New scale
										 */
										'scale'  => apply_filters( 'epwr_scale', '14d', $formatted_args, $args ),
										/**
										 * Filter search date weighting decay
										 *
										 * @hook epwr_decay
										 * @param  {string} $decay Current decay
										 * @param  {array} $formatted_args Formatted Elasticsearch arguments
										 * @param  {array} $args WP_Query arguments
										 * @return  {string} New decay
										 */
										'decay'  => apply_filters( 'epwr_decay', .25, $formatted_args, $args ),
										/**
										 * Filter search date weighting offset
										 *
										 * @hook epwr_offset
										 * @param  {string} $offset Current offset
										 * @param  {array} $formatted_args Formatted Elasticsearch arguments
										 * @param  {array} $args WP_Query arguments
										 * @return  {string} New offset
										 */
										'offset' => apply_filters( 'epwr_offset', '7d', $formatted_args, $args ),
									),
								),
							),
							array(
								/**
								 * Filter search date weight
								 *
								 * @since 3.5.6
								 * @hook epwr_weight
								 * @param  {string} $weight Current weight
								 * @param  {array} $formatted_args Formatted Elasticsearch arguments
								 * @param  {array} $args WP_Query arguments
								 * @return  {string} New weight
								 */
								'weight' => apply_filters( 'epwr_weight', 0.001, $formatted_args, $args ),
							),
						),
						/**
						 * Filter search date weighting score mode
						 *
						 * @hook epwr_score_mode
						 * @param  {string} $score_mode Current score mode
						 * @param  {array} $formatted_args Formatted Elasticsearch arguments
						 * @param  {array} $args WP_Query arguments
						 * @return  {string} New score mode
						 */
						'score_mode' => apply_filters( 'epwr_score_mode', 'sum', $formatted_args, $args ),
						/**
						 * Filter search date weighting boost mode
						 *
						 * @hook epwr_boost_mode
						 * @param  {string} $boost_mode Current boost mode
						 * @param  {array} $formatted_args Formatted Elasticsearch arguments
						 * @param  {array} $args WP_Query arguments
						 * @return  {string} New boost mode
						 */
						'boost_mode' => apply_filters( 'epwr_boost_mode', 'multiply', $formatted_args, $args ),
					),
				);

				$formatted_args['query'] = $date_score;
			}
		}

		return $formatted_args;
	}

	/**
	 * Output feature box summary
	 *
	 * @since 3.0
	 */
	public function output_feature_box_summary() {
		?>
		<p><?php esc_html_e( 'Instantly find the content you’re looking for. The first time.', 'elasticpress' ); ?></p>
		<?php
	}

	/**
	 * Output feature box long text
	 *
	 * @since 3.0
	 */
	public function output_feature_box_long() {
		?>
		<p><?php esc_html_e( 'Overcome higher-end performance and functional limits posed by the traditional WordPress structured (SQL) database to deliver superior keyword search, instantly. ElasticPress indexes custom fields, tags, and other metadata to improve search results. Fuzzy matching accounts for misspellings and verb tenses.', 'elasticpress' ); ?></p>

		<?php
	}

	/**
	 * Enable integration on search queries
	 *
	 * @param  bool     $enabled Original enabled value
	 * @param  WP_Query $query WP Query
	 * @since  2.1
	 * @return bool
	 */
	public function integrate_search_queries( $enabled, $query ) {
		if ( ! Utils\is_integrated_request( $this->slug ) ) {
			return false;
		}

		if ( ! is_a( $query, 'WP_Query' ) ) {
			return $enabled;
		}

		if ( method_exists( $query, 'is_search' ) && $query->is_search() && ! empty( $query->query_vars['s'] ) ) {
			$enabled = true;

			/**
			 * WordPress have to be version 4.6 or newer to have "fields" support
			 * since it requires the "posts_pre_query" filter.
			 *
			 * @see WP_Query::get_posts
			 */
			$fields = $query->get( 'fields' );
			if ( ! version_compare( get_bloginfo( 'version' ), '4.6', '>=' ) && ! empty( $fields ) ) {
				$enabled = false;
			}
		}

		return $enabled;
	}

	/**
	 * Display decaying settings on dashboard.
	 *
	 * @since 2.4
	 */
	public function output_feature_box_settings() {
		$settings = $this->get_settings();

		if ( ! $settings ) {
			$settings = [];
		}

		$settings = wp_parse_args( $settings, $this->default_settings );

		?>
		<div class="field js-toggle-feature" data-feature="<?php echo esc_attr( $this->slug ); ?>">
			<div class="field-name status"><?php esc_html_e( 'Weight results by date', 'elasticpress' ); ?></div>
			<div class="input-wrap">
				<label for="decaying_enabled"><input name="decaying_enabled" id="decaying_enabled" data-field-name="decaying_enabled" class="setting-field" type="radio" <?php if ( (bool) $settings['decaying_enabled'] ) : ?>checked<?php endif; ?> value="1"><?php esc_html_e( 'Enabled', 'elasticpress' ); ?></label><br>
				<label for="decaying_disabled"><input name="decaying_enabled" id="decaying_disabled" data-field-name="decaying_enabled" class="setting-field" type="radio" <?php if ( ! (bool) $settings['decaying_enabled'] ) : ?>checked<?php endif; ?> value="0"><?php esc_html_e( 'Disabled', 'elasticpress' ); ?></label>
			</div>
		</div>
		<div class="field js-toggle-feature" data-feature="<?php echo esc_attr( $this->slug ); ?>">
			<div class="field-name status"><?php esc_html_e( 'Highlighting status', 'elasticpress' ); ?></div>
			<div class="input-wrap">
				<label for="highlighting_enabled"><input name="highlight_enabled" id="highlighting_enabled" data-field-name="highlight_enabled" class="setting-field" type="radio" <?php if ( (bool) $settings['highlight_enabled'] ) : ?>checked<?php endif; ?> value="1"><?php esc_html_e( 'Enabled', 'elasticpress' ); ?></label><br>
				<label for="highlighting_disabled"><input name="highlight_enabled" id="highlighting_disabled" data-field-name="highlight_enabled" class="setting-field" type="radio" <?php if ( ! (bool) $settings['highlight_enabled'] ) : ?>checked<?php endif; ?> value="0"><?php esc_html_e( 'Disabled', 'elasticpress' ); ?></label>
				<p class="field-description"><?php esc_html_e( 'Wrap search terms in HTML tags in results for custom styling. The wrapping HTML tag comes with the "ep-highlight" class for easy styling.' ); ?></p>
			</div>
		</div>
		<div class="field" data-feature="<?php echo esc_attr( $this->slug ); ?>">
			<label for="highlight-tag" class="field-name status"><?php echo esc_html_e( 'Highlight tag ', 'elasticpress' ); ?></label>
			<div class="input-wrap">
				<select id="highlight-tag" name="highlight-tag" class="setting-field" data-field-name="highlight_tag">
					<?php
					foreach ( self::$default_highlight_tags as $option ) :
						echo '<option value="' . esc_attr( $option ) . '" ' . selected( $option, $settings['highlight_tag'] ) . '>' . esc_html( $option ) . '</option>';
					endforeach;
					?>
				</select>
			</div>
		</div>

		<div class="field js-toggle-feature" data-feature="<?php echo esc_attr( $this->slug ); ?>">
			<div class="field-name status"><?php esc_html_e( 'Excerpt highlighting', 'elasticpress' ); ?></div>
			<div class="input-wrap">
				<label for="highlight_excerpt_enabled"><input name="highlight_excerpt" id="highlight_excerpt_enabled" class="setting-field" type="radio" <?php if ( (bool) $settings['highlight_excerpt'] ) : ?>checked<?php endif; ?>  value="1" data-field-name="highlight_excerpt"><?php esc_html_e( 'Enabled', 'elasticpress' ); ?></label><br>
				<label for="highlight_excerpt_disabled"><input name="highlight_excerpt" id="highlight_excerpt_disabled" class="setting-field" type="radio" <?php if ( ! (bool) $settings['highlight_excerpt'] ) : ?>checked<?php endif; ?>  value="0" data-field-name="highlight_excerpt"><?php esc_html_e( 'Disabled', 'elasticpress' ); ?></label>
				<p class="field-description"><?php esc_html_e( 'By default, WordPress strips HTML from content excerpts. Enable when using the_excerpt() to display search results. ', 'elasticpress' ); ?></p>
			</div>
		</div>

		<?php if ( ! defined( 'EP_IS_NETWORK' ) || ! EP_IS_NETWORK ) : ?>
			<br class="clear">
			<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=elasticpress-weighting' ) ); ?>"><?php esc_html_e( 'Advanced fields and weighting settings', 'elasticpress' ); ?></a></p>
			<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=elasticpress-synonyms' ) ); ?>"><?php esc_html_e( 'Add synonyms to your post searches', 'elasticpress' ); ?></a></p>
		<?php endif; ?>

		<?php
	}
}
