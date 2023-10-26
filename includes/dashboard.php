<?php
/**
 * Create an ElasticPress dashboard page.
 *
 * @package elasticpress
 * @since   1.9
 */

namespace ElasticPress\Dashboard;

use ElasticPress\Utils;
use ElasticPress\Elasticsearch;
use ElasticPress\Features;
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
	}

	add_action( 'admin_menu', __NAMESPACE__ . '\action_admin_menu' );
	add_action( 'wp_ajax_ep_save_feature', __NAMESPACE__ . '\action_wp_ajax_ep_save_feature' );
	add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\action_admin_enqueue_dashboard_scripts' );
	add_action( 'admin_init', __NAMESPACE__ . '\maybe_clear_es_info_cache' );
	add_action( 'admin_init', __NAMESPACE__ . '\maybe_skip_install' );
	add_action( 'wp_ajax_ep_notice_dismiss', __NAMESPACE__ . '\action_wp_ajax_ep_notice_dismiss' );
	add_action( 'admin_notices', __NAMESPACE__ . '\maybe_notice' );
	add_action( 'network_admin_notices', __NAMESPACE__ . '\maybe_notice' );
	add_filter( 'plugin_action_links', __NAMESPACE__ . '\filter_plugin_action_links', 10, 2 );
	add_filter( 'network_admin_plugin_action_links', __NAMESPACE__ . '\filter_plugin_action_links', 10, 2 );
	add_action( 'ep_add_query_log', __NAMESPACE__ . '\log_version_query_error' );
	add_filter( 'ep_analyzer_language', __NAMESPACE__ . '\use_language_in_setting', 10, 2 );
	add_filter( 'wp_kses_allowed_html', __NAMESPACE__ . '\filter_allowed_html', 10, 2 );
	add_action( 'enqueue_block_editor_assets', __NAMESPACE__ . '\block_assets' );

	if ( version_compare( get_bloginfo( 'version' ), '5.8', '>=' ) ) {
		add_action( 'block_categories_all', __NAMESPACE__ . '\block_categories' );
	} else {
		add_action( 'block_categories', __NAMESPACE__ . '\block_categories' );
	}

	/**
	 * Filter whether to show 'ElasticPress Indexing' option on Multisite in admin UI or not.
	 *
	 * @since  3.6.0
	 * @hook ep_show_indexing_option_on_multisite
	 * @param  {bool}  $show True to show.
	 * @return {bool}  New value
	 */
	$show_indexing_option_on_multisite = apply_filters( 'ep_show_indexing_option_on_multisite', defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK );

	if ( $show_indexing_option_on_multisite ) {
		add_filter( 'wpmu_blogs_columns', __NAMESPACE__ . '\filter_blogs_columns', 10, 1 );
		add_action( 'manage_sites_custom_column', __NAMESPACE__ . '\add_blogs_column', 10, 2 );
		add_action( 'wp_ajax_ep_site_admin', __NAMESPACE__ . '\action_wp_ajax_ep_site_admin' );
	}
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

		$ep_tags['a'] = array_merge(
			$atts,
			[ 'target' => true ]
		);

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
	// Ignore fake requests like the autosuggest template generation
	if ( ! empty( $query['request'] ) && is_array( $query['request'] ) && ! empty( $query['request']['is_ep_fake_request'] ) ) {
		return;
	}

	$logging_key = 'logging_ep_es_info';

	$logging = Utils\get_transient( $logging_key );

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
		Utils\set_transient( $response_code_key, $response_code, $cache_time );
		Utils\set_transient( $response_error_key, $response_error, $cache_time );
		Utils\delete_transient( $logging_key );
	}
}

/**
 * Allow user to skip install process.
 *
 * @since  3.5
 */
function maybe_skip_install() {
	if ( ! is_admin() && ! is_network_admin() ) {
		return;
	}

	if ( empty( $_GET['ep-skip-install'] ) || empty( $_GET['nonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['nonce'] ), 'ep-skip-install' ) || ! in_array( Screen::factory()->get_current_screen(), [ 'install' ], true ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}

	if ( ! empty( $_GET['ep-skip-features'] ) ) {
		$features = \ElasticPress\Features::factory()->registered_features;

		foreach ( $features as $slug => $feature ) {
			\ElasticPress\Features::factory()->deactivate_feature( $slug );
		}
	}

	if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
		$redirect_url = network_admin_url( 'admin.php?page=elasticpress' );
	} else {
		$redirect_url = admin_url( 'admin.php?page=elasticpress' );
	}
	Utils\update_option( 'ep_skip_install', true );

	wp_safe_redirect( $redirect_url );
	exit;
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
		exit();
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
		if ( ! is_super_admin() || ! is_network_admin() ) {
			return false;
		}
	} else {
		if ( is_network_admin() || ! current_user_can( Utils\get_capability() ) ) {
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

	Utils\set_transient(
		'logging_ep_es_info',
		'1',
		$cache_time
	);

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

	wp_enqueue_script( 'ep_notice_script' );

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

	if ( ! current_user_can( Utils\get_capability() ) ) {
		wp_send_json_error();
		exit;
	}

	AdminNotices::factory()->dismiss_notice( sanitize_key( $_POST['notice'] ) );

	wp_send_json_success();
}

/**
 * Getting the status of ongoing index fired by WP CLI
 *
 * @since  2.1
 */
function action_wp_ajax_ep_cli_index() {
	_deprecated_function( __CLASS__, '3.6.0', '\ElasticPress\Screen::factory()->sync_screen->action_wp_ajax_ep_cli_index()' );
}

/**
 * Continue index
 *
 * @since  2.1
 */
function action_wp_ajax_ep_index() {
	_deprecated_function( __CLASS__, '3.6.0', '\ElasticPress\Screen::factory()->sync_screen->action_wp_ajax_ep_index()' );
}

/**
 * Cancel index
 *
 * @since  2.1
 */
function action_wp_ajax_ep_cancel_index() {
	_deprecated_function( __CLASS__, '3.6.0', '\ElasticPress\Screen::factory()->sync_screen->action_wp_ajax_ep_cancel_index()' );
}

/**
 * Save individual feature settings
 *
 * @since  2.2
 */
function action_wp_ajax_ep_save_feature() {
	$post = wp_unslash( $_POST );

	if ( empty( $post['feature'] ) || empty( $post['settings'] ) || ! check_ajax_referer( 'ep_dashboard_nonce', 'nonce', false ) ) {
		wp_send_json_error();
		exit;
	}

	if ( Utils\is_indexing() ) {
		$error = new \WP_Error( 'is_indexing' );

		wp_send_json_error( $error );
		exit;
	}

	$data = Features::factory()->update_feature( $post['feature'], $post['settings'] );

	// Since we deactivated, delete auto activate notice.
	if ( empty( $post['settings']['active'] ) ) {
		Utils\delete_option( 'ep_feature_auto_activated_sync' );
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
		wp_enqueue_style( 'wp-components' );

		wp_enqueue_script(
			'ep_admin_sites_scripts',
			EP_URL . 'dist/js/sites-admin-script.js',
			Utils\get_asset_info( 'sites-admin-script', 'dependencies' ),
			Utils\get_asset_info( 'sites-admin-script', 'version' ),
			true
		);

		wp_set_script_translations( 'ep_admin_sites_scripts', 'elasticpress' );

		$data = [
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'epsa' ),
		];

		wp_localize_script( 'ep_admin_sites_scripts', 'epsa', $data );
	}

	if ( in_array( Screen::factory()->get_current_screen(), [ 'dashboard', 'settings', 'install', 'health', 'weighting', 'synonyms', 'sync', 'status-report' ], true ) ) {
		wp_enqueue_style(
			'ep_admin_styles',
			EP_URL . 'dist/css/dashboard-styles.css',
			Utils\get_asset_info( 'dashboard-styles', 'dependencies' ),
			Utils\get_asset_info( 'dashboard-styles', 'version' )
		);
		wp_enqueue_script(
			'ep_admin_script',
			EP_URL . 'dist/js/admin-script.js',
			Utils\get_asset_info( 'admin-script', 'dependencies' ),
			Utils\get_asset_info( 'admin-script', 'version' ),
			true
		);

		wp_set_script_translations( 'ep_admin_script', 'elasticpress' );
	}

	if ( 'weighting' === Screen::factory()->get_current_screen() ) {

		wp_enqueue_style(
			'ep_weighting_styles',
			EP_URL . 'dist/css/weighting-script.css',
			[ 'wp-components', 'wp-edit-post' ],
			Utils\get_asset_info( 'weighting-script', 'version' )
		);

		wp_enqueue_script(
			'ep_weighting_script',
			EP_URL . 'dist/js/weighting-script.js',
			Utils\get_asset_info( 'weighting-script', 'dependencies' ),
			Utils\get_asset_info( 'weighting-script', 'version' ),
			true
		);

		$weighting = Features::factory()->get_registered_feature( 'search' )->weighting;

		$api_url                 = esc_url_raw( rest_url( 'elasticpress/v1/weighting' ) );
		$meta_mode               = $weighting->get_meta_mode();
		$weightable_fields       = $weighting->get_weightable_fields();
		$weighting_configuration = $weighting->get_weighting_configuration_with_defaults();

		wp_localize_script(
			'ep_weighting_script',
			'epWeighting',
			array(
				'apiUrl'                 => $api_url,
				'metaMode'               => $meta_mode,
				'weightableFields'       => $weightable_fields,
				'weightingConfiguration' => $weighting_configuration,
			)
		);

		wp_set_script_translations( 'ep_weighting_script', 'elasticpress' );
	}

	if ( in_array( Screen::factory()->get_current_screen(), [ 'dashboard', 'install' ], true ) ) {
		wp_enqueue_script(
			'ep_dashboard_scripts',
			EP_URL . 'dist/js/dashboard-script.js',
			Utils\get_asset_info( 'dashboard-script', 'dependencies' ),
			Utils\get_asset_info( 'dashboard-script', 'version' ),
			true
		);

		wp_set_script_translations( 'ep_dashboard_scripts', 'elasticpress' );

		$sync_url = ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) ?
				network_admin_url( 'admin.php?page=elasticpress-sync&do_sync' ) :
				admin_url( 'admin.php?page=elasticpress-sync&do_sync' );

		$skip_url = ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) ?
				network_admin_url( 'admin.php?page=elasticpress' ) :
				admin_url( 'admin.php?page=elasticpress' );

		$data = array(
			'skipUrl' => add_query_arg(
				array(
					'ep-skip-install'  => 1,
					'ep-skip-features' => 1,
					'nonce'            => wp_create_nonce( 'ep-skip-install' ),
				),
				$skip_url
			),
			'syncUrl' => $sync_url,
		);

		wp_localize_script( 'ep_dashboard_scripts', 'epDash', $data );
	}

	if ( in_array( Screen::factory()->get_current_screen(), [ 'health' ], true ) && ! empty( Utils\get_host() ) ) {
		Stats::factory()->build_stats();

		$data = Stats::factory()->get_localized();

		wp_enqueue_script(
			'ep_stats',
			EP_URL . 'dist/js/stats-script.js',
			Utils\get_asset_info( 'stats-script', 'dependencies' ),
			Utils\get_asset_info( 'stats-script', 'version' ),
			true
		);

		wp_set_script_translations( 'ep_stats', 'elasticpress' );

		wp_localize_script( 'ep_stats', 'epChartData', $data );
	}

	wp_register_script(
		'ep_notice_script',
		EP_URL . 'dist/js/notice-script.js',
		Utils\get_asset_info( 'notice-script', 'dependencies' ),
		Utils\get_asset_info( 'notice-script', 'version' ),
		true
	);

	wp_set_script_translations( 'ep_notice_script', 'elasticpress' );

	wp_localize_script(
		'ep_notice_script',
		'epAdmin',
		array(
			'nonce' => wp_create_nonce( 'ep_admin_nonce' ),
		)
	);
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
	if ( ! Utils\is_site_indexable() && ! is_network_admin() ) {
		return;
	}

	$capability = ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) ? Utils\get_network_capability() : Utils\get_capability();

	add_menu_page(
		'ElasticPress',
		'ElasticPress',
		$capability,
		'elasticpress',
		__NAMESPACE__ . '\resolve_screen',
		'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0idXRmLTgiPz48c3ZnIHZlcnNpb249IjEuMSIgaWQ9IkxheWVyXzEiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgeG1sbnM6eGxpbms9Imh0dHA6Ly93d3cudzMub3JnLzE5OTkveGxpbmsiIHg9IjBweCIgeT0iMHB4IiB2aWV3Qm94PSIwIDAgNzMgNzEuMyIgc3R5bGU9ImVuYWJsZS1iYWNrZ3JvdW5kOm5ldyAwIDAgNzMgNzEuMzsiIHhtbDpzcGFjZT0icHJlc2VydmUiPjxwYXRoIGQ9Ik0zNi41LDQuN0MxOS40LDQuNyw1LjYsMTguNiw1LjYsMzUuN2MwLDEwLDQuNywxOC45LDEyLjEsMjQuNWw0LjUtNC41YzAuMS0wLjEsMC4xLTAuMiwwLjItMC4zbDAuNy0wLjdsNi40LTYuNGMyLjEsMS4yLDQuNSwxLjksNy4xLDEuOWM4LDAsMTQuNS02LjUsMTQuNS0xNC41cy02LjUtMTQuNS0xNC41LTE0LjVTMjIsMjcuNiwyMiwzNS42YzAsMi44LDAuOCw1LjMsMi4xLDcuNWwtNi40LDYuNGMtMi45LTMuOS00LjYtOC43LTQuNi0xMy45YzAtMTIuOSwxMC41LTIzLjQsMjMuNC0yMy40czIzLjQsMTAuNSwyMy40LDIzLjRTNDkuNCw1OSwzNi41LDU5Yy0yLjEsMC00LjEtMC4zLTYtMC44bC0wLjYsMC42bC01LjIsNS40YzMuNiwxLjUsNy42LDIuMywxMS44LDIuM2MxNy4xLDAsMzAuOS0xMy45LDMwLjktMzAuOVM1My42LDQuNywzNi41LDQuN3oiLz48L3N2Zz4='
	);

	if ( ! Utils\is_top_level_admin_context() ) {
		return;
	}

	add_submenu_page(
		'elasticpress',
		esc_html__( 'ElasticPress Features', 'elasticpress' ),
		esc_html__( 'Features', 'elasticpress' ),
		$capability,
		'elasticpress',
		__NAMESPACE__ . '\resolve_screen'
	);

	add_submenu_page(
		'elasticpress',
		esc_html__( 'ElasticPress Settings', 'elasticpress' ),
		esc_html__( 'Settings', 'elasticpress' ),
		$capability,
		'elasticpress-settings',
		__NAMESPACE__ . '\resolve_screen'
	);

	add_submenu_page(
		'elasticpress',
		'ElasticPress ' . esc_html__( 'Sync', 'elasticpress' ),
		esc_html__( 'Sync', 'elasticpress' ),
		$capability,
		'elasticpress-sync',
		__NAMESPACE__ . '\resolve_screen'
	);

	add_submenu_page(
		'elasticpress',
		esc_html__( 'ElasticPress Index Health', 'elasticpress' ),
		esc_html__( 'Index Health', 'elasticpress' ),
		$capability,
		'elasticpress-health',
		__NAMESPACE__ . '\resolve_screen'
	);

	add_submenu_page(
		'elasticpress',
		esc_html__( 'ElasticPress Status Report', 'elasticpress' ),
		esc_html__( 'Status Report', 'elasticpress' ),
		$capability,
		'elasticpress-status-report',
		__NAMESPACE__ . '\resolve_screen'
	);
}

/**
 * Languages supported in Elasticsearch mappings.
 *
 * If $format is 'elasticsearch', the array format is `Elasticsearch analyzer name => [ WordPress language package names ]`.
 *
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/analysis-lang-analyzer.html
 * @since 4.7.0
 * @param string $format Format of the return ('locales' or 'elasticsearch' )
 * @return array
 */
function get_available_languages( string $format = 'elasticsearch' ) : array {
	/**
	 * Filter available languages in Elasticsearch.
	 *
	 * The returned array should follow the format `Elasticsearch analyzer name => [ WordPress language package names ]`.
	 *
	 * @since 4.7.0
	 * @hook ep_available_languages
	 * @param  {bool} $available_languages List of available languages
	 * @return {bool} New list
	 */
	$es_languages = apply_filters(
		'ep_available_languages',
		[
			'arabic'     => [ 'ar', 'ary' ],
			'armenian'   => [ 'hy' ],
			'basque'     => [ 'eu' ],
			'bengali'    => [ 'bn', 'bn_BD' ],
			'brazilian'  => [ 'pt_BR' ],
			'bulgarian'  => [ 'bg', 'bg_BG' ],
			'catalan'    => [ 'ca' ],
			'cjk'        => [], // CJK characters (not a language)
			'czech'      => [ 'cs', 'cs_CZ' ],
			'danish'     => [ 'da', 'da_DK' ],
			'dutch'      => [ 'nl_NL_formal', 'nl_NL', 'nl_BE' ],
			'english'    => [ 'en', 'en_AU', 'en_GB', 'en_NZ', 'en_CA', 'en_US', 'en_ZA' ],
			'estonian'   => [ 'et' ],
			'finnish'    => [ 'fi' ],
			'french'     => [ 'fr', 'fr_CA', 'fr_FR', 'fr_BE' ],
			'galician'   => [ 'gl_ES' ],
			'german'     => [ 'de', 'de_DE', 'de_DE_formal', 'de_CH', 'de_CH_informal', 'de_AT' ],
			'greek'      => [ 'el' ],
			'hindi'      => [ 'hi_IN' ],
			'hungarian'  => [ 'hu_HU' ],
			'indonesian' => [ 'id_ID' ],
			'irish'      => [], // WordPress doesn't support Irish as an active locale currently
			'italian'    => [ 'it_IT' ],
			'latvian'    => [ 'lv' ],
			'lithuanian' => [ 'lt_LT' ],
			'norwegian'  => [ 'nb_NO' ],
			'persian'    => [ 'fa_IR' ],
			'portuguese' => [ 'pt', 'pt_AO', 'pt_PT', 'pt_PT_ao90' ],
			'romanian'   => [ 'ro_RO' ],
			'russian'    => [ 'ru_RU' ],
			'sorani'     => [ 'ckb' ],
			'spanish'    => [ 'es_CR', 'es_MX', 'es_VE', 'es_AR', 'es_CL', 'es_GT', 'es_PE', 'es_ES', 'es_UY', 'es_CO' ],
			'swedish'    => [ 'sv_SE' ],
			'turkish'    => [ 'tr_TR' ],
			'thai'       => [ 'th' ],
		]
	);

	if ( 'locales' === $format ) {
		$arr = array_reduce(
			$es_languages,
			function ( $acc, $lang ) {
				$lang = array_filter(
					$lang,
					function ( $locale ) {
						// English is always added. This removes the duplicates
						return ! in_array( $locale, [ 'en', 'en_US' ], true );
					}
				);
				$acc  = array_merge( $acc, $lang );
				return $acc;
			},
			[]
		);
		return $arr;
	}

	return $es_languages;
}

/**
 * Uses the language from EP settings in mapping.
 *
 * @param string $language The current language.
 * @param string $context  The context where the function is running.
 * @return string          The updated language.
 */
function use_language_in_setting( $language = 'english', $context = '' ) {
	global $locale, $wp_local_package;

	// Get the currently set language.
	$ep_language = Utils\get_language();

	// Bail early if no EP language is set.
	if ( empty( $ep_language ) ) {
		return $language;
	}

	/**
	 * WordPress does not reset the language when switch_blog() is called.
	 *
	 * @see https://core.trac.wordpress.org/ticket/49263
	 */
	if ( 'site-default' === $ep_language ) {
		$locale           = null;
		$wp_local_package = null;
		$ep_language      = get_locale();
	}

	require_once ABSPATH . 'wp-admin/includes/translation-install.php';
	$translations = wp_get_available_translations();

	// Default to en_US if not in the array of available translations.
	if ( ! empty( $translations[ $ep_language ]['english_name'] ) ) {
		$wp_language = $translations[ $ep_language ]['language'];
	} else {
		$wp_language = 'en_US';
	}

	$es_languages = get_available_languages();

	/**
	 * Languages supported in Elasticsearch snowball token filters.
	 *
	 * @link https://www.elastic.co/guide/en/elasticsearch/reference/current/analysis-snowball-tokenfilter.html
	 */
	$es_snowball_languages = [
		'Armenian',
		'Basque',
		'Catalan',
		'Danish',
		'Dutch',
		'English',
		'Finnish',
		'French',
		'German',
		'German2', // currently unused
		'Hungarian',
		'Italian',
		'Kp', // currently unused
		'Lithuanian',
		'Lovins', // currently unused
		'Norwegian',
		'Porter', // currently unused
		'Portuguese',
		'Romanian',
		'Russian',
		'Spanish',
		'Swedish',
		'Turkish',
	];

	$es_snowball_similar = [
		'Brazilian' => 'Portuguese',
	];

	foreach ( $es_languages as $analyzer_name => $analyzer_language_codes ) {
		if ( in_array( $wp_language, $analyzer_language_codes, true ) ) {
			$language = $analyzer_name;
			break;
		}
	}

	if ( 'filter_ewp_snowball' === $context ) {
		$uc_first_language = ucfirst( $language );
		if ( in_array( $uc_first_language, $es_snowball_languages, true ) ) {
			return $uc_first_language;
		}

		return $es_snowball_similar[ $uc_first_language ] ?? 'English';
	}

	if ( 'filter_ep_stop' === $context ) {
		return "_{$language}_";
	}

	return $language;
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
	if ( 'elasticpress' !== $column_name ) {
		return;
	}

	$site = get_site( $blog_id );
	if ( $site->deleted || $site->archived || $site->spam ) {
		return;
	}

	$is_indexable = get_site_meta( $blog_id, 'ep_indexable', true );
	$is_indexable = '' !== $is_indexable ? $is_indexable : 'yes';

	printf(
		'<input %1$s class="index-toggle" data-blog-id="%2$s" disabled type="checkbox">',
		checked( $is_indexable, 'yes', false ),
		esc_attr( $blog_id )
	);
}

/**
 * AJAX callback to update ep_indexable site option.
 */
function action_wp_ajax_ep_site_admin() {
	$blog_id = ( ! empty( $_POST['blog_id'] ) ) ? absint( wp_unslash( $_POST['blog_id'] ) ) : - 1;
	$checked = ( ! empty( $_POST['checked'] ) ) ? sanitize_text_field( wp_unslash( $_POST['checked'] ) ) : 'no';

	if ( - 1 === $blog_id || ! check_ajax_referer( 'epsa', 'nonce', false ) ) {
		return wp_send_json_error();
	}

	/**
	 * NOTE: This will be removed in ElasticPress 5.0.0. Implementations should rely on site_meta since 4.7.0.
	 */
	$result = update_blog_option( $blog_id, 'ep_indexable', $checked );

	$result = update_site_meta( $blog_id, 'ep_indexable', $checked );
	$data   = [
		'blog_id' => $blog_id,
		'result'  => $result,
	];

	return wp_send_json_success( $data );
}

/**
 * Handle the fetch for indexing status
 */
function handle_indexing_status() {
	$indexing_status = \ElasticPress\Utils\get_indexing_status();

	$status = array(
		'method'        => '',
		'items_indexed' => 0,
		'total_items'   => 0,
		'indexable'     => '',
	);

	if ( ! empty( $indexing_status ) ) {
		if ( isset( $indexing_status['method'] ) && 'cli' === $indexing_status['method'] ) {
			$status['method']        = $indexing_status['method'];
			$status['items_indexed'] = $indexing_status['items_indexed'];
			$status['total_items']   = $indexing_status['total_items'];
			$status['indexable']     = $indexing_status['slug'];
		} else {
			$status['method']        = 'dashboard';
			$status['items_indexed'] = isset( $indexing_status['offset'] ) ? $indexing_status['offset'] : 0;
			$status['total_items']   = isset( $indexing_status['found_items'] ) ? $indexing_status['found_items'] : 0;
			$status['indexable']     = isset( $indexing_status['current_sync_item']['indexable'] ) ? $indexing_status['current_sync_item']['indexable'] : '';
		}
	}

	return $status;
}

/**
 * Add an ElasticPress block category.
 *
 * @param array $block_categories Array of categories for block types.
 * @return array Array of categories for block types.
 */
function block_categories( $block_categories ) {
	$block_categories[] = [
		'slug'  => 'elasticpress',
		'title' => 'ElasticPress',
	];

	return $block_categories;
};

/**
 * Enqueue shared block editor assets.
 *
 * @return void
 */
function block_assets() {
	wp_enqueue_script(
		'elasticpress-blocks',
		EP_URL . 'dist/js/blocks-script.js',
		Utils\get_asset_info( 'blocks-script', 'dependencies' ),
		Utils\get_asset_info( 'blocks-script', 'version' ),
		true
	);

	wp_localize_script(
		'elasticpress-blocks',
		'epBlocks',
		[
			'syncUrl' => Utils\get_sync_url(),
		]
	);
}
