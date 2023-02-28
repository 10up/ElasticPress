<?php

/**
 * Plugin Name: Fake Log Messages
 * Description: Fake log messages for E2E testings
 * Version:     1.0.0
 * Author:      10up Inc.
 * License:     GPLv2 or later
 */


/**
 * Add log message for index command with --static-bulk flag.
 */
add_action(
	'ep_after_bulk_index',
	function() {
		WP_CLI::log( 'Index command with --static-bulk flag completed.' );
	}
);
