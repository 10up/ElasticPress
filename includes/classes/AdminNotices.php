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
		'need_setup',
		'no_sync',
		'upgrade_sync',
		'auto_activate_sync',
		'using_autosuggest_defaults',
		'maybe_wrong_mapping',
		'yellow_health',
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

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			$last_sync = get_site_option( 'ep_last_sync', false );
		} else {
			$last_sync = get_option( 'ep_last_sync', false );
		}

		if ( empty( $last_sync ) ) {
			return false;
		}

		$host = Utils\get_host();

		if ( empty( $host ) ) {
			return false;
		}

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			$dismiss = get_site_option( 'ep_hide_using_autosuggest_defaults_notice', false );
		} else {
			$dismiss = get_option( 'ep_hide_using_autosuggest_defaults_notice', false );
		}

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
		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			$need_upgrade_sync = get_site_option( 'ep_need_upgrade_sync', false );
		} else {
			$need_upgrade_sync = get_option( 'ep_need_upgrade_sync', false );
		}

		// need_upgrade_sync takes priority over this notice
		if ( $need_upgrade_sync ) {
			return false;
		}

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			$auto_activate_sync = get_site_option( 'ep_feature_auto_activated_sync', false );
		} else {
			$auto_activate_sync = get_option( 'ep_feature_auto_activated_sync', false );
		}

		if ( ! $auto_activate_sync ) {
			return false;
		}

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			$last_sync = get_site_option( 'ep_last_sync', false );
		} else {
			$last_sync = get_option( 'ep_last_sync', false );
		}

		if ( empty( $last_sync ) ) {
			return false;
		}

		$host = Utils\get_host();

		if ( empty( $host ) ) {
			return false;
		}

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			$dismiss = get_site_option( 'ep_hide_auto_activate_sync_notice', false );
		} else {
			$dismiss = get_option( 'ep_hide_auto_activate_sync_notice', false );
		}

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
			$url = admin_url( 'network/admin.php?page=elasticpress&do_sync' );
		} else {
			$url = admin_url( 'admin.php?page=elasticpress&do_sync' );
		}

		$feature = Features::factory()->get_registered_feature( $auto_activate_sync );

		if ( defined( 'EP_DASHBOARD_SYNC' ) && ! EP_DASHBOARD_SYNC ) {
			$html = sprintf( esc_html__( 'Dashboard sync is disabled. The ElasticPress %s feature has been auto-activated! You will need to reindex using WP-CLI for it to work.', 'elasticpress' ), esc_html( is_object( $feature ) ? $feature->title : '' ) );
		} else {
			$html = sprintf( __( 'The ElasticPress %1$s feature has been auto-activated! You will need to <a href="%2$s">run a sync</a> for it to work.', 'elasticpress' ), esc_html( is_object( $feature ) ? $feature->title : '' ), esc_url( $url ) );
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
		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			$need_upgrade_sync = get_site_option( 'ep_need_upgrade_sync', false );
		} else {
			$need_upgrade_sync = get_option( 'ep_need_upgrade_sync', false );
		}

		if ( ! $need_upgrade_sync ) {
			return false;
		}

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			$last_sync = get_site_option( 'ep_last_sync', false );
		} else {
			$last_sync = get_option( 'ep_last_sync', false );
		}

		if ( empty( $last_sync ) ) {
			return false;
		}

		$host = Utils\get_host();

		if ( empty( $host ) ) {
			return false;
		}

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			$dismiss = get_site_option( 'ep_hide_upgrade_sync_notice', false );
		} else {
			$dismiss = get_option( 'ep_hide_upgrade_sync_notice', false );
		}

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
			$url = admin_url( 'network/admin.php?page=elasticpress&do_sync' );
		} else {
			$url = admin_url( 'admin.php?page=elasticpress&do_sync' );
		}

		if ( defined( 'EP_DASHBOARD_SYNC' ) && ! EP_DASHBOARD_SYNC ) {
			$html = esc_html__( 'Dashboard sync is disabled. The new version of ElasticPress requires that you to reindex using WP-CLI.', 'elasticpress' );
		} else {
			$html = sprintf( __( 'The new version of ElasticPress requires that you <a href="%s">run a sync</a>.', 'elasticpress' ), esc_url( $url ) );
		}

		$notice = esc_html__( 'Please note that some ElasticPress functionality may be impaired and/or content may not be searchable until the reindex has been performed.', 'elasticpress' );

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
		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			$last_sync = get_site_option( 'ep_last_sync', false );
		} else {
			$last_sync = get_option( 'ep_last_sync', false );
		}

		if ( ! empty( $last_sync ) ) {
			return false;
		}

		$host = Utils\get_host();

		if ( empty( $host ) ) {
			return false;
		}

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			$dismiss = get_site_option( 'ep_hide_no_sync_notice', false );
		} else {
			$dismiss = get_option( 'ep_hide_no_sync_notice', false );
		}

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
			$url = admin_url( 'network/admin.php?page=elasticpress' );
		} else {
			$url = admin_url( 'admin.php?page=elasticpress' );
		}

		if ( defined( 'EP_DASHBOARD_SYNC' ) && ! EP_DASHBOARD_SYNC ) {
			$html = esc_html__( 'Dashboard sync is disabled, but ElasticPress is almost ready to go. Trigger a sync from WP-CLI.', 'elasticpress' );
		} else {
			$html = sprintf( __( 'ElasticPress is almost ready to go. You just need to <a href="%s">sync your content</a>.', 'elasticpress' ), esc_url( $url ) );
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

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			$dismiss = get_site_option( 'ep_hide_need_setup_notice', false );
		} else {
			$dismiss = get_option( 'ep_hide_need_setup_notice', false );
		}

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
			'html'    => sprintf( __( 'ElasticPress is almost ready to go. You just need to <a href="%s">enter your settings</a>.', 'elasticpress' ), esc_url( $url ) ),
			'type'    => 'info',
			'dismiss' => 'dashboard' !== $screen,
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

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			$dismiss = get_site_option( 'ep_hide_es_below_compat_notice', false );
		} else {
			$dismiss = get_option( 'ep_hide_es_below_compat_notice', false );
		}

		if ( $dismiss ) {
			return false;
		}

		// First reduce version to major version i.e. 5.1 not 5.1.1.
		$major_es_version = preg_replace( '#^([0-9]+\.[0-9]+).*#', '$1', $es_version );

		// pad a version to have at least two parts (5 -> 5.0)
		$parts = explode( '.', $major_es_version );

		if ( 1 === count( $parts ) ) {
			$parts[] = 0;
		}

		$major_es_version = implode( '.', $parts );

		if ( 1 === version_compare( EP_ES_VERSION_MIN, $major_es_version ) ) {
			return [
				'html'    => sprintf( __( 'Your Elasticsearch version %1$s is below the minimum required Elasticsearch version %2$s. ElasticPress may or may not work properly.', 'elasticpress' ), esc_html( $es_version ), esc_html( EP_ES_VERSION_MIN ) ),
				'type'    => 'error',
				'dismiss' => true,
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

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			$dismiss = get_site_option( 'ep_hide_es_above_compat_notice', false );
		} else {
			$dismiss = get_option( 'ep_hide_es_above_compat_notice', false );
		}

		if ( $dismiss ) {
			return false;
		}

		// First reduce version to major version i.e. 5.1 not 5.1.1.
		$major_es_version = preg_replace( '#^([0-9]+\.[0-9]+).*#', '$1', $es_version );

		if ( -1 === version_compare( EP_ES_VERSION_MAX, $major_es_version ) ) {
			return [
				'html'    => sprintf( __( 'Your Elasticsearch version %1$s is above the maximum required Elasticsearch version %2$s. ElasticPress may or may not work properly.', 'elasticpress' ), esc_html( $es_version ), esc_html( EP_ES_VERSION_MAX ) ),
				'type'    => 'warning',
				'dismiss' => true,
			];
		}
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
			if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
				$dismiss = get_site_option( 'ep_hide_host_error_notice', false );
			} else {
				$dismiss = get_option( 'ep_hide_host_error_notice', false );
			}

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

		$html = sprintf( __( 'There is a problem with connecting to your Elasticsearch host. ElasticPress can <a href="%1$s">try your host again</a>, or you may need to <a href="%2$s">change your settings</a>.', 'elasticpress' ), esc_url( add_query_arg( 'ep-retry', 1 ) ), esc_url( $url ) );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			if ( ! empty( $response_code ) ) {
				$html .= '<span class="notice-error-es-response-code"> ' . sprintf( __( 'Response Code: %s', 'elasticpress' ), esc_html( $response_code ) ) . '</span>';
			}

			if ( ! empty( $response_error ) ) {
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
		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			$dismiss = get_site_option( 'ep_hide_maybe_wrong_mapping_notice', false );
		} else {
			$dismiss = get_option( 'ep_hide_maybe_wrong_mapping_notice', false );
		}

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
		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			$last_sync = get_site_option( 'ep_last_sync', false );
		} else {
			$last_sync = get_option( 'ep_last_sync', false );
		}

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

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			$last_sync = get_site_option( 'ep_last_sync', false );
		} else {
			$last_sync = get_option( 'ep_last_sync', false );
		}

		if ( empty( $last_sync ) ) {
			return false;
		}

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			$dismiss = get_site_option( 'ep_hide_yellow_health_notice', false );
		} else {
			$dismiss = get_option( 'ep_hide_yellow_health_notice', false );
		}

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
				'html'    => sprintf( __( 'It looks like one or more of your indices are running on a single node. While this won\'t prevent you from using ElasticPress, depending on your site\'s specific needs this can represent a performance issue. Please check the <a href="%1$s">Index Health</a> page where you can check the health of all of your indices.', 'elasticpress' ), $url ),
				'type'    => 'warning',
				'dismiss' => true,
			];
		}
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
		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			update_site_option( 'ep_hide_' . $notice . '_notice', $value );
		} else {
			update_option( 'ep_hide_' . $notice . '_notice', $value );
		}
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
}
