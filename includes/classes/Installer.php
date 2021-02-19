<?php
/**
 * ElasticPress installer handler
 *
 * @since  3.0
 * @package elasticpress
 */

namespace ElasticPress;

use ElasticPress\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Installer class
 */
class Installer {
	/**
	 * Current install status
	 *
	 * @var int|bool
	 * @since  3.0
	 */
	protected $install_status;

	/**
	 * Initialize class
	 *
	 * @since 3.0
	 */
	public function setup() {
		add_action( 'admin_init', [ $this, 'calculate_install_status' ], 9 );
		add_filter( 'admin_title', [ $this, 'filter_admin_title' ], 10, 2 );
	}

	/**
	 * Filter admin title for install page
	 *
	 * @param  string $admin_title Current title
	 * @param  string $title       Original title
	 * @since  3.0
	 * @return string
	 */
	public function filter_admin_title( $admin_title, $title ) {
		if ( 'install' === Screen::factory()->get_current_screen() ) {
			// translators: Site Name
			return sprintf( esc_html__( 'ElasticPress Setup &lsaquo; %s &#8212; WordPress', 'elasticpress' ), esc_html( get_bloginfo( 'name' ) ) );
		}

		return $admin_title;
	}

	/**
	 * Determine current install status
	 *
	 * @since 3.0
	 */
	public function calculate_install_status() {
		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			$skip_install = get_site_option( 'ep_skip_install', false );
		} else {
			$skip_install = get_option( 'ep_skip_install', false );
		}

		if ( $skip_install ) {
			$this->install_status = true;

			return;
		}

		$host      = Utils\get_host();
		$last_sync = ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) ? get_site_option( 'ep_last_sync', false ) : get_option( 'ep_last_sync', false );

		if ( ! empty( $last_sync ) ) {
			$this->install_status = true;

			return;
		}

		if ( empty( $host ) ) {
			$this->install_status = 2;

			if ( ! empty( $_POST['ep_host'] ) ) { // phpcs:ignore
				$this->install_status = 3;
			}

			return;
		} else {
			$this->install_status = 3;
		}
	}

	/**
	 * Get installation status
	 *
	 * false - not installed
	 * 2 - On step two of install
	 * 3 - On step three of install
	 * true - Install complete
	 *
	 * @return bool|int
	 */
	public function get_install_status() {
		/**
		 * Filter install status
		 *
		 * @hook ep_install_status
		 * @param  {string} $install_status Current install status
		 * @return {string} New install status
		 * @since  3.0
		 */
		return apply_filters( 'ep_install_status', $this->install_status );
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

