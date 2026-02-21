<?php
/**
 * ElasticPress related posts feature
 *
 * @since  2.1
 * @package elasticpress
 */

namespace ElasticPress\Feature\RelatedPosts;

use WP_Query;
use ElasticPress\Elasticsearch;
use ElasticPress\Feature;
use ElasticPress\REST;
use ElasticPress\Utils;

/**
 * Related posts feature class
 */
class RelatedPosts extends Feature {
	/**
	 * Initialize feature setting it's config
	 *
	 * @since  3.0
	 */
	public function __construct() {
		$this->slug = 'related_posts';

		$this->group = 'core-search';

		$this->requires_install_reindex = false;

		$this->default_settings = [
			'decaying_enabled' => '0',
		];

		parent::__construct();
	}

	/**
	 * Sets i18n strings.
	 *
	 * @return void
	 * @since 5.2.0
	 */
	public function set_i18n_strings(): void {
		$this->title = esc_html__( 'Related Posts', 'elasticpress' );

		$this->summary = '<p>' . __( 'Instantly deliver engaging and precise related content with no impact on site performance. Output related content using our block or directly in your theme using our <a href="https://www.elasticpress.io/documentation/article/related-posts-api/">API functions</a>.', 'elasticpress' ) . '</p>';

		$this->docs_url = __( 'https://www.elasticpress.io/documentation/article/configuring-elasticpress-via-the-plugin-dashboard/#related-posts', 'elasticpress' );
	}

	/**
	 * Format args for related posts
	 *
	 * @param  array $formatted_args Formatted ES args
	 * @param  array $args WP_Query args
	 * @return array
	 */
	public function formatted_args( $formatted_args, $args ) {
		if ( ! empty( $args['more_like'] ) ) {
			// lets compare ES version to see if new MLT structure applies
			$new_mlt = version_compare( (string) Elasticsearch::factory()->get_elasticsearch_version(), 6.0, '>=' );

			if ( $new_mlt && is_array( $args['more_like'] ) ) {
				foreach ( $args['more_like'] as $id ) {
					$ids[] = array( '_id' => $id );
				}
			} elseif ( $new_mlt && ! is_array( $args['more_like'] ) ) {
				$ids = array( '_id' => $args['more_like'] );
			} else {
				$ids = is_array( $args['more_like'] ) ? $args['more_like'] : array( $args['more_like'] );
			}

			$mlt_key = ( $new_mlt ) ? 'like' : 'ids';

			$formatted_args['query'] = array(
				'more_like_this' => array(
					$mlt_key          => $ids,
					/**
					 * Filter fields used to determine related posts
					 *
					 * @hook ep_related_posts_fields
					 * @param  {array} $fields Related post fields
					 * @return  {array} New fields
					 */
					'fields'          => apply_filters(
						'ep_related_posts_fields',
						array(
							'post_title',
							'post_content',
							'terms.post_tag.name',
						)
					),
					/**
					 * Filter related posts minimum term frequency
					 *
					 * @hook ep_related_posts_min_term_freq
					 * @param  {int} $minimum Minimum term frequency
					 * @return  {array} New value
					 */
					'min_term_freq'   => apply_filters( 'ep_related_posts_min_term_freq', 1 ),
					/**
					 * Filter related posts maximum query terms
					 *
					 * @hook ep_related_posts_max_query_terms
					 * @param  {int} $maximum Maximum query terms
					 * @return  {array} New value
					 */
					'max_query_terms' => apply_filters( 'ep_related_posts_max_query_terms', 12 ),
					/**
					 * Filter related posts minimum document frequency
					 *
					 * @hook ep_related_posts_min_doc_freq
					 * @param  {int} $minimum Minimum document frequency
					 * @return  {array} New value
					 */
					'min_doc_freq'    => apply_filters( 'ep_related_posts_min_doc_freq', 1 ),
				),
			);
		}

		return $formatted_args;
	}

	/**
	 * Search Elasticsearch for related content
	 *
	 * @param  int $post_id Post ID
	 * @param  int $post_return Number of posts to return
	 * @since  4.1.0
	 * @return WP_Query
	 */
	public function get_related_query( $post_id, $post_return = 5 ) {
		$args = array(
			'more_like'           => $post_id,
			'posts_per_page'      => $post_return,
			'ep_integrate'        => true,
			'ignore_sticky_posts' => true,
			'orderby'             => 'relevance',
		);

		/**
		 * Filter WP Query related post arguments
		 *
		 * @hook ep_find_related_args
		 * @param  {array} $args WP Query arguments
		 * @since  2.1
		 * @return  {array} New arguments
		 */
		return new WP_Query( apply_filters( 'ep_find_related_args', $args ) );
	}

	/**
	 * Search Elasticsearch for related content
	 *
	 * @param  int $post_id Post ID
	 * @param  int $post_return Number of posts to return
	 *
	 * @since  2.1
	 * @uses get_related_query
	 *
	 * @return array|bool
	 */
	public function find_related( $post_id, $post_return = 5 ) {
		$query = $this->get_related_query( $post_id, $post_return );

		if ( ! $query->have_posts() ) {
			return false;
		}
		return $query->posts;
	}

	/**
	 * Set the `settings_schema` attribute.
	 *
	 * @since 5.4.0
	 */
	protected function set_settings_schema() {
		$this->settings_schema = [
			[
				'default' => '0',
				'help'    => __( 'When enabled, newer content will be favored over older content in related posts results.', 'elasticpress' ),
				'key'     => 'decaying_enabled',
				'label'   => __( 'Weight related posts by date', 'elasticpress' ),
				'type'    => 'checkbox',
			],
		];
	}

	/**
	 * Returns whether date decay is enabled for related posts.
	 *
	 * @since 5.4.0
	 * @param array $args WP_Query args.
	 * @return bool
	 */
	public function is_decaying_enabled( $args = [] ) {
		$settings = $this->get_settings();

		$is_decaying_enabled = ! empty( $settings['decaying_enabled'] ) && '0' !== $settings['decaying_enabled'];

		/**
		 * Filter to enable or disable decay by date for related posts.
		 *
		 * @hook ep_related_posts_is_decaying_enabled
		 * @since 5.4.0
		 * @param {bool}  $is_decaying_enabled Whether decay by date is enabled or not.
		 * @param {array} $settings            Related Posts feature settings.
		 * @param {array} $args                WP_Query args.
		 * @return {bool} New value.
		 */
		return apply_filters( 'ep_related_posts_is_decaying_enabled', $is_decaying_enabled, $settings, $args );
	}

	/**
	 * Weight more recent content in related posts results.
	 *
	 * @param array $formatted_args Formatted ES args.
	 * @param array $args WP_Query args.
	 * @since 5.4.0
	 * @return array
	 */
	public function weight_recent( $formatted_args, $args ) {
		if ( empty( $args['more_like'] ) ) {
			return $formatted_args;
		}

		if ( ! $this->is_decaying_enabled( $args ) ) {
			return $formatted_args;
		}

		/**
		 * Filter related posts date weighting decay function.
		 *
		 * @hook epwr_related_posts_decay_function
		 * @since 5.4.0
		 * @param {string} $decay_function Current decay function.
		 * @param {array}  $formatted_args Formatted Elasticsearch arguments.
		 * @param {array}  $args           WP_Query arguments.
		 * @return {string} New decay function.
		 */
		$decay_function = apply_filters( 'epwr_related_posts_decay_function', 'exp', $formatted_args, $args );

		/**
		 * Filter related posts date weighting field.
		 *
		 * @hook epwr_related_posts_decay_field
		 * @since 5.4.0
		 * @param {string} $field          Current decay field.
		 * @param {array}  $formatted_args Formatted Elasticsearch arguments.
		 * @param {array}  $args           WP_Query arguments.
		 * @return {string} New decay field.
		 */
		$field = apply_filters( 'epwr_related_posts_decay_field', 'post_date_gmt', $formatted_args, $args );

		$date_score = array(
			'function_score' => array(
				'query'      => $formatted_args['query'],
				'functions'  => array(
					array(
						$decay_function => array(
							$field => array(
								/**
								 * Filter related posts date weighting scale.
								 *
								 * @hook epwr_related_posts_scale
								 * @since 5.4.0
								 * @param {string} $scale          Current scale.
								 * @param {array}  $formatted_args Formatted Elasticsearch arguments.
								 * @param {array}  $args           WP_Query arguments.
								 * @return {string} New scale.
								 */
								'scale'  => apply_filters( 'epwr_related_posts_scale', '360d', $formatted_args, $args ),
								/**
								 * Filter related posts date weighting decay.
								 *
								 * @hook epwr_related_posts_decay
								 * @since 5.4.0
								 * @param {float} $decay          Current decay.
								 * @param {array} $formatted_args Formatted Elasticsearch arguments.
								 * @param {array} $args           WP_Query arguments.
								 * @return {float} New decay.
								 */
								'decay'  => apply_filters( 'epwr_related_posts_decay', 0.5, $formatted_args, $args ),
								/**
								 * Filter related posts date weighting offset.
								 *
								 * @hook epwr_related_posts_offset
								 * @since 5.4.0
								 * @param {string} $offset         Current offset.
								 * @param {array}  $formatted_args Formatted Elasticsearch arguments.
								 * @param {array}  $args           WP_Query arguments.
								 * @return {string} New offset.
								 */
								'offset' => apply_filters( 'epwr_related_posts_offset', '30d', $formatted_args, $args ),
							),
						),
					),
					array(
						/**
						 * Filter related posts date weight.
						 *
						 * @hook epwr_related_posts_weight
						 * @since 5.4.0
						 * @param {float} $weight         Current weight.
						 * @param {array} $formatted_args Formatted Elasticsearch arguments.
						 * @param {array} $args           WP_Query arguments.
						 * @return {float} New weight.
						 */
						'weight' => apply_filters( 'epwr_related_posts_weight', 0.001, $formatted_args, $args ),
					),
				),
				/**
				 * Filter related posts date weighting score mode.
				 *
				 * @hook epwr_related_posts_score_mode
				 * @since 5.4.0
				 * @param {string} $score_mode     Current score mode.
				 * @param {array}  $formatted_args Formatted Elasticsearch arguments.
				 * @param {array}  $args           WP_Query arguments.
				 * @return {string} New score mode.
				 */
				'score_mode' => apply_filters( 'epwr_related_posts_score_mode', 'sum', $formatted_args, $args ),
				/**
				 * Filter related posts date weighting boost mode.
				 *
				 * @hook epwr_related_posts_boost_mode
				 * @since 5.4.0
				 * @param {string} $boost_mode     Current boost mode.
				 * @param {array}  $formatted_args Formatted Elasticsearch arguments.
				 * @param {array}  $args           WP_Query arguments.
				 * @return {string} New boost mode.
				 */
				'boost_mode' => apply_filters( 'epwr_related_posts_boost_mode', 'multiply', $formatted_args, $args ),
			),
		);

		$formatted_args['query'] = $date_score;

		return $formatted_args;
	}

	/**
	 * Setup all feature filters
	 *
	 * @since  2.1
	 */
	public function setup() {
		add_action( 'widgets_init', [ $this, 'register_widget' ] );
		add_filter( 'widget_types_to_hide_from_legacy_widget_block', [ $this, 'hide_legacy_widget' ] );
		add_filter( 'ep_formatted_args', [ $this, 'formatted_args' ], 10, 2 );
		add_filter( 'ep_formatted_args', [ $this, 'weight_recent' ], 11, 2 );
		add_action( 'init', [ $this, 'register_block' ] );
		add_action( 'rest_api_init', [ $this, 'setup_endpoint' ] );
	}

	/**
	 * Setup REST endpoints
	 *
	 * @since  3.2
	 */
	public function setup_endpoint() {
		$controller = new REST\RelatedPosts();
		$controller->register_routes();
	}

	/**
	 * Register gutenberg block
	 *
	 * @since  3.2
	 */
	public function register_block() {
		/**
		 * Registering it here so translation works
		 *
		 * @see https://core.trac.wordpress.org/ticket/54797#comment:20
		 */
		wp_register_script(
			'ep-related-posts-block-script',
			EP_URL . 'dist/js/related-posts-block-script.js',
			Utils\get_asset_info( 'related-posts-block-script.js', 'dependencies' ),
			Utils\get_asset_info( 'related-posts-block-script.js', 'version' ),
			true
		);

		wp_set_script_translations( 'ep-related-posts-block-script', 'elasticpress' );

		register_block_type_from_metadata(
			EP_PATH . 'assets/js/blocks/related-posts',
			[
				'render_callback' => [ $this, 'render_block' ],
			]
		);
	}

	/**
	 * Render Gutenberg block
	 *
	 * @param  array $attributes Block attributes
	 * @since  3.2
	 * @return string
	 */
	public function render_block( $attributes ) {
		$posts = $this->find_related( get_the_ID(), $attributes['number'] );

		if ( empty( $posts ) ) {
			return '';
		}

		$class = 'wp-block-elasticpress-related-posts';

		if ( ! empty( $attributes['align'] ) ) {
			$class .= ' align' . $attributes['align'];
		}

		ob_start();

		$wrapper_attributes = get_block_wrapper_attributes( [ 'class' => $class ] );
		?>
		<section <?php echo wp_kses_data( $wrapper_attributes ); ?>">
			<ul>
				<?php foreach ( $posts as $related_post ) : ?>
					<li>
						<a href="<?php echo esc_url( get_permalink( $related_post->ID ) ); ?>">
							<?php echo wp_kses( get_the_title( $related_post->ID ), 'ep-html' ); ?>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
		<?php

		$block_content = ob_get_clean();

		return $block_content;
	}

	/**
	 * Register related posts widget
	 *
	 * @since  2.2
	 */
	public function register_widget() {
		register_widget( __NAMESPACE__ . '\Widget' );
	}

	/**
	 * Hide the legacy widget.
	 *
	 * Hides the legacy widget in favor of the Block when the block editor
	 * is in use and the legacy widget has not been used.
	 *
	 * @since 4.3
	 * @param array $widgets An array of excluded widget-type IDs.
	 * @return array array of excluded widget-type IDs to hide.
	 */
	public function hide_legacy_widget( $widgets ) {
		$widgets[] = 'ep-related-posts';

		return $widgets;
	}
}
