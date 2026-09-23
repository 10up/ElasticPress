<?php
/**
 * Plugin Name: Filter Instant Results Excluded Term IDs
 * Description: Excludes the "markup" category term from Instant Results for test purposes.
 * Version:     1.0.0
 * Author:      10up Inc.
 * License:     GPLv2 or later
 *
 * @package ElasticPress_Tests_E2e
 */

add_filter(
	'ep_instant_results_excluded_term_ids',
	function ( $excluded ) {
		$term = get_term_by( 'slug', 'markup', 'category' );
		if ( $term ) {
			$excluded[] = $term->term_id;
		}
		return $excluded;
	}
);
