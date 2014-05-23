<?php

$_tests_dir = getenv('WP_TESTS_DIR');
if ( !$_tests_dir ) $_tests_dir = '/tmp/wordpress-tests-lib';

echo $_tests_dir . "\n\n";
exit;

require_once $_tests_dir . '/includes/functions.php';

require_once 'vendor/autoload.php';

function _manually_load_plugin() {
	require dirname( __FILE__ ) . '/../elasticsearch.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';

