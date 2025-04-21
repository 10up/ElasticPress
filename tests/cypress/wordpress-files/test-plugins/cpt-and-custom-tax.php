<?php
/**
 * Plugin Name: CPT and Custom Taxonomy
 * Description: A Custom Post Type and a Custom Taxonomy for general purposes.
 * Version:     1.0.0
 * Author:      10up Inc.
 * License:     GPLv2 or later
 *
 * @package ElasticPress_Tests_E2e
 */

namespace ElasticPress\Tests\E2e;

/**
 * Create a CPT called "Movies" and a non-searchable CPT called "Group".
 */
function create_post_type() {
	register_post_type(
		'movie',
		[
			'labels'      => [
				'name'          => __( 'Movies' ),
				'singular_name' => __( 'Movie' ),
			],
			'public'      => true,
			'has_archive' => true,
			'taxonomies'  => [ 'category' ],
		]
	);

	register_post_type(
		'group',
		[
			'labels'              => [
				'name'          => __( 'Albums' ),
				'singular_name' => __( 'Album' ),
			],
			'exclude_from_search' => true,
			'has_archive'         => true,
			'public'              => true,
			'taxonomies'          => [ 'category' ],
		]
	);
}
add_action( 'init', __NAMESPACE__ . '\\create_post_type' );

/**
 * Create a custom taxonomy called "Genres" and add it to movies.
 */
function create_taxonomy() {
	$labels = [
		'name'              => _x( 'Genres', 'taxonomy general name', 'textdomain' ),
		'singular_name'     => _x( 'Genre', 'taxonomy singular name', 'textdomain' ),
		'search_items'      => __( 'Search Genres', 'textdomain' ),
		'all_items'         => __( 'All Genres', 'textdomain' ),
		'parent_item'       => __( 'Parent Genre', 'textdomain' ),
		'parent_item_colon' => __( 'Parent Genre:', 'textdomain' ),
		'edit_item'         => __( 'Edit Genre', 'textdomain' ),
		'update_item'       => __( 'Update Genre', 'textdomain' ),
		'add_new_item'      => __( 'Add New Genre', 'textdomain' ),
		'new_item_name'     => __( 'New Genre Name', 'textdomain' ),
		'menu_name'         => __( 'Genre', 'textdomain' ),
	];

	$args = [
		'hierarchical'      => false,
		'labels'            => $labels,
		'show_ui'           => true,
		'show_admin_column' => true,
		'query_var'         => true,
		'rewrite'           => [ 'slug' => 'genre' ],
		'has_archive'       => true,
	];

	register_taxonomy( 'genre', [ 'movie' ], $args );
}
add_action( 'init', __NAMESPACE__ . '\\create_taxonomy' );

/**
 * Flush rewrite rules after registering the CPT and taxonomy
 */
function rewrite_flush() {
	create_post_type();
	create_taxonomy();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, __NAMESPACE__ . '\\rewrite_flush' );
