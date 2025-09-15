<?php
/**
 * Plugin Name: Sync Error
 * Description: Cause an error during sync, for test purposes.
 * Version:     1.0.0
 * Author:      10up Inc.
 * License:     GPLv2 or later
 *
 * @package ElasticPress_Tests_E2e
 */

namespace ElasticPress\Tests\E2E\SyncError;

const META_COUNT = 100;

add_filter( 'ep_total_field_limit', fn() => META_COUNT );

add_filter(
	'ep_prepare_meta_data',
	function ( $post_meta, $post ) {
		if ( 0 === $post->ID % 2 ) {
			for ( $i = 0; $i < META_COUNT; $i++ ) {
				$post_meta[ "test_meta_{$i}_title_{$post->ID}" ] = 'Lorem';
				$post_meta[ "test_meta_{$i}_body_{$post->ID}" ]  = 'Ipsum';
			}
		}
		return $post_meta;
	},
	10,
	2
);

add_filter(
	'ep_prepare_meta_allowed_protected_keys',
	function ( $allowed_meta, $post ) {
		for ( $i = 0; $i < META_COUNT; $i++ ) {
			$allowed_meta[] = "test_meta_{$i}_title_{$post->ID}";
			$allowed_meta[] = "test_meta_{$i}_body_{$post->ID}";
		}

		return $allowed_meta;
	},
	10,
	2
);
