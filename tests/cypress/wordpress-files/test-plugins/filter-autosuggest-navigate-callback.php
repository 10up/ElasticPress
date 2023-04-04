<?php
/**
 * Plugin Name: Filter Autosuggest Navigate Callback
 * Description: Filters the Autosuggest Navigate Callback for test purposes.
 * Version:     1.0.0
 * Author:      10up Inc.
 * License:     GPLv2 or later
 *
 * @package ElasticPress_Tests_E2e
 */

add_action(
	'wp_enqueue_scripts',
	function() {
		wp_add_inline_script(
			'filter-autosuggest-navigate-callback',
			"const myNavigateCallback = (searchTerm, url) => {
				window.open(url, '_blank');
			};
			addFilter(
				'ep.Autosuggest.navigateCallback',
				() => myNavigateCallback,
			);",
			'after'
		);
	},
	11
);
