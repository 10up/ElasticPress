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
 * On activate, all features that meet their requirements with no warnings should be activated.
 *
 * @since  2.1
 */
function ep_on_activate() {
	if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
		$feature_settings = get_site_option( 'ep_feature_settings', false );
		$old_version = get_site_option( 'ep_version', false );
	} else {
		$feature_settings = get_option( 'ep_feature_settings', false );
		$old_version = get_option( 'ep_version', false );
	}

	/**
	 * Activate necessary features if this is the first time activating
	 * the plugin.
	 */
	if ( false === $feature_settings ) {
		$registered_features = EP_Features::factory()->registered_features;
		
		foreach ( $registered_features as $slug => $feature ) {
			if ( 0 === $feature->requirements_status()->code ) {
				ep_activate_feature( $slug );
			}
		}
	}

	/**
	 * Cache requirement statuses so we can detect changes later
	 */
	EP_Features::factory()->get_requirement_statuses( true );

	/**
	 * Reindex if we cross a reindex version in the upgrade
	 */
	$reindex_versions = apply_filters( 'ep_reindex_versions', array(
		'2.2',
	) );

	$need_upgrade_sync = false;

	if ( false === $old_version ) {
		$need_upgrade_sync = true;
	} else {
		$last_reindex_version = $reindex_versions[ count( $reindex_versions ) - 1 ];

		if ( ( -1 === version_compare( $old_version, $last_reindex_version ) && 1 === version_compare( EP_VERSION , $last_reindex_version ) ) || 0 === version_compare( EP_VERSION , $last_reindex_version ) )  {
			$last_reindex_version = true;
		}
	}

	if ( $need_upgrade_sync ) {
		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			update_site_option( 'ep_need_upgrade_sync', true );
		} else {
			update_option( 'ep_need_upgrade_sync', true );
		}
	}

	if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
		update_site_option( 'ep_feature_settings', $feature_settings );
		delete_site_option( 'ep_index_meta' );
		update_site_option( 'ep_version', sanitize_text_field( EP_VERSION ) );
	} else {
		update_option( 'ep_feature_settings', $feature_settings );
		delete_option( 'ep_index_meta' );
		update_option( 'ep_version', sanitize_text_field( EP_VERSION ) );
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
