<?php

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = '/tmp/wordpress-tests-lib';
}

require_once( $_tests_dir . '/includes/functions.php' );

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

function ep_test_shard_number( $mapping ) {
	$mapping['settings']['index'] = array(
		'number_of_shards' => 1,
	);

	return $mapping;
}

function _manually_load_plugin() {
	$host = getenv( 'EP_HOST' );
	if ( empty( $host ) ) {
		$host = 'http://localhost:9200';
	}

	define( 'EP_HOST', $host );

	require( dirname( __FILE__ ) . '/../elasticpress.php' );

	add_filter( 'ep_config_mapping', 'ep_test_shard_number' );

	$tries = 5;
	$sleep = 3;
	do {
		$response = wp_remote_get( EP_HOST );
		if ( 200 == wp_remote_retrieve_response_code( $response ) ) {
			// Looks good!
			break;
		} else {
			printf( "\nInvalid response from ES, sleeping %d seconds and trying again...\n", $sleep );
			sleep( $sleep );
		}
	} while ( --$tries );

	if ( 200 != wp_remote_retrieve_response_code( $response ) ) {
		exit( 'Could not connect to ElasticPress server.' );
	}

	require_once( dirname( __FILE__ ) . '/includes/functions.php' );
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

require( $_tests_dir . '/includes/bootstrap.php' );
require_once( dirname( __FILE__ ) . '/includes/class-ep-test-base.php' );