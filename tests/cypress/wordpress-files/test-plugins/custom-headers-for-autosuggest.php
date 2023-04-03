<?php
/**
 * Plugin Name: Custom Header for Autosuggest
 * Description: Customizes the headers using fetchOptions.
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
			'elasticpress-autosuggest',
			"const epAutosuggestFetchOptions = ({ fetchOptions }) => {
				fetchOptions.headers = {
					'X-Custom-Autosuggest-Header': 'MyAutosuggest',
				};
			return fetchOptions;
            };
            wp.hooks.addFilter( 'ep.Autosuggest.fetchOptions', 'ep/epAutosuggestFetchOptions', () => epAutosuggestFetchOptions );",
			'before'
		);
	},
	11
);
