<?php

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) $_tests_dir = '/tmp/wordpress-tests-lib';

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

require_once $_tests_dir . '/includes/functions.php';

function _manually_load_plugin() {
	require dirname( __FILE__ ) . '/../elasticpress.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

require_once 'vendor/autoload.php';

require $_tests_dir . '/includes/bootstrap.php';