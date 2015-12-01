<?php
/**
 * Create an ElasticPress settings page.
 *
 * @package elasticpress
 *
 * @since   1.7
 *
 * @author  Allan Collins <allan.collins@10up.com>
 */

/**
 * ElasticPress Settings Page
 *
 * Sets up the settings page to handle ElasticPress configuration.
 */
class EP_Settings {

	/**
	 * WordPress options page
	 *
	 * @since 1.7
	 *
	 * @var object
	 */
	var $options_page;

	/**
	 * Register WordPress hooks
	 *
	 * Loads initial actions.
	 *
	 * @since 1.7
	 *
	 * @return EP_Settings
	 */
	public function __construct() {

		ep_set_api_key();
		ep_check_host();

		if ( is_multisite() ) { // Must be network admin in multisite.

			add_action( 'network_admin_menu', array( $this, 'action_admin_menu' ) );

		} else {

			add_action( 'admin_menu', array( $this, 'action_admin_menu' ) );

		}

		// Add JavaScripts.
		add_action( 'admin_enqueue_scripts', array( $this, 'action_admin_enqueue_scripts' ) );

		add_action( 'admin_init', array( $this, 'action_admin_init' ) );

	}

	/**
	 * Register and Enqueue JavaScripts
	 *
	 * Registers and enqueues the necessary JavaScripts for the interface.
	 *
	 * @since 1.7
	 *
	 * @return void
	 */
	public function action_admin_enqueue_scripts() {

		// Enqueue more easily debugged version if applicable.
		if ( defined( 'WP_DEBUG' ) && true === WP_DEBUG ) {

			wp_register_script( 'ep_admin', EP_URL . 'assets/js/elasticpress-admin.js', array( 'jquery' ), EP_VERSION );

			wp_register_style( 'ep_progress_style', EP_URL . 'assets/css/jquery-ui.css', array(), EP_VERSION );
			wp_register_style( 'ep_styles', EP_URL . 'assets/css/elasticpress.css', array(), EP_VERSION );

		} else {

			wp_register_script( 'ep_admin', EP_URL . 'assets/js/elasticpress-admin.min.js', array( 'jquery' ), EP_VERSION );

			wp_register_style( 'ep_progress_style', EP_URL . 'assets/css/jquery-ui.min.css', array(), EP_VERSION );
			wp_register_style( 'ep_styles', EP_URL . 'assets/css/elasticpress.min.css', array(), EP_VERSION );

		}

		// Only add the following to the settings page.
		if ( isset( get_current_screen()->id ) && strpos( get_current_screen()->id, 'settings_page_elasticpress' ) !== false ) {

			wp_enqueue_style( 'ep_progress_style' );
			wp_enqueue_style( 'ep_styles' );

			wp_enqueue_script( 'ep_admin' );

		}
	}

	/**
	 * Admin-init actions
	 *
	 * Sets up Settings API.
	 *
	 * @since 1.7
	 *
	 * @return void
	 */
	public function action_admin_init() {

		//Save options for multisite
		if ( is_multisite() && ( isset( $_POST['ep_host'] ) || isset( $_POST['ep_activate'] ) ) ) {

			if ( ! check_admin_referer( 'elasticpress-options' ) ) {
				die( esc_html__( 'Security error!', 'elasticpress' ) );
			}

			if ( isset( $_POST['ep_host'] ) ) {

				$host = $this->sanitize_ep_host( $_POST['ep_host'] );
				update_site_option( 'ep_host', $host );

			}

			if ( isset( $_POST['ep_api_key'] ) ) {

				$host = sanitize_text_field( $_POST['ep_api_key'] );
				update_site_option( 'ep_api_key', $host );

			}

			if ( isset( $_POST['ep_activate'] ) ) {

				$this->sanitize_ep_activate( $_POST['ep_activate'] );

			} else {

				$this->sanitize_ep_activate( false );

			}
		}

		add_settings_section( 'ep_settings_section_main', '', array( $this, 'ep_settings_section_hightlight' ), 'elasticpress' );

		if ( is_wp_error( ep_check_host() ) || get_site_option( 'ep_host' ) ) {

			add_settings_field( 'ep_host', esc_html__( 'ElasticSearch Host:', 'elasticpress' ), array( $this, 'setting_callback_host' ), 'elasticpress', 'ep_settings_section_main' );
			add_settings_field( 'ep_api_key', esc_html__( 'ElasticPress API Key:', 'elasticpress' ), array( $this, 'setting_callback_api_key' ), 'elasticpress', 'ep_settings_section_main' );

		}

		$stats = EP_Lib::ep_get_index_status();

		if ( $stats['status'] && ! is_wp_error( ep_check_host() ) ) {

			add_settings_field( 'ep_activate', esc_html__( 'Use ElasticSearch:', 'elasticpress' ), array( $this, 'setting_callback_activate' ), 'elasticpress', 'ep_settings_section_main' );

		}

		register_setting( 'elasticpress', 'ep_host', array( $this, 'sanitize_ep_host' ) );
		register_setting( 'elasticpress', 'ep_api_key', 'sanitize_text_field' );
		register_setting( 'elasticpress', 'ep_activate', array( $this, 'sanitize_ep_activate' ) );

	}

	/**
	 * Admin menu actions
	 *
	 * Adds options page to admin menu.
	 *
	 * @since 1.7
	 *
	 * @return void
	 */
	public function action_admin_menu() {

		$parent_slug = 'options-general.php';
		$capability  = 'manage_options';

		if ( is_multisite() ) {

			$parent_slug = 'settings.php';
			$capability  = 'manage_network';

		}

		$this->options_page = add_submenu_page(
			$parent_slug,
			'ElasticPress',
			'ElasticPress',
			$capability,
			'elasticpress',
			array( $this, 'settings_page' )
		);
	}

	/**
	 * Load the settings page view
	 *
	 * Callback for add_meta_box to load column view.
	 *
	 * @since 1.7
	 *
	 * @param WP_Post|NULL $post Normally WP_Post object, but NULL in our case.
	 * @param array        $args Arguments passed from add_meta_box.
	 *
	 * @return void
	 */
	public function load_view( $post, $args ) {

		$file = dirname( dirname( __FILE__ ) ) . '/includes/settings/' . sanitize_file_name( $args['args'][0] );

		if ( file_exists( $file ) ) {
			require $file;
		}
	}

	/**
	 * Populate settings page columns
	 *
	 * Creates meta boxes for the settings page columns.
	 *
	 * @since 1.7
	 *
	 * @return void
	 */
	protected function populate_columns() {

		add_meta_box(
			'ep-contentbox-1',
			'Settings',
			array(
				$this,
				'load_view',
			),
			$this->options_page,
			'normal',
			'core',
			array( 'form.php' )
		);

		add_meta_box(
			'ep-contentbox-2',
			'Current Status',
			array(
				$this,
				'load_view',
			),
			$this->options_page,
			'side',
			'core',
			array( 'status.php' )
		);

		/**
		 * Allow other metaboxes
		 *
		 * Allows individual features to add their own meta-boxes.
		 *
		 * @since 0.4.0
		 *
		 * @param EP_Settings $this Instance of ep_Settings.
		 */
		do_action( 'ep_do_settings_meta', $this );

	}

	/**
	 * Sanitize activation
	 *
	 * Sanitizes the activation input from the dashboard and performs activation/deactivation.
	 *
	 * @since 0.2.0
	 *
	 * @param string $input input items.
	 *
	 * @return string Sanitized input items
	 */
	public function sanitize_ep_activate( $input ) {

		$input = ( isset( $input ) && 1 === intval( $input ) ? true : false );

		if ( true === $input ) {

			EP_Lib::ep_activate();

		} else {

			EP_Lib::ep_deactivate();

		}

		return $input;

	}

	/**
	 * Sanitize EP_HOST
	 *
	 * Sanitizes the EP_HOST inputed from the dashboard.
	 *
	 * @since 0.2.0
	 *
	 * @param string $input input items.
	 *
	 * @return string Sanitized input items
	 */
	public function sanitize_ep_host( $input ) {

		$input = esc_url_raw( $input );

		return $input;

	}

	/**
	 * Setting callback
	 *
	 * Callback for settings field. Displays textbox to specify the EP_HOST.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function setting_callback_activate() {

		echo '<input type="checkbox" value="1" name="ep_activate" id="ep_activate"' . checked( true, ep_is_activated(), false ) . ' />';

	}

	/**
	 * Setting callback
	 *
	 * Callback for settings field. Displays textbox to specify the EP_API_KEY.
	 *
	 * @since 0.3.0
	 *
	 * @return void
	 */
	public function setting_callback_api_key() {

		echo '<input name="ep_api_key" id="ep_api_key" type="text" value="' . esc_attr( get_site_option( 'ep_api_key' ) ) . '">';

	}

	/**
	 * Setting callback
	 *
	 * Callback for settings field. Displays textbox to specify the EP_HOST.
	 *
	 * @since 1.7
	 *
	 * @return void
	 */
	public function setting_callback_host() {

		echo '<input name="ep_host" id="ep_host" type="text" value="' . esc_attr( get_site_option( 'ep_host' ) ) . '">';

	}

	/**
	 * Build settings page
	 *
	 * Loads up the settings page.
	 *
	 * @since 1.7
	 *
	 * @return void
	 */
	public function settings_page() {

		$this->populate_columns();

		include dirname( __FILE__ ) . '/../includes/settings-page.php';

	}

	/**
	 * Displays Settings header
	 *
	 * Adds a header to main settings information
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function ep_settings_section_hightlight() {

		echo '<h2>' . esc_html__( 'ElasticSearch Integration Options', 'elasticpress' ) . '</h2>';

	}
}
