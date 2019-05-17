<?php
/**
 * Create an ElasticPress dashboard page.
 *
 * @package elasticpress
 * @since   1.9
 */

namespace ElasticPress\Dashboard;

use ElasticPress\Utils as Utils;
use ElasticPress\Elasticsearch as Elasticsearch;
use ElasticPress\Features as Features;
use ElasticPress\Indexables as Indexables;

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
	add_action( 'admin_init', __NAMESPACE__ . '\intro_or_dashboard' );
	add_action( 'plugins_loaded', __NAMESPACE__ . '\maybe_clear_es_info_cache' );
	add_action( 'wp_ajax_ep_index', __NAMESPACE__ . '\action_wp_ajax_ep_index' );
	add_action( 'wp_ajax_ep_notice_dismiss', __NAMESPACE__ . '\action_wp_ajax_ep_notice_dismiss' );
	add_action( 'wp_ajax_ep_cancel_index', __NAMESPACE__ . '\action_wp_ajax_ep_cancel_index' );
	add_action( 'admin_notices', __NAMESPACE__ . '\maybe_notice' );
	add_action( 'network_admin_notices', __NAMESPACE__ . '\maybe_notice' );
	add_filter( 'plugin_action_links', __NAMESPACE__ . '\filter_plugin_action_links', 10, 2 );
	add_filter( 'network_admin_plugin_action_links', __NAMESPACE__ . '\filter_plugin_action_links', 10, 2 );
	add_action( 'ep_add_query_log', __NAMESPACE__ . '\log_version_query_error' );
	add_filter( 'ep_analyzer_language', __NAMESPACE__ . '\use_language_in_setting' );
	add_action( 'admin_init', __NAMESPACE__ . '\intro_after_host' );
	add_filter( 'admin_title', __NAMESPACE__ . '\filter_admin_title', 10, 2 );
	add_filter( 'wp_kses_allowed_html', __NAMESPACE__ . '\filter_allowed_html', 10, 2 );
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
			'type'        => true,
			'checked'     => true,
			'selected'    => true,
			'disabled'    => true,
			'value'       => true,
			'class'       => true,
			'data-*'      => true,
			'id'          => true,
			'style'       => true,
			'title'       => true,
			'name'        => true,
			'placeholder' => '',
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

		return $ep_tags;
	}

	return $allowedtags;
}

/**
 * Filter admin title for intro page
 *
 * @param  string $admin_title Current title
 * @param  string $title       Original title
 * @since  3.0
 * @return string
 */
function filter_admin_title( $admin_title, $title ) {
	if ( ! empty( $_GET['page'] ) && 'elasticpress-intro' === $_GET['page'] ) {
		return esc_html__( 'Welcome to ElasticPress ', 'elasticpress' ) . $admin_title;
	}

	return $admin_title;
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

	if ( empty( $_GET['ep-retry'] ) && ( empty( $_GET['page'] ) || ( 'elasticpress' !== $_GET['page'] && 'elasticpress-settings' !== $_GET['page'] ) ) ) {
		return;
	}

	if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
		delete_site_transient( 'ep_es_info' );
	} else {
		delete_transient( 'ep_es_info' );
	}

	if ( ! empty( $_GET['ep-retry'] ) ) {
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
		$new_actions['ep_dashboard'] = sprintf( __( '<a href="%s">Dashboard</a>', 'elasticpress' ), esc_url( $url ) );
	}

	return array_merge( $new_actions, $plugin_actions );
}

/**
 * Output variety of dashboard notices. Only one at a time :)
 *
 * @param  bool $force Force ES info hard lookup.
 * @since  2.2
 */
function maybe_notice( $force = false ) {
	// Admins only.
	if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
		if ( ! is_super_admin() ) {
			return;
		}
	} else {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
	}
	// Don't show notice on intro page ever.
	if ( ! empty( $_GET['page'] ) && 'elasticpress-intro' === $_GET['page'] ) {
		return;
	}

	// If in network mode, don't output notice in admin and vice-versa.
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

	$host = Utils\get_host();

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

		if ( defined( 'EP_DASHBOARD_SYNC' ) && ! EP_DASHBOARD_SYNC ) {
			if ( 'auto-activate-sync' === $notice ) {
				$notice = 'sync-disabled-auto-activate';
			} elseif ( 'upgrade-sync' === $notice ) {
				$notice = 'sync-disabled-upgrade';
			} elseif ( 'no-sync' === $notice ) {
				$notice = 'sync-disabled-no-sync';
			}
		}
	}

	// Turn on logging for the version query.
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
	$es_version = Elasticsearch::factory()->get_elasticsearch_version( $force );

	/**
	 * Check Elasticsearch version compat
	 */

	if ( false !== $es_version ) {
		// Clear out any errors.
		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			delete_site_transient( 'ep_es_info_response_code' );
			delete_site_transient( 'ep_es_info_response_error' );
		} else {
			delete_transient( 'ep_es_info_response_code' );
			delete_transient( 'ep_es_info_response_error' );
		}

		// First reduce version to major version i.e. 5.1 not 5.1.1.
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
		$intro_shown             = get_site_option( 'ep_intro_shown', false );
		$skip_intro_shown_notice = get_site_option( 'ep_hide_intro_shown_notice', false );
	} else {
		$intro_shown             = get_option( 'ep_intro_shown', false );
		$skip_intro_shown_notice = get_option( 'ep_hide_intro_shown_notice', false );
	}

	$config_status = get_config_status();

	if ( true === $config_status && ! $skip_intro_shown_notice && ! $intro_shown && false === $options_host && ( ! defined( 'EP_HOST' ) || ! EP_HOST ) ) {
		$notice = 'need-setup';
	}

	// Autosuggest defaults notice
	$autosuggest = Features::factory()->get_registered_feature( 'autosuggest' );

	$using_autosuggest_defaults_dismiss = ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) ? get_site_option( 'ep_using_autosuggest_defaults_dismiss', false ) : get_option( 'ep_using_autosuggest_defaults_dismiss', false );

	if ( empty( $using_autosuggest_defaults_dismiss ) && $autosuggest->is_active() && (bool) $autosuggest->get_settings()['defaults_enabled'] ) {
		$notice = 'using-autosuggest-defaults';
	}

	$notice = apply_filters( 'ep_admin_notice_type', $notice );

	switch ( $notice ) {
		case 'bad-host':
			if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
				$url            = admin_url( 'network/admin.php?page=elasticpress-settings' );
				$options_host   = get_site_option( 'ep_host' );
				$response_code  = get_site_transient( 'ep_es_info_response_code' );
				$response_error = get_site_transient( 'ep_es_info_response_error' );
			} else {
				$url            = admin_url( 'admin.php?page=elasticpress-settings' );
				$options_host   = get_option( 'ep_host' );
				$response_code  = get_transient( 'ep_es_info_response_code' );
				$response_error = get_transient( 'ep_es_info_response_error' );
			}

			?>
			<div class="notice notice-error">
				<?php if ( empty( $host ) ) : ?>
					<p><?php printf( wp_kses_post( __( 'ElasticPress needs a host to work properly. <a href="%1$s">Please add one.</a>', 'elasticpress' ) ), esc_url( $url ) ); ?></p>
				<?php else : ?>
					<p><?php printf( wp_kses_post( __( 'There is a problem with connecting to your Elasticsearch host. ElasticPress can <a href="%1$s">try your host again</a>, or you may need to <a href="%2$s">change your settings</a>.', 'elasticpress' ) ), esc_url( add_query_arg( 'ep-retry', 1 ) ), esc_url( $url ) ); ?></p>

					<?php if ( ! empty( $response_code ) || ! empty( $response_error ) ) : ?>
						<p>
							<?php if ( ! empty( $response_code ) ) : ?>
								<span class="notice-error-es-response-code">
									<?php printf( wp_kses_post( __( 'Response Code: %s', 'elasticpress' ) ), esc_html( $response_code ) ); ?>
								</span>
							<?php endif; ?>

							<?php if ( ! empty( $response_error ) ) : ?>
								<span class="notice-error-es-response-error">
									<?php printf( wp_kses_post( __( 'Error: %s', 'elasticpress' ) ), esc_html( $response_error ) ); ?>
								</span>
							<?php endif; ?>
						</p>
					<?php endif; ?>
				<?php endif; ?>
			</div>
			<?php
			break;
		case 'above-es-compat':
			?>
			<div class="notice notice-error">
				<p><?php printf( wp_kses_post( __( 'Your Elasticsearch version %1$s is above the maximum required Elasticsearch version %2$s. ElasticPress may or may not work properly.', 'elasticpress' ) ), esc_html( $es_version ), esc_html( EP_ES_VERSION_MAX ) ); ?></p>
			</div>
			<?php
			break;
		case 'below-es-compat':
			?>
			<div class="notice notice-error">
				<p><?php printf( wp_kses_post( __( 'Your Elasticsearch version %1$s is below the minimum required Elasticsearch version %2$s. ElasticPress may or may not work properly.', 'elasticpress' ) ), esc_html( $es_version ), esc_html( EP_ES_VERSION_MIN ) ); ?></p>
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
				<p><?php printf( wp_kses_post( __( 'Thanks for installing ElasticPress! You will need to run through a <a href="%s">quick set up process</a> to get the plugin working.', 'elasticpress' ) ), esc_url( $url ) ); ?></p>
			</div>
			<?php
			break;
		case 'no-sync':
			if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
				$url = admin_url( 'network/admin.php?page=elasticpress-settings&do_sync' );
			} else {
				$url = admin_url( 'admin.php?page=elasticpress-settings&do_sync' );
			}

			?>
			<div data-ep-notice="no-sync" class="notice notice-info is-dismissible">
				<p><?php printf( wp_kses_post( __( 'ElasticPress is almost ready. You will need to complete a <a href="%s">sync</a> to get the plugin working.', 'elasticpress' ) ), esc_url( $url ) ); ?></p>
			</div>
			<?php
			break;
		case 'upgrade-sync':
			if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
				$url = admin_url( 'network/admin.php?page=elasticpress-settings&do_sync' );
			} else {
				$url = admin_url( 'admin.php?page=elasticpress-settings&do_sync' );
			}

			?>
			<div data-ep-notice="upgrade-sync" class="notice notice-warning is-dismissible">
				<p><?php printf( wp_kses_post( __( 'The new version of ElasticPress requires that you <a href="%s">run a sync</a>.', 'elasticpress' ) ), esc_url( $url ) ); ?></p>
			</div>
			<?php
			break;
		case 'auto-activate-sync':
			if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
				$url = admin_url( 'network/admin.php?page=elasticpress-settings&do_sync' );
			} else {
				$url = admin_url( 'admin.php?page=elasticpress-settings&do_sync' );
			}

			$feature = Features::factory()->get_registered_feature( $auto_activate_sync );

			?>
			<div data-ep-notice="auto-activate-sync" class="notice notice-warning is-dismissible">
				<p><?php echo wp_kses( sprintf( __( 'The ElasticPress %1$s feature has been auto-activated! You will need to <a href="%2$s">run a sync</a> for it to work.', 'elasticpress' ), esc_html( is_object( $feature ) ? $feature->title : '' ), esc_url( $url ) ), 'ep-html' ); ?></p>
			</div>
			<?php
			break;
		case 'sync-disabled-auto-activate':
			$feature = Features::factory()->get_registered_feature( $auto_activate_sync );
			?>
			<div data-ep-notice="sync-disabled-auto-activate" class="notice notice-warning is-dismissible">
				<p>
					<?php
					// phpcs:disable
					printf( esc_html__( 'Dashboard sync is disabled. The ElasticPress %s feature has been auto-activated! You will need to reindex using WP-CLI for it to work.', 'elasticpress' ), esc_html( is_object( $feature ) ? $feature->title : '' ) );
					// phpcs:enable
					?>
				</p>
			</div>
			<?php
			break;
		case 'sync-disabled-upgrade':
			?>
			<div data-ep-notice="sync-disabled-upgrade" class="notice notice-warning is-dismissible">
				<p><?php printf( esc_html__( 'Dashboard sync is disabled. The new version of ElasticPress requires that you to reindex using WP-CLI.', 'elasticpress' ) ); ?></p>
			</div>
			<?php
			break;
		case 'sync-disabled-no-sync':
			?>
			<div data-ep-notice="sync-disabled-no-sync" class="notice notice-warning is-dismissible">
				<p><?php printf( esc_html__( 'Dashboard sync is disabled. You will need to index using WP-CLI to finish setup.', 'elasticpress' ) ); ?></p>
			</div>
			<?php
			break;
		case 'using-autosuggest-defaults':
			?>
			<div data-ep-notice="using-autosuggest-defaults" class="notice notice-warning is-dismissible">
				<p><?php printf( esc_html__( 'Autosuggest feature is enabled. If protected content or documents feature is enabled, your protected content will also become searchable. Please checkmark the "Use safe values" checkbox in Autosuggest settings to default to safe content search', 'elasticpress' ) ); ?></p>
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
function action_wp_ajax_ep_notice_dismiss() {
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
		case 'sync-disabled-no-sync':
			// We use 'never' here as a placeholder value to trick EP into thinking a sync has happened.
			if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
				update_site_option( 'ep_last_sync', 'never' );
			} else {
				update_option( 'ep_last_sync', 'never' );
			}

			break;
		case 'upgrade-sync':
		case 'sync-disabled-upgrade':
			if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
				delete_site_option( 'ep_need_upgrade_sync' );
			} else {
				delete_option( 'ep_need_upgrade_sync' );
			}

			break;
		case 'auto-activate-sync':
		case 'sync-disabled-auto-activate':
			if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
				delete_site_option( 'ep_feature_auto_activated_sync' );
			} else {
				delete_option( 'ep_feature_auto_activated_sync' );
			}

			break;
		case 'using-autosuggest-defaults':
			if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
				update_site_option( 'ep_using_autosuggest_defaults_dismiss', true );
			} else {
				update_option( 'ep_using_autosuggest_defaults_dismiss', true );
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
				foreach ( $non_global_indexables as $indexable ) {
					$index_meta['sync_stack'][] = [
						'url'       => untrailingslashit( $site['domain'] . $site['path'] ),
						'blog_id'   => (int) $site['blog_id'],
						'indexable' => $indexable,
					];
				}
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

		foreach ( $global_indexables as $indexable ) {
			$index_meta['sync_stack'][] = [
				'indexable' => $indexable,
			];
		}

		do_action( 'ep_dashboard_start_index', $index_meta );
	} elseif ( ! empty( $index_meta['sync_stack'] ) && $index_meta['offset'] >= $index_meta['found_items'] ) {
		$status = 'start';

		$index_meta['start']             = true;
		$index_meta['offset']            = 0;
		$index_meta['current_sync_item'] = array_shift( $index_meta['sync_stack'] );
	} else {
		$index_meta['start'] = false;
	}

	$index_meta = apply_filters( 'ep_index_meta', $index_meta );
	$indexable  = Indexables::factory()->get( $index_meta['current_sync_item']['indexable'] );

	if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK && ! empty( $index_meta['current_sync_item']['blog_id'] ) ) {
		switch_to_blog( $index_meta['current_sync_item']['blog_id'] );
	}

	if ( ! empty( $index_meta['start'] ) ) {
		if ( ! apply_filters( 'ep_skip_index_reset', false, $index_meta ) ) {
			$indexable->delete_index();

			$indexable->put_mapping();

			do_action( 'ep_dashboard_put_mapping', $index_meta, $status );
		}
	}

	if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
		$bulk_setting = get_site_option( 'ep_bulk_setting', 350 );
	} else {
		$bulk_setting = get_option( 'ep_bulk_setting', 350 );
	}

	$per_page = apply_filters( 'ep_index_default_per_page', $bulk_setting );

	do_action( 'ep_pre_dashboard_index', $index_meta, $status, $indexable );

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
	if ( isset( get_current_screen()->id ) && strpos( get_current_screen()->id, 'elasticpress' ) !== false ) {
		wp_enqueue_style( 'ep_admin_styles', EP_URL . 'dist/css/dashboard.min.css', [], EP_VERSION );

		if ( ! empty( $_GET['page'] ) && ( 'elasticpress' === $_GET['page'] || 'elasticpress-settings' === $_GET['page'] ) ) {
			wp_enqueue_script( 'ep_dashboard_scripts', EP_URL . 'dist/js/dashboard.min.js', [ 'jquery' ], EP_VERSION, true );

			$data = array( 'nonce' => wp_create_nonce( 'ep_dashboard_nonce' ) );

			if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
				$index_meta = get_site_option( 'ep_index_meta', [] );
				$wpcli_sync = (bool) get_site_transient( 'ep_wpcli_sync' );
			} else {
				$index_meta = get_option( 'ep_index_meta', [] );
				$wpcli_sync = (bool) get_transient( 'ep_wpcli_sync' );
			}

			if ( ! empty( $wpcli_sync ) ) {
				$index_meta['wpcli_sync'] = true;
			}

			if ( isset( $_GET['do_sync'] ) && ( ! defined( 'EP_DASHBOARD_SYNC' ) || EP_DASHBOARD_SYNC ) ) {
				$data['auto_start_index'] = true;
			}

			if ( ! empty( $index_meta ) ) {
				$data['index_meta'] = $index_meta;
			}

			$indexables = Indexables::factory()->get_all();

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

			$data['intro_shown']   = esc_html( get_option( 'ep_intro_shown' ) );
			$data['ep_intro_url']  = esc_html( admin_url( 'admin.php?page=elasticpress-intro' ) );
			$data['sync_complete'] = esc_html__( 'Sync complete', 'elasticpress' );
			$data['sync_paused']   = esc_html__( 'Sync paused', 'elasticpress' );
			$data['sync_syncing']  = esc_html__( 'Syncing', 'elasticpress' );
			$data['sync_initial']  = esc_html__( 'Starting sync', 'elasticpress' );
			$data['sync_wpcli']    = esc_html__( "WP CLI sync is occurring. Refresh the page to see if it's finished", 'elasticpress' );
			$data['sync_error']    = esc_html__( 'An error occurred while syncing', 'elasticpress' );

			wp_localize_script( 'ep_dashboard_scripts', 'epDash', $data );
		}
	}
}

/**
 * Enqueue scripts to be used across all of WP admin
 *
 * @since 2.2
 */
function action_admin_enqueue_admin_scripts() {
	wp_enqueue_script( 'ep_admin_scripts', EP_URL . 'dist/js/admin.min.js', [ 'jquery' ], EP_VERSION, true );

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
 * Conditionally show dashboard or intro
 *
 * @since  2.1
 */
function intro_or_dashboard() {
	global $pagenow;

	if ( 'admin.php' !== $pagenow || empty( $_GET['page'] ) || 'elasticpress' !== $_GET['page'] ) {
		return;
	}

	$host = Utils\get_host();

	if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
		$intro_shown = get_site_option( 'ep_intro_shown', false );
	} else {
		$intro_shown = get_option( 'ep_intro_shown', false );
	}

	if ( ! $intro_shown ) {
		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			wp_safe_redirect( admin_url( 'network/admin.php?page=elasticpress-intro' ) );
		} else {
			wp_safe_redirect( admin_url( 'admin.php?page=elasticpress-intro' ) );
		}
		exit;
	}
}

/**
 * Build dashboard page
 *
 * @since 2.1
 */
function dashboard_page() {
	include __DIR__ . '/partials/dashboard-page.php';
}

/**
 * Build settings page
 *
 * @since  2.1
 */
function settings_page() {
	$current_step = get_config_status();

	if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK && true === $current_step ) {
		update_site_option( 'ep_intro_shown', true );
	} elseif ( true === $current_step ) {
		update_option( 'ep_intro_shown', true );
	}

	include __DIR__ . '/partials/settings-page.php';
}

/**
 * Determines where a user is in the config process
 *
 * @since  3.0
 * @return bool|int true if setup done
 */
function get_config_status() {
	$ep_host = Utils\get_host();

	if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
		if ( ! get_site_option( 'ep_intro_shown', false ) ) {
			$in_step = 2;
			if ( $ep_host && false !== Elasticsearch::factory()->get_elasticsearch_version( true ) ) {
				$in_step = 3;
			}
			if ( 3 === $in_step && false !== get_site_option( 'ep_last_sync', false ) ) {
				$in_step = true;
			}
		} else {
			$in_step = true;
		}
	} else {
		if ( ! get_option( 'ep_intro_shown', false ) ) {
			$in_step = 2;

			if ( $ep_host ) {
				$in_step = 3;
			}

			if ( 3 === $in_step && false !== get_option( 'ep_last_sync', false ) ) {
				$in_step = true;
			}
		} else {
			$in_step = true;
		}
	}

	return $in_step;
}
/**
 * Redirect to the intro page after setting host for the first time
 *
 * @since  3.0
 */
function intro_after_host() {
	$doing_sync = isset( $_GET['do_sync'] );

	if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
		if ( 3 === get_config_status() && ! get_site_option( 'ep_intro_shown' ) && 'elasticpress-settings' === $_GET['page'] && ! $doing_sync ) {
			wp_safe_redirect( admin_url( 'network/admin.php?page=elasticpress-intro' ) );
			exit();
		}
	} else {
		if ( 3 === get_config_status() && ! get_option( 'ep_intro_shown' ) && 'elasticpress-settings' === $_GET['page'] && ! $doing_sync ) {
			wp_safe_redirect( admin_url( 'admin.php?page=elasticpress-intro' ) );
			exit();
		}
	}
}

/**
 * Build settings page
 *
 * @since  2.1
 */
function intro_page() {
	$current_step = get_config_status();

	if ( true === $current_step ) {
		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			update_site_option( 'ep_intro_shown', true );
		} else {
			update_option( 'ep_intro_shown', true );
		}
	}

	include __DIR__ . '/partials/intro-page.php';
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
		__NAMESPACE__ . '\dashboard_page',
		'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0idXRmLTgiPz48c3ZnIHZlcnNpb249IjEuMSIgaWQ9IkxheWVyXzEiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgeG1sbnM6eGxpbms9Imh0dHA6Ly93d3cudzMub3JnLzE5OTkveGxpbmsiIHg9IjBweCIgeT0iMHB4IiB2aWV3Qm94PSIwIDAgNzMgNzEuMyIgc3R5bGU9ImVuYWJsZS1iYWNrZ3JvdW5kOm5ldyAwIDAgNzMgNzEuMzsiIHhtbDpzcGFjZT0icHJlc2VydmUiPjxwYXRoIGQ9Ik0zNi41LDQuN0MxOS40LDQuNyw1LjYsMTguNiw1LjYsMzUuN2MwLDEwLDQuNywxOC45LDEyLjEsMjQuNWw0LjUtNC41YzAuMS0wLjEsMC4xLTAuMiwwLjItMC4zbDAuNy0wLjdsNi40LTYuNGMyLjEsMS4yLDQuNSwxLjksNy4xLDEuOWM4LDAsMTQuNS02LjUsMTQuNS0xNC41cy02LjUtMTQuNS0xNC41LTE0LjVTMjIsMjcuNiwyMiwzNS42YzAsMi44LDAuOCw1LjMsMi4xLDcuNWwtNi40LDYuNGMtMi45LTMuOS00LjYtOC43LTQuNi0xMy45YzAtMTIuOSwxMC41LTIzLjQsMjMuNC0yMy40czIzLjQsMTAuNSwyMy40LDIzLjRTNDkuNCw1OSwzNi41LDU5Yy0yLjEsMC00LjEtMC4zLTYtMC44bC0wLjYsMC42bC01LjIsNS40YzMuNiwxLjUsNy42LDIuMywxMS44LDIuM2MxNy4xLDAsMzAuOS0xMy45LDMwLjktMzAuOVM1My42LDQuNywzNi41LDQuN3oiLz48L3N2Zz4='
	);

	add_submenu_page(
		'elasticpress',
		'ElasticPress ' . esc_html__( 'Settings', 'elasticpress' ),
		esc_html__( 'Settings', 'elasticpress' ),
		$capability,
		'elasticpress-settings',
		__NAMESPACE__ . '\settings_page'
	);

	add_submenu_page(
		null,
		'ElasticPress ' . esc_html__( 'Welcome', 'elasticpress' ),
		'ElasticPress ' . esc_html__( 'Welcome', 'elasticpress' ),
		$capability,
		'elasticpress-intro',
		__NAMESPACE__ . '\intro_page'
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
