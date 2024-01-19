<?php
/**
 * Plugin Name: Open Instant Results Modal with Buttons
 * Description: Opens the Instant Results modal when clicking a button block.
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
			'elasticpress-instant-results',
			"document.querySelector('.wp-block-button__link')?.addEventListener('click', (event) => {
				event.preventDefault();
				window.epInstantResults.openModal({ search: \"block\" });
			});",
			'after'
		);
	},
	11
);
