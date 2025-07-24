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

	$reflection          = new \ReflectionClass( $feature );
	$reflection_property = $reflection->getProperty( 'requires_feature' );
	$reflection_property->setAccessible( true );
	$reflection_property->setValue( $feature, [ 'facets', 'documents' ] );

	return $feature;
};
add_filter( 'ep_feature_create', $modify_feature, 10, 3 );
