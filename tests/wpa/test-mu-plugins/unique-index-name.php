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

function get_docker_cid() {
	return exec( 'cat /etc/hostname' );
}
