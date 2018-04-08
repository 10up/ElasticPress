<?php

/**
 * Setup hooks and filters for feature
 *
 * @since 2.5
 */
function ep_facets_setup() {
	add_action( 'widgets_init', 'ep_facets_register_widgets' );
	add_action( 'ep_retrieve_raw_response', 'ep_facets_get_aggs' );
	add_action( 'pre_get_posts', 'ep_facets_facet_query' );
	add_action( 'admin_enqueue_scripts', 'ep_facets_admin_scripts' );
	add_action( 'wp_enqueue_scripts', 'ep_facets_front_scripts' );
	add_action( 'admin_footer', 'ep_facets_widget_js_templates' );
}

/**
 * Output Underscores templates needed for widget
 *
 * @since  2.5
 */
function ep_facets_widget_js_templates() {
	$taxonomies = get_taxonomies( array( 'public' => true ), 'object' );
	?>
	<script type="text/html" id="tmpl-ep-facets-widget-facet">
		<p class="facet">
			<select id="{{{ data.fieldId }}}" name="{{{ data.fieldName }}}">
				<option value="0"><?php esc_html_e( 'Choose Taxonomy', 'elasticpress' ); ?>
				<?php foreach ( $taxonomies as $slug => $taxonomy_object ) : ?>
					<option value="<?php echo esc_attr( $taxonomy_object->name ); ?>"><?php echo esc_html( $taxonomy_object->labels->name ); ?></option>
				<?php endforeach; ?>
			</select>

			<a class="delete-facet" title="<?php esc_attr_e( 'Delete Facet', 'elasticpress' ); ?>"></a>
		</p>
	</script>
	<?php
}

/**
 * Output scripts for widget admin
 *
 * @param  string $hook
 * @since  2.5
 */
function ep_facets_admin_scripts( $hook ) {
	if ( 'widgets.php' !== $hook ) {
        return;
    }

    $js_url = ( defined( 'SCRIPT_DEBUG' ) && true === SCRIPT_DEBUG ) ? EP_URL . 'features/facets/assets/js/src/admin.js' : EP_URL . 'features/facets/assets/js/admin.min.js';
    $css_url = ( defined( 'SCRIPT_DEBUG' ) && true === SCRIPT_DEBUG ) ? EP_URL . 'features/facets/assets/css/admin.css' : EP_URL . 'features/facets/assets/css/admin.min.css';

	wp_enqueue_script(
		'elasticpress-facets-admin',
		$js_url,
		array( 'jquery', 'jquery-ui-sortable' ),
		EP_VERSION,
		true
	);

	wp_enqueue_style(
		'elasticpress-facets-admin',
		$css_url,
		array(),
		EP_VERSION
	);
}

/**
 * Output front end facets styles
 *
 * @since 2.5
 */
function ep_facets_front_scripts() {
	$js_url = ( defined( 'SCRIPT_DEBUG' ) && true === SCRIPT_DEBUG ) ? EP_URL . 'features/facets/assets/js/src/facets.js' : EP_URL . 'features/facets/assets/js/facets.min.js';
    $css_url = ( defined( 'SCRIPT_DEBUG' ) && true === SCRIPT_DEBUG ) ? EP_URL . 'features/facets/assets/css/facets.css' : EP_URL . 'features/facets/assets/css/facets.min.css';

	wp_enqueue_script(
		'elasticpress-facets',
		$js_url,
		array( 'jquery', 'underscore' ),
		EP_VERSION,
		true
	);

	wp_enqueue_style(
		'elasticpress-facets',
		$css_url,
		array(),
		EP_VERSION
	);
}

/**
 * Figure out if we can/should facet the query
 *
 * @param  WP_Query $query
 * @since  2.5
 * @return bool
 */
function ep_facets_is_facetable( $query ) {
	if ( is_admin() ) {
		return false;
	}

	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		return false;
	}

	if ( ! $query->is_main_query() ) {
		return false;
	}

	if ( ! ( $query->is_archive() || $query->is_search() || ( is_home() && empty( $query->get( 'page_id' ) ) ) ) ) {
		return false;
	}

	return true;
}
/**
 * We enable ElasticPress facet on all archive/search queries as well as non-static home pages. There is no way to know
 * when a facet widget is used before the main query is executed so we enable EP
 * everywhere where a facet widget could be used.
 *
 * @since  2.5
 */
function ep_facets_facet_query( $query ) {
	if ( ! ep_facets_is_facetable( $query) ) {
		return;
	}

	$taxonomies = get_taxonomies( array( 'public' => true ) );

	if ( empty( $taxonomies ) ) {
		return;
	}

	$query->set( 'ep_integrate', true );

    $facets = array();

	foreach ( $taxonomies as $slug ) {
		$facets[ $slug ] = array(
			'terms' => array(
				'size' => 1000,
				'field' => 'terms.' . $slug . '.slug',
			),
		);
	}

	$aggs = array(
		'name' => 'terms',
    	'use-filter' => true,
    	'aggs' => $facets,
    );

	$query->set( 'aggs', $aggs );

	$selected_filters = ep_facets_get_selected();

	$tax_query = $query->get( 'tax_query', array() );

	foreach ( $selected_filters['taxonomies'] as $taxonomy => $terms ) {
		$tax_query[] = [
			'taxonomy' => $taxonomy,
			'field'    => 'slug',
			'terms'    => array_keys( $terms ),
			'operator' => 'and',
		];
	}

	$query->set( 'tax_query', $tax_query );
}

/**
 * Hacky. Save aggregation data for later in a global
 *
 * @param  array $response
 * @since  2.5
 */
function ep_facets_get_aggs( $response ) {
	$response_body = wp_remote_retrieve_body( $response );
	$response = json_decode( $response_body, true );

	$GLOBALS['ep_facet_aggs'] = false;

	if ( ! empty( $response['aggregations'] ) ) {
		$GLOBALS['ep_facet_aggs'] = array();

		foreach ( $response['aggregations']['terms'] as $key => $agg ) {
			if ( 'doc_count' === $key ) {
				continue;
			}

			$GLOBALS['ep_facet_aggs'][ $key ] = array();

			foreach ( $agg['buckets'] as $bucket ) {
				$GLOBALS['ep_facet_aggs'][ $key ][ $bucket['key'] ] = $bucket['doc_count'];
			}

		}
	}
}

/**
 * Get currently selected facets from query args
 *
 * @since  2.5
 * @return array
 */
function ep_facets_get_selected() {
	$filters = array(
		'taxonomies' => array(),
	);

	foreach ( $_GET as $key => $value ) {
		if ( 0 === strpos( $key, 'filter_taxonomy' ) ) {
			$taxonomy = str_replace( 'filter_taxonomy_', '', $key );

			$filters['taxonomies'][ $taxonomy ] = array_fill_keys( array_map( 'trim', explode( ',', trim( $value, ',' ) ) ), true );
		}
	}

	return $filters;
}

/**
 * Register facet widget(s)
 *
 * @since 2.5
 */
function ep_facets_register_widgets() {
	require_once( dirname( __FILE__ ) . '/class-ep-facet-widget.php' );

	register_widget( 'EP_Facet_Widget' );
}

/**
 * Output feature box summary
 *
 * @since 2.5
 */
function ep_facets_feature_box_summary() {
	?>
	<p><?php esc_html_e( 'Add controls to your website to filter content by one or more taxonomies.', 'elasticpress' ); ?></p>
	<?php
}

/**
 * Output feature box long
 *
 * @since 2.5
 */
function ep_facets_feature_box_long() {
	?>
	<p><?php echo wp_kses_post( sprintf( __( "Adds a <a href='%s'>Facet widget</a> that administrators can add to the website's sidebars (widgetized areas), so that visitors can filter applicable content and search results by one or more taxonomy terms.", 'elasticpress' ), esc_url( admin_url( 'widgets.php' ) ) ) ); ?></p>
	<?php
}

/**
 * Register the feature
 *
 * @since  2.5
 */
ep_register_feature( 'facets', array(
	'title'                    => 'Facets',
	'setup_cb'                 => 'ep_facets_setup',
	'feature_box_summary_cb'   => 'ep_facets_feature_box_summary',
	'feature_box_long_cb'      => 'ep_facets_feature_box_long',
	'requires_install_reindex' => false,
) );
