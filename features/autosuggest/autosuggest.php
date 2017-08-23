<?php

/**
 * Output feature box summary
 * 
 * @since 2.4
 */
function ep_autosuggest_feature_box_summary() {
	?>
	<p><?php esc_html_e( 'Add autosuggest to ElasticPress powered search fields on the front end.', 'elasticpress' ); ?></p>
	<?php
}

/**
 * Output feature box long
 * 
 * @since 2.4
 */
function ep_autosuggest_feature_box_long() {
	?>
	<p><?php esc_html_e( 'Autosuggest is a very powerful search feature. As a user types a search query, they are automatically suggested items. Autosuggest dramatically increases that users will find what they are looking for on your site, improving overall experience.', 'elasticpress' ); ?></p>
	<?php
}

/**
 * Setup feature functionality
 *
 * @since  2.4
 */
function ep_autosuggest_setup() {
	add_action( 'wp_enqueue_scripts', 'ep_autosuggest_enqueue_scripts' );
	add_filter( 'ep_config_mapping', 'ep_autosuggest_completion_mapping' );
	add_filter( 'ep_post_sync_args', 'ep_autosuggest_filter_term_suggest', 10, 2 );
	add_filter( 'ep_post_sync_args_post_prepare_meta', 'ep_autosuggest_no_blank_title', 10, 2 );
	add_action( 'ep_feature_box_settings_autosuggest', 'ep_autosugguest_settings', 10, 1 );
}

/**
 * Display decaying settings on dashboard.
 *
 * @param EP_Feature $feature Feature object.
 * @since 2.4
 */
function ep_autosugguest_settings( $feature ) {
	$settings = $feature->get_settings();

	if ( ! $settings ) {
		$settings = array();
	}
	$settings = wp_parse_args( $settings, $feature->default_settings );
	?>

	<div class="field js-toggle-feature" data-feature="<?php echo esc_attr( $feature->slug ); ?>">
		<div class="field-name status"><label for="feature_autosuggest_endpoint"><?php esc_html_e( 'Endpoint Address', 'elasticpress' ); ?></label></div>
		<div class="input-wrap">
			<input value="<?php echo esc_url( $settings['endpoint'] ); ?>" type="text" data-field-name="endpoint" class="setting-field" id="feature_autosuggest_endpoint">
		</div>
	</div>

<?php
}

/**
 * Blank titles dont work with the completion mapping type
 *
 * @param  array $post_args
 * @param  int $post_id
 * @since  2.4
 * @return array
 */
function ep_autosuggest_no_blank_title( $post_args, $post_id ) {
	if ( empty( $post_args['post_title'] ) ) {
		unset( $post_args['post_title'] );
	}

	return $post_args;
}

/**
 * Add mapping for completion fields
 * 
 * @param  array $mapping
 * @since  2.4
 * @return array
 */
function ep_autosuggest_completion_mapping( $mapping ) {
	$mapping['mappings']['post']['properties']['post_title']['fields']['completion'] = array(
		'type' => 'completion',
		'analyzer' => 'simple',
		'search_analyzer' => 'simple',
	);

	$mapping['mappings']['post']['properties']['term_suggest'] = array(
		'type' => 'completion',
		'analyzer' => 'simple',
		'search_analyzer' => 'simple',
	);

	return $mapping;
}

/**
 * Add term suggestions to be indexed
 *
 * @param $post_args
 * @param $post_id
 * @since  2.4
 * @return array
 */
function ep_autosuggest_filter_term_suggest( $post_args, $post_id ) {
	$suggest = array();

	if ( ! empty( $post_args['terms'] ) ) {
		foreach ( $post_args['terms'] as $taxonomy ) {
			foreach ( $taxonomy as $term ) {
				$suggest[] = $term['name'];
			}
		}
	}

	if ( ! empty( $suggest ) ) {
		$post_args['term_suggest'] = $suggest;
	}

	return $post_args;
}

/**
 * Enqueue our autosuggest script
 *
 * @since  2.4
 */
function ep_autosuggest_enqueue_scripts() {

	$js_url = ( defined( 'SCRIPT_DEBUG' ) && true === SCRIPT_DEBUG ) ? EP_URL . 'features/autosuggest/assets/js/src/autosuggest.js' : EP_URL . 'features/autosuggest/assets/js/autosuggest.min.js';
	$css_url = ( defined( 'SCRIPT_DEBUG' ) && true === SCRIPT_DEBUG ) ? EP_URL . 'features/autosuggest/assets/css/autosuggest.css' : EP_URL . 'features/autosuggest/assets/css/autosuggest.min.css';

	wp_enqueue_script(
		'elasticpress-autosuggest',
		$js_url,
		array( 'jquery' ),
		EP_VERSION,
		true
	);

	wp_enqueue_style(
		'elasticpress-autosuggest',
		$css_url,
		array(),
		EP_VERSION
	);

	/**
	 * Output variables to use in Javascript
	 * index: the Elasticsearch index name
	 * host:  the Elasticsearch host
	 * postType: which post types to use for suggestions
	 * action: the action to take when selecting an item. Possible values are "search" and "navigate".
	 */
	wp_localize_script( 'elasticpress-autosuggest', 'epas', array(
		'index' => ep_get_index_name( get_current_blog_id() ),
		'host'  => apply_filters( 'epas_host', ep_get_host() ),
		'postType' => apply_filters( 'epas_term_suggest_post_type', 'all' ),
		'action' => apply_filters( 'epas_click_action', 'search' ),
	) );
}

/**
 * Determine WC feature reqs status
 *
 * @param  EP_Feature_Requirements_Status $status
 * @since  2.4
 * @return EP_Feature_Requirements_Status
 */
function ep_autosuggest_requirements_status( $status ) {
	$host = ep_get_host();

	if ( ! preg_match( '#elasticpress\.io#i', $host ) ) {
		$status->code = 1;
		$status->message = __( "You aren't using <a href='https://elasticpress.io'>ElasticPress.io</a> so we can't be sure your Elasticsearch instance is secure.", 'elasticpress' );
	}

	return $status;
}

/**
 * Register the feature
 *
 * @since  2.4
 */
ep_register_feature( 'autosuggest', array(
	'title' => 'Autosuggest',
	'setup_cb' => 'ep_autosuggest_setup',
	'feature_box_summary_cb' => 'ep_autosuggest_feature_box_summary',
	'feature_box_long_cb' => 'ep_autosuggest_feature_box_long',
	'requires_install_reindex' => true,
	'requirements_status_cb' => 'ep_autosuggest_requirements_status',
	'default_settings' => array(
		'endpoint' => '',
	),
) );
