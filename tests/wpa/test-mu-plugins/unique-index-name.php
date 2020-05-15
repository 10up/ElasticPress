<?php

add_filter( 'ep_index_name', function( $index_name ) {
    $docker_cid = exec( 'cat /etc/hostname' );
    
    if ( $docker_cid ) {
        return $index_name . '-' . $docker_cid;
    }

    return $index_name;
} );