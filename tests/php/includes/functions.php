<?php
/**
 * ElasticPress test functions
 *
 * @package elasticpress
 */

namespace ElasticPressTest\Functions;

use ElasticPress;

/**
 * Create and sync a comment
 *
 * @param  string $comment Comment content.
 * @param  int    $post_id Post ID.
 * @param  int    $parent  Parent comment ID.
 *
 * @since  3.6
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
