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
	$docker_cid = get_option( 'ep_tests_docker_cid', '' );
	if ( ! $docker_cid ) {
		$docker_cid = exec( 'cat /etc/hostname' );
		update_option( 'ep_tests_docker_cid', $docker_cid );
	}
	return $docker_cid;
}

add_filter( 'ep_es_info_cache_expiration', '__return_zero' );
