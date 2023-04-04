<?php
/**
 * Plugin Name: Disable Welcome Guide
 * Description: Disable Welcome Guide automatically on fresh WordPress install
 * Version:     1.0.0
 * Author:      10up Inc.
 * License:     GPLv2 or later
 *
 * @package ElasticPress_Tests_E2e
 */

add_action(
	'enqueue_block_editor_assets',
	function() {
		wp_add_inline_script(
			'wp-data',
			"window.onload = function() {
		wp.data.dispatch('core/preferences').set('core/edit-widgets', 'welcomeGuide', false);
		}"
		);
	},
	999
);

