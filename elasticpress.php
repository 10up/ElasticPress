<?php

/**
 * Plugin Name: ElasticPress
 * Description: Integrate WordPress search with Elasticsearch
 * Version:     0.1.2
 * Author:      Aaron Holbrook, Taylor Lovett, 10up
 * Author URI:  http://10up.com
 * License:     GPLv2+
 */


require_once( 'classes/class-ep-config.php' );
require_once( 'classes/class-ep-sync-status.php' );
require_once( 'classes/class-ep-api.php' );
require_once( 'classes/class-ep-sync-manager.php' );
require_once( 'classes/class-ep-cron.php' );
require_once( 'classes/class-ep-ajax.php' );
require_once( 'classes/class-ep-elasticpress.php' );
require_once( 'classes/class-ep-query.php' );
require_once( 'classes/class-ep-search.php' );

/**
 * WP CLI Commands
 */
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once 'bin/wp-cli.php';
}