<?php
/**
 * Determine which ElasticPress screen we are viewing
 *
 * @since  3.0
 * @package elasticpress
 */

namespace ElasticPress;

use ElasticPress\Utils;
use ElasticPress\Installer;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Screen class
 */
class Screen {
	/**
	 * Current screen
	 *
	 * @var string
	 * @since  3.0
	 */
	protected $screen = null;

	/**
	 * Sync screen instance
	 *
	 * @var Screen\Sync
	 * @since  3.6.0
	 */
	public $sync_screen;

	/**
	 * Info screen instance
	 *
	 * @var Screen\HealthInfo
	 * @since  4.3.0
	 */
	public $health_info_screen;

	/**
	 * Status report instance
	 *
	 * @var Screen\StatusReport
	 * @since  4.5.0
	 */
	public $status_report;

	/**
	 * Features instance
	 *
	 * @var Screen\Features
	 * @since  5.0.0
	 */
	public $features;

	/**
	 * Settings instance
	 *
	 * @var Screen\Settings
	 * @since  5.0.0
	 */
	public $settings;

	/**
	 * Initialize class
	 *
	 * @since 3.0
	 */
	public function setup() {
		add_action( 'admin_init', [ $this, 'determine_screen' ] );

		$this->sync_screen        = new Screen\Sync();
		$this->health_info_screen = new Screen\HealthInfo();
		$this->status_report      = new Screen\StatusReport();
		$this->features           = new Screen\Features();
		$this->settings           = new Screen\Settings();

		$this->sync_screen->setup();
		$this->health_info_screen->setup();
		$this->status_report->setup();
		$this->features->setup();
		$this->settings->setup();
	}

	/**
	 * Determine current ElasticPress screen. null means not EP screen.
	 *
	 * @since 3.0
	 */
	public function determine_screen() {
		$page = ! empty( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : false; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! $page || false === strpos( $page, 'elasticpress' ) ) {
			return;
		}

		$install_status   = Installer::factory()->get_install_status();
		$install_complete = ! empty( $_GET['install_complete'] ) ? sanitize_key( $_GET['install_complete'] ) : false; // phpcs:ignore WordPress.Security.NonceVerification

		$this->screen = 'install';

		// Handle main dashboard page with special logic
		if ( 'elasticpress' === $page ) {
			$can_access = ! $install_complete && ( true === $install_status || Utils\isset_do_sync_parameter() );
			if ( $can_access ) {
				$this->screen = Utils\is_top_level_admin_context() ? 'dashboard' : 'weighting';
			}
			return;
		}

		// Map page slugs to screen names with their access conditions
		$page_screen_map = [
			'elasticpress-settings'      => 'settings',
			'elasticpress-health'        => 'health',
			'elasticpress-weighting'     => 'weighting',
			'elasticpress-synonyms'      => 'synonyms',
			'elasticpress-sync'          => 'sync',
			'elasticpress-status-report' => 'status-report',
		];

		if ( ! isset( $page_screen_map[ $page ] ) ) {
			return;
		}

		$screen_name = $page_screen_map[ $page ];
		$is_settings = 'settings' === $screen_name;

		// Settings screen allows install status 2, others require completed install or sync parameter
		$can_access = $is_settings
			? ( true === $install_status || 2 === $install_status || Utils\isset_do_sync_parameter() )
			: ( ! $install_complete && ( true === $install_status || Utils\isset_do_sync_parameter() ) );

		if ( $can_access ) {
			$this->screen = $screen_name;
		}
	}

	/**
	 * Output template for current screen
	 *
	 * @since 3.0
	 */
	public function output() {
		$page_screen_map = [
			'dashboard'     => 'dashboard',
			'settings'      => 'settings',
			'install'       => 'install',
			'health'        => 'stats',
			'sync'          => 'sync',
			'status-report' => 'status-report',
		];

		if ( ! isset( $page_screen_map[ $this->screen ] ) ) {
			return;
		}

		require_once __DIR__ . '/../partials/' . $page_screen_map[ $this->screen ] . '-page.php';
	}

	/**
	 * Get current screen
	 *
	 * @since  3.0
	 * @return string
	 */
	public function get_current_screen() {
		return $this->screen;
	}

	/**
	 * Set current screen
	 *
	 * @since  3.0
	 * @param  string $screen Screen to set
	 */
	public function set_current_screen( $screen ) {
		$this->screen = $screen;
	}

	/**
	 * Return singleton instance of class
	 *
	 * @return self
	 * @since 3.0
	 */
	public static function factory() {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new self();
			$instance->setup();
		}

		return $instance;
	}
}
