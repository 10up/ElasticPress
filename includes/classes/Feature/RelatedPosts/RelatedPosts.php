<?php
/**
 * ElasticPress related posts feature
 *
 * @since  2.1
 * @package elasticpress
 */

namespace ElasticPress\Feature\RelatedPosts;

use \WP_Query;
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

		$this->title = esc_html__( 'Related Posts', 'elasticpress' );

		$this->summary = '<p>' . __( 'Instantly deliver engaging and precise related content with no impact on site performance. Output related content using our block or directly in your theme using our <a href="https://elasticpress.zendesk.com/hc/en-us/articles/16671825423501-Features#related-posts">API functions</a>.', 'elasticpress' ) . '</p>';

		$this->docs_url = __( 'https://elasticpress.zendesk.com/hc/en-us/articles/360050447492-Configuring-ElasticPress-via-the-Plugin-Dashboard#related-posts', 'elasticpress' );

		$this->requires_install_reindex = false;

		parent::__construct();
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
	 * @param  int $return Return code
	 * @since  4.1.0
	 * @return WP_Query
	 */
	public function get_related_query( $post_id, $return = 5 ) {
		$args = array(
			'more_like'           => $post_id,
			'posts_per_page'      => $return,
			'ep_integrate'        => true,
			'ignore_sticky_posts' => true,
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
	 * @param  int $return Return code
	 *
	 * @since  2.1
	 * @uses get_related_query
	 *
	 * @return array|bool
	 */
	public function find_related( $post_id, $return = 5 ) {
		$query = $this->get_related_query( $post_id, $return );

		if ( ! $query->have_posts() ) {
			return false;
		}
		return $query->posts;
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

	/**
	 * Output feature box long
	 *
	 * @since 2.1
	 */
	public function output_feature_box_long() {
		?>
		<p><?php echo wp_kses_post( __( 'Output related content using our Widget or directly in your theme using our <a href="https://elasticpress.zendesk.com/hc/en-us/articles/16671825423501-Features#related-posts">API functions.</a>', 'elasticpress' ) ); ?></p>
		<?php
	}
}
