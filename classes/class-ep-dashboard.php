<?php
/**
 * Create an ElasticPress dashboard page.
 *
 * @package elasticpress
 * @since   1.9
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * ElasticPress Dashboard Page
 *
 * Sets up the dashboard page to handle ElasticPress configuration.
 */
class EP_Dashboard {

	/**
	 * Placeholder
	 *
	 * @since 1.9
	 */
	public function __construct() { }

	/**
	 * Setup actions and filters for all things settings
	 *
	 * @since  2.1
	 */
	public function setup() {
		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) { // Must be network admin in multisite.
			add_action( 'network_admin_menu', array( $this, 'action_admin_menu' ) );
			add_action( 'admin_bar_menu', array( $this, 'action_network_admin_bar_menu' ), 50 );
		} else {
			add_action( 'admin_menu', array( $this, 'action_admin_menu' ) );
		}

		add_action( 'wp_ajax_ep_save_feature', array( $this, 'action_wp_ajax_ep_save_feature' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'action_admin_enqueue_dashboard_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'action_admin_enqueue_admin_scripts' ) );
		add_action( 'admin_init', array( $this, 'action_admin_init' ) );
		add_action( 'admin_init', array( $this, 'intro_or_dashboard' ) );
		add_action( 'wp_ajax_ep_index', array( $this, 'action_wp_ajax_ep_index' ) );
		add_action( 'wp_ajax_ep_notice_dismiss', array( $this, 'action_wp_ajax_ep_notice_dismiss' ) );
		add_action( 'wp_ajax_ep_cancel_index', array( $this, 'action_wp_ajax_ep_cancel_index' ) );
		add_action( 'admin_notices', array( $this, 'maybe_notice' ) );
		add_action( 'network_admin_notices', array( $this, 'maybe_notice' ) );
		add_filter( 'plugin_action_links', array( $this, 'filter_plugin_action_links' ), 10, 2 );
		add_filter( 'network_admin_plugin_action_links', array( $this, 'filter_plugin_action_links' ), 10, 2 );
	}

	/**
	 * Show ElasticPress in network admin menu bar
	 * 
	 * @param  object $admin_bar
	 * @since  2.2
	 */
	public function action_network_admin_bar_menu( $admin_bar ) {
		$admin_bar->add_menu( array(
			'id'     => 'network-admin-elasticpress',
			'parent' => 'network-admin',
			'title'  => 'ElasticPress',
			'href'   => esc_url( network_admin_url( 'admin.php?page=elasticpress' ) ),
		) );
	}

	/**
	 * Output dashboard link in plugin actions
	 * 
	 * @param  array $plugin_actions
	 * @param  string $plugin_file
	 * @since  2.1
	 * @return array
	 */
	public function filter_plugin_action_links( $plugin_actions, $plugin_file ) {

		if ( is_network_admin() ) {
			$url = admin_url( 'network/admin.php?page=elasticpress' );

			if ( ! defined( 'EP_IS_NETWORK' ) || ! EP_IS_NETWORK ) {
				return $plugin_actions;
			}
		} else {
			$url = admin_url( 'admin.php?page=elasticpress' );

			if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
				return $plugin_actions;
			}
		}

		$new_actions = array();

		if ( basename( EP_PATH ) . '/elasticpress.php' === $plugin_file ) {
			$new_actions['ep_dashboard'] = sprintf( __( '<a href="%s">Dashboard</a>', 'elasticpress' ), esc_url( $url ) );
		}

		return array_merge( $new_actions, $plugin_actions );
	}

	/**
	 * Output variety of dashboard notices. Only one at a time :)
	 *
	 * @param  bool $force
	 * @since  2.2
	 */
	public function maybe_notice( $force = false ) {
		// Admins only
		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			if ( ! is_super_admin() ) {
				return;
			}
		} else {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}
		}
		// Don't show notice on intro page ever
		if ( ! empty( $_GET['page'] ) && 'elasticpress-intro' === $_GET['page'] ) {
			return;
		}

		// If in network mode, don't output notice in admin and vice-versa
		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			if ( ! is_network_admin() ) {
				return;
			}
		} else {
			if ( is_network_admin() ) {
				return;
			}
		}

		$notice = false;

		$on_settings_page = ( ! empty( $_GET['page'] ) && 'elasticpress-settings' === $_GET['page'] );

		$host = ep_get_host();

		/**
		 * Bad host notice checks
		 */
		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			$options_host = get_site_option( 'ep_host' );
		} else {
			$options_host = get_option( 'ep_host' );
		}

		$never_set_host = true;
		if ( false !== $options_host || ( defined( 'EP_HOST' ) && EP_HOST ) ) {
			$never_set_host = false;
		}

		if ( ! $never_set_host ) {
			/**
			 * Feature auto-activated sync notice check
			 */
			if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
				$auto_activate_sync = get_site_option( 'ep_feature_auto_activated_sync', false );
			} else {
				$auto_activate_sync = get_option( 'ep_feature_auto_activated_sync', false );
			}

			if ( ! empty( $auto_activate_sync ) && ! isset( $_GET['do_sync'] ) ) {
				$notice = 'auto-activate-sync';
			}

			/**
			 * Upgrade sync notice check
			 */
			if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
				$need_upgrade_sync = get_site_option( 'ep_need_upgrade_sync', false );
			} else {
				$need_upgrade_sync = get_option( 'ep_need_upgrade_sync', false );
			}

			if ( $need_upgrade_sync && ! isset( $_GET['do_sync'] ) ) {
				$notice = 'upgrade-sync';
			}

			/**
			 * Never synced notice check
			 */
			if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
				$last_sync = get_site_option( 'ep_last_sync', false );
			} else {
				$last_sync = get_option( 'ep_last_sync', false );
			}

			if ( false === $last_sync && ! isset( $_GET['do_sync'] ) ) {
				$notice = 'no-sync';
			}
		}

		$es_version = ep_get_elasticsearch_version( $force );

		/**
		 * Check Elasticsearch version compat
		 */

		if ( false !== $es_version ) {
			// First reduce version to major version i.e. 5.1 not 5.1.1
			$major_es_version = preg_replace( '#^([0-9]+\.[0-9]+).*#', '$1', $es_version );

			if ( -1 === version_compare( EP_ES_VERSION_MAX, $major_es_version ) ) {
				$notice = 'above-es-compat';
			} elseif ( 1 === version_compare( EP_ES_VERSION_MIN, $major_es_version ) ) {
				$notice = 'below-es-compat';
			}
		}

		if ( empty( $host ) || false === $es_version ) {
			if ( $on_settings_page ) {
				if ( ! $never_set_host ) {
					$notice = 'bad-host';
				}
			} else {
				$notice = 'bad-host';
			}
		}

		/**
		 * Need setup up notice check
		 */

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			$intro_shown = get_site_option( 'ep_intro_shown', false );
			$skip_intro_shown_notice = get_site_option( 'ep_hide_intro_shown_notice', false );
		} else {
			$intro_shown = get_option( 'ep_intro_shown', false );
			$skip_intro_shown_notice = get_option( 'ep_hide_intro_shown_notice', false );
		}

		if ( ! $skip_intro_shown_notice && ! $intro_shown && false === $options_host && ( ! defined( 'EP_HOST' ) || ! EP_HOST ) ) {
			$notice = 'need-setup';
		}

		switch ( $notice ) {
			case 'bad-host':
				if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
					$url = admin_url( 'network/admin.php?page=elasticpress-settings' );
					$options_host = get_site_option( 'ep_host' );
				} else {
					$url = admin_url( 'admin.php?page=elasticpress-settings' );
					$options_host = get_option( 'ep_host' );
				}

				?>
				<div class="notice notice-error">
					<p><?php printf( __( 'There is a problem with connecting to your Elasticsearch host. You will need to <a href="%s">fix it</a> for ElasticPress to work.', 'elasticpress' ), esc_url( $url ) ); ?></p>
				</div>
				<?php
				break;
			case 'above-es-compat':
				?>
				<div class="notice notice-error">
					<p><?php printf( __( 'Your Elasticsearch version %s is above the maximum required Elasticsearch version %s. ElasticPress may or may not work properly.', 'elasticpress' ), esc_html( $es_version ), esc_html( EP_ES_VERSION_MAX ) ); ?></p>
				</div>
				<?php
				break;
			case 'below-es-compat':
				?>
				<div class="notice notice-error">
					<p><?php printf( __( 'Your Elasticsearch version %s is below the minimum required Elasticsearch version %s. ElasticPress may or may not work properly.', 'elasticpress' ), esc_html( $es_version ), esc_html( EP_ES_VERSION_MIN ) ); ?></p>
				</div>
				<?php
				break;
			case 'need-setup':
				if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
					$url = admin_url( 'network/admin.php?page=elasticpress-intro' );
				} else {
					$url = admin_url( 'admin.php?page=elasticpress-intro' );
				}

				?>
				<div data-ep-notice="need-setup" class="notice notice-info is-dismissible">
					<p><?php printf( __( 'Thanks for installing ElasticPress! You will need to run through a <a href="%s">quick set up process</a> to get the plugin working.', 'elasticpress' ), esc_url( $url ) ); ?></p>
				</div>
				<?php
				break;
			case 'no-sync':
				if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
					$url = admin_url( 'network/admin.php?page=elasticpress&do_sync' );
				} else {
					$url = admin_url( 'admin.php?page=elasticpress&do_sync' );
				}

				?>
				<div data-ep-notice="no-sync" class="notice notice-info is-dismissible">
					<p><?php printf( __( 'ElasticPress is almost ready. You will need to complete a <a href="%s">sync</a> to get the plugin working.', 'elasticpress' ), esc_url( $url ) ); ?></p>
				</div>
				<?php
				break;
			case 'upgrade-sync':
				if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
					$url = admin_url( 'network/admin.php?page=elasticpress&do_sync' );
				} else {
					$url = admin_url( 'admin.php?page=elasticpress&do_sync' );
				}

				?>
				<div data-ep-notice="upgrade-sync" class="notice notice-warning is-dismissible">
					<p><?php printf( __( 'The new version of ElasticPress requires that you <a href="%s">run a sync</a>.', 'elasticpress' ), esc_url( $url ) ); ?></p>
				</div>
				<?php
				break;
			case 'auto-activate-sync':
				if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
					$url = admin_url( 'network/admin.php?page=elasticpress&do_sync' );
				} else {
					$url = admin_url( 'admin.php?page=elasticpress&do_sync' );
				}

				$feature = ep_get_registered_feature( $auto_activate_sync );

				?>
				<div data-ep-notice="auto-activate-sync" class="notice notice-warning is-dismissible">
					<p><?php printf( __( 'The ElasticPress %s feature has been auto-activated! You will need to <a href="%s">run a sync</a> for it to work.', 'elasticpress' ), esc_html( $feature->title ), esc_url( $url ) ); ?></p>
				</div>
				<?php
				break;
		}

		return $notice;
	}

	/**
	 * Dismiss notice via ajax
	 *
	 * @since 2.2
	 */
	public function action_wp_ajax_ep_notice_dismiss() {
		if ( empty( $_POST['notice'] ) || ! check_ajax_referer( 'ep_admin_nonce', 'nonce', false ) ) {
			wp_send_json_error();
			exit;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
			exit;
		}

		switch ( $_POST['notice'] ) {
			case 'need-setup':
				if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
					update_site_option( 'ep_hide_intro_shown_notice', true );
				} else {
					update_option( 'ep_hide_intro_shown_notice', true );
				}

				break;
			case 'no-sync':
				// We use 'never' here as a placeholder value to trick EP into thinking a sync has happened
				if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
					update_site_option( 'ep_last_sync', 'never' );
				} else {
					update_option( 'ep_last_sync', 'never' );
				}

				break;
			case 'upgrade-sync':
				if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
					delete_site_option( 'ep_need_upgrade_sync' );
				} else {
					delete_option( 'ep_need_upgrade_sync' );
				}

				break;
			case 'auto-activate-sync':
				if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
					delete_site_option( 'ep_feature_auto_activated_sync' );
				} else {
					delete_option( 'ep_feature_auto_activated_sync' );
				}

				break;
		}

		wp_send_json_success();
	}

	/**
	 * Continue index
	 *
	 * @since  2.1
	 */
	public function action_wp_ajax_ep_index() {
		if ( ! check_ajax_referer( 'ep_dashboard_nonce', 'nonce', false ) ) {
			wp_send_json_error();
			exit;
		}

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			$index_meta = get_site_option( 'ep_index_meta', false );
		} else {
			$index_meta = get_option( 'ep_index_meta', false );
		}

		$status = false;

		// No current index going on. Let's start over
		if ( false === $index_meta ) {
			$status = 'start';
			$index_meta = array(
				'offset' => 0,
				'start' => true,
			);

			if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
				$sites = ep_get_sites();

				$index_meta['site_stack'] = array();

				foreach ( $sites as $site ) {
					$index_meta['site_stack'][] = array(
						'url' => untrailingslashit( $site['domain'] . $site['path'] ),
						'id' => (int) $site['blog_id'],
					);
				}

				$index_meta['current_site'] = array_shift( $index_meta['site_stack'] );

				update_site_option( 'ep_last_sync', time() );
				delete_site_option( 'ep_need_upgrade_sync' );
				delete_site_option( 'ep_feature_auto_activated_sync' );
			} else {
				if ( ! apply_filters( 'ep_skip_index_reset', false, $index_meta ) ) {
					ep_delete_index();

					ep_put_mapping();
				}

				update_option( 'ep_last_sync', time() );
				delete_option( 'ep_need_upgrade_sync' );
				delete_option( 'ep_feature_auto_activated_sync' );
			}

			if ( ! empty( $_POST['feature_sync'] ) ) {
				$index_meta['feature_sync'] = esc_attr( $_POST['feature_sync'] );
			}
		} else if ( ! empty( $index_meta['site_stack'] ) && $index_meta['offset'] >= $index_meta['found_posts'] ) {
			$status = 'start';

			$index_meta['start'] = true;
			$index_meta['offset'] = 0;
			$index_meta['current_site'] = array_shift( $index_meta['site_stack'] );
		} else {
			$index_meta['start'] = false;
		}

		$index_meta = apply_filters( 'ep_index_meta', $index_meta );

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			switch_to_blog( $index_meta['current_site']['id'] );

			if ( ! empty( $index_meta['start'] ) ) {
				if ( ! apply_filters( 'ep_skip_index_reset', false, $index_meta ) ) {
					ep_delete_index();

					ep_put_mapping();
				}
			}
		}

		$posts_per_page = apply_filters( 'ep_index_posts_per_page', 350 );

		do_action( 'ep_pre_dashboard_index', $index_meta, $status );

		$args = apply_filters( 'ep_index_posts_args', array(
			'posts_per_page'         => $posts_per_page,
			'post_type'              => ep_get_indexable_post_types(),
			'post_status'            => ep_get_indexable_post_status(),
			'offset'                 => $index_meta['offset'],
			'ignore_sticky_posts'    => true,
			'orderby'                => 'ID',
			'order'                  => 'DESC',
			'fields' => 'all',
		) );

		$query = new WP_Query( $args );

		$index_meta['found_posts'] = $query->found_posts;

		if ( $status !== 'start' ) {
			if ( $query->have_posts() ) {
				$queued_posts = array();

				while ( $query->have_posts() ) {
					$query->the_post();
					$killed_post_count = 0;

					$post_args = ep_prepare_post( get_the_ID() );

					if ( apply_filters( 'ep_post_sync_kill', false, $post_args, get_the_ID() ) ) {

						$killed_post_count++;

					} else { // Post wasn't killed so process it.

						$queued_posts[ get_the_ID() ][] = '{ "index": { "_id": "' . absint( get_the_ID() ) . '" } }';

						if ( function_exists( 'wp_json_encode' ) ) {
							$queued_posts[ get_the_ID() ][] = addcslashes( wp_json_encode( $post_args ), "\n" );
						} else {
							$queued_posts[ get_the_ID() ][] = addcslashes( json_encode( $post_args ), "\n" );
						}
					}
				}

				if ( ! empty( $queued_posts ) ) {
					$flatten = array();

					foreach ( $queued_posts as $post ) {
						$flatten[] = $post[0];
						$flatten[] = $post[1];
					}

					// make sure to add a new line at the end or the request will fail
					$body = rtrim( implode( "\n", $flatten ) ) . "\n";

					ep_bulk_index_posts( $body );
				}

				$index_meta['offset'] = absint( $index_meta['offset'] + $posts_per_page );

				if ( $index_meta['offset'] >= $index_meta['found_posts'] ) {
					$index_meta['offset'] = $index_meta['found_posts'];
				}

				if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
					update_site_option( 'ep_index_meta', $index_meta );
				} else {
					update_option( 'ep_index_meta', $index_meta );
				}
			} else {
				// We are done (with this site)
				
				if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
					if ( empty( $index_meta['site_stack'] ) ) {
						delete_site_option( 'ep_index_meta' );

						$sites   = ep_get_sites();
						$indexes = array();

						foreach ( $sites as $site ) {
							switch_to_blog( $site['blog_id'] );
							$indexes[] = ep_get_index_name();
							restore_current_blog();
						}
						
						ep_create_network_alias( $indexes );
					} else {
						$index_meta['offset'] = (int) $query->found_posts;
					}

				} else {
					$index_meta['offset'] = (int) $query->found_posts;

					delete_option( 'ep_index_meta' );
				}
			}
		} else {

			if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
				update_site_option( 'ep_index_meta', $index_meta );
			} else {
				update_option( 'ep_index_meta', $index_meta );
			}
		}

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			restore_current_blog();
		}

		wp_send_json_success( $index_meta );
	}

	/**
	 * Cancel index
	 *
	 * @since  2.1
	 */
	public function action_wp_ajax_ep_cancel_index() {
		if ( ! check_ajax_referer( 'ep_dashboard_nonce', 'nonce', false ) ) {
			wp_send_json_error();
			exit;
		}

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			delete_site_option( 'ep_index_meta' );
		} else {
			delete_option( 'ep_index_meta' );
		}

		wp_send_json_success();
	}

	/**
	 * Save individual feature settings
	 *
	 * @since  2.2
	 */
	public function action_wp_ajax_ep_save_feature() {
		if ( empty( $_POST['feature'] ) || empty( $_POST['settings'] ) || ! check_ajax_referer( 'ep_dashboard_nonce', 'nonce', false ) ) {
			wp_send_json_error();
			exit;
		}

		$data = ep_update_feature( $_POST['feature'], $_POST['settings'] );

		// Since we deactivated, delete auto activate notice
		if ( empty( $_POST['settings']['active'] ) ) {
			if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
				delete_site_option( 'ep_feature_auto_activated_sync' );
			} else {
				delete_option( 'ep_feature_auto_activated_sync' );
			}
		}

		wp_send_json_success( $data );
	}

	/**
	 * Register and Enqueue JavaScripts for dashboard
	 * 
	 * @since 2.2
	 */
	public function action_admin_enqueue_dashboard_scripts() {
		if ( isset( get_current_screen()->id ) && strpos( get_current_screen()->id, 'elasticpress' ) !== false ) {
			if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
				wp_enqueue_style( 'ep_admin_styles', EP_URL . 'assets/css/admin.css', array(), EP_VERSION );
			} else {
				wp_enqueue_style( 'ep_admin_styles', EP_URL . 'assets/css/admin.min.css', array(), EP_VERSION );
			}

			if ( ! empty( $_GET['page'] ) && ( 'elasticpress' === $_GET['page'] || 'elasticpress-settings' === $_GET['page'] ) ) {
				if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
					wp_enqueue_script( 'ep_dashboard_scripts', EP_URL . 'assets/js/src/dashboard.js', array( 'jquery' ), EP_VERSION, true );
				} else {
					wp_enqueue_script( 'ep_dashboard_scripts', EP_URL . 'assets/js/dashboard.min.js', array( 'jquery' ), EP_VERSION, true );
				}

				$data = array( 'nonce' => wp_create_nonce( 'ep_dashboard_nonce' ) );

				if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
					$index_meta = get_site_option( 'ep_index_meta', array() );
					$wpcli_sync = (bool) get_site_transient( 'ep_wpcli_sync' );
				} else {
					$index_meta = get_option( 'ep_index_meta', array() );
					$wpcli_sync = (bool) get_transient( 'ep_wpcli_sync' );
				}

				if ( ! empty( $wpcli_sync ) ) {
					$index_meta['wpcli_sync'] = true;
				}

				if ( isset( $_GET['do_sync'] ) ) {
					$data['auto_start_index'] = true;
				}

				if ( ! empty( $index_meta ) ) {
					$data['index_meta'] = $index_meta;
				}
				
				$data['sync_complete'] = esc_html__( 'Sync complete', 'elasticpress' );
				$data['sync_paused'] = esc_html__( 'Sync paused', 'elasticpress' );
				$data['sync_syncing'] = esc_html__( 'Syncing', 'elasticpress' );
				$data['sync_initial'] = esc_html__( 'Starting sync', 'elasticpress' );
				$data['sync_wpcli'] = esc_html__( "WP CLI sync is occuring. Refresh the page to see if it's finished", 'elasticpress' );
				$data['sync_error'] = esc_html__( 'An error occured while syncing', 'elasticpress' );

				wp_localize_script( 'ep_dashboard_scripts', 'epDash', $data );
			}
		}
	}

	/**
	 * Enqueue scripts to be used across all of WP admin
	 * 
	 * @since 2.2
	 */
	public function action_admin_enqueue_admin_scripts() {
		if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
			wp_enqueue_script( 'ep_admin_scripts', EP_URL . 'assets/js/src/admin.js', array( 'jquery' ), EP_VERSION, true );
		} else {
			wp_enqueue_script( 'ep_admin_scripts', EP_URL . 'assets/js/admin.min.js', array( 'jquery' ), EP_VERSION, true );
		}

		wp_localize_script( 'ep_admin_scripts', 'epAdmin', array(
			'nonce' => wp_create_nonce( 'ep_admin_nonce' ),
		) );
	}

	/**
	 * Admin-init actions
	 *
	 * Sets up Settings API.
	 *
	 * @since 1.9
	 * @return void
	 */
	public function action_admin_init() {

		//Save options for multisite
		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK && isset( $_POST['ep_host'] ) ) {
			if ( ! check_admin_referer( 'elasticpress-options' ) ) {
				die( esc_html__( 'Security error!', 'elasticpress' ) );
			}

			$host = esc_url_raw( $_POST['ep_host'] );
			update_site_option( 'ep_host', $host );
		} else {
			register_setting( 'elasticpress', 'ep_host', 'esc_url_raw' );
		}
	}

	/**
	 * Conditionally show dashboard or intro
	 *
	 * @since  2.1
	 */
	public function intro_or_dashboard() {
		global $pagenow;

		if ( 'admin.php' !== $pagenow || empty( $_GET['page'] ) || 'elasticpress' !== $_GET['page'] ) {
			return;
		}

		$host =  ep_get_host();

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			$intro_shown = get_site_option( 'ep_intro_shown', false );
		} else {
			$intro_shown = get_option( 'ep_intro_shown', false );
		}

		if ( ! $intro_shown ) {
			if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
				wp_redirect( admin_url( 'network/admin.php?page=elasticpress-intro' ) );
			} else {
				wp_redirect( admin_url( 'admin.php?page=elasticpress-intro' ) );
			}
			exit;
		} else {
			if ( empty( $host ) ) {
				if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
					wp_redirect( admin_url( 'network/admin.php?page=elasticpress-intro' ) );
				} else {
					wp_redirect( admin_url( 'admin.php?page=elasticpress-intro' ) );
				}
				exit;
			}
		}
	}

	/**
	 * Build dashboard page
	 *
	 * @since 2.1
	 */
	public function dashboard_page() {
		include( dirname( __FILE__ ) . '/../includes/dashboard-page.php' );
	}

	/**
	 * Build settings page
	 *
	 * @since  2.1
	 */
	public function settings_page() {
		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			update_site_option( 'ep_intro_shown', true );
		} else {
			update_option( 'ep_intro_shown', true );
		}

		include( dirname( __FILE__ ) . '/../includes/settings-page.php' );
	}

	/**
	 * Build settings page
	 *
	 * @since  2.1
	 */
	public function intro_page() {
		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			update_site_option( 'ep_intro_shown', true );
		} else {
			update_option( 'ep_intro_shown', true );
		}

		include( dirname( __FILE__ ) . '/../includes/intro-page.php' );
	}

	/**
	 * Admin menu actions
	 *
	 * Adds options page to admin menu.
	 *
	 * @since 1.9
	 * @return void
	 */
	public function action_admin_menu() {
		$capability  = 'manage_options';

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			$capability  = 'manage_network';
		}

		add_menu_page(
			'ElasticPress',
			'ElasticPress',
			$capability,
			'elasticpress',
			array( $this, 'dashboard_page' ),
			'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0idXRmLTgiPz48c3ZnIHZlcnNpb249IjEuMSIgaWQ9IkxheWVyXzEiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgeG1sbnM6eGxpbms9Imh0dHA6Ly93d3cudzMub3JnLzE5OTkveGxpbmsiIHg9IjBweCIgeT0iMHB4IiB2aWV3Qm94PSIwIDAgNzMgNzEuMyIgc3R5bGU9ImVuYWJsZS1iYWNrZ3JvdW5kOm5ldyAwIDAgNzMgNzEuMzsiIHhtbDpzcGFjZT0icHJlc2VydmUiPjxwYXRoIGQ9Ik0zNi41LDQuN0MxOS40LDQuNyw1LjYsMTguNiw1LjYsMzUuN2MwLDEwLDQuNywxOC45LDEyLjEsMjQuNWw0LjUtNC41YzAuMS0wLjEsMC4xLTAuMiwwLjItMC4zbDAuNy0wLjdsNi40LTYuNGMyLjEsMS4yLDQuNSwxLjksNy4xLDEuOWM4LDAsMTQuNS02LjUsMTQuNS0xNC41cy02LjUtMTQuNS0xNC41LTE0LjVTMjIsMjcuNiwyMiwzNS42YzAsMi44LDAuOCw1LjMsMi4xLDcuNWwtNi40LDYuNGMtMi45LTMuOS00LjYtOC43LTQuNi0xMy45YzAtMTIuOSwxMC41LTIzLjQsMjMuNC0yMy40czIzLjQsMTAuNSwyMy40LDIzLjRTNDkuNCw1OSwzNi41LDU5Yy0yLjEsMC00LjEtMC4zLTYtMC44bC0wLjYsMC42bC01LjIsNS40YzMuNiwxLjUsNy42LDIuMywxMS44LDIuM2MxNy4xLDAsMzAuOS0xMy45LDMwLjktMzAuOVM1My42LDQuNywzNi41LDQuN3oiLz48L3N2Zz4='
		);

		add_submenu_page(
			null,
			'ElasticPress' . esc_html__( 'Settings', 'elasticpress' ),
			'ElasticPress' . esc_html__( 'Settings', 'elasticpress' ),
			$capability,
			'elasticpress-settings',
			array( $this, 'settings_page' )
		);

		add_submenu_page(
			null,
			'ElasticPress' . esc_html__( 'Welcome', 'elasticpress' ),
			'ElasticPress' . esc_html__( 'Welcome', 'elasticpress' ),
			$capability,
			'elasticpress-intro',
			array( $this, 'intro_page' )
		);
	}

	/**
	 * Return a singleton instance of the current class
	 *
	 * @since 2.1
	 * @return object
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

EP_Dashboard::factory();

