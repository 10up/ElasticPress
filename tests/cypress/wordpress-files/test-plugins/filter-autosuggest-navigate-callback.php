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
		wp_register_script( 'filter-autosuggest-navigate-callback', '', [], EP_VERSION, true );
		wp_enqueue_script( 'filter-autosuggest-navigate-callback' );
		wp_add_inline_script(
			'filter-autosuggest-navigate-callback',
			"
		const myNavigateCallback = (searchTerm, url) => {
				let cypressQueryArg = new URLSearchParams(window.location.search)
				cypressQueryArg.set('cypress', 'foobar');
				let newURL = url + '?' + cypressQueryArg.toString();
				window.location.href = newURL;
			};
			wp.hooks.addFilter(
				'ep.Autosuggest.navigateCallback', 'myTheme/myNavigateCallback',
				() => myNavigateCallback,
			);",
			'after'
		);
	},
	999
);
