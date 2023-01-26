<?php
/**
 * Class responsible for rendering the block.
 *
 * @since 4.5.0
 * @package elasticpress
 */

namespace ElasticPress\Feature\Facets\Types\MetaRange;

use ElasticPress\Features as Features;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Facets render class
 */
class Renderer {
	/**
	 * Holds the meta field selected.
	 *
	 * @var string
	 */
	protected $meta_field = '';

	/**
	 * Output the widget or block HTML.
	 *
	 * @param array $args     Widget args
	 * @param array $instance Instance settings
	 */
	public function render( $args, $instance ) {
		$this->meta_field = $instance['facet'];

		if ( ! $this->should_render() ) {
			return;
		}

		$feature    = Features::factory()->get_registered_feature( 'facets' );
		$facet_type = $feature->types['meta-range'];

		$min_field_name = $facet_type->get_filter_name() . $this->meta_field . '_min';
		if ( empty( $GLOBALS['ep_facet_aggs'][ $min_field_name ] ) ) {
			return;
		} else {
			$min = $GLOBALS['ep_facet_aggs'][ $min_field_name ];
		}

		$max_field_name = $facet_type->get_filter_name() . $this->meta_field . '_max';
		if ( empty( $GLOBALS['ep_facet_aggs'][ $max_field_name ] ) ) {
			return;
		} else {
			$max = $GLOBALS['ep_facet_aggs'][ $max_field_name ];
		}

		$selected_filters = $feature->get_selected();
		unset( $selected_filters[ $facet_type->get_filter_type() ][ $this->meta_field ] );
		$form_action = wp_parse_url( $feature->build_query_url( $selected_filters ) );
		wp_parse_str( $form_action['query'], $filter_fields );
		?>
		<form action="<?php echo esc_url( $_SERVER['REQUEST_URI'] ); ?>">
			<?php foreach ( $filter_fields as $field => $value ) { ?>
				<input type="hidden" name="<?php echo esc_attr( $field ); ?>" value="<?php echo esc_attr( $value ); ?>">
			<?php } ?>
			<label for="">
				Min.
				<input type="number" name="<?php echo esc_attr( $min_field_name ); ?>" min="<?php echo absint( $min ); ?>" max="<?php echo absint( $max ); ?>">
			</label>
			<label for="">
				Max.
				<input type="number" name="<?php echo esc_attr( $max_field_name ); ?>" min="<?php echo absint( $min ); ?>" max="<?php echo absint( $max ); ?>">
			</label>
			<input type="submit" value="<?php esc_attr_e( 'Filter', 'elasticpress' ); ?>">
		</form>
		<p>From <?php echo absint( $min ); ?> to <?php echo absint( $max ); ?></p>
		<?php
	}

	/**
	 * Determine if the block/widget should or not be rendered.
	 *
	 * @return boolean
	 */
	protected function should_render() : bool {
		global $wp_query;

		if ( empty( $this->meta_field ) ) {
			return false;
		}

		$feature = Features::factory()->get_registered_feature( 'facets' );
		if ( $wp_query->get( 'ep_facet', false ) && ! $feature->is_facetable( $wp_query ) ) {
			return false;
		}

		$es_success = ( ! empty( $wp_query->elasticsearch_success ) ) ? true : false;
		if ( ! $es_success ) {
			return false;
		}

		return true;
	}
}
