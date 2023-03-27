<?php
/**
 * Plugin Name: Filter Metadata Management Mode.
 * Description: Filters Metadata Management Mode to restore the pre 5.0.0 behaviour, for test purposes.
 * Version:     1.0.0
 * Author:      10up Inc.
 * License:     GPLv2 or later
 *
 * @package ElasticPress_Tests_E2e
 */

add_filter(
	'ep_meta_mode',
	function() {
		return 'auto';
	}
);
