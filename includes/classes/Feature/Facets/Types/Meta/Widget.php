<?php
/**
 *
 * Meta Facet Widget
 *
 * @since 5.3.0
 * @package elasticpress
 */

namespace ElasticPress\Feature\Facets\Types\Meta;

use ElasticPress\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Meta Facet Widget class
 */
class Widget extends \WP_Widget {

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct(
			'ep-facet-meta',
			esc_html__( 'ElasticPress - Filter by Metadata', 'elasticpress' ),
			[
				'description'           => esc_html__( 'Add a meta facet filter to your sidebar or widget area.', 'elasticpress' ),
				'show_instance_in_rest' => true,
				'classname'             => 'wp-widget-elasticpress-facet widget_ep-facet-meta',
			]
		);
	}

	/**
	 * Output the widget content.
	 *
	 * @param array $args     Widget args
	 * @param array $instance Widget instance settings
	 */
	public function widget( $args, $instance ) {
		/** This filter is documented in includes/classes/Feature/Facets/Types/Taxonomy/Block.php */
		$renderer_class = apply_filters( 'ep_facet_renderer_class', __NAMESPACE__ . '\\Renderer', 'meta', 'widget', $instance );
		$renderer       = new $renderer_class();

		$renderer->render( $args, $instance );
	}

	/**
	 * Output the widget settings form.
	 *
	 * @param array $instance Widget instance settings
	 */
	public function form( $instance ) {
		$meta_fields = array_merge(
			[ '' => __( 'Select key', 'elasticpress' ) ],
			\ElasticPress\Indexables::factory()->get( 'post' )->get_distinct_meta_field_keys()
		);

		$instance = wp_parse_args(
			$instance,
			[
				'title'             => '',
				'facet'             => '',
				'orderby'           => 'count',
				'order'             => 'desc',
				'displayCount'      => false,
				'searchPlaceholder' => esc_html__( 'Search', 'elasticpress' ),
			]
		);

		$orderby_options = [
			[
				'label' => __( 'Highest to lowest count', 'elasticpress' ),
				'value' => 'count/desc',
			],
			[
				'label' => __( 'Lowest to highest count', 'elasticpress' ),
				'value' => 'count/asc',
			],
			[
				'label' => _x( 'A → Z', 'label for ordering posts by title in ascending order', 'elasticpress' ),
				'value' => 'name/asc',
			],
			[
				'label' => _x( 'Z → A', 'label for ordering posts by title in descending order', 'elasticpress' ),
				'value' => 'name/desc',
			],
		];

		?>
		<div class="widget-ep-facet-meta">
			<p>
				<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>">
					<?php esc_html_e( 'Title:', 'elasticpress' ); ?>
				</label>
				<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $instance['title'] ); ?>" />
			</p>
			<p>
				<label for="<?php echo esc_attr( $this->get_field_id( 'facet' ) ); ?>">
					<?php esc_html_e( 'Filter by:', 'elasticpress' ); ?>
				</label>
				<select id="<?php echo esc_attr( $this->get_field_id( 'facet' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'facet' ) ); ?>" class="widefat">
					<?php foreach ( $meta_fields as $meta_field ) : ?>
						<option <?php selected( $instance['facet'], $meta_field ); ?> value="<?php echo esc_attr( $meta_field ); ?>"><?php echo esc_html( $meta_field ); ?></option>
					<?php endforeach; ?>
				</select>
				<small>
					<?php
					printf(
						/* translators: %s: URL to sync content */
						esc_html__( 'This is the list of metadata fields indexed in Elasticsearch. If your desired field does not appear in this list please try to %1$ssync your content%2$s', 'elasticpress' ),
						'<a href="' . esc_url( Utils\get_sync_url( true ) ) . '">',
						'</a>'
					);
					?>
				</small>
			</p>
			<p>
				<label for="<?php echo esc_attr( $this->get_field_id( 'searchPlaceholder' ) ); ?>">
					<?php esc_html_e( 'Search field placeholder:', 'elasticpress' ); ?>
				</label>
				<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'searchPlaceholder' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'searchPlaceholder' ) ); ?>" type="text" value="<?php echo esc_attr( $instance['searchPlaceholder'] ); ?>" />
			</p>
			<p>
				<input class="checkbox" type="checkbox" <?php checked( $instance['displayCount'] ); ?> id="<?php echo esc_attr( $this->get_field_id( 'displayCount' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'displayCount' ) ); ?>" />
				<label for="<?php echo esc_attr( $this->get_field_id( 'displayCount' ) ); ?>">
					<?php esc_html_e( 'Display count', 'elasticpress' ); ?>
				</label>
			</p>
			<p>
				<label for="<?php echo esc_attr( $this->get_field_id( 'orderby_custom' ) ); ?>">
					<?php esc_html_e( 'Order by:', 'elasticpress' ); ?>
				</label><br>
				<select id="<?php echo esc_attr( $this->get_field_id( 'orderby_order' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'orderby_order' ) ); ?>" class="widefat">
					<?php foreach ( $orderby_options as $option ) : ?>
						<option <?php selected( $instance['orderby'] . '/' . $instance['order'], $option['value'] ); ?> value="<?php echo esc_attr( $option['value'] ); ?>"><?php echo esc_html( $option['label'] ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
		</div>
		<?php
	}

	/**
	 * Sanitize and save widget settings
	 *
	 * @param array $new_instance New settings
	 * @param array $old_instance Old settings
	 * @return array Sanitized settings
	 */
	public function update( $new_instance, $old_instance ) {
		// Parse order and orderby from combined field or individual fields
		$orderby_order = ! empty( $new_instance['orderby_order'] ) ? explode( '/', $new_instance['orderby_order'] ) : [];
		$order         = ! empty( $orderby_order[1] ) ? $orderby_order[1] : $new_instance['order'];
		$orderby       = ! empty( $orderby_order[0] ) ? $orderby_order[0] : $new_instance['orderby'];

		// Sanitize and store all widget settings
		$instance = [
			'title'             => sanitize_text_field( $new_instance['title'] ?? '' ),
			'facet'             => sanitize_text_field( $new_instance['facet'] ?? '' ),
			'orderby'           => sanitize_text_field( $orderby ),
			'order'             => sanitize_text_field( $order ),
			'displayCount'      => ! empty( $new_instance['displayCount'] ) ? 1 : 0,
			'searchPlaceholder' => sanitize_text_field( $new_instance['searchPlaceholder'] ?? '' ),
		];

		return $instance;
	}
}
