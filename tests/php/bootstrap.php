<?php
/**
 * ElasticPress test bootstrap
 *
 * @package elasticpress
 */

namespace ElasticPressTest;

set_time_limit( 0 );

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = '/tmp/wordpress-tests-lib';
}

require_once $_tests_dir . '/includes/functions.php';

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

/**
 * Make sure we only test on 1 shard because any more will lead to inconsistent results
 *
 * @since  3.0
 */
function test_shard_number() {
	return 1;
}

/**
 * Bootstrap EP plugin
 *
 * @since 3.0
 */
function load_plugin() {
	global $wp_version;

	$host = getenv( 'EP_HOST' );

	if ( empty( $host ) ) {
		$host = 'http://127.0.0.1:9200';
	}

	update_option( 'ep_host', $host );
	update_site_option( 'ep_host', $host );

	define( 'EP_UNIT_TESTS', true );

	if ( defined( 'WP_TESTS_MULTISITE' ) && '1' === WP_TESTS_MULTISITE ) {
		define( 'EP_IS_NETWORK', true );
		define( 'WP_NETWORK_ADMIN', true );
	}

	include_once __DIR__ . '/../../vendor/woocommerce/woocommerce.php';
	require_once __DIR__ . '/../../elasticpress.php';

	add_filter( 'ep_default_index_number_of_shards', __NAMESPACE__ . '\test_shard_number' );

	$tries = 5;
	$sleep = 3;

	do {
		$response = wp_remote_get( $host );
		if ( 200 === wp_remote_retrieve_response_code( $response ) ) {
			// Looks good!
			break;
		} else {
			printf( "\nInvalid response from ES, sleeping %d seconds and trying again...\n", intval( $sleep ) );
			sleep( $sleep );
		}
	} while ( --$tries );

	if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
		exit( 'Could not connect to ElasticPress server.' );
	}

	require_once __DIR__ . '/includes/functions.php';

	echo 'WordPress version ' . $wp_version . "\n"; // phpcs:ignore
	echo 'Elasticsearch version ' . \ElasticPress\Elasticsearch::factory()->get_elasticsearch_version( true ) . "\n"; // phpcs:ignore
}

tests_add_filter( 'muplugins_loaded', __NAMESPACE__ . '\load_plugin' );

/**
 * Setup WooCommerce for tests
 *
 * @since  3.0
 */
function setup_wc() {
	if ( ! class_exists( '\WC_Install' ) ) {
		return;
	}

	define( 'WP_UNINSTALL_PLUGIN', true );

	update_option( 'woocommerce_status_options', array( 'uninstall_data' => 1 ) );
	include_once __DIR__ . '/../../vendor/woocommerce/uninstall.php';

	\WC_Install::install();

	$GLOBALS['wp_roles'] = new \WP_Roles();

	echo 'Installing WooCommerce version ' . WC()->version . ' ...' . PHP_EOL; // phpcs:ignore
}
tests_add_filter( 'setup_theme', __NAMESPACE__ . '\setup_wc' );

/**
 * Set WooCommerce as an active plugin
 *
 * @since 5.0.0
 * @param array $active_plugins Active plugins
 * @return array
 */
function add_woocommerce_to_active_plugins( $active_plugins ) {
	$active_plugins   = (array) $active_plugins;
	$active_plugins[] = 'woocommerce/woocommerce.php';
	return $active_plugins;
}
tests_add_filter( 'option_active_plugins', __NAMESPACE__ . '\add_woocommerce_to_active_plugins' );

/**
 * Completely skip looking up translations
 *
 * @since  3.0
 * @return array
 */
function skip_translations_api() {
	return [
		'translations' => [],
	];
}

tests_add_filter( 'translations_api', __NAMESPACE__ . '\skip_translations_api' );

require_once $_tests_dir . '/includes/bootstrap.php';

require_once __DIR__ . '/includes/classes/factory/PostFactory.php';
require_once __DIR__ . '/includes/classes/factory/UserFactory.php';
require_once __DIR__ . '/includes/classes/factory/TermFactory.php';
require_once __DIR__ . '/includes/classes/factory/CommentFactory.php';
require_once __DIR__ . '/includes/classes/factory/ProductFactory.php';
require_once __DIR__ . '/includes/classes/BaseTestCase.php';
require_once __DIR__ . '/includes/classes/FeatureTest.php';
require_once __DIR__ . '/includes/classes/mock/Global/Feature.php';
require_once __DIR__ . '/includes/classes/mock/class-wp-cli-command.php';
require_once __DIR__ . '/includes/classes/mock/class-wp-cli.php';
require_once __DIR__ . '/includes/wp-cli-utils.php';
