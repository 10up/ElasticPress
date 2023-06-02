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
	 * Initialize class
	 *
	 * @since 3.0
	 */
	public function setup() {
		add_action( 'admin_init', [ $this, 'determine_screen' ] );

		$this->sync_screen        = new Screen\Sync();
		$this->health_info_screen = new Screen\HealthInfo();
		$this->status_report      = new Screen\StatusReport();

		$this->sync_screen->setup();
		$this->health_info_screen->setup();
		$this->status_report->setup();
	}

	/**
	 * Determine current ElasticPress screen. null means not EP screen.
	 *
	 * @since 3.0
	 */
	public function determine_screen() {
		// If in network mode, don't output notice in admin and vice-versa.
		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			if ( ! is_network_admin() ) {
				return false;
			}
		} else {
			if ( is_network_admin() ) {
				return false;
			}
		}

		// phpcs:disable WordPress.Security.NonceVerification
		if ( ! empty( $_GET['page'] ) && false !== strpos( sanitize_key( $_GET['page'] ), 'elasticpress' ) ) {
			$install_status = Installer::factory()->get_install_status();

			$this->screen = 'install';

			if ( 'elasticpress' === $_GET['page'] ) {
				if ( ! isset( $_GET['install_complete'] ) && ( true === $install_status || isset( $_GET['do_sync'] ) ) ) {
					$this->screen = 'dashboard';
				}
			} elseif ( 'elasticpress-settings' === $_GET['page'] ) {
				if ( true === $install_status || 2 === $install_status || isset( $_GET['do_sync'] ) ) {
					$this->screen = 'settings';
				}
			} elseif ( 'elasticpress-health' === $_GET['page'] ) {
				if ( ! isset( $_GET['install_complete'] ) && ( true === $install_status || isset( $_GET['do_sync'] ) ) ) {
					$this->screen = 'health';
				}
			} elseif ( 'elasticpress-weighting' === $_GET['page'] ) {
				if ( ! isset( $_GET['install_complete'] ) && ( true === $install_status || isset( $_GET['do_sync'] ) ) ) {
					$this->screen = 'weighting';
				}
			} elseif ( 'elasticpress-synonyms' === $_GET['page'] ) {
				if ( ! isset( $_GET['install_complete'] ) && ( true === $install_status || isset( $_GET['do_sync'] ) ) ) {
					$this->screen = 'synonyms';
				}
			} elseif ( 'elasticpress-sync' === $_GET['page'] ) {
				if ( ! isset( $_GET['install_complete'] ) && ( true === $install_status || isset( $_GET['do_sync'] ) ) ) {
					$this->screen = 'sync';
				}
			} elseif ( 'elasticpress-status-report' === $_GET['page'] ) {
				if ( ! isset( $_GET['install_complete'] ) && ( true === $install_status || isset( $_GET['do_sync'] ) ) ) {
					$this->screen = 'status-report';
				}
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification
	}

	/**
	 * Output template for current screen
	 *
	 * @since 3.0
	 */
	public function output() {
		$install_status = Installer::factory()->get_install_status();

		switch ( $this->screen ) {
			case 'dashboard':
				require_once __DIR__ . '/../partials/dashboard-page.php';
				break;
			case 'settings':
				require_once __DIR__ . '/../partials/settings-page.php';
				break;
			case 'install':
				require_once __DIR__ . '/../partials/install-page.php';
				break;
			case 'health':
				require_once __DIR__ . '/../partials/stats-page.php';
				break;
			case 'sync':
				require_once __DIR__ . '/../partials/sync-page.php';
				break;
			case 'status-report':
				require_once __DIR__ . '/../partials/status-report-page.php';
				break;
		}
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
