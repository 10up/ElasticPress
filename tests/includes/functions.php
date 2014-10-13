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

	$args = wp_parse_args( $post_args, array(
		'post_type' => $post_type_values[0],
		'post_status' => 'publish',
		'post_title' => 'Test Post ' . time(),
	) );

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