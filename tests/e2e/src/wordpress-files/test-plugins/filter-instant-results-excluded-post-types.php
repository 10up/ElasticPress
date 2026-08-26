<?php
/**
 * Plugin Name: Filter Instant Results Excluded Post Types
 * Description: Excludes the "page" post type from Instant Results for test purposes.
 * Version:     1.0.0
 * Author:      10up Inc.
 * License:     GPLv2 or later
 *
 * @package ElasticPress_Tests_E2e
 */

add_filter(
	'ep_instant_results_excluded_post_types',
	function ( $excluded ) {
		$excluded[] = 'page';
		return $excluded;
	}
);
