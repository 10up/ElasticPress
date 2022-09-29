<?php

add_filter( 'ep_index_name', function( $index_name ) {
    $docker_cid = get_docker_cid();

    if ( $docker_cid ) {
        return $index_name . '-' . $docker_cid;
    }

    return $index_name;
} );

add_action( 'admin_footer', function() {
	printf(
		'<div id="docker-cid">%s</div>',
		get_docker_cid()
	);
});

/**
 * Return a unique id based on the docker environment.
 *
 * As in `wp-env` a container is used for cli and another one for web server accesses,
 * we pick the first one called, store it, and use it across all calls.
 *
 * @return string
 */
function get_docker_cid() {
	$docker_cid = get_site_option( 'ep_tests_docker_cid', '' );
	if ( ! $docker_cid ) {
		$docker_cid = exec( 'cat /etc/hostname' );
		update_site_option( 'ep_tests_docker_cid', $docker_cid );
	}
	return $docker_cid;
}

add_filter( 'ep_es_info_cache_expiration', '__return_zero' );

/**
 * From this point, only WP-CLI context should be executed.
 */
if ( ! defined( 'WP_CLI' ) ) {
	return;
}

/**
 * WP-CLI command to delete all indices used in the search.
 */
function ep_tests_delete_all_indices() {
	$docker_cid = get_docker_cid();
	if ( ! $docker_cid ) {
		WP_CLI::error( 'Docker CID not set.' );
	}

	// Get full list of indices.
	$response_cat_indices = \ElasticPress\Elasticsearch::factory()->remote_request( '_cat/indices?format=json' );

	if ( is_wp_error( $response_cat_indices ) ) {
		WP_CLI::error( 'Could not fetch indices names.' );
	}

	// Delete all indices matching the docker unique id.
	$indices_from_cat_indices_api = json_decode( wp_remote_retrieve_body( $response_cat_indices ), true );
	foreach ( $indices_from_cat_indices_api as $index ) {
		if ( false === strpos( $index['index'], $docker_cid ) ) {
			continue;
		}

		\ElasticPress\Elasticsearch::factory()->delete_index( $index['index'] );
	}
}
WP_CLI::add_command( 'elasticpress-tests delete-all-indices', 'ep_tests_delete_all_indices' );
