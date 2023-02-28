<?php
/**
 * Plugin Name: Filter Instant Results Per Page
 * Description: Filters the number of Instant Results per page for test purposes.
 * Version:     1.0.0
 * Author:      10up Inc.
 * License:     GPLv2 or later
 */

add_filter(
	'ep_instant_results_per_page',
	function() {
		return 3;
	}
);
