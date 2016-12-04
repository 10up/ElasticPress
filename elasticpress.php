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
require_once( 'classes/class-ep-feature.php' );
require_once( 'classes/class-ep-features.php' );
require_once( 'classes/class-ep-dashboard.php' );

// Include core features
require_once( 'features/search/search.php' );
require_once( 'features/related-posts/related-posts.php' );
require_once( 'features/admin/admin.php' );
require_once( 'features/woocommerce/woocommerce.php' );

/**
 * WP CLI Commands
 */
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once( 'bin/wp-cli.php' );
}

/**
 * Run on ElasticPress activation
 *
 * @since  2.1
 */
function ep_on_activate() {
	ep_auto_activate_features();
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

if( is_admin() && ! defined( 'DOING_AJAX' )  ) {
	
	/**
	 * Set features option.
	 *
	 * This is basically required in case of plugin update which won't run activation hook.
	 * This option is getting set in plugin activation hook as well.
	 *
	 * @since 2.2
	 */
	add_filter( 'plugins_loaded', 'ep_auto_activate_features' );
}
