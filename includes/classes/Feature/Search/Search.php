<?php
/**
 * Search feature
 *
 * @since  1.9
 * @package  elasticpress
 */

namespace ElasticPress\Feature\Search;

use ElasticPress\Feature;
use ElasticPress\Features;
use ElasticPress\Indexables;
use ElasticPress\Utils;

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

		$this->summary = '<p>' . __( 'Instantly find the content you’re looking for. The first time.', 'elasticpress' ) . '</p>' .
			'<p>' . __( 'Overcome higher-end performance and functional limits posed by the traditional WordPress structured (SQL) database to deliver superior keyword search, instantly. ElasticPress indexes custom fields, tags, and other metadata to improve search results. Fuzzy matching accounts for misspellings and verb tenses.', 'elasticpress' ) . '</p>';

		$this->docs_url = __( 'https://elasticpress.zendesk.com/hc/en-us/articles/360050447492-Configuring-ElasticPress-via-the-Plugin-Dashboard#post-search', 'elasticpress' );

		$this->requires_install_reindex = false;

		$this->default_settings = [
			'decaying_enabled'     => '1',
			'synonyms_editor_mode' => 'simple',
			'highlight_enabled'    => '0',
			'highlight_excerpt'    => '0',
			'highlight_tag'        => 'mark',
		];

		$this->available_during_installation = true;

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

		add_action( 'init', [ $this, 'register_meta' ], 20 );
		add_filter( 'ep_prepare_meta_allowed_keys', [ $this, 'add_exclude_from_search' ] );
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_block_editor_assets' ] );
		add_filter( 'ep_post_filters', [ $this, 'exclude_posts_from_search' ], 10, 3 );
		add_action( 'post_submitbox_misc_actions', [ $this, 'output_exclude_from_search_setting' ] );
		add_action( 'edit_post', [ $this, 'save_exclude_from_search_meta' ] );
		add_filter( 'ep_skip_query_integration', [ $this, 'skip_query_integration' ], 10, 2 );

		add_action( 'attachment_submitbox_misc_actions', [ $this, 'output_exclude_from_search_setting' ], 15 );
		add_action( 'edit_attachment', [ $this, 'save_exclude_from_search_meta' ] );
	}


	/**
	 * Enqueue styles for highlighting.
	 */
	public function enqueue_scripts() {
		$settings = $this->get_settings();

		if ( '1' !== $settings['highlight_enabled'] ) {
			return;
		}

		wp_enqueue_style(
			'searchterm-highlighting',
			EP_URL . 'dist/css/highlighting-styles.css',
			Utils\get_asset_info( 'highlighting-styles', 'dependencies' ),
			Utils\get_asset_info( 'highlighting-styles', 'version' )
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

		if ( '1' !== $settings['highlight_enabled'] ) {
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
		$opening_tag = '<' . $highlight_tag . " class='" . $highlight_class . "'>";
		$closing_tag = '</' . $highlight_tag . '>';

		foreach ( $fields_to_highlight as $field ) {
			$formatted_args['highlight']['fields'][ $field ] = [
				'pre_tags'            => [ $opening_tag ],
				'post_tags'           => [ $closing_tag ],
				'type'                => 'plain',
				/**
				 * Filter the maximum number of fragments highlighted for a searched field.
				 *
				 * @since 4.7.2
				 * @hook ep_highlight_number_of_fragments
				 * @param  {int}    $max_fragments Maximum number of fragments for field.
				 * @param  {string} $field Search field being setup.
				 * @return {int}    New maximum number of fragments to highlight for the searched field.
				 */
				'number_of_fragments' => apply_filters( 'ep_highlight_number_of_fragments', 0, $field ),
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
		if ( ! Utils\is_integrated_request( 'highlighting', [ 'public' ] ) ) {
			return;
		}

		$settings = $this->get_settings();

		if ( ! empty( $settings['highlight_excerpt'] ) && '1' === $settings['highlight_excerpt'] ) {
			remove_filter( 'get_the_excerpt', 'wp_trim_excerpt' );
			add_filter( 'get_the_excerpt', [ $this, 'ep_highlight_excerpt' ], 10, 2 );
			add_filter( 'ep_highlighting_fields', [ $this, 'ep_highlight_add_excerpt_field' ] );
		}
	}

	/**
	 * Called by allow_excerpt_html
	 * logic for the excerpt filter allowing the currently selected tag.
	 *
	 * @param string  $text excerpt string
	 * @param WP_Post $post Post Object
	 *
	 * @return string $text the new excerpt
	 */
	public function ep_highlight_excerpt( $text, $post ) {

		$settings = $this->get_settings();

		// reproduces wp_trim_excerpt filter, preserving the excerpt_more and excerpt_length filters
		if ( '' === $text ) {
			$text = get_the_content( '', false, $post );
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
			$settings['search']['highlight_excerpt'] = $settings['search']['highlight_excerpt'];
		}

		if ( ! empty( $settings['search']['highlight_enabled'] ) ) {
			$settings['search']['highlight_enabled'] = $settings['search']['highlight_enabled'];
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
	 * @param array $args WP_Query args
	 *
	 * @return bool
	 */
	public function is_decaying_enabled( $args = [] ) {
		$settings = $this->get_settings();

		$is_decaying_enabled = $settings['decaying_enabled'] && '0' !== $settings['decaying_enabled'];

		/**
		 * Filter to modify decaying
		 *
		 * @hook ep_is_decaying_enabled
		 * @since 4.6.0
		 * @param {bool}  $is_decaying_enabled Whether decay by date is enabled or not
		 * @param {array} $settings            Settings
		 * @param {array} $args                WP_Query args
		 * @return {bool} Decaying
		 */
		return apply_filters( 'ep_is_decaying_enabled', $is_decaying_enabled, $settings, $args );
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
		if ( empty( $args['s'] ) ) {
			return $formatted_args;
		}

		if ( ! $this->is_decaying_enabled( $args ) ) {
			return $formatted_args;
		}

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

		/**
		 * Filter search date weighting field
		 *
		 * @hook epwr_decay_field
		 * @param  {string} $field Current decay field
		 * @param  {array} $formatted_args Formatted Elasticsearch arguments
		 * @param  {array} $args WP_Query arguments
		 * @return  {string} New decay field
		 * @since 4.3.0
		 */
		$field      = apply_filters( 'epwr_decay_field', 'post_date_gmt', $formatted_args, $args );
		$date_score = array(
			'function_score' => array(
				'query'      => $formatted_args['query'],
				'functions'  => array(
					array(
						$decay_function => array(
							$field => array(
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
								 * @param  {float} $decay Current decay
								 * @param  {array} $formatted_args Formatted Elasticsearch arguments
								 * @param  {array} $args WP_Query arguments
								 * @return  {float} New decay
								 */
								'decay'  => apply_filters( 'epwr_decay', 0.25, $formatted_args, $args ),
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
						 * @param  {float} $weight Current weight
						 * @param  {array} $formatted_args Formatted Elasticsearch arguments
						 * @param  {array} $args WP_Query arguments
						 * @return  {float} New weight
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

		return $formatted_args;
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

		if ( isset( $query->query_vars['ep_integrate'] ) && ! filter_var( $query->query_vars['ep_integrate'], FILTER_VALIDATE_BOOLEAN ) ) {
			return false;
		}

		if ( method_exists( $query, 'is_search' ) && $query->is_search() && ! empty( $query->query_vars['s'] ) ) {
			$enabled = true;
		}

		/**
		 * Filter whether to enable integration on search queries or not.
		 *
		 * @hook ep_integrate_search_queries
		 * @since 4.2.0
		 * @param {bool}     $enabled Original enabled value
		 * @param {WP_Query} $query   WP_Query
		 * @return {bool} New $enabled value
		 */
		return apply_filters( 'ep_integrate_search_queries', $enabled, $query );
	}

	/**
	 * Display decaying settings on dashboard.
	 *
	 * @since 2.4
	 */
	public function output_feature_box_settings() {
		$settings = $this->get_settings();
		?>
		<div class="field">
			<div class="field-name status"><?php esc_html_e( 'Weight results by date', 'elasticpress' ); ?></div>
			<div class="input-wrap">
				<label><input name="settings[decaying_enabled]" type="radio" <?php checked( (bool) $settings['decaying_enabled'] ); ?> value="1"><?php esc_html_e( 'Enabled', 'elasticpress' ); ?></label><br>
				<label><input name="settings[decaying_enabled]" type="radio" <?php checked( ! (bool) $settings['decaying_enabled'] ); ?> value="0"><?php esc_html_e( 'Disabled', 'elasticpress' ); ?></label><br>
				<?php
				/**
				 * Fires after the default Weight results by date settings
				 *
				 * @since  4.6.0
				 * @hook ep_weight_settings_after_search
				 * @param  {array} $settings settings array
				 */
				do_action( 'ep_weight_settings_after_search', $settings );
				?>
			</div>
		</div>
		<div class="field">
			<div class="field-name status"><?php esc_html_e( 'Highlighting status', 'elasticpress' ); ?></div>
			<div class="input-wrap">
				<label><input name="settings[highlight_enabled]" type="radio" <?php checked( $settings['highlight_enabled'], '1' ); ?> value="1"><?php esc_html_e( 'Enabled', 'elasticpress' ); ?></label><br>
				<label><input name="settings[highlight_enabled]" type="radio" <?php checked( $settings['highlight_enabled'], '0' ); ?> value="0"><?php esc_html_e( 'Disabled', 'elasticpress' ); ?></label>
				<p class="field-description"><?php esc_html_e( 'Wrap search terms in HTML tags in results for custom styling. The wrapping HTML tag comes with the "ep-highlight" class for easy styling.' ); ?></p>
			</div>
		</div>
		<div class="field">
			<label for="highlight-tag" class="field-name status"><?php echo esc_html_e( 'Highlight tag ', 'elasticpress' ); ?></label>
			<div class="input-wrap">
				<select id="highlight-tag" name="settings[highlight_tag]">
					<?php
					foreach ( self::$default_highlight_tags as $option ) :
						echo '<option value="' . esc_attr( $option ) . '" ' . selected( $option, $settings['highlight_tag'] ) . '>' . esc_html( $option ) . '</option>';
					endforeach;
					?>
				</select>
			</div>
		</div>

		<div class="field">
			<div class="field-name status"><?php esc_html_e( 'Excerpt highlighting', 'elasticpress' ); ?></div>
			<div class="input-wrap">
				<label><input name="settings[highlight_excerpt]" type="radio" <?php checked( $settings['highlight_excerpt'], '1' ); ?> value="1"><?php esc_html_e( 'Enabled', 'elasticpress' ); ?></label><br>
				<label><input name="settings[highlight_excerpt]" type="radio" <?php checked( $settings['highlight_excerpt'], '0' ); ?> value="0"><?php esc_html_e( 'Disabled', 'elasticpress' ); ?></label>
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

	/**
	 * Registers post meta for exclude from search feature.
	 */
	public function register_meta() {
		register_post_meta(
			'',
			'ep_exclude_from_search',
			[
				'show_in_rest' => true,
				'single'       => true,
				'type'         => 'boolean',
			]
		);
	}

	/**
	 * Add ep_exclude_from_search to the allowed meta fields list.
	 *
	 * @since 5.0.0
	 * @param array $keys List of allowed meta fields
	 * @return array
	 */
	public function add_exclude_from_search( $keys ) {
		$keys[] = 'ep_exclude_from_search';
		return $keys;
	}

	/**
	 * Enqueue block editor assets.
	 */
	public function enqueue_block_editor_assets() {
		global $post;

		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		wp_enqueue_script(
			'ep-search-editor',
			EP_URL . 'dist/js/search-editor-script.js',
			Utils\get_asset_info( 'search-editor-script', 'dependencies' ),
			Utils\get_asset_info( 'search-editor-script', 'version' ),
			true
		);

		wp_set_script_translations( 'ep-search-editor', 'elasticpress' );
	}

	/**
	 * Exclude posts based on ep_exclude_from_search post meta.
	 *
	 * @param array    $filters Filters to be applied to the query
	 * @param array    $args WP Query args
	 * @param WP_Query $query WP Query object
	 */
	public function exclude_posts_from_search( $filters, $args, $query ) {
		$bypass_exclusion_from_search = is_admin() || ! $query->is_search();
		/**
		 * Filter whether the exclusion from the "exclude from search" checkbox should be applied
		 *
		 * @since 4.4.0
		 * @hook ep_bypass_exclusion_from_search
		 * @param  {bool}     $bypass_exclusion_from_search  True means all posts will be returned
		 * @param  {WP_Query} $query                         WP Query
		 * @return {bool} New $bypass_exclusion_from_search value
		 */
		if ( apply_filters( 'ep_bypass_exclusion_from_search', $bypass_exclusion_from_search, $query ) ) {
			return $filters;
		}

		$filters[] = [
			'bool' => [
				'must_not' => [
					[
						'terms' => [
							'meta.ep_exclude_from_search.raw' => [ '1' ],
						],
					],
				],
			],
		];

		return $filters;
	}

	/**
	 * Outputs the checkbox to exclude a post from search.
	 *
	 * @param WP_POST $post Post object.
	 */
	public function output_exclude_from_search_setting( $post ) {
		$searchable_post_types = $this->get_searchable_post_types();
		if ( ! in_array( $post->post_type, $searchable_post_types, true ) ) {
			return;
		}
		?>
		<div class="misc-pub-section">
			<input id="ep_exclude_from_search" name="ep_exclude_from_search" type="checkbox" value="1" <?php checked( get_post_meta( get_the_ID(), 'ep_exclude_from_search', true ) ); ?>>
			<label for="ep_exclude_from_search"><?php esc_html_e( 'Exclude from search results', 'elasticpress' ); ?></label>
			<p class="howto">
				<?php if ( 'attachment' === $post->post_type ) : ?>
					<?php esc_html_e( 'Excludes this media from the results of your site\'s search form while ElasticPress is active.', 'elasticpress' ); ?>
				<?php else : ?>
					<?php esc_html_e( 'Excludes this post from the results of your site\'s search form while ElasticPress is active.', 'elasticpress' ); ?>
				<?php endif; ?>
			</p>
			<?php wp_nonce_field( 'save-exclude-from-search', 'ep-exclude-from-search-nonce' ); ?>
		</div>
		<?php
	}

	/**
	 * Saves exclude from search meta.
	 *
	 * @param int $post_id The post ID.
	 */
	public function save_exclude_from_search_meta( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! isset( $_POST['ep-exclude-from-search-nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['ep-exclude-from-search-nonce'] ), 'save-exclude-from-search' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $_POST['ep_exclude_from_search'] ) ) {
			update_post_meta( $post_id, 'ep_exclude_from_search', true );
		} else {
			delete_post_meta( $post_id, 'ep_exclude_from_search' );
		}

	}

	/**
	 * If WP_Query has unsupported orderby, skip ES query integration and use the WP query instead.
	 *
	 * @param bool      $skip Whether to skip ES query integration
	 * @param \WP_Query $query WP_Query object
	 *
	 * @since 4.5
	 * @return bool
	 */
	public function skip_query_integration( $skip, $query ) {
		if ( ! $query instanceof \WP_Query ) {
			return $skip;
		}

		$unsupported_orderby = [
			'post__in',
			'post_name__in',
			'post_parent__in',
			'parent',
		];

		$orderby = is_string( $query->get( 'orderby' ) ) ? explode( ' ', $query->get( 'orderby' ) ) : $query->get( 'orderby', 'date' );

		$parse_orderby = array();
		foreach ( $orderby as $key => $value ) {
			$parse_orderby[] = is_string( $key ) ? $key : $value;
		}

		if ( array_intersect( $parse_orderby, $unsupported_orderby ) ) {
			return true;
		}

		return $skip;
	}

	/**
	 * Set the `settings_schema` attribute
	 *
	 * @since 5.0.0
	 */
	protected function set_settings_schema() {
		$this->settings_schema = [
			[
				'default' => '1',
				'key'     => 'decaying_enabled',
				'label'   => __( 'Weighting by date', 'elasticpress' ),
				'options' => [
					[
						'label' => __( 'Don\'t weight results by date', 'elasticpress' ),
						'value' => '0',
					],
					[
						'label' => __( 'Weight results by date', 'elasticpress' ),
						'value' => '1',
					],
				],
				'type'    => 'radio',
			],
			[
				'default' => '0',
				'help'    => __( 'Enable to wrap search terms in HTML tags in results for custom styling. The wrapping HTML tag comes with the <code>ep-highlight</code> class for easy styling.' ),
				'key'     => 'highlight_enabled',
				'label'   => __( 'Highlight search terms', 'elasticpress' ),
				'type'    => 'checkbox',
			],
			[
				'default' => '0',
				'help'    => __( 'By default, WordPress strips HTML from content excerpts. Enable when using <code>the_excerpt()</code> to display search results.', 'elasticpress' ),
				'key'     => 'highlight_excerpt',
				'label'   => __( 'Highlight search terms in excerpts', 'elasticpress' ),
				'type'    => 'checkbox',
			],
			[
				'default' => 'mark',
				'help'    => __( 'Select the HTML tag used to highlight search terms.', 'elasticpress' ),
				'key'     => 'highlight_tag',
				'label'   => __( 'Highlight tag', 'elasticpress' ),
				'options' => [
					[
						'label' => __( 'None', 'elasticpress' ),
						'value' => '',
					],
					[
						'label' => 'mark',
						'value' => 'mark',
					],
					[
						'label' => 'span',
						'value' => 'span',
					],
					[
						'label' => 'strong',
						'value' => 'strong',
					],
					[
						'label' => 'em',
						'value' => 'em',
					],
					[
						'label' => 'i',
						'value' => 'i',
					],
				],
				'type'    => 'select',
			],
			[
				'default' => 'simple',
				'key'     => 'synonyms_editor_mode',
				'type'    => 'hidden',
			],
		];

		if ( ! defined( 'EP_IS_NETWORK' ) || ! EP_IS_NETWORK ) {
			$weighting_url = esc_url( admin_url( 'admin.php?page=elasticpress-weighting' ) );
			$synonyms_url  = esc_url( admin_url( 'admin.php?page=elasticpress-synonyms' ) );

			$text = sprintf(
				'<p><a href="%1$s">%2$s</a></p><p><a href="%3$s">%4$s</a></p>',
				$weighting_url,
				__( 'Advanced fields and weighting settings', 'elasticpress' ),
				$synonyms_url,
				__( 'Add synonyms to your post searches', 'elasticpress' ),
			);

			$this->settings_schema[] = [
				'key'   => 'additional_links',
				'label' => $text,
				'type'  => 'markup',
			];
		}
	}
}
