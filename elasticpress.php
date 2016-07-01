<?php

/**
 * Plugin Name: ElasticPress
 * Description: Supercharge WordPress performance and search with Elasticsearch.
 * Version:     2.1
 * Author:      Aaron Holbrook, Taylor Lovett, Matt Gross, 10up
 * Author URI:  http://10up.com
 * License:     GPLv2 or later
 * Text Domain: elasticpress
 * Domain Path: /lang/
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
define( 'EP_PATH', plugin_dir_path( __FILE__ ) );
define( 'EP_VERSION', '2.1' );
define( 'EP_MODULES_DIR', dirname( __FILE__ ) . '/modules' );

require_once( 'classes/class-ep-config.php' );
require_once( 'classes/class-ep-api.php' );

// Define a constant if we're network activated to allow plugin to respond accordingly.
$network_activated = ep_is_network_activated( plugin_basename( __FILE__ ) );

if ( $network_activated ) {
	define( 'EP_IS_NETWORK', true );
}

require_once( 'classes/class-ep-sync-manager.php' );
require_once( 'classes/class-ep-wp-query-integration.php' );
require_once( 'classes/class-ep-wp-date-query.php' );
require_once( 'classes/class-ep-module.php' );
require_once( 'classes/class-ep-modules.php' );
require_once( 'classes/class-ep-dashboard.php' );

// Include core modules
require_once( 'modules/search/search.php' );
require_once( 'modules/related-posts/related-posts.php' );
require_once( 'modules/admin/admin.php' );
require_once( 'modules/woocommerce/woocommerce.php' );

/**
 * WP CLI Commands
 */
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once( 'bin/wp-cli.php' );
}

/**
 * If we activate the plugin with no modules option, activate search by default. This
 * should only happy when first upgrading to 2.1. We also want to clear any syncs that were
 * in progress when the plugin was deactivated.
 *
 * @since  2.1
 */
function ep_on_activate() {
	$active_modules = get_option( 'ep_active_modules', false );

	if ( false === $active_modules ) {
		$active_modules = array( 'search' );
	}

	if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
		update_site_option( 'ep_active_modules', $active_modules );
		delete_site_option( 'ep_index_meta' );
	} else {
		update_option( 'ep_active_modules', $active_modules );
		delete_option( 'ep_index_meta' );
	}
}
register_activation_hook( __FILE__, 'ep_on_activate' );

/**
 * Load text domain and handle debugging
 */
function ep_loader() {
	load_plugin_textdomain( 'elasticpress', false, basename( dirname( __FILE__ ) ) . '/lang' ); // Load any available translations first.
	
	if ( is_user_logged_in() && ! defined( 'WP_EP_DEBUG' ) ) {
		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		define( 'WP_EP_DEBUG', is_plugin_active( 'debug-bar-elasticpress/debug-bar-elasticpress.php' ) );
	}
}
add_action( 'plugins_loaded', 'ep_loader' );
