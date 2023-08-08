<?php
/**
 * ElasticPress admin notice handler
 *
 * phpcs:disable WordPress.WP.I18n.MissingTranslatorsComment
 *
 * @since  3.0
 * @package elasticpress
 */

namespace ElasticPress;

use ElasticPress\Utils;
use ElasticPress\Elasticsearch;
use ElasticPress\Screen;
use ElasticPress\Features;
use ElasticPress\Indexables;
use ElasticPress\Stats;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Admin notices class
 */
class AdminNotices {

	/**
	 * Notice keys
	 *
	 * @since  3.0
	 * @var array
	 */
	protected $notice_keys = [
		'host_error',
		'es_below_compat',
		'es_above_compat',
		'different_server_type',
		'need_setup',
		'no_sync',
		'upgrade_sync',
		'auto_activate_sync',
		'using_autosuggest_defaults',
		'maybe_wrong_mapping',
		'yellow_health',
		'too_many_fields',
	];

	/**
	 * Notices that should be shown on a screen
	 *
	 * @var array
	 * @since  3.0
	 */
	protected $notices = [];

	/**
	 * Process all notifications and prepare ones that should be displayed
	 *
	 * @since 3.0
	 */
	public function process_notices() {
		$this->notices = [];

		foreach ( $this->notice_keys as $notice ) {
			$output = call_user_func( [ $this, 'process_' . $notice . '_notice' ] );

			if ( ! empty( $output ) ) {
				$this->notices[ $notice ] = $output;
			}
		}
	}

	/**
	 * Autosuggest defaults are being used. Feature must be active.
	 *
	 * Type: notice
	 * Dismiss: Everywhere
	 * Show: Settings and dashboard only
	 *
	 * @since  3.0
	 * @return array|bool
	 */
	protected function process_using_autosuggest_defaults_notice() {
		$feature = Features::factory()->get_registered_feature( 'autosuggest' );
		if ( ! $feature instanceof Feature ) {
			return false;
		}

		if ( ! $feature->is_active() ) {
			return false;
		}

		$last_sync = Utils\get_option( 'ep_last_sync', false );

		if ( empty( $last_sync ) ) {
			return false;
		}

		$host = Utils\get_host();

		if ( empty( $host ) ) {
			return false;
		}

		$dismiss = Utils\get_option( 'ep_hide_using_autosuggest_defaults_notice', false );

		if ( $dismiss ) {
			return false;
		}

		$screen = Screen::factory()->get_current_screen();

		if ( ! in_array( $screen, [ 'dashboard', 'settings' ], true ) ) {
			return false;
		}

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			$url = admin_url( 'network/admin.php?page=elasticpress&do_sync' );
		} else {
			$url = admin_url( 'admin.php?page=elasticpress&do_sync' );
		}

		return [
			'html'    => sprintf( esc_html__( 'Autosuggest feature is enabled. If documents feature is enabled, your media will also become searchable in the frontend.', 'elasticpress' ) ),
			'type'    => 'info',
			'dismiss' => true,
		];
	}

	/**
	 * An EP feature was auto-activated that requires a sync.
	 *
	 * Type: warning
	 * Dismiss: Everywhere except ep dash and settings
	 * Show: All screens except install. Dont show during sync ever
	 *
	 * @since  3.0
	 * @return array|bool
	 */
	protected function process_auto_activate_sync_notice() {
		$need_upgrade_sync = Utils\get_option( 'ep_need_upgrade_sync', false );

		// need_upgrade_sync takes priority over this notice
		if ( $need_upgrade_sync ) {
			return false;
		}

		$auto_activate_sync = Utils\get_option( 'ep_feature_auto_activated_sync', false );

		if ( ! $auto_activate_sync ) {
			return false;
		}

		$last_sync = Utils\get_option( 'ep_last_sync', false );

		if ( empty( $last_sync ) ) {
			return false;
		}

		$host = Utils\get_host();

		if ( empty( $host ) ) {
			return false;
		}

		$dismiss = Utils\get_option( 'ep_hide_auto_activate_sync_notice', false );

		$screen = Screen::factory()->get_current_screen();

		if ( 'install' === $screen ) {
			return false;
		}

		if ( $dismiss && ! in_array( $screen, [ 'dashboard', 'settings' ], true ) ) {
			return false;
		}

		if ( isset( $_GET['do_sync'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return false;
		}

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			$url = admin_url( 'network/admin.php?page=elasticpress-sync&do_sync' );
		} else {
			$url = admin_url( 'admin.php?page=elasticpress-sync&do_sync' );
		}

		$feature = Features::factory()->get_registered_feature( $auto_activate_sync );

		if ( defined( 'EP_DASHBOARD_SYNC' ) && ! EP_DASHBOARD_SYNC ) {
			$html = sprintf(
				/* translators: Feature name */
				esc_html__( 'Dashboard sync is disabled. The ElasticPress %s feature has been auto-activated! You will need to reindex using WP-CLI for it to work.', 'elasticpress' ),
				esc_html( is_object( $feature ) ? $feature->get_short_title() : '' )
			);
		} else {
			$html = sprintf(
				/* translators: 1. Feature name; 2: Sync page URL */
				__( 'The ElasticPress %1$s feature has been auto-activated! You will need to <a href="%2$s">run a sync</a> for it to work.', 'elasticpress' ),
				esc_html( is_object( $feature ) ? $feature->get_short_title() : '' ),
				esc_url( $url )
			);
		}

		return [
			'html'    => $html,
			'type'    => 'warning',
			'dismiss' => ! in_array( $screen, [ 'dashboard', 'settings' ], true ),
		];
	}

	/**
	 * EP was upgraded to or past a version that requires a sync.
	 *
	 * Type: warning
	 * Dismiss: Everywhere except ep dash and settings
	 * Show: All screens except install. Dont show during sync ever
	 *
	 * @since  3.0
	 * @return array|bool
	 */
	protected function process_upgrade_sync_notice() {
		$need_upgrade_sync = Utils\get_option( 'ep_need_upgrade_sync', false );

		if ( ! $need_upgrade_sync ) {
			return false;
		}

		$last_sync = Utils\get_option( 'ep_last_sync', false );

		if ( empty( $last_sync ) ) {
			return false;
		}

		$host = Utils\get_host();

		if ( empty( $host ) ) {
			return false;
		}

		$dismiss = Utils\get_option( 'ep_hide_upgrade_sync_notice', false );

		$screen = Screen::factory()->get_current_screen();

		if ( 'install' === $screen ) {
			return false;
		}

		if ( $dismiss && ! in_array( $screen, [ 'dashboard', 'settings' ], true ) ) {
			return false;
		}

		if ( isset( $_GET['do_sync'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return false;
		}

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			$url = admin_url( 'network/admin.php?page=elasticpress-sync&do_sync' );
		} else {
			$url = admin_url( 'admin.php?page=elasticpress-sync&do_sync' );
		}

		if ( defined( 'EP_DASHBOARD_SYNC' ) && ! EP_DASHBOARD_SYNC ) {
			$html = esc_html__( 'Dashboard sync is disabled. The new version of ElasticPress requires that you delete all data and start a fresh sync using WP-CLI.', 'elasticpress' );
		} else {
			$html = sprintf(
				/* translators: Sync Page URL */
				__( 'The new version of ElasticPress requires that you <a href="%s">delete all data and start a fresh sync</a>.', 'elasticpress' ),
				esc_url( $url )
			);
		}

		$notice = esc_html__( 'Please note that some ElasticPress functionality may be impaired and/or content may not be searchable until the full sync has been performed.', 'elasticpress' );

		return [
			'html'    => '<span class="dashicons dashicons-warning"></span> ' . $html . ' ' . $notice,
			'type'    => 'error',
			'dismiss' => ! in_array( $screen, [ 'dashboard', 'settings' ], true ),
		];
	}

	/**
	 * EP has no never had a sync. We assume the user is on step 3 of the install.
	 *
	 * Type: notice
	 * Dismiss: Everywhere except ep dash and settings
	 * Show: All screens except install. Dont show during sync ever
	 *
	 * @since  3.0
	 * @return array|bool
	 */
	protected function process_no_sync_notice() {
		$last_sync = Utils\get_option( 'ep_last_sync', false );

		if ( ! empty( $last_sync ) ) {
			return false;
		}

		$host = Utils\get_host();

		if ( empty( $host ) ) {
			return false;
		}

		$dismiss = Utils\get_option( 'ep_hide_no_sync_notice', false );

		$screen = Screen::factory()->get_current_screen();

		if ( 'install' === $screen ) {
			return false;
		}

		if ( $dismiss && ! in_array( $screen, [ 'dashboard', 'settings' ], true ) ) {
			return false;
		}

		if ( isset( $_GET['do_sync'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return false;
		}

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			$url = admin_url( 'network/admin.php?page=elasticpress-sync' );
		} else {
			$url = admin_url( 'admin.php?page=elasticpress-sync' );
		}

		if ( defined( 'EP_DASHBOARD_SYNC' ) && ! EP_DASHBOARD_SYNC ) {
			$html = esc_html__( 'Dashboard sync is disabled, but ElasticPress is almost ready to go. Trigger a sync from WP-CLI.', 'elasticpress' );
		} else {
			$html = sprintf(
				/* translators: Sync Page URL */
				__( 'ElasticPress is almost ready to go. You just need to <a href="%s">sync your content</a>.', 'elasticpress' ),
				esc_url( $url )
			);
		}

		return [
			'html'    => $html,
			'type'    => 'info',
			'dismiss' => ! in_array( $screen, [ 'dashboard', 'settings' ], true ),
		];
	}

	/**
	 * EP has no host set. We assume the user needs to run an install.
	 *
	 * Type: notice
	 * Dismiss: Anywhere except EP dashboard
	 * Show: All screens except settings and install
	 *
	 * @since  3.0
	 * @return array|bool
	 */
	protected function process_need_setup_notice() {
		$host = Utils\get_host();

		if ( ! empty( $host ) ) {
			return false;
		}

		$dismiss = Utils\get_option( 'ep_hide_need_setup_notice', false );

		$screen = Screen::factory()->get_current_screen();

		if ( in_array( $screen, [ 'settings', 'install' ], true ) ) {
			return false;
		}

		if ( $dismiss && 'dashboard' !== $screen ) {
			return false;
		}

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			$url = admin_url( 'network/admin.php?page=elasticpress-settings' );
		} else {
			$url = admin_url( 'admin.php?page=elasticpress-settings' );
		}

		return [
			'type'    => 'info',
			'dismiss' => 'dashboard' !== $screen,
			'html'    => sprintf(
				/* translators: Sync Page URL */
				__( 'ElasticPress is almost ready to go. You just need to <a href="%s">enter your settings</a>.', 'elasticpress' ),
				esc_url( $url )
			),
		];
	}

	/**
	 * Below ES compat error.
	 *
	 * Type: error
	 * Dismiss: Anywhere
	 * Show: All screens
	 *
	 * @since  3.0
	 * @return array|bool
	 */
	protected function process_es_below_compat_notice() {
		if ( Utils\is_epio() ) {
			return false;
		}

		$host = Utils\get_host();

		if ( empty( $host ) ) {
			return false;
		}

		$es_version = Elasticsearch::factory()->get_elasticsearch_version();

		if ( false === $es_version ) {
			return false;
		}

		$dismiss = Utils\get_option( 'ep_hide_es_below_compat_notice', false );

		if ( $dismiss ) {
			return false;
		}

		// First reduce version to major version i.e. 7.10 not 7.10.1.
		$major_es_version = preg_replace( '#^([0-9]+\.[0-9]+).*#', '$1', $es_version );

		// pad a version to have at least two parts (7 -> 7.0)
		$parts = explode( '.', $major_es_version );

		if ( 1 === count( $parts ) ) {
			$parts[] = 0;
		}

		$major_es_version = implode( '.', $parts );

		if ( 1 === version_compare( EP_ES_VERSION_MIN, $major_es_version ) ) {
			return [
				'type'    => 'error',
				'dismiss' => true,
				'html'    => sprintf(
					/* translators: 1. Current Elasticsearch version; 2. Minimum required ES version */
					__( 'Your Elasticsearch version %1$s is below the minimum required Elasticsearch version %2$s. ElasticPress may or may not work properly.', 'elasticpress' ),
					esc_html( $es_version ),
					esc_html( EP_ES_VERSION_MIN )
				),
			];
		}

		return false;
	}

	/**
	 * Above ES compat error.
	 *
	 * Type: error
	 * Dismiss: Anywhere
	 * Show: All screens
	 *
	 * @since  3.0
	 * @return array|bool
	 */
	protected function process_es_above_compat_notice() {
		if ( Utils\is_epio() ) {
			return false;
		}

		$host = Utils\get_host();

		if ( empty( $host ) ) {
			return false;
		}

		$es_version = Elasticsearch::factory()->get_elasticsearch_version();

		if ( false === $es_version ) {
			return false;
		}

		$dismiss = Utils\get_option( 'ep_hide_es_above_compat_notice', false );

		if ( $dismiss ) {
			return false;
		}

		// First reduce version to major version i.e. 7.10 not 7.10.1.
		$major_es_version = preg_replace( '#^([0-9]+\.[0-9]+).*#', '$1', $es_version );

		if ( -1 === version_compare( EP_ES_VERSION_MAX, $major_es_version ) ) {
			return [
				'type'    => 'warning',
				'dismiss' => true,
				'html'    => sprintf(
					/* translators: 1. Current Elasticsearch version; 2. Maximum supported ES version */
					__( 'Your Elasticsearch version %1$s is above the maximum required Elasticsearch version %2$s. ElasticPress may or may not work properly.', 'elasticpress' ),
					esc_html( $es_version ),
					esc_html( EP_ES_VERSION_MAX )
				),
			];
		}
	}

	/**
	 * Server software different from Elasticsearch warning.
	 *
	 * Type: warning
	 * Dismiss: Anywhere
	 * Show: All screens
	 *
	 * @since  4.2.1
	 * @return array|bool
	 */
	protected function process_different_server_type_notice() {
		if ( Utils\is_epio() ) {
			return false;
		}

		$host = Utils\get_host();

		if ( empty( $host ) ) {
			return false;
		}

		$server_type = Elasticsearch::factory()->get_server_type();

		if ( false === $server_type || 'elasticsearch' === $server_type ) {
			return false;
		}

		$dismiss = Utils\get_option( 'ep_hide_different_server_type_notice', false );

		if ( $dismiss ) {
			return false;
		}

		$doc_url = 'https://10up.github.io/ElasticPress/tutorial-compatibility.html';
		$html    = sprintf(
			/* translators: Document page URL */
			__( 'Your server software is not supported. To learn more about server compatibility please <a href="%s">visit our documentation</a>.', 'elasticpress' ),
			esc_url( $doc_url )
		);

		return [
			'html'    => $html,
			'type'    => 'warning',
			'dismiss' => true,
		];
	}

	/**
	 * Host error notification. Shows when EP can't reach ES host.
	 *
	 * Type: error
	 * Dismiss: Only on non-EP screens
	 * Show: All screens except install
	 *
	 * @since  3.0
	 * @return array|bool
	 */
	protected function process_host_error_notice() {
		$host = Utils\get_host();

		if ( empty( $host ) ) {
			return false;
		}

		$screen = Screen::factory()->get_current_screen();

		if ( 'install' === $screen ) {
			return false;
		}

		$es_version = Elasticsearch::factory()->get_elasticsearch_version( false );

		if ( false !== $es_version ) {
			return false;
		}

		// Only dismissable on non-EP screens
		if ( ! in_array( $screen, [ 'settings', 'dashboard' ], true ) ) {
			$dismiss = Utils\get_option( 'ep_hide_host_error_notice', false );

			if ( $dismiss ) {
				return false;
			}
		}

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			$url            = admin_url( 'network/admin.php?page=elasticpress-settings' );
			$response_code  = get_site_transient( 'ep_es_info_response_code' );
			$response_error = get_site_transient( 'ep_es_info_response_error' );
		} else {
			$url            = admin_url( 'admin.php?page=elasticpress-settings' );
			$response_code  = get_transient( 'ep_es_info_response_code' );
			$response_error = get_transient( 'ep_es_info_response_error' );
		}

		$html = sprintf(
			/* translators: 1. Current URL with retry parameter; 2. Settings Page URL */
			__( 'There is a problem with connecting to your Elasticsearch host. ElasticPress can <a href="%1$s">try your host again</a>, or you may need to <a href="%2$s">change your settings</a>.', 'elasticpress' ),
			esc_url( add_query_arg( 'ep-retry', 1 ) ),
			esc_url( $url )
		);

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			if ( ! empty( $response_code ) ) {
				/* translators: Response Code Number */
				$html .= '<span class="notice-error-es-response-code"> ' . sprintf( __( 'Response Code: %s', 'elasticpress' ), esc_html( $response_code ) ) . '</span>';
			}

			if ( ! empty( $response_error ) ) {
				/* translators: Response Code Message */
				$html .= '<span class="notice-error-es-response-error"> ' . sprintf( __( 'Response error: %s', 'elasticpress' ), esc_html( $response_error ) ) . '</span>';
			}
		}

		return [
			'html'    => $html,
			'type'    => 'error',
			'dismiss' => ( ! in_array( Screen::factory()->get_current_screen(), [ 'settings', 'dashboard' ], true ) ) ? true : false,
		];
	}

	/**
	 * Determine if the wrong mapping might be installed
	 *
	 * Type: error
	 * Dismiss: Always dismissable per es_version as custom mapping could exist
	 * Show: All screens
	 *
	 * @since   3.6.2
	 * @return array|bool
	 */
	protected function process_maybe_wrong_mapping_notice() {
		$screen = Screen::factory()->get_current_screen();

		if ( 'install' === $screen ) {
			return false;
		}

		// we might have this dismissed
		$dismiss = Utils\get_option( 'ep_hide_maybe_wrong_mapping_notice', false );

		// we need a host
		$host = Utils\get_host();
		if ( empty( $host ) ) {
			return false;
		}

		// we also need a version
		$es_version = Elasticsearch::factory()->get_elasticsearch_version( false );

		if ( false === $es_version || $dismiss === $es_version ) {
			return false;
		}

		// we also likely need a sync to have a mapping
		$last_sync = Utils\get_option( 'ep_last_sync', false );

		if ( empty( $last_sync ) ) {
			return false;
		}

		$post_indexable = Indexables::factory()->get( 'post' );

		$mapping_file_wanted  = $post_indexable->get_mapping_name();
		$mapping_file_current = $post_indexable->determine_mapping_version();
		if ( is_wp_error( $mapping_file_current ) ) {
			return false;
		}

		if ( ! $mapping_file_current || $mapping_file_wanted !== $mapping_file_current ) {
			$html = sprintf(
				/* translators: 1. <em>; 2. </em> */
				esc_html__( 'It seems the mapping data in your index does not match the Elasticsearch version used. We recommend to reindex your content using the sync button on the top of the screen or through wp-cli by adding the %1$s--setup%2$s flag', 'elasticpress' ),
				'<em>',
				'</em>'
			);

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				/* translators: 1. Current mapping file; 2. Mapping file that should be used */
				$html .= '<span class="notice-error-es-response-code"> ' . sprintf( esc_html__( 'Current mapping: %1$s. Expected mapping: %2$s', 'elasticpress' ), esc_html( $mapping_file_current ), esc_html( $mapping_file_wanted ) ) . '</span>';
			}

			return [
				'html'    => $html,
				'type'    => 'error',
				'dismiss' => true,
			];

		}

	}

	/**
	 * Single node notification. Shows when index health is yellow.
	 *
	 * Type: warning
	 * Dismiss: Anywhere
	 * Show: All screens except install
	 *
	 * @since  3.2
	 * @return array|bool
	 */
	protected function process_yellow_health_notice() {
		$host = Utils\get_host();

		if ( empty( $host ) ) {
			return false;
		}

		$last_sync = Utils\get_option( 'ep_last_sync', false );

		if ( empty( $last_sync ) ) {
			return false;
		}

		$dismiss = Utils\get_option( 'ep_hide_yellow_health_notice', false );

		$screen = Screen::factory()->get_current_screen();

		if ( ! in_array( $screen, [ 'dashboard', 'settings' ], true ) || $dismiss ) {
			return false;
		}

		$nodes = Stats::factory()->get_nodes();

		if ( false !== $nodes && $nodes < 2 && $nodes > 0 ) {
			if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
				$url = network_admin_url( 'admin.php?page=elasticpress-health' );
			} else {
				$url = admin_url( 'admin.php?page=elasticpress-health' );
			}

			return [
				'type'    => 'warning',
				'dismiss' => true,
				'html'    => sprintf(
					/* translators: Index Health URL */
					__( 'It looks like one or more of your indices are running on a single node. While this won\'t prevent you from using ElasticPress, depending on your site\'s specific needs this can represent a performance issue. Please check the <a href="%s">Index Health</a> page where you can check the health of all of your indices.', 'elasticpress' ),
					$url
				),
			];
		}
	}

	/**
	 * Too many fields notification. Shows when the site has potentially more fields than ES could handle.
	 *
	 * Type: warning|error
	 * Dismiss: Anywhere
	 * Show: Sync and Install page
	 *
	 * @since 4.4.0
	 * @return array|bool
	 */
	protected function process_too_many_fields_notice() {
		$host = Utils\get_host();

		if ( empty( $host ) ) {
			return false;
		}

		$dismiss = Utils\get_option( 'ep_hide_too_many_fields_notice', false );

		$screen = Screen::factory()->get_current_screen();

		if ( ! in_array( $screen, [ 'install', 'sync' ], true ) || $dismiss ) {
			return false;
		}

		$has_error   = false;
		$has_warning = false;

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			$sites = Utils\get_sites( 0, true );
			foreach ( $sites as $site ) {
				switch_to_blog( $site['blog_id'] );

				list( $has_error, $site_has_warning ) = $this->check_field_count();

				restore_current_blog();

				$has_warning = $has_warning || $site_has_warning;
				if ( $has_error ) {
					break;
				}
			}
		} else {
			list( $has_error, $has_warning ) = $this->check_field_count();
		}

		if ( $has_error ) {
			$message = sprintf(
				/* translators: Elasticsearch or ElasticPress.io; 2. Link to article; 3. Link to article */
				__( 'Your website content has more public custom fields than %1$s is able to store. Check our articles about <a href="%2$s">Elasticsearch field limitations</a> and <a href="%3$s">how to index just the custom fields you need</a> before trying to sync.', 'elasticpress' ),
				Utils\is_epio() ? __( 'ElasticPress.io', 'elasticpress' ) : __( 'Elasticsearch', 'elasticpress' ),
				'https://elasticpress.zendesk.com/hc/en-us/articles/360051401212-I-get-the-error-Limit-of-total-fields-in-index-has-been-exceeded-',
				'https://elasticpress.zendesk.com/hc/en-us/articles/360052019111'
			);

			return [
				'type'    => 'error',
				'dismiss' => true,
				'html'    => $message,
			];
		}

		if ( $has_warning ) {
			$message = sprintf(
				/* translators: Elasticsearch or ElasticPress.io; 2. Link to article; 3. Link to article */
				__( 'Your website content seems to have more public custom fields than %1$s is able to store. Check our articles about <a href="%2$s">Elasticsearch field limitations</a> and <a href="%3$s">how to index just the custom fields you need</a> if you receive any errors while syncing.', 'elasticpress' ),
				Utils\is_epio() ? __( 'ElasticPress.io', 'elasticpress' ) : __( 'Elasticsearch', 'elasticpress' ),
				'https://elasticpress.zendesk.com/hc/en-us/articles/360051401212-I-get-the-error-Limit-of-total-fields-in-index-has-been-exceeded-',
				'https://elasticpress.zendesk.com/hc/en-us/articles/360052019111'
			);

			return [
				'type'    => 'warning',
				'dismiss' => true,
				'html'    => $message,
			];
		}

		return false;
	}

	/**
	 * Get notices that should be displayed
	 *
	 * @since  3.0
	 * @return array
	 */
	public function get_notices() {
		/**
		 * Filter admin notices
		 *
		 * @hook ep_admin_notices
		 * @param  {array} $notices Admin notices
		 * @return {array} New notices
		 */
		return apply_filters( 'ep_admin_notices', $this->notices );
	}

	/**
	 * Dismiss a notice given a notice key.
	 *
	 * @param  string $notice Notice key
	 * @since  3.0
	 */
	public function dismiss_notice( $notice ) {
		$value = true;
		// allow version dependent dismissal
		if ( in_array( $notice, [ 'maybe_wrong_mapping' ], true ) ) {
			$value = Elasticsearch::factory()->get_elasticsearch_version( false );
		}

		Utils\update_option( 'ep_hide_' . $notice . '_notice', $value );
	}

	/**
	 * Return singleton instance of class
	 *
	 * @return object
	 * @since 0.1.0
	 */
	public static function factory() {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new self();
		}

		return $instance;
	}

	/**
	 * Compare the number of fields in the site and the number of allowed fields in ES
	 *
	 * @since 4.4.0
	 * @return array
	 */
	protected function check_field_count() {
		$post_indexable = Indexables::factory()->get( 'post' );

		$indexable_fields = $post_indexable->get_predicted_indexable_meta_keys();
		$count_fields_db  = count( $indexable_fields );

		$index_name     = $post_indexable->get_index_name();
		$es_field_limit = Elasticsearch::factory()->get_index_total_fields_limit( $index_name );
		$es_field_limit = $es_field_limit ?? apply_filters( 'ep_total_field_limit', 5000 );

		$predicted_es_field_count = $count_fields_db * 8;

		return [
			$predicted_es_field_count > $es_field_limit,
			$predicted_es_field_count * 1.2 > $es_field_limit,
		];
	}
}
