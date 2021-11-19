<?php
/**
 * Plugin Name: Unsupported Elasticsearch version
 * Version:     1.0.0
 * Author:      10up Inc.
 * License:     GPLv2 or later
 */

add_filter( 'pre_option_ep_hide_es_above_compat_notice', function() {
	return 0;
} );

add_filter( 'ep_elasticsearch_version', function() {
	return '8.0';
} );
