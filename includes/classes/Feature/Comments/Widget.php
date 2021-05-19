<?php
/**
 * Search comments widget
 *
 * @since  3.6
 * @package  elasticpress
 */

namespace ElasticPress\Feature\Comments;

use \WP_Widget as WP_Widget;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Search comment widget class
 */
class Widget extends WP_Widget {

	/**
	 * Initialize the widget
	 *
	 * @since 3.6
	 */
	public function __construct() {
		$options = array( 'description' => esc_html__( 'A search form for comments.', 'elasticpress' ) );
		parent::__construct( 'ep-related-posts', esc_html__( 'ElasticPress - Comments', 'elasticpress' ), $options );
	}

	/**
	 * Display widget
	 *
	 * @param array $args Widget arguments
	 * @param array $instance Widget instance variables
	 * @since  3.6
	 */
	public function widget( $args, $instance ) {

		ob_start();

		if ( ! empty( $instance['title'] ) ) {
			echo wp_kses_post( $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ) . $args['after_title'] );
		}
		?>

		<div class="ep-widget-search-comments"></div>

		<?php
		$comments_search_form = ob_get_clean();

		// Enqueue Script & Styles
		wp_enqueue_script(
			'elasticpress-comments',
			EP_URL . 'dist/js/comments-script.min.js',
			[],
			EP_VERSION,
			true
		);

		wp_enqueue_style(
			'elasticpress-comments',
			EP_URL . 'dist/css/comments-styles.min.css',
			[],
			EP_VERSION
		);

		wp_localize_script(
			'elasticpress-comments',
			'epc',
			[
				'restApiEndpoint'    => get_rest_url( null, 'elasticpress/v1/comments' ),
				/**
				 * Filter text to be showed when no results were found
				 *
				 * @since  3.6
				 * @hook ep_widget_search_comments_no_results_found_text
				 * @param  {string} $text Default text when no results were found
				 * @return  {string} New text to be showed when no results were found
				 */
				'noResultsFoundText' => esc_html( apply_filters( 'ep_widget_search_comments_no_results_found_text', __( 'We could not find any results', 'elasticpress' ) ) ),
			]
		);

		echo wp_kses_post( $comments_search_form );
	}

	/**
	 * Display widget settings form
	 *
	 * @param array $instance Widget instance variables
	 * @since  3.6
	 */
	public function form( $instance ) {
		$title = ( isset( $instance['title'] ) ) ? $instance['title'] : '';
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>">
				<?php esc_html_e( 'Title:', 'elasticpress' ); ?>
			</label>

			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
		</p>
		<?php
	}

	/**
	 * Update widget settings
	 *
	 * @param  array $new_instance New instance settings
	 * @param  array $old_instance Old instance settings
	 * @since  2.2
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {

		$instance          = [];
		$instance['title'] = sanitize_text_field( $new_instance['title'] );

		return $instance;
	}
}
