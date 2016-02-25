<?php

/**
 * Plugin Name: ElasticPress
 * Description: Integrate WordPress search with Elasticsearch
 * Version:     1.8
 * Author:      Aaron Holbrook, Taylor Lovett, Matt Gross, 10up
 * Author URI:  http://10up.com
 * License:     GPLv2 or later
 *
 * This program derives work from Alley Interactive's SearchPress
 * and Automattic's VIP search plugin:
 *
 * Copyright (C) 2012-2013 Automattic
 * Copyright (C) 2013 SearchPress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'EP_URL', plugin_dir_url( __FILE__ ) );
define( 'EP_VERSION', '1.8' );

require_once( 'classes/class-ep-config.php' );
require_once( 'classes/class-ep-api.php' );
require_once( 'classes/class-ep-sync-manager.php' );
require_once( 'classes/class-ep-elasticpress.php' );
require_once( 'classes/class-ep-wp-query-integration.php' );
require_once( 'classes/class-ep-wp-date-query.php' );
require_once( 'classes/class-ep-endpoint.php' );

// Define a constant if we're network activated to allow plugin to respond accordingly.
$network_activated = ep_is_network_activated( plugin_basename( __FILE__ ) );

if ( $network_activated ) {
	define( 'EP_IS_NETWORK', true );
}

/**
 * WP CLI Commands
 */
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once 'bin/wp-cli.php';
}

add_action( 'plugins_loaded', 'ep_loader' );

/**
 * Loads GUI classes if needed.
 */
function ep_loader() {

	if ( class_exists( 'EP_Config' ) ) {

		load_plugin_textdomain( 'elasticpress', false, dirname( dirname( __FILE__ ) ) . '/lang' ); // Load any available translations first.

		// Load the settings page.
		require( dirname( __FILE__ ) . '/classes/class-ep-settings.php' );
		new EP_Settings();

		// Load the indexing GUI.
		if ( true === apply_filters( 'ep_load_index_gui', true ) ) {

			require( dirname( __FILE__ ) . '/classes/class-ep-index-gui.php' );
			new EP_Index_GUI();

		}

		// Load index statuses.
		if ( true === apply_filters( 'ep_load_index_status', true ) ) {

			require( dirname( __FILE__ ) . '/classes/class-ep-index-status.php' );
			new EP_Index_Status();

		}
	}
}
