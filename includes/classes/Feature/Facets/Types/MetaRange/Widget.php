<?php
/**
 * Meta Range Facets widget
 *
 * @since 5.3.0
 * @package elasticpress
 */

namespace ElasticPress\Feature\Facets\Types\MetaRange;

use ElasticPress\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Meta Range Facets widget class
 */
class Widget extends \WP_Widget {

	/**
	 * Create widget
	 */
	public function __construct() {
		$options = array(
			'description'           => esc_html__( 'Let visitors filter your content by a range of metadata values.', 'elasticpress' ),
			'show_instance_in_rest' => true,
			'classname'             => 'wp-widget-elasticpress-facet widget_ep-facet-meta-range',
		);

		parent::__construct( 'ep-facet-meta-range', esc_html__( 'ElasticPress - Filter by Metadata Range - Beta', 'elasticpress' ), $options );
	}

	/**
	 * Output widget
	 *
	 * @param  array $args Widget args
	 * @param  array $instance Instance settings
	 */
	public function widget( $args, $instance ) {
		// Enqueue the front-end script
		wp_enqueue_script( 'ep-facets-meta-range-block-view-script' );

		/** This filter is documented in includes/classes/Feature/Facets/Types/MetaRange/Block.php */
		$renderer_class = apply_filters( 'ep_facet_renderer_class', __NAMESPACE__ . '\Renderer', 'meta-range', 'widget', $instance );
		$renderer       = new $renderer_class();

		$renderer->render( $args, $instance );
	}

	/**
	 * Output widget form
	 *
	 * @param  array $instance Instance settings
	 */
	public function form( $instance ) {
		$instance = wp_parse_args(
			$instance,
			[
				'title'  => '',
				'facet'  => '',
				'prefix' => '',
				'suffix' => '',
			]
		);

		$meta_fields = array_merge(
			[ '' => __( 'Select key', 'elasticpress' ) ],
			\ElasticPress\Indexables::factory()->get( 'post' )->get_distinct_meta_field_keys()
		);

		?>
		<div class="widget-ep-facet-meta-range">
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
				<label for="<?php echo esc_attr( $this->get_field_id( 'prefix' ) ); ?>">
					<?php esc_html_e( 'Value Prefix:', 'elasticpress' ); ?>
				</label>
				<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'prefix' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'prefix' ) ); ?>" type="text" value="<?php echo esc_attr( $instance['prefix'] ); ?>" />
			</p>
			<p>
				<label for="<?php echo esc_attr( $this->get_field_id( 'suffix' ) ); ?>">
					<?php esc_html_e( 'Value Suffix:', 'elasticpress' ); ?>
				</label>
				<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'suffix' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'suffix' ) ); ?>" type="text" value="<?php echo esc_attr( $instance['suffix'] ); ?>" />
			</p>
		</div>
		<?php
	}

	/**
	 * Sanitize fields
	 *
	 * @param  array $new_instance New instance settings
	 * @param  array $old_instance Old instance settings
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = [];

		$instance['title']  = sanitize_text_field( $new_instance['title'] );
		$instance['facet']  = sanitize_text_field( $new_instance['facet'] );
		$instance['prefix'] = sanitize_text_field( $new_instance['prefix'] );
		$instance['suffix'] = sanitize_text_field( $new_instance['suffix'] );

		return $instance;
	}
}
