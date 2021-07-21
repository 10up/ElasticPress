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
		global $wpdb;

		// Delete ep_related_posts_* transients
		if ( is_multisite() ) {
			$sites = get_sites();

			foreach ( $sites as $site ) {
				switch_to_blog( $site->blog_id );

				// Delete options.
				delete_option( 'ep_host' );
				delete_site_option( 'ep_index_meta' );
				delete_option( 'ep_index_meta' );
				delete_site_option( 'ep_feature_settings' );
				delete_option( 'ep_feature_settings' );
				delete_site_option( 'ep_version' );
				delete_option( 'ep_version' );
				delete_option( 'ep_intro_shown' );
				delete_site_option( 'ep_intro_shown' );
				delete_option( 'ep_last_sync' );
				delete_site_option( 'ep_last_sync' );
				delete_option( 'ep_need_upgrade_sync' );
				delete_site_option( 'ep_need_upgrade_sync' );
				delete_option( 'ep_feature_requirement_statuses' );
				delete_site_option( 'ep_feature_requirement_statuses' );
				delete_option( 'ep_feature_auto_activated_sync' );
				delete_site_option( 'ep_feature_auto_activated_sync' );
				delete_option( 'ep_hide_intro_shown_notice' );
				delete_site_option( 'ep_hide_intro_shown_notice' );
				delete_option( 'ep_skip_install' );
				delete_site_option( 'ep_skip_install' );
				delete_option( 'ep_last_cli_index' );
				delete_site_option( 'ep_last_cli_index' );
				delete_option( 'ep_credentials' );
				delete_site_option( 'ep_credentials' );
				delete_option( 'ep_prefix' );
				delete_site_option( 'ep_prefix' );
				delete_option( 'ep_language' );
				delete_site_option( 'ep_language' );
				delete_option( 'ep_bulk_setting' );
				delete_site_option( 'ep_bulk_setting' );

				// Delete admin notices options
				delete_site_option( 'ep_hide_host_error_notice' );
				delete_option( 'ep_hide_host_error_notice' );
				delete_site_option( 'ep_hide_es_below_compat_notice' );
				delete_option( 'ep_hide_es_below_compat_notice' );
				delete_site_option( 'ep_hide_es_above_compat_notice' );
				delete_option( 'ep_hide_es_above_compat_notice' );
				delete_site_option( 'ep_hide_need_setup_notice' );
				delete_option( 'ep_hide_need_setup_notice' );
				delete_site_option( 'ep_hide_no_sync_notice' );
				delete_option( 'ep_hide_no_sync_notice' );
				delete_site_option( 'ep_hide_upgrade_sync_notice' );
				delete_option( 'ep_hide_upgrade_sync_notice' );
				delete_site_option( 'ep_hide_auto_activate_sync_notice' );
				delete_option( 'ep_hide_auto_activate_sync_notice' );
				delete_site_option( 'ep_hide_using_autosuggest_defaults_notice' );
				delete_option( 'ep_hide_using_autosuggest_defaults_notice' );
				delete_site_option( 'ep_hide_yellow_health_notice' );
				delete_option( 'ep_hide_yellow_health_notice' );

				// Delete transients
				delete_site_transient( 'ep_es_info_response_code' );
				delete_transient( 'ep_es_info_response_code' );
				delete_site_transient( 'ep_es_info_response_error' );
				delete_transient( 'ep_es_info_response_error' );
				delete_site_transient( 'logging_ep_es_info' );
				delete_transient( 'logging_ep_es_info' );
				delete_site_transient( 'ep_wpcli_sync_interrupted' );
				delete_transient( 'ep_wpcli_sync_interrupted' );
				delete_site_transient( 'ep_wpcli_sync' );
				delete_transient( 'ep_wpcli_sync' );
				delete_site_transient( 'ep_es_info' );
				delete_transient( 'ep_es_info' );
				delete_site_transient( 'ep_autosuggest_query_request_cache' );
				delete_transient( 'ep_autosuggest_query_request_cache' );

				$related_posts_transients = $wpdb->get_col( "SELECT option_name FROM {$wpdb->prefix}options WHERE option_name LIKE '_transient_ep_related_posts_%'" );

				foreach ( $related_posts_transients as $related_posts_transient ) {
					$related_posts_transient = str_replace( '_transient_', '', $related_posts_transient );
					delete_site_transient( $related_posts_transient );
					delete_transient( $related_posts_transient );
				}

				restore_current_blog();
			}
		} else {
			// Delete options.
			delete_option( 'ep_host' );
			delete_option( 'ep_index_meta' );
			delete_option( 'ep_feature_settings' );
			delete_option( 'ep_version' );
			delete_option( 'ep_intro_shown' );
			delete_option( 'ep_last_sync' );
			delete_option( 'ep_need_upgrade_sync' );
			delete_option( 'ep_feature_requirement_statuses' );
			delete_option( 'ep_feature_auto_activated_sync' );
			delete_option( 'ep_hide_intro_shown_notice' );
			delete_option( 'ep_skip_install' );
			delete_option( 'ep_last_cli_index' );
			delete_option( 'ep_credentials' );
			delete_option( 'ep_prefix' );
			delete_option( 'ep_language' );
			delete_option( 'ep_bulk_setting' );

			// Delete admin notices options
			delete_option( 'ep_hide_host_error_notice' );
			delete_option( 'ep_hide_es_below_compat_notice' );
			delete_option( 'ep_hide_es_above_compat_notice' );
			delete_option( 'ep_hide_need_setup_notice' );
			delete_option( 'ep_hide_no_sync_notice' );
			delete_option( 'ep_hide_upgrade_sync_notice' );
			delete_option( 'ep_hide_auto_activate_sync_notice' );
			delete_option( 'ep_hide_using_autosuggest_defaults_notice' );
			delete_option( 'ep_hide_yellow_health_notice' );

			// Delete transients
			delete_transient( 'ep_es_info_response_code' );
			delete_transient( 'ep_es_info_response_error' );
			delete_transient( 'logging_ep_es_info' );
			delete_transient( 'ep_wpcli_sync_interrupted' );
			delete_transient( 'ep_wpcli_sync' );
			delete_transient( 'ep_es_info' );
			delete_transient( 'ep_autosuggest_query_request_cache' );

			$related_posts_transients = $wpdb->get_col( "SELECT option_name FROM {$wpdb->prefix}options WHERE option_name LIKE '_transient_ep_related_posts_%'" );

			foreach ( $related_posts_transients as $related_posts_transient ) {
				$related_posts_transient = str_replace( '_transient_', '', $related_posts_transient );
				delete_transient( $related_posts_transient );
			}
		}
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
