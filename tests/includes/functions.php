<?php

/**
 * Recursive version of PHP's in_array
 *
 * @todo Max recursion restriction
 * @since 0.1.2
 * @param mixed $needle
 * @param array $haystack
 * @return bool
 */
function ep_deep_in_array( $needle, $haystack ) {
	if ( in_array( $needle, $haystack, true ) ) {
		return true;
	}

	$result = false;

	foreach ( $haystack as $new_haystack ) {
		if ( is_array( $new_haystack ) ) {
			$result = $result || $this->_deepInArray( $needle, $new_haystack );
		}
	}

	return $result;
}

/**
 * Create a WP post and "sync" it to Elasticsearch. We are mocking the sync
 *
 * @param array $post_args
 * @param array $post_meta
 * @param int $site_id
 * @since 0.9
 * @return int|WP_Error
 */
function ep_create_and_sync_post( $post_args = array(), $post_meta = array(), $site_id = null ) {
	if ( $site_id != null ) {
		switch_to_blog( $site_id );
	}

	$post_types = ep_get_indexable_post_types();
	$post_type_values = array_values( $post_types );

	$args = array(
		'post_status' => 'publish',
		'post_title' => 'Test Post ' . time(),
	);

	if ( ! empty( $post_type_values ) ) {
		$args['post_type'] = $post_type_values[0];
	}

	$args = wp_parse_args( $post_args, $args );

	$post_id = wp_insert_post( $args );

	// Quit if we have a WP_Error object
	if ( is_wp_error( $post_id ) ) {
		return $post_id;
	}

	if ( ! empty( $post_meta ) ) {
		foreach ( $post_meta as $key => $value ) {
			// No need for sanitization here
			update_post_meta( $post_id, $key, $value );
		}
	}

	// Force a re-sync
	wp_update_post( array( 'ID' => $post_id ) );

	if ( $site_id != null ) {
		restore_current_blog();
	}

	return $post_id;
}

function ep_create_date_query_posts() {
	$sites = ep_get_sites();
	$beginning_tz = date_default_timezone_get();

	date_default_timezone_set('America/Los_Angeles');

	foreach ( $sites as $site ) {
		switch_to_blog( $site['blog_id'] );

		$post_date = strtotime( "January 6th, 2012 11:59PM" );

		for( $i = 0; $i <= 10; ++$i ) {

			ep_create_and_sync_post( array(
				'post_title' => 'post_title' . $site['blog_id'],
				'post_content' => 'findme',
				'post_date'    => date( "Y-m-d H:i:s", strtotime( "-$i days", strtotime( "-$i hours", $post_date ) ) ),
				'post_date_gmt' => gmdate( "Y-m-d H:i:s", strtotime( "-$i days", strtotime( "-$i hours", $post_date ) ) ),
			) );

			ep_refresh_index();
		}

		restore_current_blog();
	}
	date_default_timezone_set($beginning_tz);

}

/**
 * Get all sites, count indexes
 *
 * @return array total index count with last blog id to manipulate blog with an index
 */
function ep_count_indexes() {
	$sites = ep_get_sites();

	$count_indexes = 0;
	foreach ( $sites as $site ) {
		if ( $index_name = ep_get_index_name( $site[ 'blog_id' ] ) ) {
			if ( ep_index_exists( $index_name ) ) {
				$count_indexes++;
				$last_blog_id_with_index = $site[ 'blog_id' ];
			}
		}
	}
	return array(
		'total_indexes' => $count_indexes,
		'last_blog_id_with_index' => $last_blog_id_with_index,
	);
}
