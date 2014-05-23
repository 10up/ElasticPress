<?php

class ES_Elasticsearch {

	/**
	 * Setup class
	 *
	 * @since 0.1.0
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_taxonomy') );

		if ( is_admin() ) {
			add_action( 'network_admin_menu', array( $this, 'network_admin_menu' ) );

			$config = es_get_option( 0 );

			if ( empty( $config['cross_site_search_active'] ) ) {
				add_action( 'admin_menu', array( $this, 'admin_menu' ) );
				add_action( 'admin_post_es_settings', array( $this, 'save_settings' ) );
				add_action( 'admin_post_es_sync', array( $this, 'sync' ) );
			}
		}
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

		register_taxonomy( 'es_hidden', $post_types, $args );
	}

	/**
	 * Add Elasticsearch menu to the Network admin menu
	 *
	 * @since 0.1.0
	 */
	public function network_admin_menu() {
		add_submenu_page( 'settings.php', 'Elasticsearch', 'Elasticsearch', 'administrator', 'es_settings', array( $this, 'network_screen_options' ) );
		add_submenu_page( 'settings.php', 'Elasticsearch Debug', 'Elasticsearch Debug', 'administrator', 'es_debug', array( $this, 'network_debug_screen_options' ) );
		add_action( 'network_admin_edit_es_settings', array( $this, 'network_save_settings' ) );
		add_action( 'network_admin_edit_es_sync', array( $this, 'network_sync' ) );
	}

	/**
	 * Create single site admin menu
	 *
	 * @since 0.1.0
	 */
	public function admin_menu() {
		add_options_page( 'Elasticsearch', 'Elasticsearch', 'manage_options', 'es_settings', array( $this, 'screen_options' ) );
	}

	/**
	 * Save single site settings
	 *
	 * @since 0.1.0
	 */
	public function save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'You do not have sufficient permissions to access this page.' );
		}

		if ( empty( $_POST['es_settings_nonce'] ) || ! wp_verify_nonce( $_POST['es_settings_nonce'], 'es_settings_action' ) ) {
			wp_die( 'Are you lost?' );
		}

		$site_config = es_get_option();

		if ( isset( $_POST['es_config']['host'] ) ) {
			$site_config['host'] = esc_url_raw( $_POST['es_config']['host'] );
		}

		if ( isset( $_POST['es_config']['index_name'] ) ) {
			$site_config['index_name'] = sanitize_text_field( $_POST['es_config']['index_name'] );
		}

		if ( isset( $_POST['es_config']['post_types'] ) &&  is_array( $_POST['es_config']['post_types'] ) ) {
			$site_config['post_types'] = array_map( 'sanitize_text_field', $_POST['es_config']['post_types'] );
		} else {
			$site_config['post_types'] = array();
		}

		es_update_option( $site_config );

		wp_redirect( admin_url( 'options-general.php?page=es_settings' ) );
		exit();
	}

	/**
	 * Sync single site posts
	 *
	 * @since 0.1.0
	 */
	public function sync() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'You do not have sufficient permissions to access this page.' );
		}

		if ( isset( $_POST['es_sync_nonce'] ) && wp_verify_nonce( $_POST['es_sync_nonce'], 'es_sync_action' ) ) {

			if ( ! empty( $_POST['es_full_sync'] ) ) {
				es_schedule_sync();
			} elseif ( ! empty( $_POST['es_cancel_sync'] ) ) {
				es_reset_sync();
			}

		}

		wp_redirect( admin_url( 'options-general.php?page=es_settings' ) );
		exit;
	}

	/**
	 * Cross site post sync
	 *
	 * @since 0.1.0
	 */
	public function network_sync() {
		if ( ! is_super_admin() ) {
			wp_die( 'You do not have sufficient permissions to access this page.' );
		}

		if ( isset( $_POST['es_network_sync_nonce'] ) && wp_verify_nonce( $_POST['es_network_sync_nonce'], 'es_network_sync_action' ) ) {

			if ( ! empty( $_POST['es_full_sync'] ) ) {
				$sites = wp_get_sites();

				foreach ( $sites as $site ) {
					$site_config = es_get_option( $site['blog_id'] );

					if ( ! empty( $site_config['post_types'] ) ) {
						es_schedule_sync( $site['blog_id'] );
					}
				}
			} elseif ( ! empty( $_POST['es_cancel_sync'] ) ) {
				$sites = wp_get_sites();

				foreach ( $sites as $site ) {
					$site_config = es_get_option( $site['blog_id'] );

					if ( ! empty( $site_config['post_types'] ) ) {
						es_reset_sync( $site['blog_id'] );
					}
				}
			}

		}

		wp_redirect( admin_url( 'network/settings.php?page=es_settings' ) );
		exit;
	}

	/**
	 * Save cross-site settings
	 *
	 * @since 0.1.0
	 */
	public function network_save_settings() {
		if ( ! is_super_admin() ) {
			wp_die( 'You do not have sufficient permissions to access this page.' );
		}

		if ( empty( $_POST['es_network_settings_nonce'] ) || ! wp_verify_nonce( $_POST['es_network_settings_nonce'], 'es_network_settings_action' ) ) {
			wp_die( 'Are you lost?' );
		}

		/**
		 * Handle global config stuff
		 */

		$global_config = es_get_option( 0 );

		if ( isset( $_POST['es_config'][0]['host'] ) ) {
			$global_config['host'] = esc_url_raw( $_POST['es_config'][0]['host'] );
		}

		if ( isset( $_POST['es_config'][0]['index_name'] ) ) {
			$global_config['index_name'] = sanitize_text_field( $_POST['es_config'][0]['index_name'] );
		}

		if ( isset( $_POST['es_config'][0]['cross_site_search_active'] ) ) {
			$global_config['cross_site_search_active'] = 1;
		} else {
			$global_config['cross_site_search_active'] = 0;
		}

		es_update_option( $global_config, 0 );

		/**
		 * Handle site by site config
		 */

		if ( ! empty( $_POST['es_config'] ) && is_array( $_POST['es_config'] ) ) {
			foreach ( $_POST['es_config'] as $site_id => $new_site_config ) {
				if ( $site_id == 0 )
					continue;

				$site_config = es_get_option( $site_id );

				if ( isset( $new_site_config['post_types'] ) &&  is_array( $new_site_config['post_types'] ) ) {
					$site_config['post_types'] = array_map( 'sanitize_text_field', $new_site_config['post_types'] );
				} else {
					$site_config['post_types'] = array();
				}

				es_update_option( $site_config, $site_id );
			}
		}

		wp_redirect( admin_url( 'network/settings.php?page=es_settings' ) );
		exit();
	}

	/**
	 * Output settings for single site
	 *
	 * @since 0.1.0
	 */
	public function screen_options() {

		$site_config = es_get_option();
		$post_types = get_post_types();
		?>

		<div class="wrap">
			<form method="post" action="<?php echo admin_url( 'admin-post.php' ) ?>">
				<?php wp_nonce_field( 'es_settings_action', 'es_settings_nonce' ); ?>
				<input type="hidden" name="action" value="es_settings">

				<h2>Elasticsearch Settings</h2>

				<p>
					<label for="es_host">Host:</label>
					<input value="<?php echo esc_attr( $site_config['host'] ); ?>" type="text" name="es_config[host]" id="es_host">
				</p>

				<p>
					<label for="es_index_name">Index Name:</label>
					<input value="<?php echo esc_attr( $site_config['index_name'] ); ?>" type="text" name="es_config[index_name]" id="es_index_name">
				</p>

				<fieldset>
					<legend>Post types to index:</legend>

					<?php foreach ( $post_types as $post_type ) : ?>

						<p><input <?php checked( in_array( $post_type, $site_config['post_types'] ), true ); ?> type="checkbox" name="es_config[post_types][]" value="<?php echo esc_attr( $post_type ); ?>"> <?php echo esc_html( $post_type ); ?></p>

					<?php endforeach; ?>
				</fieldset>

				<?php submit_button(); ?>
			</form>

			<form method="post" action="<?php echo admin_url( 'admin-post.php' ) ?>">
				<?php wp_nonce_field( 'es_sync_action', 'es_sync_nonce' ); ?>
				<input type="hidden" name="action" value="es_sync">

				<h3>Sync</h3>

				<?php if ( ! es_is_sync_alive() ) : ?>
					<?php submit_button( 'Start Full Sync', 'primary', 'es_full_sync' ); ?>
				<?php else : ?>
					<p>
						Syncs are currently running.
					</p>
					<?php submit_button( 'Cancel Full Sync', 'primary', 'es_cancel_sync' ); ?>
				<?php endif; ?>
			</form>
		</div>

		<?php
	}

	/**
	 * Create a rudimentary debug page
	 *
	 * @todo Remove me!
	 */
	public function network_debug_screen_options() {
		$config = get_site_option( 'es_config_by_site' );
		$statii = get_site_option( 'es_status_by_site' );
		?>

		<h2>Config by Site:</h2>
		<pre><?php var_dump( $config ); ?></pre>

		<h2>Status by Site:</h2>
		<pre><?php var_dump( $statii ); ?></pre>

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

		$global_config = es_get_option( 0 );
		?>

		<div class="wrap">
			<form method="post" action="<?php echo admin_url( 'network/edit.php?action=es_settings' ) ?>">
				<?php wp_nonce_field( 'es_network_settings_action', 'es_network_settings_nonce' ); ?>

				<h2>Elasticsearch Network Settings</h2>

				<p>
					<label for="es_cross_site_search_active">Activate Cross Site Search:</label>
					<input <?php checked( ! empty( $global_config['cross_site_search_active'] ), true ); ?> type="checkbox" name="es_config[0][cross_site_search_active]" id="es_cross_site_search_active" value="1">
				</p>

				<p>
					<label for="es_host">Host:</label>
					<input value="<?php echo esc_attr( $global_config['host'] ); ?>" type="text" name="es_config[0][host]" id="es_host" value="">
				</p>

				<p>
					<label for="es_index_name">Index Name:</label>
					<input value="<?php echo esc_attr( $global_config['index_name'] ); ?>" type="text" name="es_config[0][index_name]" id="es_index_name">
				</p>

				<?php foreach ( $sites as $site ) :
					switch_to_blog( $site['blog_id'] );
					$post_types = get_post_types();
					$site_config = es_get_option( $site['blog_id'] ); ?>

					<fieldset>
						<input type="hidden" name="es_config[<?php echo $site['blog_id']; ?>][active]" value="1">
						<legend>Post types for site <?php echo $site['blog_id']; ?></legend>

						<?php foreach ( $post_types as $post_type ) : ?>

							<p><input <?php checked( in_array( $post_type, $site_config['post_types'] ), true ); ?> type="checkbox" name="es_config[<?php echo $site['blog_id']; ?>][post_types][]" value="<?php echo esc_attr( $post_type ); ?>"> <?php echo esc_html( $post_type ); ?></p>

						<?php endforeach; ?>
					</fieldset>

				<?php restore_current_blog(); endforeach; ?>

				<?php submit_button(); ?>

			</form>

			<form method="post" action="<?php echo admin_url( 'network/edit.php?action=es_sync' ) ?>">
				<?php wp_nonce_field( 'es_network_sync_action', 'es_network_sync_nonce' ); ?>

				<h3>Site-wide Sync</h3>

				<?php if ( es_get_alive_sync_count() < 1 ) : ?>
					<?php submit_button( 'Start Full Sync', 'primary', 'es_full_sync' ); ?>
				<?php else : ?>
					<p>
						Syncs are currently running.
					</p>
					<?php submit_button( 'Cancel Full Sync', 'primary', 'es_cancel_sync' ); ?>
				<?php endif; ?>
			</form>
		</div>

		<?php
	}

	/**
	 * Return a singleton instance of the class.
	 *
	 * @return ES_Elasticsearch
	 */
	public static function factory() {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new self();
		}

		return $instance;
	}

}

ES_Elasticsearch::factory();