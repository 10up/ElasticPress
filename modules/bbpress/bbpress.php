<?php
/**
 * ElasticPress bbPress
 *
 * @since  2.1
 * @package elasticpress
 */


/**
 * Setup all module filters
 *
 * @since  2.1
 */
function ep_bbp_setup() {
	add_filter( 'ep_prepare_meta_whitelist_key', 'ep_bbp_whitelist_meta_keys', 10, 3 );
	add_action( 'pre_get_posts', 'ep_bbp_search', 10, 1 );

	/**
	 * We have to do this in case the search module isn't active
	 */
	if ( ! is_admin() ) {
		add_filter( 'ep_elasticpress_enabled', 'ep_integrate_search_queries', 10, 2 );
	}
}

function ep_bbp_search( $query ) {
	if ( is_admin() ) {
		return;
	}

	/**
	 * Make sure this is an ElasticPress search query
	 */
	if ( ! ep_elasticpress_enabled( $query ) || ! $query->is_search() ) {
		return;
	}

	/**
	 * Make sure we are on a bbpress search page
	 */
	if ( empty( get_query_var( 'bbp_search' ) ) ) {
		return;
	}

	$query->set( 'search_fields', array(
		'post_title',
		'post_content',
		'post_excerpt',
		'author_name',
		'taxonomies' => array( 'topic-tag' ),
    ) );
}

/**
 * In case the search module isn't active
 * 
 * @param  bool $enabled
 * @param  WP_Query $query
 * @since  2.1
 * @return bool
 */
function ep_bbp_integrate_search_queries( $enabled, $query ) {
	if ( isset( $query->query_vars['ep_integrate'] ) && false === $query->query_vars['ep_integrate'] ) {
		$enabled = false;
	} else if ( method_exists( $query, 'is_search' ) && $query->is_search() && ! empty( $query->query_vars['s'] ) ) {
		$enabled = true;
	}

	return $enabled;
}

/**
 * Index BBPress meta
 *
 * @param   boolean $whitelist
 * @param   string $key
 * @param   array $post
 * @since   2.1
 * @return  boolean
 */
function ep_bbp_whitelist_meta_keys( $whitelist, $key, $post ) {
	if ( preg_match( '#^_bbp#i', $key ) ) {
		return true;
	}

	return $whitelist;
}

/**
 * Output module box summary
 * 
 * @since 2.1
 */
function ep_bbp_module_box_summary() {
	?>
	<p><?php esc_html_e( 'Speed up bbPress and improve forum search relevancy. Enable forums users to more quickly and effectively find useful content.', 'elasticpress' ); ?></p>
	<?php
}

/**
 * Output module box long
 * 
 * @since 2.1
 */
function ep_bbp_module_box_long() {
	?>
	<p><?php _e( 'This module will improve bbPress forum search relevancy by enabling search to scan authors, topic tags, weight recent results, etc.', 'elasticpress' ); ?></p>
	<p><?php _e( 'Additionally, overall bbPress performance will improve with faster page load times.', 'elasticpress' ); ?></p>
	<?php
}

/**
 * Make sure bbPress is activated
 *
 * @since  2.1
 * @return bool|WP_Error
 */
function ep_bp_dependencies_met_cb() {
	if ( class_exists( 'bbPress' ) ) {
		return true;
	} else {
		return new WP_Error( 'ep-no-bbpress', esc_html__( 'bbPress must be active to use this module.', 'elasticpress' ) );
	}
}

/**
 * Register the module
 */
ep_register_module( 'bbpress', array(
	'title' => 'bbPress',
	'setup_cb' => 'ep_bbp_setup',
	'module_box_summary_cb' => 'ep_bbp_module_box_summary',
	'module_box_long_cb' => 'ep_bbp_module_box_long',
	'requires_install_reindex' => true,
	'dependencies_met_cb' => 'ep_bp_dependencies_met_cb',
) );

