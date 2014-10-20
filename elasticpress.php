<?php

/**
 * Plugin Name: ElasticPress
 * Description: Integrate WordPress search with Elasticsearch
 * Version:     1.0
 * Author:      Aaron Holbrook, Taylor Lovett, Matt Gross, 10up
 * Author URI:  http://10up.com
 * License:     MIT
 */

require_once( 'classes/class-ep-config.php' );
require_once( 'classes/class-ep-api.php' );
require_once( 'classes/class-ep-sync-manager.php' );
require_once( 'classes/class-ep-elasticpress.php' );
require_once( 'classes/class-ep-wp-query-integration.php' );

/**
 * WP CLI Commands
 */
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once 'bin/wp-cli.php';
}