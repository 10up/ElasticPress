<?php
/**
 * Facets widget
 *
 * @package elasticpress
 */

namespace ElasticPress\Feature\Facets\Types\Taxonomy;

use ElasticPress\Features;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Facets widget class
 */
class Widget extends \WP_Widget {
	/**
	 * The renderer instance.
	 *
	 * @var Renderer
	 */
	public $renderer;

	/**
	 * Create widget
	 */
	public function __construct() {
		$options = array(
			'description'           => esc_html__( 'Add a facet to an archive or search results page.', 'elasticpress' ),
			'show_instance_in_rest' => true,
		);

		parent::__construct( 'ep-facet', esc_html__( 'ElasticPress - Filter by Taxonomy', 'elasticpress' ), $options );
	}

	/**
	 * Output widget
	 *
	 * @param  array $args Widget args
	 * @param  array $instance Instance settings
	 * @since 2.5, 4.2.0 made a wrapper for the renderer call.
	 */
	public function widget( $args, $instance ) {
		/** This filter is documented in includes/classes/Feature/Facets/Types/Taxonomy/Block.php */
		$renderer_class = apply_filters( 'ep_facet_renderer_class', __NAMESPACE__ . '\Renderer', 'taxonomy', 'block', $instance );
		$renderer       = new $renderer_class();

		$renderer->render( $args, $instance );
	}

	/**
	 * Get the markup for an individual facet item.
	 *
	 * @param WP_Term $term     Term object.
	 * @param string  $url      Filter URL.
	 * @param boolean $selected Whether the term is currently selected.
	 * @since 3.6.3, 4.2.0 deprecated in favor of a method in the renderer.
	 * @return string HTML for an individual facet term.
	 */
	public function get_facet_term_html( $term, $url, $selected = false ) {
		_deprecated_function( __FUNCTION__, '4.2.0', '$this->renderer->get_facet_term_html()' );

		/** This filter is documented in includes/classes/Feature/Facets/Types/Taxonomy/Block.php */
		$renderer_class = apply_filters( 'ep_facet_renderer_class', __NAMESPACE__ . '\Renderer', 'taxonomy', 'block', [] );
		$renderer       = new $renderer_class();

		return $renderer->get_facet_item_value_html( $term, $url, $selected );
	}

	/**
	 * Output widget form
	 *
	 * @param  array $instance Instance settings
	 * @since 2.5
	 */
	public function form( $instance ) {
		$dashboard_url = admin_url( 'admin.php?page=elasticpress' );

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			$dashboard_url = network_admin_url( 'admin.php?page=elasticpress' );
		}

		$feature  = Features::factory()->get_registered_feature( 'facets' );
		$settings = [];

		if ( $feature ) {
			$settings = $feature->get_settings();
		}

		$settings = wp_parse_args(
			$settings,
			array(
				'match_type' => 'all',
			)
		);

		$set     = esc_html__( 'all', 'elasticpress' );
		$not_set = esc_html__( 'any', 'elasticpress' );

		if ( 'any' === $settings['match_type'] ) {
			$set     = esc_html__( 'any', 'elasticpress' );
			$not_set = esc_html__( 'all', 'elasticpress' );
		}

		$title   = ( ! empty( $instance['title'] ) ) ? $instance['title'] : '';
		$facet   = ( ! empty( $instance['facet'] ) ) ? $instance['facet'] : '';
		$orderby = ( ! empty( $instance['orderby'] ) ) ? $instance['orderby'] : '';
		$order   = ( ! empty( $instance['order'] ) ) ? $instance['order'] : '';

		$taxonomies = $feature->types['taxonomy']->get_facetable_taxonomies();

		$orderby_options = [
			'count' => __( 'Count', 'elasticpress' ),
			'name'  => __( 'Term Name', 'elasticpress' ),
		];

		$order_options = [
			'desc' => __( 'Descending', 'elasticpress' ),
			'asc'  => __( 'Ascending', 'elasticpress' ),
		];

		?>
		<div class="widget-ep-facet">
			<p>
				<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>">
					<?php esc_html_e( 'Title:', 'elasticpress' ); ?>
				</label>
				<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
			</p>

			<p>
				<label for="<?php echo esc_attr( $this->get_field_id( 'facet' ) ); ?>">
					<?php esc_html_e( 'Taxonomy:', 'elasticpress' ); ?>
				</label><br>

				<select id="<?php echo esc_attr( $this->get_field_id( 'facet' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'facet' ) ); ?>">
					<?php foreach ( $taxonomies as $slug => $taxonomy_object ) : ?>
						<option <?php selected( $facet, $taxonomy_object->name ); ?> value="<?php echo esc_attr( $taxonomy_object->name ); ?>"><?php echo esc_html( $taxonomy_object->labels->name ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>

			<p>
				<label for="<?php echo esc_attr( $this->get_field_id( 'orderby' ) ); ?>">
					<?php esc_html_e( 'Order Terms By:', 'elasticpress' ); ?>
				</label><br>

				<select id="<?php echo esc_attr( $this->get_field_id( 'orderby' ) ); ?>"
						name="<?php echo esc_attr( $this->get_field_name( 'orderby' ) ); ?>">
					<?php foreach ( $orderby_options as $name => $title ) : ?>
						<option <?php selected( $orderby, $name ); ?>
								value="<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $title ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>

			<p>
				<label for="<?php echo esc_attr( $this->get_field_id( 'order' ) ); ?>">
					<?php esc_html_e( 'Term Order:', 'elasticpress' ); ?>
				</label><br>

				<select id="<?php echo esc_attr( $this->get_field_id( 'order' ) ); ?>"
						name="<?php echo esc_attr( $this->get_field_name( 'order' ) ); ?>">
					<?php foreach ( $order_options as $name => $title ) : ?>
						<option <?php selected( $order, $name ); ?>
								value="<?php echo esc_attr( $name ); ?>"><?php echo esc_html( $title ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>

			<?php // translators: "all" or "any", depending on configuration values, 3: URL ?>
			<p><?php echo wp_kses_post( sprintf( __( 'Faceting will  filter out any content that is not tagged to all selected terms; change this to show <strong>%1$s</strong> content tagged to <strong>%2$s</strong> selected term in <a href="%3$s">ElasticPress settings</a>.', 'elasticpress' ), $set, $not_set, esc_url( $dashboard_url ) ) ); ?></p>
		</div>

		<?php
	}

	/**
	 * Sanitize fields
	 *
	 * @param  array $new_instance New instance settings
	 * @param  array $old_instance Old instance settings
	 * @since 2.5
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = [];

		$instance['title']   = sanitize_text_field( $new_instance['title'] );
		$instance['facet']   = sanitize_text_field( $new_instance['facet'] );
		$instance['orderby'] = sanitize_text_field( $new_instance['orderby'] );
		$instance['order']   = sanitize_text_field( $new_instance['order'] );

		return $instance;
	}
}
