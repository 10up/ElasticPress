<?php
/**
 * Plugin Name: Custom Autosuggest Placeholder
 * Description: Changes the default autosuggest placeholder text for testing purposes
 * Author:      10up Inc.
 * License:     GPLv2 or later
 *
 * @package ElasticPress_Tests_E2e
 */

/**
 * Filter the autosuggest placeholder text
 */
add_filter(
	'ep_autosuggest_query_placeholder',
	function () {
		return 'ep_autosuggest_custom_placeholder';
	}
);
