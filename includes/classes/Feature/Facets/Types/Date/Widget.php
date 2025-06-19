<?php
/**
 * Date Facet Widget
 *
 * @since 5.3.0
 * @package elasticpress
 */

namespace ElasticPress\Feature\Facets\Types\Date;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Date Facet Widget class
 */
class Widget extends \WP_Widget {

	/**
	 * Create widget.
	 */
	public function __construct() {
		$options = array(
			'description'           => esc_html__( 'Let visitors filter your content by post date.', 'elasticpress' ),
			'show_instance_in_rest' => true,
			'classname'             => 'wp-widget-elasticpress-facet widget_ep-facet-date',
		);

		parent::__construct( 'ep-facet-date', esc_html__( 'ElasticPress - Filter by Post Date', 'elasticpress' ), $options );
	}

	/**
	 * Output widget.
	 *
	 * @param array $args Widget args
	 * @param array $instance Instance settings
	 */
	public function widget( $args, $instance ) {
		// Enqueue the front-end script.
		wp_enqueue_script( 'ep-facets-date-block-view-script' );

		/** This filter is documented in includes/classes/Feature/Facets/Types/Taxonomy/Block.php */
		$renderer_class = apply_filters( 'ep_facet_renderer_class', __NAMESPACE__ . '\\Renderer', 'date', 'widget', $instance );
		$renderer       = new $renderer_class();

		$renderer->render( $args, $instance );
	}

	/**
	 * Output widget form.
	 *
	 * @param array $instance Instance settings
	 */
	public function form( $instance ) {
		$instance = wp_parse_args(
			$instance,
			[
				'title'             => '',
				'displayCustomDate' => false,
			]
		);

		?>
		<div class="widget-ep-facet-date">
			<p>
				<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'elasticpress' ); ?></label>
				<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $instance['title'] ); ?>" />
			</p>
			<p>
				<input class="checkbox" type="checkbox" <?php checked( $instance['displayCustomDate'] ); ?> id="<?php echo esc_attr( $this->get_field_id( 'displayCustomDate' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'displayCustomDate' ) ); ?>" />
				<label for="<?php echo esc_attr( $this->get_field_id( 'displayCustomDate' ) ); ?>">
					<?php esc_html_e( 'Display custom date option', 'elasticpress' ); ?>
				</label>
			</p>
		</div>
		<?php
	}

	/**
	 * Update widget settings
	 *
	 * @param array $new_instance New instance settings
	 * @param array $old_instance Old instance settings
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {
		$instance                      = [];
		$instance['title']             = ! empty( $new_instance['title'] ) ? sanitize_text_field( $new_instance['title'] ) : '';
		$instance['displayCustomDate'] = ! empty( $new_instance['displayCustomDate'] );

		return $instance;
	}
}
