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
	 * Create widget
	 */
	public function __construct() {
		$options = array(
			'description'           => esc_html__( 'Let visitors filter your content by post date.', 'elasticpress' ),
			'show_instance_in_rest' => true,
		);

		parent::__construct( 'ep-date-facet', esc_html__( 'ElasticPress - Filter by Post Date', 'elasticpress' ), $options );
	}

	/**
	 * Output widget
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
	 * Output widget form
	 *
	 * @param array $instance Instance settings
	 */
	public function form( $instance ) {
		$display_custom_date = $instance['displayCustomDate'] ?? false;
		?>
		<div class="widget-ep-date-facet">
			<p>
				<input class="checkbox" type="checkbox" <?php checked( $display_custom_date ); ?> id="<?php echo esc_attr( $this->get_field_id( 'displayCustomDate' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'displayCustomDate' ) ); ?>" />
				<label for="<?php echo esc_attr( $this->get_field_id( 'displayCustomDate' ) ); ?>">
					<?php esc_html_e( 'Display custom date option', 'elasticpress' ); ?>
				</label>
			</p>
		</div>
		<?php
	}

	/**
	 * Sanitize fields
	 *
	 * @param array $new_instance New instance settings
	 * @param array $old_instance Old instance settings
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {
		$instance                      = [];
		$instance['displayCustomDate'] = ! empty( $new_instance['displayCustomDate'] ) ? 1 : 0;

		return $instance;
	}
}
