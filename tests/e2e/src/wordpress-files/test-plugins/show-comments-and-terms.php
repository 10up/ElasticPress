<?php
/**
 * Plugin Name: Make Comments and Terms features visible
 * Version:     1.0.0
 * Author:      10up Inc.
 * License:     GPLv2 or later
 *
 * @package ElasticPress_Tests_E2e
 */

add_filter(
	'ep_feature_is_visible',
	function ( $is_visible, $feature_slug ) {
		return in_array( $feature_slug, [ 'comments', 'terms' ], true ) ? true : $is_visible;
	},
	10,
	2
);
