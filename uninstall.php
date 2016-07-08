<?php
/**
 * ElasticPress uninstaller
 *
 * Used when clicking "Delete" from inside of WordPress's plugins page.
 *
 * @package elasticpress
 * @since   1.7
 */

/**
 * Class EP_Uninstaller
 */
class EP_Uninstaller {

	/**
	 * Initialize uninstaller
	 *
	 * Perform some checks to make sure plugin can/should be uninstalled
	 *
	 * @since 1.7
	 * @return EP_Uninstaller
	 */
	public function __construct() {

		// Exit if accessed directly.
		if ( ! defined( 'ABSPATH' ) ) {
			$this->exit_uninstaller();
		}

		// Not uninstalling.
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			$this->exit_uninstaller();
		}

		// Not uninstalling.
		if ( ! WP_UNINSTALL_PLUGIN ) {
			$this->exit_uninstaller();
		}

		// Not uninstalling this plugin.
		if ( dirname( WP_UNINSTALL_PLUGIN ) !== dirname( plugin_basename( __FILE__ ) ) ) {
			$this->exit_uninstaller();
		}

		// Uninstall ElasticPress.
		self::clean_options();
	}

	/**
	 * Cleanup options
	 *
	 * Deletes ElasticPress options and transients.
	 *
	 * @since 1.7
	 * @return void
	 */
	protected static function clean_options() {

		// Delete options.
		delete_site_option( 'ep_host' );
		delete_option( 'ep_host' );
		delete_site_option( 'ep_index_meta' );
		delete_option( 'ep_index_meta' );
		delete_site_option( 'ep_active_modules' );
		delete_option( 'ep_active_modules' );
	}

	/**
	 * Exit uninstaller
	 *
	 * Gracefully exit the uninstaller if we should not be here
	 *
	 * @since 1.7
	 * @return void
	 */
	protected function exit_uninstaller() {

		status_header( 404 );
		exit;

	}
}

new EP_Uninstaller();
