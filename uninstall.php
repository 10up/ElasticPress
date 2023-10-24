<?php
/**
 * ElasticPress uninstaller
 *
 * Used when clicking "Delete" from inside of WordPress's plugins page.
 *
 * @package elasticpress
 * @since   1.7
 */

use ElasticPress\Utils;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/utils.php';

/**
 * Class EP_Uninstaller
 */
class EP_Uninstaller {

	/**
	 * List of option keys that need to be deleted when uninstalling the plugin.
	 *
	 * @var array
	 */
	protected $options = [
		'ep_host',
		'ep_index_meta',
		'ep_feature_settings',
		'ep_feature_settings_draft',
		'ep_version',
		'ep_intro_shown',
		'ep_last_sync',
		'ep_need_upgrade_sync',
		'ep_feature_requirement_statuses',
		'ep_feature_auto_activated_sync',
		'ep_hide_intro_shown_notice',
		'ep_skip_install',
		'ep_last_cli_index',
		'ep_credentials',
		'ep_prefix',
		'ep_language',
		'ep_bulk_setting',
		'ep_sync_history',

		// Admin notices options
		'ep_hide_host_error_notice',
		'ep_hide_es_below_compat_notice',
		'ep_hide_es_above_compat_notice',
		'ep_hide_need_setup_notice',
		'ep_hide_no_sync_notice',
		'ep_hide_upgrade_sync_notice',
		'ep_hide_auto_activate_sync_notice',
		'ep_hide_using_autosuggest_defaults_notice',
		'ep_hide_yellow_health_notice',
	];

	/**
	 * List of transient keys that need to be deleted when uninstalling the plugin.
	 *
	 * @var array
	 */
	protected $transients = [
		'ep_autosuggest_query_request_cache',
		'ep_elasticpress_io_messages',
		'ep_es_info',
		'ep_es_info_response_code',
		'ep_es_info_response_error',
		'ep_meta_field_keys',
		'ep_wpcli_sync',
		'ep_wpcli_sync_interrupted',
		'logging_ep_es_info',
	];

	/**
	 * Initialize uninstaller
	 *
	 * Perform some checks to make sure plugin can/should be uninstalled
	 *
	 * @since 1.7
	 */
	public function __construct() {
		// Exit if accessed directly.
		if ( ! defined( 'ABSPATH' ) ) {
			$this->exit_uninstaller();
		}

		// If testing, do not do anything automatically
		if ( defined( 'EP_UNIT_TESTS' ) && EP_UNIT_TESTS ) {
			return;
		}

		// EP_MANUAL_SETTINGS_RESET is used by the `settings-reset` WP-CLI command.
		if ( ! defined( 'EP_MANUAL_SETTINGS_RESET' ) || ! EP_MANUAL_SETTINGS_RESET ) {
			// Not uninstalling.
			if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) || ! WP_UNINSTALL_PLUGIN ) {
				$this->exit_uninstaller();
			}

			// Not uninstalling this plugin.
			if ( dirname( WP_UNINSTALL_PLUGIN ) !== dirname( plugin_basename( __FILE__ ) ) ) {
				$this->exit_uninstaller();
			}
		}

		// Uninstall ElasticPress.
		$this->clean_options_and_transients();
		$this->clean_site_meta();
		$this->remove_elasticpress_capability();
	}

	/**
	 * Delete all the options in a single site context.
	 */
	protected function delete_options() {
		foreach ( $this->options as $option ) {
			delete_option( $option );
		}
	}

	/**
	 * Delete all the transients in a single site context.
	 */
	protected function delete_transients() {
		foreach ( $this->transients as $transient ) {
			delete_transient( $transient );
		}
	}

	/**
	 * Delete remaining transients by their option names.
	 *
	 * @since 4.7.0
	 */
	protected function delete_transients_by_option_name() {
		global $wpdb;

		$transients = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT option_name
			FROM {$wpdb->prefix}options
			WHERE
				option_name LIKE '_transient_ep_index_settings_%'
				OR option_name LIKE '_transient_ep_related_posts_%'
			"
		);

		foreach ( $transients as $transient ) {
			$transient_name = str_replace( '_transient_', '', $transient );
			delete_site_transient( $transient_name );
			delete_transient( $transient_name );
		}
	}

	/**
	 * DEPRECATED. Delete all transients of the Related Posts feature.
	 */
	protected function delete_related_posts_transients() {
		_deprecated_function( __METHOD__, '4.7.0', '\EP_Uninstaller::delete_transients_by_name()' );
	}

	/**
	 * DEPRECATED. Delete all transients of the total fields limit.
	 */
	protected function delete_total_fields_limit_transients() {
		_deprecated_function( __METHOD__, '4.7.0', '\EP_Uninstaller::delete_transients_by_name()' );
	}

	/**
	 * Cleanup options and transients
	 *
	 * Deletes ElasticPress options and transients.
	 *
	 * @since 4.2.0
	 */
	protected function clean_options_and_transients() {
		if ( is_multisite() ) {
			foreach ( $this->options as $option ) {
				delete_site_option( $option );
			}
			foreach ( $this->transients as $transient ) {
				delete_site_transient( $transient );
			}

			$sites = \get_sites();

			foreach ( $sites as $site ) {
				switch_to_blog( $site->blog_id );

				$this->delete_options();
				$this->delete_transients();
				$this->delete_transients_by_option_name();

				restore_current_blog();
			}
		} else {
			$this->delete_options();
			$this->delete_transients();
			$this->delete_transients_by_option_name();
		}
	}

	/**
	 * Delete all site meta
	 *
	 * @since 4.7.0
	 */
	protected function clean_site_meta() {
		if ( ! is_multisite() ) {
			return;
		}

		$sites = Utils\get_sites();
		foreach ( $sites as $site ) {
			delete_site_meta( $site['blog_id'], 'ep_indexable' );
		}
	}

	/**
	 * Remove the ElasticPress' capability
	 *
	 * @since 4.5.0
	 */
	protected function remove_elasticpress_capability() {
		$role = get_role( 'administrator' );
		$role->remove_cap( Utils\get_capability() );
	}

	/**
	 * Cleanup options (deprecated, kept for documenting reasons.)
	 *
	 * @since 1.7
	 * @return void
	 * @see clean_options_and_transients
	 */
	protected static function clean_options() {
		_deprecated_function( __FUNCTION__, '4.2.0', '\EP_Uninstaller->clean_options_and_transients()' );
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
