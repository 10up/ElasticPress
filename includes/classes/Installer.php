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
		add_filter( 'admin_title', [ $this, 'filter_admin_title' ], 10 );
	}

	/**
	 * Filter admin title for install page
	 *
	 * @param  string $admin_title Current title
	 * @since  3.0
	 * @return string
	 */
	public function filter_admin_title( $admin_title ) {
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
		$skip_install = Utils\get_option( 'ep_skip_install', false );

		if ( $skip_install ) {
			$this->install_status = true;

			return;
		}

		$last_sync = Utils\get_option( 'ep_last_sync', false );
		if ( ! empty( $last_sync ) ) {
			$this->install_status = true;

			return;
		}

		$host = Utils\get_host();

		if ( empty( $host ) && empty( $_POST['ep_host'] ) ) {
			$this->install_status = 2;

			return;
		}

		$this->install_status = 3;

		$this->maybe_set_features();
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
	 * Check if it should use the features selected during the install to update the settings.
	 */
	public function maybe_set_features() {
		if ( empty( $_POST['ep_install_page_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['ep_install_page_nonce'] ), 'ep_install_page' ) ) {
			return;
		}

		if ( ! isset( $_POST['features'] ) || ! is_array( $_POST['features'] ) ) {
			return;
		}

		$registered_features = \ElasticPress\Features::factory()->registered_features;
		$activation_features = wp_list_filter( $registered_features, array( 'available_during_installation' => true ) );

		foreach ( $activation_features as $slug => $feature ) {
			if ( in_array( $slug, $_POST['features'], true ) ) {
				\ElasticPress\Features::factory()->activate_feature( $slug );
			} else {
				\ElasticPress\Features::factory()->deactivate_feature( $slug );
			}
		}

		$this->install_status = 4;
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
