<?php
/**
 * ElasticPress The Events Calendar module
 *
 * @since  2.1
 * @package elasticpress
 */

/**
 * Setup all module filters
 *
 * @since  2.1
 */
function ep_tec_setup() {
	add_action( 'pre_get_posts', 'ep_tec_integrate' );
	add_filter( 'ep_prepare_meta_whitelist_key', 'ep_tec_whitelist_meta_keys', 10, 3 );
	add_filter( 'ep_sync_taxonomies', 'ep_tec_whitelist_taxonomies', 10, 2 );
	add_filter( 'ep_indexable_post_types', 'ep_tec_post_types', 10, 1 );
}

/**
 * Index taxonomies
 *
 * @param   array $taxonomies Index taxonomies array.
 * @param   array $post Post properties array.
 * @since   2.1
 * @return  array
 */
function ep_tec_whitelist_taxonomies( $taxonomies, $post ) {
	$taxonomies[] = 'tribe_events_cat';

	return $taxonomies;
}

/**
 * Index post types
 *
 * @param   array $post_types Existing post types.
 * @since   2.1
 * @return  array
 */
function ep_tec_post_types( $post_types ) {
	return array_unique( array_merge( $post_types, array(
		'tribe_events' => 'tribe_events',
		'tribe_venue' => 'tribe_venue',
		'tribe_organizer' => 'tribe_organizer',
	) ) );
}

/**
 * Integrate with relevent TEC queries
 * @param  WP_Query $query
 * @since  2.1
 */
function ep_tec_integrate( $query ) {
	if ( is_admin() ) {
		return;
	}

	$post_types = (array) $query->get( 'post_type', array() );

	$integrate_post_types = array(
		'tribe_events',
		'tribe_venue',
		'tribe_organizer',
	);

	foreach ( $integrate_post_types as $post_type ) {
		if ( in_array( $post_type, $post_types ) ) {
			$query->set( 'ep_integrate', true );
		}
	}
}

/**
 * Index meta
 *
 * @param   boolean $whitelist
 * @param   string $key
 * @param   array $post
 * @since   2.1
 * @return  boolean
 */
function ep_tec_whitelist_meta_keys( $whitelist, $key, $post ) {
	if ( preg_match( '#^(_Event|_Venue|_Organizer)#i', $key ) ) {
		return true;
	}

	return $whitelist;
}

/**
 * Output module box summary
 * 
 * @since 2.1
 */
function ep_tec_module_box_summary() {
	?>
	<p><?php esc_html_e( 'Dramatically increase the performance of The Events Calendar plugin.', 'elasticpress' ); ?></p>
	<?php
}

/**
 * Output module box long
 * 
 * @since 2.1
 */
function ep_tec_module_box_long() {
	?>
	<p><?php esc_html_e( 'The Events Calendar runs some very tough queries to find out which events fall on which day, which events fall in which venue, who organized what event, etc. MySQL has a tough time handling these queries - especially as the number of events gets larger. This module will dramatically improve the performance of your calendars.', 'elasticpress' ); ?></p>
	<?php
}

/**
 * Make sure WC is activated
 *
 * @since  2.1
 * @return bool|WP_Error
 */
function ep_tec_dependencies_met_cb() {
	if ( class_exists( 'Tribe__Events__Main' ) ) {
		return true;
	} else {
		return new WP_Error( 'ep-no-the-events-calendar', esc_html__( 'The Events Calendar must be active to use this module.', 'elasticpress' ) );
	}
}

/**
 * Register the module
 */
ep_register_module( 'the-events-calendar', array(
	'title' => 'The Events Calendar',
	'setup_cb' => 'ep_tec_setup',
	'module_box_summary_cb' => 'ep_tec_module_box_summary',
	'module_box_long_cb' => 'ep_tec_module_box_long',
	'requires_install_reindex' => true,
	'dependencies_met_cb' => 'ep_tec_dependencies_met_cb',
) );

