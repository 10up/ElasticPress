<?php

/**
 * Plugin Name: ElasticPress
 * Description: A fast and flexible search and query engine for WordPress.
 * Version:     2.2
 * Author:      Taylor Lovett, Matt Gross, Aaron Holbrook, 10up
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
define( 'EP_VERSION', '2.2' );
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
 * On activate, all modules that meet their requirements with no warnings should be activated.
 *
 * @since  2.1
 */
function ep_on_activate() {
	if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
		$module_settings = get_site_option( 'ep_module_settings', false );
	} else {
		$module_settings = get_option( 'ep_module_settings', false );
	}

	if ( false === $module_settings ) {
		$registered_modules = EP_Modules::factory()->registered_modules;
		
		foreach ( $registered_modules as $slug => $module ) {
			if ( 0 === $module->requirements_status()->code ) {
				$module_settings[ $slug ] = ( ! empty( $module->default_settings ) ) ? $module->default_settings : array();
				$module_settings[ $slug ]['active'] = true;

				$module->post_activation();
			}
		}
	}

	if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
		update_site_option( 'ep_module_settings', $module_settings );
		delete_site_option( 'ep_index_meta' );
	} else {
		update_option( 'ep_module_settings', $module_settings );
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
