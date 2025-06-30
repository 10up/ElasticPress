<?php
/**
 * Plugin Name: Add Multiple Required Features
 * Version:     1.0.0
 * Author:      10up Inc.
 * License:     GPLv2 or later
 *
 * @package ElasticPress_Tests_E2e
 */

$modify_feature = function ( $feature ) {
	if ( 'related_posts' !== $feature->slug ) {
		return $feature;
	}
	$feature->requires_feature = [ 'facets', 'documents' ];
	return $feature;
};
add_filter( 'ep_feature_create', $modify_feature, 10, 3 );
