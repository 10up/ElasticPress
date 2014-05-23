<?php

/**
 * Plugin Name: Elasticsearch
 * Description: WordPress plugin for Elasticsearch
 * Version:     0.1.0
 * Author:      Taylor Lovett, 10up
 * Author URI:  http://10up.com
 * License:     GPLv2+
 */


require_once( 'classes/class-es-config.php' );
require_once( 'classes/class-es-sync-statii.php' );
require_once( 'classes/class-es-api.php' );
require_once( 'classes/class-es-sync-manager.php' );
require_once( 'classes/class-es-cron.php' );
require_once( 'classes/class-es-elasticsearch.php' );
require_once( 'classes/class-es-query.php' );
