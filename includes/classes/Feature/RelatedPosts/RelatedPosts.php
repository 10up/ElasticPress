<?php
/**
 * ElasticPress related posts feature
 *
 * @since  2.1
 * @package elasticpress
 */

namespace ElasticPress\Feature\RelatedPosts;

use ElasticPress\Feature as Feature;
use ElasticPress\Elasticsearch as Elasticsearch;
use ElasticPress\Post\Post as Post;
use \WP_Query as WP_Query;

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
			$new_mlt = version_compare( Elasticsearch::factory()->get_elasticsearch_version(), 6.0, '>=' );

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
	 * @since  2.1
	 * @return array|bool
	 */
	public function find_related( $post_id, $return = 5 ) {
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
		$query = new WP_Query( apply_filters( 'ep_find_related_args', $args ) );

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
		register_rest_route(
			'wp/v2',
			'/posts/(?P<id>[0-9]+)/related',
			[
				'methods'  => 'GET',
				'callback' => [ $this, 'output_endpoint' ],
				'args'     => [
					'id'     => [
						'description' => 'Post ID.',
						'type'        => 'numeric',
					],
					'number' => [
						'description' => 'Number of posts',
						'type'        => 'numeric',
						'default'     => 5,
					],
				],
			]
		);
	}

	/**
	 * Output related posts endpoint
	 *
	 * @param  \WP_REST_Request $request REST request
	 * @since  3.2
	 * @return \WP_REST_Response
	 */
	public function output_endpoint( $request ) {
		$id = $request['id'];

		$posts          = $this->find_related( $id, (int) $request['number'] );
		$prepared_posts = [];

		if ( ! empty( $posts ) ) {
			foreach ( $posts as $post ) {
				$prepared_post = [];

				$prepared_post['id']           = $post->ID;
				$prepared_post['link']         = get_permalink( $post->ID );
				$prepared_post['status']       = $post->post_status;
				$prepared_post['title']        = [
					'raw'      => $post->post_title,
					'rendered' => get_the_title( $post->ID ),
				];
				$prepared_post['author']       = (int) $post->post_author;
				$prepared_post['parent']       = (int) $post->post_parent;
				$prepared_post['menu_order']   = (int) $post->menu_order;
				$prepared_post['content']      = [
					'rendered' => post_password_required( $post ) ? '' : apply_filters( 'the_content', $post->post_content ),
				];
				$prepared_post['date']         = $post->post_date;
				$prepared_post['date_gmt']     = $post->post_date_gmt;
				$prepared_post['modified']     = $post->post_modified;
				$prepared_post['modified_gmt'] = $post->post_modified_gmt;

				$prepared_posts[] = $prepared_post;
			}
		}

		$response = new \WP_REST_Response();
		$response->set_data( $prepared_posts );

		return $response;
	}

	/**
	 * Register gutenberg block
	 *
	 * @since  3.2
	 */
	public function register_block() {
		wp_register_script(
			'elasticpress-related-posts-block',
			EP_URL . 'dist/js/related-posts-block-script.min.js',
			[
				'wp-blocks',
				'wp-element',
				'wp-editor',
				'wp-api-fetch',
			],
			EP_VERSION,
			true
		);

		// The wp-edit-blocks style dependency is not needed on the front end of the site.
		$style_dependencies = is_admin() ? [ 'wp-edit-blocks' ] : [];

		wp_register_style(
			'elasticpress-related-posts-block',
			EP_URL . 'dist/css/related-posts-block-styles.min.css',
			$style_dependencies,
			EP_VERSION
		);

		register_block_type(
			'elasticpress/related-posts',
			[
				'attributes'      => [
					'number' => [
						'type'    => 'number',
						'default' => 5,
					],
					'align'  => [
						'type' => 'string',
						'enum' => [ 'left', 'center', 'right', 'wide', 'full' ],
					],
				],
				'editor_script'   => 'elasticpress-related-posts-block',
				'editor_style'    => 'elasticpress-related-posts-block',
				'style'           => 'elasticpress-related-posts-block',
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

		if ( ! empty( $attributes['className'] ) ) {
			$class .= ' ' . $attributes['className'];
		}

		ob_start();
		?>
		<section class="<?php echo esc_attr( $class ); ?>">
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
	 * Output feature box summary
	 *
	 * @since 2.1
	 */
	public function output_feature_box_summary() {
		?>
		<p><?php esc_html_e( 'ElasticPress understands data in real time, so it can instantly deliver engaging and precise related content with no impact on site performance.', 'elasticpress' ); ?></p>
		<?php
	}

	/**
	 * Output feature box long
	 *
	 * @since 2.1
	 */
	public function output_feature_box_long() {
		?>
		<p><?php echo wp_kses_post( __( 'Output related content using our Widget or directly in your theme using our <a href="https://github.com/10up/ElasticPress/#related-posts">API functions.</a>', 'elasticpress' ) ); ?></p>
		<?php
	}
}
