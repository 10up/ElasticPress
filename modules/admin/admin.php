<?php
/**
 * ElasticPress admin module
 *
 * @since  2.1
 * @package elasticpress
 */


/**
 * Setup all module filters
 *
 * @since  2.1
 */
function ep_admin_setup() {
	add_filter( 'ep_indexable_post_status', 'ep_admin_get_statuses' );
	add_filter( 'ep_admin_wp_query_integration', '__return_true' );
	add_action( 'pre_get_posts', 'ep_admin_integrate' );
	add_filter( 'ep_indexable_post_types', 'ep_admin_indexable_post_types' );
}

/**
 * Index all public post types
 *
 * @since  2.1
 * @return array
 */
function ep_admin_indexable_post_types() {
	return get_post_types( array( 'public' => true ) );
}

/**
 * Integrate EP into proper queries
 * 
 * @param  WP_Query $query
 * @since  2.1
 */
function ep_admin_integrate( $query ) {

	// Lets make sure this doesn't interfere with the CLI
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		return;
	}

	if ( ! $query->is_main_query() ) {
		return;
	}

	$post_types = ep_get_indexable_post_types();

	$supported_post_types = apply_filters( 'ep_admin_supported_post_types', $post_types );

	$post_type = $query->get( 'post_type' );

	if ( is_array( $post_type ) ) {
		foreach ( $post_type as $pt ) {
			if ( empty( $supported_post_types[$pt] ) ) {
				return;
			}
		}

		$query->set( 'ep_integrate', true );
	} else {
		if ( ! empty( $supported_post_types[$post_type] ) ) {
			$query->set( 'ep_integrate', true );
		}
	}
}

/**
 * Output module box summary
 * 
 * @since 2.1
 */
function ep_admin_module_box_summary() {
	?>
	<p><?php esc_html_e( 'The posts table can be painfully slow to load, especially when trying to filter by date, taxonomy, or meta. This module makes post, page, and custom post type lists run fast no matter what.', 'elasticpress' ); ?></p>
	<?php
}

/**
 * Output module box long
 * 
 * @since 2.1
 */
function ep_admin_module_box_long() {
	?>
	<p><?php _e( 'Within the admin panel, posts, pages, and custom post types are shown in a standarized easy to use table format. After activating an SEO plugin, increasing post per pages, and making other modifications, that table view loads very slowly.', 'elasticpress' ); ?></p>

	<p><?php _e( 'ElasticPress admin will make your admin curation experience much faster and easier. No longer will you have to wait 60 seconds to do things that should be easy such as viewing 200 posts at once.', 'elasticpress' ); ?></p>

	<p><?php _e( 'Using the search module in conjunction with this module will supercharge your admin search.', 'elasticpress' ); ?></p>
	<?php
}

/**
 * Fetches all post statuses we need to index
 *
 * @since   2.1
 * @return  array
 */
function ep_admin_get_statuses() {
	$post_statuses = get_post_stati();

	unset( $post_statuses['auto-draft'] );

	return array_values( $post_statuses );
}

/**
 * Register the module
 */
ep_register_module( 'admin', array(
	'title' => 'Admin',
	'setup_cb' => 'ep_admin_setup',
	'module_box_summary_cb' => 'ep_admin_module_box_summary',
	'module_box_long_cb' => 'ep_admin_module_box_long',
	'requires_install_reindex' => true,
) );

