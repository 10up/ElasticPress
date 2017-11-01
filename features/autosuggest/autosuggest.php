<?php

/**
 * Output feature box summary
 *
 * @since 2.4
 */
function ep_autosuggest_feature_box_summary() {
	?>
	<p><?php esc_html_e( 'Suggest relevant content as text is entered into the search field.', 'elasticpress' ); ?></p>
	<?php
}

/**
 * Output feature box long
 *
 * @since 2.4
 */
function ep_autosuggest_feature_box_long() {
	?>
	<p><?php esc_html_e( 'Input fields of type "search" or with the CSS class "search-field" or "ep-autosuggest" will be enhanced with autosuggest functionality. As text is entered into the search field, suggested content will appear below it, based on top search results for the text. Suggestions link directly to the content.', 'elasticpress' ); ?></p>
	<?php
}

/**
 * Setup feature functionality
 *
 * @since  2.4
 */
function ep_autosuggest_setup() {
	add_action( 'wp_enqueue_scripts', 'ep_autosuggest_enqueue_scripts' );
	add_filter( 'ep_config_mapping', 'ep_autosuggest_suggest_mapping' );
	add_filter( 'ep_post_sync_args', 'ep_autosuggest_filter_term_suggest', 10, 2 );
}

/**
 * Display decaying settings on dashboard.
 *
 * @param EP_Feature $feature Feature object.
 * @since 2.4
 */
function ep_autosugguest_settings( $feature ) {
	$host = ep_get_host();
	$settings = $feature->get_settings();

	if ( ! $settings ) {
		$settings = array();
	}

	$settings = wp_parse_args( $settings, $feature->default_settings );

	if ( preg_match( '#elasticpress\.io#i', $host ) ) {
		return;
	}
	?>

	<div class="field js-toggle-feature" data-feature="<?php echo esc_attr( $feature->slug ); ?>">
		<div class="field-name status"><label for="feature_autosuggest_endpoint_url"><?php esc_html_e( 'Endpoint URL', 'elasticpress' ); ?></label></div>
		<div class="input-wrap">
			<input value="<?php echo esc_url( $settings['endpoint_url'] ); ?>" type="text" data-field-name="endpoint_url" class="setting-field" id="feature_autosuggest_endpoint_url">
			<p class="field-description"><?php esc_html_e( 'This address will be exposed to the public.', 'elasticpress' ); ?></p>
		</div>
	</div>

	<?php
}

/**
 * Add mapping for suggest fields
 *
 * @param  array $mapping
 * @since  2.4
 * @return array
 */
function ep_autosuggest_suggest_mapping( $mapping ) {
	$mapping['mappings']['post']['properties']['post_title']['fields']['suggest'] = array(
		'type' => 'text',
		'analyzer' => 'edge_ngram_analyzer',
		'search_analyzer' => 'standard',
	);

	$mapping['settings']['analysis']['analyzer']['edge_ngram_analyzer'] = array(
		'type' => 'custom',
		'tokenizer' => 'standard',
		'filter' => array(
			'lowercase',
			'edge_ngram',
		),
	);

	$mapping['mappings']['post']['properties']['term_suggest'] = array(
		'type' => 'text',
		'analyzer' => 'edge_ngram_analyzer',
		'search_analyzer' => 'standard',
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
	$feature = ep_get_registered_feature( 'autosuggest' );

	$host = ep_get_host();

	$endpoint_url = false;

	if ( preg_match( '#elasticpress\.io#i', $host ) ) {
		$endpoint_url = $host . '/' . ep_get_index_name() . '/post/_search';
	} else {
		$settings = $feature->get_settings();

		if ( ! $settings ) {
			$settings = array();
		}

		$settings = wp_parse_args( $settings, $feature->default_settings );

		if ( empty( $settings['endpoint_url'] ) ) {
			return;
		}

		$endpoint_url = $settings['endpoint_url'];
	}

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
	 * endpointUrl:  the Elasticsearch autosuggest endpoint url
	 * postType: which post types to use for suggestions
	 * action: the action to take when selecting an item. Possible values are "search" and "navigate".
	 */
	wp_localize_script( 'elasticpress-autosuggest', 'epas', apply_filters( 'ep_autosuggest_options', array(
		'endpointUrl'  => esc_url( untrailingslashit( $endpoint_url ) ),
		'postType'     => apply_filters( 'ep_term_suggest_post_type', array( 'post', 'page' ) ),
		'postStatus'   => apply_filters( 'ep_term_suggest_post_status', 'publish' ),
		'searchFields' => apply_filters( 'ep_term_suggest_search_fields', array(
			'post_title.suggest',
			'term_suggest',
		) ),
		'action'       => 'navigate',
	) ) );
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

	$status->code = 1;

	$status->message = array();

	$status->message[] = esc_html__( 'This feature modifies the site’s default user experience by presenting a list of suggestions below detected search fields as text is entered into the field.', 'elasticpress' );

	if ( ! preg_match( '#elasticpress\.io#i', $host ) ) {
		$status->message[] = wp_kses_post( __( "You aren't using <a href='https://elasticpress.io'>ElasticPress.io</a> so we can't be sure your host is properly secured. Autosuggest requires a publicly accessible endpoint, which can expose private content and allow data modification if improperly configured.", 'elasticpress' ) );
	}

	return $status;
}

/**
 * Add autosuggest setting fields
 *
 * @since 2.4
 */
function ep_autosuggest_setup_settings() {
	add_action( 'ep_feature_box_settings_autosuggest', 'ep_autosugguest_settings', 10, 1 );
}
add_action( 'admin_init', 'ep_autosuggest_setup_settings' );

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
		'endpoint_url' => '',
	),
) );
