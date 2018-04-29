<?php

/**
 * Plugin Name: ElasticPress
 * Description: A fast and flexible search and query engine for WordPress.
 * Version:     2.6
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
define( 'EP_VERSION', '2.6' );

/**
 * PSR-4 autoloading
 *
 * @since  2.6
 */
spl_autoload_register(
	function( $class ) {
			// project-specific namespace prefix
			$prefix = 'ElasticPress\\';

			// base directory for the namespace prefix
			$base_dir = __DIR__ . '/includes/classes/';

			// does the class use the namespace prefix?
			$len = strlen( $prefix );

		if ( strncmp( $prefix, $class, $len ) !== 0 ) {
			return;
		}

			$relative_class = substr( $class, $len );

			$file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

			// if the file exists, require it
		if ( file_exists( $file ) ) {
			require $file;
		}
	}
);

/**
 * We compare the current ES version to this compatibility version number. Compatibility is true when:
 *
 * EP_ES_VERSION_MIN <= YOUR ES VERSION <= EP_ES_VERSION_MAX
 *
 * We don't check minor releases so if your ES version if 5.1.1, we consider that 5.1 in our comparison.
 *
 * @since  2.2
 */
define( 'EP_ES_VERSION_MAX', '6.2' );
define( 'EP_ES_VERSION_MIN', '1.7' );

require_once __DIR__ . '/includes/utils.php';

// Define a constant if we're network activated to allow plugin to respond accordingly.
$network_activated = \ElasticPress\Utils\is_network_activated( plugin_basename( __FILE__ ) );

if ( $network_activated ) {
	define( 'EP_IS_NETWORK', true );
}

/**
 * Setup features framework
 */
\ElasticPress\Features::factory();

/**
 * Setup post indexable type
 */
\ElasticPress\Post\SyncManager::factory();
\ElasticPress\Post\QueryIntegration::factory();

/**
 * Setup dashboard
 */
require_once __DIR__ . '/includes/dashboard.php';
\ElasticPress\Dashboard\setup();

/**
 * Include core features
 */
\ElasticPress\Features::factory()->register_feature(
	new \ElasticPress\Feature\Search\Search()
);

\ElasticPress\Features::factory()->register_feature(
	new \ElasticPress\Feature\ProtectedContent\ProtectedContent()
);

\ElasticPress\Features::factory()->register_feature(
	new \ElasticPress\Feature\Autosuggest\Autosuggest()
);

\ElasticPress\Features::factory()->register_feature(
	new \ElasticPress\Feature\RelatedPosts\RelatedPosts()
);

\ElasticPress\Features::factory()->register_feature(
	new \ElasticPress\Feature\WooCommerce\WooCommerce()
);

\ElasticPress\Features::factory()->register_feature(
	new \ElasticPress\Feature\Facets\Facets()
);

\ElasticPress\Features::factory()->register_feature(
	new \ElasticPress\Feature\Documents\Documents()
);

/**
 * WP CLI Commands
 */
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'elasticpress', '\ElasticPress\Command' );
}

/**
 * Set the availability of dashboard sync functionality. Defaults to true (enabled).
 *
 * Sync can be disabled by defining EP_DASHBOARD_SYNC as false in wp-config.php.
 * NOTE: Must be defined BEFORE `require_once(ABSPATH . 'wp-settings.php');` in wp-config.php.
 *
 * @since  2.3
 */
if ( ! defined( 'EP_DASHBOARD_SYNC' ) ) {
	define( 'EP_DASHBOARD_SYNC', true );
}

/**
 * Handle upgrades
 *
 * @since  2.2
 */
function ep_handle_upgrades() {
	if ( ! is_admin() || defined( 'DOING_AJAX' ) ) {
		return;
	}

	if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
		$old_version = get_site_option( 'ep_version', false );
	} else {
		$old_version = get_option( 'ep_version', false );
	}

	/**
	 * Reindex if we cross a reindex version in the upgrade
	 */
	$reindex_versions = apply_filters( 'ep_reindex_versions', array(
		'2.2',
		'2.3.1',
		'2.4',
	) );

	$need_upgrade_sync = false;

	if ( false === $old_version ) {
		$need_upgrade_sync = true;
	} else {
		$last_reindex_version = $reindex_versions[ count( $reindex_versions ) - 1 ];

		if ( -1 === version_compare( $old_version, $last_reindex_version ) && 0 <= version_compare( EP_VERSION , $last_reindex_version ) )  {
			$need_upgrade_sync = true;
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
		update_site_option( 'ep_version', sanitize_text_field( EP_VERSION ) );
	} else {
		update_option( 'ep_version', sanitize_text_field( EP_VERSION ) );
	}
}
add_action( 'plugins_loaded', 'ep_handle_upgrades', 5 );

/**
 * Load text domain and handle debugging
 *
 * @since  2.2
 */
function ep_setup_misc() {
	load_plugin_textdomain( 'elasticpress', false, basename( dirname( __FILE__ ) ) . '/lang' ); // Load any available translations first.

	if ( is_user_logged_in() && ! defined( 'WP_EP_DEBUG' ) ) {
		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		define( 'WP_EP_DEBUG', is_plugin_active( 'debug-bar-elasticpress/debug-bar-elasticpress.php' ) );
	}
}
add_action( 'plugins_loaded', 'ep_setup_misc' );

do_action( 'elasticpress_loaded' );
