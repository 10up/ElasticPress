<?php

class EP_ElasticPress {

	/**
	 * Placeholder method
	 *
	 * @since 0.1.0
	 */
	public function __construct() { }

	/**
	 * Setup actions and filters
	 *
	 * @since 0.1.2
	 */
	public function setup() {
		add_action( 'init', array( $this, 'register_taxonomy') );

		if ( is_admin() ) {
			add_action( 'network_admin_menu', array( $this, 'network_admin_menu' ) );

			add_action( 'admin_enqueue_scripts', array( $this, 'network_scripts' ) );

			// @todo ensure that this should be sent 0 and not null
			$config = ep_get_option( 0 );

			if ( empty( $config['cross_site_search_active'] ) ) {
				add_action( 'admin_menu', array( $this, 'admin_menu' ) );
				add_action( 'admin_post_ep_settings', array( $this, 'save_settings' ) );
				add_action( 'admin_post_ep_sync', array( $this, 'sync' ) );
			}
		}
	}

	/**
	 * Localize plugin
	 *
	 * @since 0.1.3
	 * @return void
	 */
	public function action_plugins_loaded() {
		load_plugin_textdomain( 'elasticpress', false, basename( dirname( __FILE__ ) ) . '/lang' );
	}

	/**
	 * Set up network settings scripts
	 *
	 * @since 0.1.0
	 */
	public function network_scripts() {

		if ( defined( SCRIPT_DEBUG ) && SCRIPT_DEBUG ) {
			$js_path = '/js/network-settings.js';
		} else {
			$js_path = '/build/js/network-settings.min.js';
		}

		wp_enqueue_script( 'ep-network-settings', plugins_url( $js_path, dirname( __FILE__ ) ), array( 'jquery' ), '1.0', true );
	}

	/**
	 * Register taxonomies
	 *
	 * @since 0.1.0
	 */
	public function register_taxonomy() {
		$args = array(
			'hierarchical' => false,
			'public' => false,
			'query_var' => false,
			'rewrite' => false,
		);

		$post_types = get_post_types();

		register_taxonomy( 'ep_hidden', $post_types, $args );
	}

	/**
	 * Add Elasticsearch menu to the Network admin menu
	 *
	 * @since 0.1.0
	 */
	public function network_admin_menu() {
		add_submenu_page( 'settings.php', __( 'ElasticPress', 'elasticpress' ), __( 'ElasticPress', 'elasticpress' ), 'administrator', 'ep_settings', array( $this, 'network_screen_options' ) );
		add_action( 'network_admin_edit_ep_settings', array( $this, 'network_save_settings' ) );
		add_action( 'network_admin_edit_ep_sync', array( $this, 'network_sync' ) );
	}

	/**
	 * Create single site admin menu
	 *
	 * @since 0.1.0
	 */
	public function admin_menu() {
		add_options_page( __( 'ElasticPress', 'elasticpress' ), __( 'ElasticPress', 'elasticpress' ), 'manage_options', 'ep_settings', array( $this, 'screen_options' ) );
	}

	/**
	 * Save single site settings
	 *
	 * @since 0.1.0
	 */
	public function save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'elasticpress' ) );
		}

		if ( empty( $_POST['ep_settings_nonce'] ) || ! wp_verify_nonce( $_POST['ep_settings_nonce'], 'ep_settings_action' ) ) {
			wp_die( __( 'Are you lost?', 'elasticpress' ) );
		}

		$site_config = ep_get_option();

		if ( isset( $_POST['ep_config']['host'] ) ) {
			$site_config['host'] = esc_url_raw( $_POST['ep_config']['host'] );
		}

		if ( isset( $_POST['ep_config']['index_name'] ) ) {
			$site_config['index_name'] = sanitize_text_field( $_POST['ep_config']['index_name'] );
		}

		if ( isset( $_POST['ep_config']['post_types'] ) &&  is_array( $_POST['ep_config']['post_types'] ) ) {
			$site_config['post_types'] = array_map( 'sanitize_text_field', $_POST['ep_config']['post_types'] );
		} else {
			$site_config['post_types'] = array();
		}

		ep_update_option( $site_config );

		wp_redirect( admin_url( 'options-general.php?page=ep_settings' ) );
		exit();
	}

	/**
	 * Sync single site posts
	 *
	 * @since 0.1.0
	 */
	public function sync() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'elasticpress' ) );
		}

		if ( isset( $_POST['ep_sync_nonce'] ) && wp_verify_nonce( $_POST['ep_sync_nonce'], 'ep_sync_action' ) ) {

			if ( ! empty( $_POST['ep_full_sync'] ) ) {
				ep_schedule_sync();
			} elseif ( ! empty( $_POST['ep_cancel_sync'] ) ) {
				ep_reset_sync();
			}

		}

		wp_redirect( admin_url( 'options-general.php?page=ep_settings' ) );
		exit;
	}

	/**
	 * Cross site post sync
	 *
	 * @since 0.1.0
	 */
	public function network_sync() {
		if ( ! is_super_admin() ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'elasticpress' ) );
		}

		if ( isset( $_POST['ep_network_sync_nonce'] ) && wp_verify_nonce( $_POST['ep_network_sync_nonce'], 'ep_network_sync_action' ) ) {

			if ( ! empty( $_POST['ep_full_sync'] ) ) {
				$sites = wp_get_sites();

				foreach ( $sites as $site ) {
					$site_config = ep_get_option( $site['blog_id'] );

					if ( ! empty( $site_config['post_types'] ) ) {
						ep_schedule_sync( $site['blog_id'] );
					}
				}
			} elseif ( ! empty( $_POST['ep_cancel_sync'] ) ) {
				$sites = wp_get_sites();

				foreach ( $sites as $site ) {
					$site_config = ep_get_option( $site['blog_id'] );

					if ( ! empty( $site_config['post_types'] ) ) {
						ep_reset_sync( $site['blog_id'] );
					}
				}
			}

		}

		wp_redirect( admin_url( 'network/settings.php?page=ep_settings' ) );
		exit;
	}

	/**
	 * Save cross-site settings
	 *
	 * @since 0.1.0
	 */
	public function network_save_settings() {
		if ( ! is_super_admin() ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'elasticpress' ) );
		}

		if ( empty( $_POST['ep_network_settings_nonce'] ) || ! wp_verify_nonce( $_POST['ep_network_settings_nonce'], 'ep_network_settings_action' ) ) {
			wp_die( __( 'Are you lost?', 'elasticpress' ) );
		}

		/**
		 * Handle global config stuff
		 */

		$global_config = ep_get_option( 0 );

		if ( isset( $_POST['ep_config'][0]['host'] ) ) {
			$global_config['host'] = esc_url_raw( $_POST['ep_config'][0]['host'] );
		}

		if ( isset( $_POST['ep_config'][0]['index_name'] ) ) {
			$global_config['index_name'] = sanitize_text_field( $_POST['ep_config'][0]['index_name'] );
		}

		if ( isset( $_POST['ep_config'][0]['cross_site_search_active'] ) ) {
			$global_config['cross_site_search_active'] = 1;
		} else {
			$global_config['cross_site_search_active'] = 0;
		}

		ep_update_option( $global_config, 0 );

		/**
		 * Handle site by site config
		 */

		if ( ! empty( $_POST['ep_config'] ) && is_array( $_POST['ep_config'] ) ) {
			foreach ( $_POST['ep_config'] as $site_id => $new_site_config ) {
				if ( 0 === $site_id )
					continue;

				$site_config = ep_get_option( $site_id );

				if ( isset( $new_site_config['post_types'] ) &&  is_array( $new_site_config['post_types'] ) ) {
					$site_config['post_types'] = array_map( 'sanitize_text_field', $new_site_config['post_types'] );
				} else {
					$site_config['post_types'] = array();
				}

				ep_update_option( $site_config, $site_id );
			}
		}

		wp_redirect( admin_url( 'network/settings.php?page=ep_settings' ) );
		exit();
	}

	/**
	 * Output settings for single site
	 *
	 * @since 0.1.0
	 */
	public function screen_options() {

		$site_config = ep_get_option();
		$post_types = get_post_types();
		?>

		<div class="wrap">
			<form method="post" action="<?php echo admin_url( 'admin-post.php' ) ?>">
				<?php wp_nonce_field( 'ep_settings_action', 'ep_settings_nonce' ); ?>
				<input type="hidden" name="action" value="ep_settings">

				<h2><?php _e( 'ElasticPress Settings', 'elasticpress' ); ?></h2>

				<p>
					<label for="ep_host"><?php _e( 'Host:', 'elasticpress' ); ?></label><br />
					<input class="regular-text" value="<?php echo esc_attr( $site_config['host'] ); ?>" type="text" name="ep_config[host]" id="ep_host">
				</p>

				<p>
					<label for="ep_index_name"><?php _e( 'Index Name:', 'elasticpress' ); ?></label><br />
					<input class="regular-text" value="<?php echo esc_attr( $site_config['index_name'] ); ?>" type="text" name="ep_config[index_name]" id="ep_index_name">
				</p>

				<fieldset>
					<legend><h3><?php _e( 'Post Types to Index:', 'elasticpress' ); ?></h3></legend>

					<?php foreach ( $post_types as $post_type ) : ?>

						<p><input <?php checked( in_array( $post_type, $site_config['post_types'] ), true ); ?> type="checkbox" name="ep_config[post_types][]" value="<?php echo esc_attr( $post_type ); ?>"> <?php echo esc_html( $post_type ); ?></p>

					<?php endforeach; ?>
				</fieldset>

				<?php submit_button(); ?>
			</form>

			<form method="post" action="<?php echo admin_url( 'admin-post.php' ) ?>">
				<?php wp_nonce_field( 'ep_sync_action', 'ep_sync_nonce' ); ?>
				<input type="hidden" name="action" value="ep_sync">

				<h2><?php _e( 'Post Sync', 'elasticpress' ); ?></h2>

				<p><?php _e( 'A sync will send all the posts in post types marked for sync to your Elasticsearch server.
				Existing posts will be updated. This can take hours depending on how many posts you have in your
				database.', 'elasticpress' ); ?></p>

				<?php if ( ! ep_is_sync_alive() ) : ?>
					<?php submit_button( __( 'Start Sync', 'elasticpress' ), 'primary', 'ep_full_sync' ); ?>
				<?php else : ?>
					<p>
						<em><?php _e( 'Syncs are currently running.', 'elasticpress' ); ?></em>
					</p>
					<?php submit_button( __( 'Cancel Sync', 'elasticpress' ), 'primary', 'ep_cancel_sync' ); ?>
				<?php endif; ?>
			</form>
		</div>

		<?php
	}

	/**
	 * Output settings for network settings page
	 *
	 * @since 0.1.0
	 */
	public function network_screen_options() {
		if ( ! is_super_admin() ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'ewp' ) );
		}

		$sites = wp_get_sites();

		$global_config = ep_get_option( 0 );
		?>

		<div class="wrap">
			<form method="post" action="<?php echo admin_url( 'network/edit.php?action=ep_settings' ) ?>">
				<?php wp_nonce_field( 'ep_network_settings_action', 'ep_network_settings_nonce' ); ?>

				<h2><?php _e( 'ElasticPress Network Settings', 'elasticpress' ); ?></h2>

				<p>
					<label for="ep_cross_site_search_active"><?php _e( 'Activate Cross Site Search:', 'elasticpress' ); ?></label>
					<input style="margin-left: .4em" <?php checked( ! empty( $global_config['cross_site_search_active'] ), true ); ?> type="checkbox" name="ep_config[0][cross_site_search_active]" id="ep_cross_site_search_active" value="1">
				</p>

				<p>
					<label for="ep_host"><?php _e( 'Host:', 'elasticpress' ); ?></label><br />
					<input class="regular-text" value="<?php echo esc_attr( $global_config['host'] ); ?>" type="text" name="ep_config[0][host]" id="ep_host" value="">
				</p>

				<p>
					<label for="ep_index_name"><?php _e( 'Index Name:', 'elasticpress' ); ?></label><br />
					<input class="regular-text" value="<?php echo esc_attr( $global_config['index_name'] ); ?>" type="text" name="ep_config[0][index_name]" id="ep_index_name">
				</p>

				<div id="ep-post-type-chooser">
					<?php foreach ( $sites as $site ) :
						switch_to_blog( $site['blog_id'] );
						$site_config = ep_get_option( $site['blog_id'] ); ?>

						<fieldset>
							<input type="hidden" name="ep_config[<?php echo (int) $site['blog_id']; ?>][active]" value="1">
							<legend><h3><?php _e( 'Post Types for Site:', 'elasticpress' ); ?> <?php echo (int) $site['blog_id']; ?></h3></legend>

							<div class="post-types" data-selected="<?php echo esc_attr( implode( ',', $site_config['post_types'] ) ); ?>" data-site-id="<?php echo (int) $site['blog_id']; ?>" data-ajax-url="<?php echo esc_url( home_url( '?ep_query=post_types' ) ); ?>"></div>
						</fieldset>

					<?php restore_current_blog(); endforeach; ?>
				</div>

				<?php submit_button(); ?>

			</form>

			<form method="post" action="<?php echo admin_url( 'network/edit.php?action=ep_sync' ) ?>">
				<?php wp_nonce_field( 'ep_network_sync_action', 'ep_network_sync_nonce' ); ?>

				<h2><?php _e( 'Site-wide Post Sync', 'elasticpress' ); ?></h2>

				<p><?php _e( 'A site-wide sync will send all the posts in post types marked for sync to your
				Elasticsearch server. Existing posts will be updated. This can take hours depending on how many
				posts you have across your network.', 'elasticpress' ); ?></p>

				<?php if ( ep_get_alive_sync_count() < 1 ) : ?>
					<?php submit_button( __( 'Start Sync', 'elasticpress' ), 'primary', 'ep_full_sync' ); ?>
				<?php else : ?>
					<p>
						<em><?php _e( 'Syncs are currently running.', 'elasticpress' ); ?></em>
					</p>
					<?php submit_button( __( 'Cancel Sync', 'elasticpress' ), 'primary', 'ep_cancel_sync' ); ?>
				<?php endif; ?>
			</form>
		</div>

		<?php
	}

	/**
	 * Return a singleton instance of the class.
	 *
	 * @return EP_ElasticPress
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

EP_ElasticPress::factory();