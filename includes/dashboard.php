<?php
/**
 * Create an ElasticPress dashboard page.
 *
 * @package elasticpress
 * @since   1.9
 */

namespace ElasticPress\Dashboard;

use ElasticPress\Utils as Utils;
use ElasticPress\Elasticsearch;
use ElasticPress\Features;
use ElasticPress\Indexables;
use ElasticPress\Installer;
use ElasticPress\AdminNotices;
use ElasticPress\Screen;
use ElasticPress\Stats;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Setup actions and filters for all things settings
 *
 * @since  2.1
 */
function setup() {
	if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) { // Must be network admin in multisite.
		add_action( 'network_admin_menu', __NAMESPACE__ . '\action_admin_menu' );
		add_action( 'admin_bar_menu', __NAMESPACE__ . '\action_network_admin_bar_menu', 50 );
	} else {
		add_action( 'admin_menu', __NAMESPACE__ . '\action_admin_menu' );
	}

	add_action( 'wp_ajax_ep_save_feature', __NAMESPACE__ . '\action_wp_ajax_ep_save_feature' );
	add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\action_admin_enqueue_dashboard_scripts' );
	add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\action_admin_enqueue_admin_scripts' );
	add_action( 'admin_init', __NAMESPACE__ . '\action_admin_init' );
	add_action( 'admin_init', __NAMESPACE__ . '\maybe_clear_es_info_cache' );
	add_action( 'wp_ajax_ep_index', __NAMESPACE__ . '\action_wp_ajax_ep_index' );
	add_action( 'wp_ajax_ep_notice_dismiss', __NAMESPACE__ . '\action_wp_ajax_ep_notice_dismiss' );
	add_action( 'wp_ajax_ep_cancel_index', __NAMESPACE__ . '\action_wp_ajax_ep_cancel_index' );
	add_action( 'admin_notices', __NAMESPACE__ . '\maybe_notice' );
	add_action( 'network_admin_notices', __NAMESPACE__ . '\maybe_notice' );
	add_filter( 'plugin_action_links', __NAMESPACE__ . '\filter_plugin_action_links', 10, 2 );
	add_filter( 'network_admin_plugin_action_links', __NAMESPACE__ . '\filter_plugin_action_links', 10, 2 );
	add_action( 'ep_add_query_log', __NAMESPACE__ . '\log_version_query_error' );
	add_filter( 'ep_analyzer_language', __NAMESPACE__ . '\use_language_in_setting' );
	add_filter( 'wp_kses_allowed_html', __NAMESPACE__ . '\filter_allowed_html', 10, 2 );
	add_filter( 'wpmu_blogs_columns', __NAMESPACE__ . '\filter_blogs_columns', 10, 1 );
	add_action( 'manage_sites_custom_column', __NAMESPACE__ . '\add_blogs_column', 10, 2 );
	add_action( 'manage_blogs_custom_column', __NAMESPACE__ . '\add_blogs_column', 10, 2 );
	add_action( 'wp_ajax_ep_site_admin', __NAMESPACE__ . '\action_wp_ajax_ep_site_admin' );
}

/**
 * Add ep-html kses context
 *
 * @param  array  $allowedtags HTML tags
 * @param  string $context     Context string
 * @since  3.0
 * @return array
 */
function filter_allowed_html( $allowedtags, $context ) {
	global $allowedposttags;

	if ( 'ep-html' === $context ) {
		$ep_tags = $allowedposttags;

		$atts = [
			'type'            => true,
			'checked'         => true,
			'selected'        => true,
			'disabled'        => true,
			'value'           => true,
			'href'            => true,
			'class'           => true,
			'data-*'          => true,
			'data-field-name' => true,
			'data-ep-notice'  => true,
			'data-feature'    => true,
			'id'              => true,
			'style'           => true,
			'title'           => true,
			'name'            => true,
			'placeholder'     => '',
		];

		$ep_tags['input']    = $atts;
		$ep_tags['select']   = $atts;
		$ep_tags['textarea'] = $atts;
		$ep_tags['option']   = $atts;

		$ep_tags['form'] = [
			'action'         => true,
			'accept'         => true,
			'accept-charset' => true,
			'enctype'        => true,
			'method'         => true,
			'name'           => true,
			'target'         => true,
		];

		$ep_tags['a'] = $atts;

		return $ep_tags;
	}

	return $allowedtags;
}

/**
 * Stores the results of the version query.
 *
 * @param  array $query The version query.
 * @since  3.0
 */
function log_version_query_error( $query ) {
	$is_network = defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK;

	$logging_key = 'logging_ep_es_info';

	if ( $is_network ) {
		$logging = get_site_transient( $logging_key );
	} else {
		$logging = get_transient( $logging_key );
	}

	// Are we logging the version query results?
	if ( '1' === $logging ) {
		/**
		 * Filter how long results of Elasticsearch version query are stored
		 *
		 * @since  23.0
		 * @hook ep_es_info_cache_expiration
		 * @param  {int} Time in seconds
		 * @return  {int} New time in seconds
		 */
		$cache_time         = apply_filters( 'ep_es_info_cache_expiration', ( 5 * MINUTE_IN_SECONDS ) );
		$response_code_key  = 'ep_es_info_response_code';
		$response_error_key = 'ep_es_info_response_error';
		$response_code      = 0;
		$response_error     = '';

		if ( ! empty( $query['request'] ) ) {
			$response_code  = absint( wp_remote_retrieve_response_code( $query['request'] ) );
			$response_error = wp_remote_retrieve_response_message( $query['request'] );
			if ( empty( $response_error ) && is_wp_error( $query['request'] ) ) {
				$response_error = $query['request']->get_error_message();
			}
		}

		// Store the response code, and remove the flag that says
		// we're logging the response code so we don't log additional
		// queries.
		if ( $is_network ) {
			set_site_transient( $response_code_key, $response_code, $cache_time );
			set_site_transient( $response_error_key, $response_error, $cache_time );
			delete_site_transient( $logging_key );
		} else {
			set_transient( $response_code_key, $response_code, $cache_time );
			set_transient( $response_error_key, $response_error, $cache_time );
			delete_transient( $logging_key );
		}
	}
}

/**
 * Clear ES info cache whenever EP dash or settings page is viewed. Also clear cache
 * when "try again" notification link is clicked.
 *
 * @since  2.3.1
 */
function maybe_clear_es_info_cache() {
	if ( ! is_admin() && ! is_network_admin() ) {
		return;
	}

	if ( empty( $_GET['ep-retry'] ) && ! in_array( Screen::factory()->get_current_screen(), [ 'dashboard', 'settings', 'install' ], true ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}

	if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
		delete_site_transient( 'ep_es_info' );
	} else {
		delete_transient( 'ep_es_info' );
	}

	if ( ! empty( $_GET['ep-retry'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		wp_safe_redirect( remove_query_arg( 'ep-retry' ) );
	}
}

/**
 * Show ElasticPress in network admin menu bar
 *
 * @param  object $admin_bar WP_Admin Bar reference.
 * @since  2.2
 */
function action_network_admin_bar_menu( $admin_bar ) {
	$admin_bar->add_menu(
		array(
			'id'     => 'network-admin-elasticpress',
			'parent' => 'network-admin',
			'title'  => 'ElasticPress',
			'href'   => esc_url( network_admin_url( 'admin.php?page=elasticpress' ) ),
		)
	);
}

/**
 * Output dashboard link in plugin actions
 *
 * @param  array  $plugin_actions Array of HTML.
 * @param  string $plugin_file Path to plugin file.
 * @since  2.1
 * @return array
 */
function filter_plugin_action_links( $plugin_actions, $plugin_file ) {

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

	$new_actions = [];

	if ( basename( EP_PATH ) . '/elasticpress.php' === $plugin_file ) {
		$new_actions['ep_dashboard'] = sprintf( '<a href="%s">%s</a>', esc_url( $url ), __( 'Dashboard', 'elasticpress' ) );
	}

	return array_merge( $new_actions, $plugin_actions );
}

/**
 * Output variety of dashboard notices.
 *
 * @param  bool $force Force ES info hard lookup.
 * @since  3.0
 */
function maybe_notice( $force = false ) {
	// Admins only.
	if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
		if ( ! is_super_admin() ) {
			return false;
		}
	} else {
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}
	}

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

	/**
	 * Filter how long results of Elasticsearch version query are stored
	 *
	 * @since  23.0
	 * @hook ep_es_info_cache_expiration
	 * @param  {int} Time in seconds
	 * @return  {int} New time in seconds
	 */
	$cache_time = apply_filters( 'ep_es_info_cache_expiration', ( 5 * MINUTE_IN_SECONDS ) );

	if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
		set_site_transient(
			'logging_ep_es_info',
			'1',
			$cache_time
		);
	} else {
		$a = set_transient(
			'logging_ep_es_info',
			'1',
			$cache_time
		);
	}

	// Fetch ES version
	Elasticsearch::factory()->get_elasticsearch_version( $force );

	AdminNotices::factory()->process_notices();

	$notices = AdminNotices::factory()->get_notices();

	foreach ( $notices as $notice_key => $notice ) {
		?>
		<div data-ep-notice="<?php echo esc_attr( $notice_key ); ?>" class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> <?php
		if ( $notice['dismiss'] ) :
			?>
			is-dismissible<?php endif; ?>">
			<p>
				<?php echo wp_kses( $notice['html'], 'ep-html' ); ?>
			</p>
		</div>
		<?php
	}

	return $notices;
}

/**
 * Dismiss notice via ajax
 *
 * @since 2.2
 */
function action_wp_ajax_ep_notice_dismiss() {
	if ( empty( $_POST['notice'] ) || ! check_ajax_referer( 'ep_admin_nonce', 'nonce', false ) ) {
		wp_send_json_error();
		exit;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error();
		exit;
	}

	AdminNotices::factory()->dismiss_notice( $_POST['notice'] );

	wp_send_json_success();
}

/**
 * Continue index
 *
 * @since  2.1
 */
function action_wp_ajax_ep_index() {
	if ( ! check_ajax_referer( 'ep_dashboard_nonce', 'nonce', false ) ) {
		wp_send_json_error();
		exit;
	}

	if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
		$index_meta = get_site_option( 'ep_index_meta', false );
	} else {
		$index_meta = get_option( 'ep_index_meta', false );
	}

	$global_indexables     = Indexables::factory()->get_all( true, true );
	$non_global_indexables = Indexables::factory()->get_all( false, true );

	$status = false;

	// No current index going on. Let's start over.
	if ( false === $index_meta ) {
		$status     = 'start';
		$index_meta = [
			'offset'     => 0,
			'start'      => true,
			'sync_stack' => [],
		];

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			$sites = Utils\get_sites();

			foreach ( $sites as $site ) {
				if ( ! Utils\is_site_indexable( $site['blog_id'] ) ) {
					continue;
				}

				foreach ( $non_global_indexables as $indexable ) {
					$index_meta['sync_stack'][] = [
						'url'       => untrailingslashit( $site['domain'] . $site['path'] ),
						'blog_id'   => (int) $site['blog_id'],
						'indexable' => $indexable,
					];
				}
			}

			if ( 0 === count( $index_meta['sync_stack'] ) && empty( $global_indexables ) ) {
				wp_send_json_error(
					[
						'found_items' => 0,
						'offset'      => 0,
					]
				);

				return;
			}

			$index_meta['current_sync_item'] = array_shift( $index_meta['sync_stack'] );

			update_site_option( 'ep_last_sync', time() );
			delete_site_option( 'ep_need_upgrade_sync' );
			delete_site_option( 'ep_feature_auto_activated_sync' );
		} else {
			foreach ( $non_global_indexables as $indexable ) {
				$index_meta['sync_stack'][] = [
					'url'       => untrailingslashit( home_url() ),
					'blog_id'   => (int) get_current_blog_id(),
					'indexable' => $indexable,
				];
			}

			$index_meta['current_sync_item'] = array_shift( $index_meta['sync_stack'] );

			update_option( 'ep_last_sync', time() );
			delete_option( 'ep_need_upgrade_sync' );
			delete_option( 'ep_feature_auto_activated_sync' );
		}

		if ( ! empty( $_POST['feature_sync'] ) ) {
			$index_meta['feature_sync'] = esc_attr( $_POST['feature_sync'] );
		}

		// Handle global indexables case if non globals disabled
		if ( 0 === count( $index_meta['sync_stack'] ) && ! in_array( $index_meta['current_sync_item']['indexable'], $non_global_indexables, true ) ) {
			foreach ( $sites as $site ) {
				foreach ( $global_indexables as $indexable ) {
					$index_meta['sync_stack'][] = [
						'url'       => untrailingslashit( $site['domain'] . $site['path'] ),
						'blog_id'   => (int) $site['blog_id'],
						'indexable' => $indexable,
					];
				}
			}
			$index_meta['current_sync_item'] = array_shift( $index_meta['sync_stack'] );

		} else {
			foreach ( $global_indexables as $indexable ) {
				$index_meta['sync_stack'][] = [
					'indexable' => $indexable,
				];
			}
		}

		/**
		 * Fires at start of new index
		 *
		 * @since  2.1
		 * @hook ep_dashboard_start_index
		 * @param  {array} $index_meta Index meta information
		 */
		do_action( 'ep_dashboard_start_index', $index_meta );
	} elseif ( ! empty( $index_meta['sync_stack'] ) && $index_meta['offset'] >= $index_meta['found_items'] ) {
		$status = 'start';

		$index_meta['start']             = true;
		$index_meta['offset']            = 0;
		$index_meta['current_sync_item'] = array_shift( $index_meta['sync_stack'] );
	} else {
		$index_meta['start'] = false;
	}

	/**
	 * Filter index meta during dashboard sync
	 *
	 * @since  3.0
	 * @hook ep_index_meta
	 * @param  {array} $index_meta Current index meta
	 * @return  {array} New index meta
	 */
	$index_meta = apply_filters( 'ep_index_meta', $index_meta );
	$indexable  = Indexables::factory()->get( $index_meta['current_sync_item']['indexable'] );

	if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK && ! empty( $index_meta['current_sync_item']['blog_id'] ) ) {
		switch_to_blog( $index_meta['current_sync_item']['blog_id'] );
	}

	if ( ! empty( $index_meta['start'] ) ) {
		/**
		 * Filter whether we should delete index and send new mapping at the start of the sync
		 *
		 * @since  2.1
		 * @hook ep_skip_index_reset
		 * @param  {bool} $skip True means skip
		 * @param  {array} $index_meta Current index meta
		 * @return  {bool} New skip value
		 */
		if ( ! apply_filters( 'ep_skip_index_reset', false, $index_meta ) ) {
			$indexable->delete_index();

			$indexable->put_mapping();

			/**
			 * Fires after dashboard put mapping is completed
			 *
			 * @since  2.1
			 * @hook ep_dashboard_put_mapping
			 * @param  {array} $index_meta Index meta information
			 * @param  {string} $status Current indexing status
			 */
			do_action( 'ep_dashboard_put_mapping', $index_meta, $status );
		}
	}

	if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
		$bulk_setting = get_site_option( 'ep_bulk_setting', 350 );
	} else {
		$bulk_setting = get_option( 'ep_bulk_setting', 350 );
	}

	/**
	 * Filter number of items to index per cycle in the dashboard
	 *
	 * @since  2.1
	 * @hook ep_index_default_per_page
	 * @param  {int} Entries per cycle
	 * @return  {int} New number of entries
	 */
	$per_page = apply_filters( 'ep_index_default_per_page', $bulk_setting );

	/**
	 * Fires right before entries are about to be indexed in a dashboard sync
	 *
	 * @since  2.1
	 * @hook ep_pre_dashboard_index
	 * @param  {array} $args Args to query content with
	 */
	do_action( 'ep_pre_dashboard_index', $index_meta, $status, $indexable );

	/**
	 * Filters arguments used to query for content for each indexable
	 *
	 * @since  3.0
	 * @hook ep_dashboard_index_args
	 * @param  {array} $args Args to query content with
	 * @return  {array} New query args
	 */
	$args = apply_filters(
		'ep_dashboard_index_args',
		[
			'posts_per_page' => $per_page,
			'offset'         => $index_meta['offset'],
		]
	);

	$query = $indexable->query_db( $args );

	$index_meta['found_items'] = (int) $query['total_objects'];

	if ( 'start' !== $status ) {
		if ( ! empty( $query['objects'] ) ) {
			$queued_items = [];

			foreach ( $query['objects'] as $object ) {
				$killed_item_count = 0;

				/**
				 * Filter whether to not sync sepcific item in dashboard or not
				 *
				 * @since  2.1
				 * @hook ep_item_sync_kill
				 * @param  {boolean} $kill False means dont sync
				 * @param  {array} $object Object to sync
				 * @return {Indexable} Indexable that object belongs to
				 */
				if ( apply_filters( 'ep_item_sync_kill', false, $object, $indexable ) ) {
					$killed_item_count++;
				} else {
					$queued_items[ $object->ID ] = true;
				}
			}

			if ( ! empty( $queued_items ) ) {
				$return = $indexable->bulk_index( array_keys( $queued_items ) );

				if ( is_wp_error( $return ) ) {
					header( 'HTTP/1.1 500 Internal Server Error' );
					wp_send_json_error();
					exit;
				}
			}

			$index_meta['offset'] = absint( $index_meta['offset'] + $per_page );

			if ( $index_meta['offset'] >= $index_meta['found_items'] ) {
				$index_meta['offset'] = $index_meta['found_items'];
			}

			if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
				update_site_option( 'ep_index_meta', $index_meta );
			} else {
				update_option( 'ep_index_meta', $index_meta );
			}
		} else {
			// We are done (with this site).
			if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
				if ( empty( $index_meta['sync_stack'] ) ) {
					delete_site_option( 'ep_index_meta' );

					$sites = Utils\get_sites();

					foreach ( $non_global_indexables as $indexable_slug ) {
						$indexes          = [];
						$indexable_object = Indexables::factory()->get( $indexable_slug );

						foreach ( $sites as $site ) {
							switch_to_blog( $site['blog_id'] );
							$indexes[] = $indexable_object->get_index_name();
							restore_current_blog();
						}

						$indexable_object->create_network_alias( $indexes );
					}
				} else {
					$index_meta['offset'] = (int) $query['total_objects'];
				}
			} else {
				$index_meta['offset'] = (int) $query['total_objects'];

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

	if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK && ! empty( $index_meta['current_sync_item']['blog_id'] ) ) {
		restore_current_blog();
	}

	wp_send_json_success( $index_meta );

}

/**
 * Cancel index
 *
 * @since  2.1
 */
function action_wp_ajax_ep_cancel_index() {
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
function action_wp_ajax_ep_save_feature() {
	if ( empty( $_POST['feature'] ) || empty( $_POST['settings'] ) || ! check_ajax_referer( 'ep_dashboard_nonce', 'nonce', false ) ) {
		wp_send_json_error();
		exit;
	}

	$data = Features::factory()->update_feature( $_POST['feature'], $_POST['settings'] );

	// Since we deactivated, delete auto activate notice.
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
function action_admin_enqueue_dashboard_scripts() {
	if ( isset( get_current_screen()->id ) && strpos( get_current_screen()->id, 'sites-network' ) !== false ) {
		wp_enqueue_style( 'ep_admin_sites_styles', EP_URL . 'dist/css/sites-admin-styles.min.css', [], EP_VERSION );
		wp_enqueue_script( 'ep_admin_sites_scripts', EP_URL . 'dist/js/sites-admin-script.min.js', [ 'jquery' ], EP_VERSION, true );
		$data = [
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'epsa' ),
		];
		wp_localize_script( 'ep_admin_sites_scripts', 'epsa', $data );
	}

	if ( in_array( Screen::factory()->get_current_screen(), [ 'dashboard', 'settings', 'install', 'health' ], true ) ) {
		wp_enqueue_style( 'ep_admin_styles', EP_URL . 'dist/css/dashboard-styles.min.css', [], EP_VERSION );
	}

	if ( in_array( Screen::factory()->get_current_screen(), [ 'dashboard', 'settings' ], true ) ) {
		wp_enqueue_script( 'ep_dashboard_scripts', EP_URL . 'dist/js/dashboard-script.min.js', [ 'jquery' ], EP_VERSION, true );

		$data = array( 'nonce' => wp_create_nonce( 'ep_dashboard_nonce' ) );

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			$index_meta           = get_site_option( 'ep_index_meta', [] );
			$wpcli_sync           = (bool) get_site_transient( 'ep_wpcli_sync' );
			$install_complete_url = admin_url( 'network/admin.php?page=elasticpress&install_complete' );
			$last_sync            = get_site_option( 'ep_last_sync', false );
		} else {
			$index_meta           = get_option( 'ep_index_meta', [] );
			$wpcli_sync           = (bool) get_transient( 'ep_wpcli_sync' );
			$install_complete_url = admin_url( 'admin.php?page=elasticpress&install_complete' );
			$last_sync            = get_option( 'ep_last_sync', false );
		}

		if ( ! empty( $wpcli_sync ) ) {
			$index_meta['wpcli_sync'] = true;
		}

		if ( isset( $_GET['do_sync'] ) && ( ! defined( 'EP_DASHBOARD_SYNC' ) || EP_DASHBOARD_SYNC ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$data['auto_start_index'] = true;
		}

		if ( ! empty( $index_meta ) ) {
			$data['index_meta'] = $index_meta;
		}

		$indexables = Indexables::factory()->get_all();

		/**
		 * Filter indexable labels used in dashboard sync UI
		 *
		 * @since  3.0
		 * @hook ep_dashboard_indexable_labels
		 * @param  {array} $labels Current indexable lables
		 * @return {array} New labels
		 */
		$data['sync_indexable_labels'] = apply_filters(
			'ep_dashboard_indexable_labels',
			[
				'post' => [
					'singular' => esc_html__( 'Post', 'elasticpress' ),
					'plural'   => esc_html__( 'Posts', 'elasticpress' ),
				],
				'user' => [
					'singular' => esc_html__( 'User', 'elasticpress' ),
					'plural'   => esc_html__( 'Users', 'elasticpress' ),
				],
			]
		);

		$data['install_sync']         = empty( $last_sync );
		$data['install_complete_url'] = esc_url( $install_complete_url );
		$data['sync_complete']        = esc_html__( 'Sync complete', 'elasticpress' );
		$data['sync_paused']          = esc_html__( 'Sync paused', 'elasticpress' );
		$data['sync_syncing']         = esc_html__( 'Syncing', 'elasticpress' );
		$data['sync_initial']         = esc_html__( 'Starting sync', 'elasticpress' );
		$data['sync_wpcli']           = esc_html__( "WP CLI sync is occurring. Refresh the page to see if it's finished", 'elasticpress' );
		$data['sync_error']           = esc_html__( 'An error occurred while syncing', 'elasticpress' );

		wp_localize_script( 'ep_dashboard_scripts', 'epDash', $data );
	}

	if ( in_array( Screen::factory()->get_current_screen(), [ 'health' ], true ) && ! empty( Utils\get_host() ) ) {
		Stats::factory()->build_stats();

		$data = Stats::factory()->get_localized();

		wp_enqueue_script( 'ep_stats', EP_URL . 'dist/js/stats-script.min.js', [], EP_VERSION, true );
		wp_localize_script( 'ep_stats', 'epChartData', $data );
	}
}

/**
 * Enqueue scripts to be used across all of WP admin
 *
 * @since 2.2
 */
function action_admin_enqueue_admin_scripts() {
	wp_enqueue_script( 'ep_admin_scripts', EP_URL . 'dist/js/admin-script.min.js', [ 'jquery' ], EP_VERSION, true );

	wp_localize_script(
		'ep_admin_scripts',
		'epAdmin',
		array(
			'nonce' => wp_create_nonce( 'ep_admin_nonce' ),
		)
	);
}

/**
 * Admin-init actions
 *
 * Sets up Settings API.
 *
 * @since 1.9
 * @return void
 */
function action_admin_init() {

	// Save options for multisite.
	if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK && isset( $_POST['ep_language'] ) ) {
		check_admin_referer( 'elasticpress-options' );

		$language = sanitize_text_field( $_POST['ep_language'] );
		update_site_option( 'ep_language', $language );

		if ( isset( $_POST['ep_host'] ) ) {
			$host = esc_url_raw( trim( $_POST['ep_host'] ) );
			update_site_option( 'ep_host', $host );
		}

		if ( isset( $_POST['ep_prefix'] ) ) {
			$prefix = ( isset( $_POST['ep_prefix'] ) ) ? sanitize_text_field( wp_unslash( $_POST['ep_prefix'] ) ) : '';
			update_site_option( 'ep_prefix', $prefix );
		}

		if ( isset( $_POST['ep_credentials'] ) ) {
			$credentials = ( isset( $_POST['ep_credentials'] ) ) ? Utils\sanitize_credentials( $_POST['ep_credentials'] ) : [
				'username' => '',
				'token'    => '',
			];

			update_site_option( 'ep_credentials', $credentials );
		}

		if ( isset( $_POST['ep_bulk_setting'] ) ) {
			update_site_option( 'ep_bulk_setting', intval( $_POST['ep_bulk_setting'] ) );
		}
	} else {
		register_setting( 'elasticpress', 'ep_host', 'esc_url_raw' );
		register_setting( 'elasticpress', 'ep_prefix', 'sanitize_text_field' );
		register_setting( 'elasticpress', 'ep_credentials', 'ep_sanitize_credentials' );
		register_setting( 'elasticpress', 'ep_language', 'sanitize_text_field' );
		register_setting(
			'elasticpress',
			'ep_bulk_setting',
			[
				'type'              => 'integer',
				'sanitize_callback' => __NAMESPACE__ . '\sanitize_bulk_settings',
			]
		);
	}
}

/**
 * Sanitize bulk settings.
 *
 * @param int $bulk_settings Number of bulk content items
 * @return int
 */
function sanitize_bulk_settings( $bulk_settings = 350 ) {
	$bulk_settings = absint( $bulk_settings );

	return ( 0 === $bulk_settings ) ? 350 : $bulk_settings;
}

/**
 * Output current ElasticPress dashboard screen
 *
 * @since 3.0
 */
function resolve_screen() {
	Screen::factory()->output();
}

/**
 * Admin menu actions
 *
 * Adds options page to admin menu.
 *
 * @since 1.9
 * @return void
 */
function action_admin_menu() {
	$capability = 'manage_options';

	if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
		$capability = 'manage_network';
	}

	add_menu_page(
		'ElasticPress',
		'ElasticPress',
		$capability,
		'elasticpress',
		__NAMESPACE__ . '\resolve_screen',
		'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0idXRmLTgiPz48c3ZnIHZlcnNpb249IjEuMSIgaWQ9IkxheWVyXzEiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgeG1sbnM6eGxpbms9Imh0dHA6Ly93d3cudzMub3JnLzE5OTkveGxpbmsiIHg9IjBweCIgeT0iMHB4IiB2aWV3Qm94PSIwIDAgNzMgNzEuMyIgc3R5bGU9ImVuYWJsZS1iYWNrZ3JvdW5kOm5ldyAwIDAgNzMgNzEuMzsiIHhtbDpzcGFjZT0icHJlc2VydmUiPjxwYXRoIGQ9Ik0zNi41LDQuN0MxOS40LDQuNyw1LjYsMTguNiw1LjYsMzUuN2MwLDEwLDQuNywxOC45LDEyLjEsMjQuNWw0LjUtNC41YzAuMS0wLjEsMC4xLTAuMiwwLjItMC4zbDAuNy0wLjdsNi40LTYuNGMyLjEsMS4yLDQuNSwxLjksNy4xLDEuOWM4LDAsMTQuNS02LjUsMTQuNS0xNC41cy02LjUtMTQuNS0xNC41LTE0LjVTMjIsMjcuNiwyMiwzNS42YzAsMi44LDAuOCw1LjMsMi4xLDcuNWwtNi40LDYuNGMtMi45LTMuOS00LjYtOC43LTQuNi0xMy45YzAtMTIuOSwxMC41LTIzLjQsMjMuNC0yMy40czIzLjQsMTAuNSwyMy40LDIzLjRTNDkuNCw1OSwzNi41LDU5Yy0yLjEsMC00LjEtMC4zLTYtMC44bC0wLjYsMC42bC01LjIsNS40YzMuNiwxLjUsNy42LDIuMywxMS44LDIuM2MxNy4xLDAsMzAuOS0xMy45LDMwLjktMzAuOVM1My42LDQuNywzNi41LDQuN3oiLz48L3N2Zz4='
	);

	add_submenu_page(
		'elasticpress',
		'ElasticPress ' . esc_html__( 'Settings', 'elasticpress' ),
		esc_html__( 'Settings', 'elasticpress' ),
		$capability,
		'elasticpress-settings',
		__NAMESPACE__ . '\resolve_screen'
	);

	add_submenu_page(
		'elasticpress',
		'ElasticPress ' . esc_html__( 'Index Health', 'elasticpress' ),
		esc_html__( 'Index Health', 'elasticpress' ),
		$capability,
		'elasticpress-health',
		__NAMESPACE__ . '\resolve_screen'
	);
}

/**
 * Uses the language from EP settings in mapping.
 *
 * @param string $language The current language.
 * @return string          The updated language.
 */
function use_language_in_setting( $language = 'english' ) {
	// Get the currently set language.
	$ep_language = Utils\get_language();

	// Bail early if no EP language is set.
	if ( empty( $ep_language ) ) {
		return $language;
	}

	require_once ABSPATH . 'wp-admin/includes/translation-install.php';
	$translations = wp_get_available_translations();

	// Bail early if not in the array of available translations.
	if ( empty( $translations[ $ep_language ]['english_name'] ) ) {
		return $language;
	}

	$english_name = strtolower( $translations[ $ep_language ]['english_name'] );

	/**
	 * Languages supported in Elasticsearch mappings.
	 *
	 * @link https://www.elastic.co/guide/en/elasticsearch/reference/current/analysis-lang-analyzer.html
	 */
	$ep_languages = [
		'arabic',
		'armenian',
		'basque',
		'bengali',
		'brazilian',
		'bulgarian',
		'catalan',
		'cjk',
		'czech',
		'danish',
		'dutch',
		'english',
		'finnish',
		'french',
		'galician',
		'german',
		'greek',
		'hindi',
		'hungarian',
		'indonesian',
		'irish',
		'italian',
		'latvian',
		'lithuanian',
		'norwegian',
		'persian',
		'portuguese',
		'romanian',
		'russian',
		'sorani',
		'spanish',
		'swedish',
		'turkish',
		'thai',
	];

	return in_array( $english_name, $ep_languages, true ) ? $english_name : $language;
}

/**
 * Add column to sites admin table.
 *
 * @param string[] $columns Array of columns.
 *
 * @return string[]
 */
function filter_blogs_columns( $columns ) {
	$columns['elasticpress'] = esc_html__( 'ElasticPress Indexing', 'elasticpress' );

	return $columns;
}

/**
 * Populate column with checkbox/switch.
 *
 * @param string $column_name The name of the current column.
 * @param int    $blog_id The blog ID.
 *
 * @return void | string
 */
function add_blogs_column( $column_name, $blog_id ) {
	$site = get_site( $blog_id );
	if ( $site->deleted || $site->archived || $site->spam ) {
		return;
	}
	if ( 'elasticpress' === $column_name ) {
		$is_indexable = get_blog_option( $blog_id, 'ep_indexable', 'yes' );
		$checked      = ( 'yes' === $is_indexable ) ? 'checked' : '';
		echo '<label class="switch"><input type="checkbox" ' . esc_attr( $checked ) . ' class="index-toggle" data-blogId="' . esc_attr( $blog_id ) . '"><span class="slider round"></span></label>';
		echo '<span class="switch-label" id="switch-label-' . esc_attr( $blog_id ) . '">';
		if ( 'yes' === $is_indexable ) {
			esc_html_e( 'On', 'elasticpress' );
		} else {
			esc_html_e( 'Off', 'elasticpress' );
		}

		echo '</span>';
	}

	return $column_name;
}

/**
 * AJAX callback to update ep_indexable site option.
 */
function action_wp_ajax_ep_site_admin() {
	$blog_id = ( ! empty( $_GET['blog_id'] ) ) ? absint( wp_unslash( $_GET['blog_id'] ) ) : - 1;
	$checked = ( ! empty( $_GET['checked'] ) ) ? sanitize_text_field( wp_unslash( $_GET['checked'] ) ) : 'no';

	if ( - 1 === $blog_id || ! check_ajax_referer( 'epsa', 'nonce', false ) ) {
		return wp_send_json_error();
	}
	$old    = get_blog_option( $blog_id, 'ep_indexable' );
	$result = update_blog_option( $blog_id, 'ep_indexable', $checked );
	$data   = [
		'blog_id' => $blog_id,
		'result'  => $result,
	];

	return wp_send_json_success( $data );
}
