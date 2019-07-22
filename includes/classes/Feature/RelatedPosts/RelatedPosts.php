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
					'fields'          => apply_filters(
						'ep_related_posts_fields',
						array(
							'post_title',
							'post_content',
							'terms.post_tag.name',
						)
					),
					'min_term_freq'   => 1,
					'max_query_terms' => 12,
					'min_doc_freq'    => 1,
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
			'more_like'      => $post_id,
			'posts_per_page' => $return,
			'ep_integrate'   => true,
		);

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
