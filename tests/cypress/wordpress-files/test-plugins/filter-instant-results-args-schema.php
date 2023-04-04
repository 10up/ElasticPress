<?php
/**
 * Plugin Name: Filter Instant Results Argument Schema
 * Description: Filters the Instant Results arguments schema for test purposes.
 * Version:     1.0.0
 * Author:      10up Inc.
 * License:     GPLv2 or later
 *
 * @package ElasticPress_Tests_E2e
 */

add_filter(
	'ep_instant_results_args_schema',
	function( $args_schema ) {
		$args_schema['orderby']['default'] = 'date';

		return $args_schema;
	}
);
