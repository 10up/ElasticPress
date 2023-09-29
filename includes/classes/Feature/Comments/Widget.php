<?php
/**
 * Search comments widget
 *
 * @since  3.6.0
 * @package  elasticpress
 */

namespace ElasticPress\Feature\Comments;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Search comment widget class
 */
class Widget extends \WP_Widget {

	/**
	 * Initialize the widget
	 *
	 * @since 3.6.0
	 */
	public function __construct() {
		$options = array(
			'description'           => esc_html__( 'A search form for comments.', 'elasticpress' ),
			'show_instance_in_rest' => true,
		);

		parent::__construct( 'ep-comments', esc_html__( 'ElasticPress - Comments', 'elasticpress' ), $options );
	}

	/**
	 * Display widget
	 *
	 * @param array $args Widget arguments
	 * @param array $instance Widget instance variables
	 * @since  3.6.0
	 */
	public function widget( $args, $instance ) {

		echo wp_kses_post( $args['before_widget'] );

		ob_start();

		if ( ! empty( $instance['title'] ) ) {
			echo wp_kses_post( $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ) . $args['after_title'] );
		}

		$wrapper_id = 'ep-search-comments-' . uniqid();
		?>
		<div id="<?php echo esc_attr( $wrapper_id ); ?>" class="ep-widget-search-comments">
			<?php
			if ( ! empty( $instance['post_type'] ) ) {
				?>
				<input
					class="ep-widget-search-comments-post-type"
					type="hidden"
					id="<?php echo esc_attr( $wrapper_id ); ?>-post-type"
					value="<?php echo esc_attr( implode( ',', $instance['post_type'] ) ); ?>"
				/>
				<?php
			}
			?>
		</div>

		<?php
		$search_comments_html = ob_get_clean();

		// Enqueue Script & Styles
		wp_enqueue_script( 'elasticpress-comments' );
		wp_enqueue_style( 'elasticpress-comments' );

		// phpcs:disable
		/**
		 * Filter comment search widget HTML
		 *
		 * @hook ep_widget_search_comments
		 * @since 3.6.0
		 * @param  {string} $search_comments_html Widget HTML
		 * @param  {string} $title Widget title
		 * @return  {string} New HTML
		 */
		echo apply_filters( 'ep_widget_search_comments', $search_comments_html, $instance['title'] );
		// phpcs:enable

		echo wp_kses_post( $args['after_widget'] );
	}

	/**
	 * Display widget settings form
	 *
	 * @param array $instance Widget instance variables
	 * @since  3.6.0
	 */
	public function form( $instance ) {
		$title     = ( isset( $instance['title'] ) ) ? $instance['title'] : '';
		$post_type = ( isset( $instance['post_type'] ) ) ? $instance['post_type'] : [];

		$post_types_options = Comments::get_searchable_post_types();
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>">
				<?php esc_html_e( 'Title:', 'elasticpress' ); ?>
			</label>

			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'post_type' ) ); ?>">
				<?php esc_html_e( 'Search for comments on:', 'elasticpress' ); ?>
			</label>

			<?php foreach ( $post_types_options as $indexable_post_type => $label ) : ?>
				<p>
					<input
						class="checkbox"
						type="checkbox"
						id="<?php echo esc_attr( $this->get_field_id( 'post_type' ) . '-' . $indexable_post_type ); ?>"
						name="<?php echo esc_attr( $this->get_field_name( 'post_type' ) ); ?>[]"
						value="<?php echo esc_attr( $indexable_post_type ); ?>"
						<?php checked( in_array( $indexable_post_type, $post_type, true ) ); ?>
					/>
					<label for="<?php echo esc_attr( $this->get_field_id( 'post_type' ) . '-' . $indexable_post_type ); ?>">
						<?php echo esc_html( $label ); ?>
					</label>
				</p>
			<?php endforeach; ?>
		</p>
		<?php
	}

	/**
	 * Update widget settings
	 *
	 * @param  array $new_instance New instance settings
	 * @param  array $old_instance Old instance settings
	 * @since  3.6.0
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {
		$instance          = [];
		$instance['title'] = sanitize_text_field( $new_instance['title'] );

		if ( is_array( $new_instance['post_type'] ) ) {
			$instance['post_type'] = array_map(
				'sanitize_text_field',
				$new_instance['post_type']
			);
		}

		return $instance;
	}
}
