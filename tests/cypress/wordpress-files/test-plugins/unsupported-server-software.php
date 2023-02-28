<?php
/**
 * Plugin Name: Unsupported server software
 * Version:     1.0.0
 * Author:      10up Inc.
 * License:     GPLv2 or later
 *
 * @package ElasticPress_Tests_E2e
 */

add_filter(
	'pre_option_ep_hide_different_server_type_notice',
	function() {
		return 0;
	}
);

add_filter(
	'ep_server_type',
	function() {
		return 'opensearch';
	}
);
