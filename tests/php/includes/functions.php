<?php
/**
 * ElasticPress test functions
 *
 * @package elasticpress
 */

namespace ElasticPressTest\Functions;

use ElasticPress;

/**
 * Create a WP post and "sync" it to Elasticsearch
 *
 * @param array $post_args Post arguments
 * @param array $post_meta Post meta
 * @param int   $site_id Site ID
 * @since 0.9
 * @return int|WP_Error
 */
function create_and_sync_post( $post_args = array(), $post_meta = array(), $site_id = null ) {
	if ( null !== $site_id ) {
		switch_to_blog( $site_id );
	}

	$post_types       = ElasticPress\Indexables::factory()->get( 'post' )->get_indexable_post_types();
	$post_type_values = array_values( $post_types );

	$args = array(
		'post_status' => 'publish',
		'post_title'  => 'Test Post ' . time(),
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

	ElasticPress\Indexables::factory()->get( 'post' )->index( $post_id, true );

	if ( null !== $site_id ) {
		restore_current_blog();
	}

	return $post_id;
}

/**
 * Create a WP User and "sync" it to Elasticsearch
 *
 * @param array $user_args User arguments
 * @param array $user_meta User meta
 * @since 3.0
 * @return int|WP_Error
 */
function create_and_sync_user( $user_args = array(), $user_meta = array() ) {
	$args = [
		'role'      => 'administrator',
		'user_pass' => null,
	];

	$args = wp_parse_args( $user_args, $args );

	$user_id = wp_insert_user( $args );

	// Quit if we have a WP_Error object
	if ( is_wp_error( $user_id ) ) {
		return $user_id;
	}

	if ( ! empty( $user_meta ) ) {
		foreach ( $user_meta as $key => $value ) {
			update_user_meta( $user_id, $key, $value );
		}
	}

	ElasticPress\Indexables::factory()->get( 'user' )->index( $user_id, true );

	return $user_id;
}

/**
 * Create and sync a term
 *
 * @param  string $slug        Term slug
 * @param  string $name        Term name
 * @param  string $description Term description
 * @param  string $taxonomy    Taxonomy
 * @param  array  $posts       Posts to use term on
 * @param  int    $parent      Parent term id
 * @since  3.3
 * @return int                 Term ID
 */
function create_and_sync_term( $slug, $name, $description, $taxonomy, $posts = [], $parent = null ) {
	$args = [
		'slug' => $slug,
		'description' => $description,
	];

	if ( ! empty( $parent ) ) {
		$args['parent'] = $parent;
	}

	$term = wp_insert_term( $name, $taxonomy, $args );

	if ( ! empty( $posts ) ) {
		foreach ( $posts as $post_id ) {
			wp_set_object_terms( $post_id, $term['term_id'], $taxonomy, true );
		}
	}

	ElasticPress\Indexables::factory()->get( 'term' )->index( $term['term_id'], true );

	return $term['term_id'];
}

/**
 * Create and sync a comment
 *
 * @param  string $comment Comment content.
 * @param  int    $post_id Post ID.
 * @param  int    $parent  Parent comment ID.
 *
 * @since  3.5
 *
 * @return int Comment ID.
 */
function create_and_sync_comment( $args = [] ) {

	$args = array_merge(
		[
			'comment_content' => 'Test comment'
		],
		$args
	);

	$comment_id = wp_insert_comment( $args );

	ElasticPress\Indexables::factory()->get( 'comment' )->index( $comment_id, true );

	return (int) $comment_id;
}

/**
 * Create posts for date query testing
 *
 * @since  3.0
 */
function create_date_query_posts() {
	$post_date = wp_date( 'U', strtotime( 'January 6th, 2012 11:59PM' ) );

	for ( $i = 0; $i <= 10; ++$i ) {
		create_and_sync_post(
			array(
				'post_title'    => 'post_title ' . $i,
				'post_content'  => 'findme',
				'post_date'     => wp_date( 'Y-m-d H:i:s', strtotime( "-$i days", strtotime( "-$i hours", $post_date ) ) ),
				'post_date_gmt' => wp_date( 'Y-m-d H:i:s', strtotime( "-$i days", strtotime( "-$i hours", $post_date ) ), new \DateTimeZone( 'GMT' ) ),
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();
	}
}

/**
 * Get all sites, count indexes
 *
 * @return array total index count with last blog id to manipulate blog with an index
 */
function count_indexes() {
	$sites = ElasticPress\Utils\get_sites();

	$last_blog_id_with_index = 0;

	$count_indexes = 0;
	foreach ( $sites as $site ) {
		if ( ElasticPress\Indexables::factory()->get( 'post' )->index_exists( $site['blog_id'] ) ) {
			$count_indexes++;
			$last_blog_id_with_index = $site['blog_id'];
		}
	}

	return array(
		'total_indexes'           => $count_indexes,
		'last_blog_id_with_index' => $last_blog_id_with_index,
	);
}
