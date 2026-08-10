<?php
/**
 * Plugin Name: Filter Instant Results Config
 * Description: Filters the Instant Results front-end configuration for test purposes.
 * Version:     1.0.0
 * Author:      10up Inc.
 * License:     GPLv2 or later
 *
 * @package ElasticPress_Tests_E2e
 */

add_filter(
	'ep_instant_results_config',
	function ( $config ) {
		$config['highlightTag']         = 'mark';
		$config['epTestFilteredConfig'] = true;
		return $config;
	}
);
