<?php

WP_CLI::add_command( 'elasticpress', 'ElasticPress_CLI_Command' );

/**
 * CLI Commands for ElasticPress
 *
 */
class ElasticPress_CLI_Command extends WP_CLI_Command {
    /**
     * Holds the posts that will be bulk synced.
     *
     * @since 0.9
     */
    private $posts = array();

    /**
     * Holds all of the posts that failed to index during a bulk index.
     *
     * @since 0.9
     */
    private $failed_posts = array();

    /**
     * Add the document mapping
     *
     * @synopsis [--network-wide]
     * @subcommand put-mapping
     * @since 0.9
     * @param array $args
     * @param array $assoc_args
     */
    public function put_mapping( $args, $assoc_args ) {

        if ( !empty( $assoc_args['network-wide'] ) ) {
            $sites = wp_get_sites();

            foreach ( $sites as $site ) {
                switch_to_blog( $site['blog_id'] );

                WP_CLI::line( sprintf( __( 'Adding mapping for site %d...', 'elasticpress' ), (int) $site['blog_id'] ) );

                // Deletes index first
                ep_delete_index();

                $result = ep_put_mapping();

                if ( $result ) {
                    WP_CLI::success( __( 'Mapping sent', 'elasticpress' ) );
                } else {
                    WP_CLI::error( __( 'Mapping failed', 'elasticpress' ) );
                }

                restore_current_blog();
            }
        } else {
            WP_CLI::line( __( 'Adding mapping...', 'elasticpress' ) );

            // Deletes index first
            $this->delete_index( $args, $assoc_args );

            $result = ep_put_mapping();

            if ( $result ) {
                WP_CLI::success( __( 'Mapping sent', 'elasticpress' ) );
            } else {
                WP_CLI::error( __( 'Mapping failed', 'elasticpress' ) );
            }
        }
    }

    /**
     * Delete the current index. !!Warning!! This removes your elasticsearch index for the entire site.
     *
     * @todo replace this function with one that updates all rows with a --force option
     * @synopsis [--network-wide]
     * @subcommand delete-index
     * @since 0.9
     * @param array $args
     * @param array $assoc_args
     */
    public function delete_index( $args, $assoc_args ) {
        if ( !empty( $assoc_args['network-wide'] ) ) {
            $sites = wp_get_sites();

            foreach ( $sites as $site ) {
                switch_to_blog( $site['blog_id'] );

                WP_CLI::line( sprintf( __( 'Deleting index for site %d...', 'elasticpress' ), (int) $site['blog_id'] ) );

                $result = ep_delete_index();

                if ( $result ) {
                    WP_CLI::success( __( 'Index deleted', 'elasticpress' ) );
                } else {
                    WP_CLI::error( __( 'Delete index failed', 'elasticpress' ) );
                }

                restore_current_blog();
            }
        } else {
            WP_CLI::line( __( 'Deleting index...', 'elasticpress' ) );

            $result = ep_delete_index();

            if ( $result ) {
                WP_CLI::success( __( 'Index deleted', 'elasticpress' ) );
            } else {
                WP_CLI::error( __( 'Index delete failed', 'elasticpress' ) );
            }
        }
    }

    /**
     * Map network alias to every index in the network
     *
     * @param array $args
     * @subcommand recreate-network-alias
     * @since 0.9
     * @param array $assoc_args
     */
    public function recreate_network_alias( $args, $assoc_args ) {
        WP_CLI::line( __( 'Recreating network alias...', 'elasticpress' ) );

        ep_delete_network_alias();

        $create_result = $this->_create_network_alias();

        if ( $create_result ) {
            WP_CLI::success( __( 'Done!', 'elasticpress' ) );
        } else {
            WP_CLI::error( __( 'An error occurred', 'elasticpress' ) );
        }
    }

    /**
     * Helper method for creating the network alias
     *
     * @since 0.9
     * @return array|bool
     */
    private function _create_network_alias() {
        $sites   = apply_filters( 'ep_indexable_sites', wp_get_sites() );
        $indexes = array();

        foreach ( $sites as $site ) {
            switch_to_blog( $site['blog_id'] );

            $indexes[] = ep_get_index_name();

            restore_current_blog();
        }

        return ep_create_network_alias( $indexes );
    }

    /**
     * Index all posts for a site or network wide
     *
     * @synopsis [--network-wide]
     * @param array $args
     * @since 0.1.2
     * @param array $assoc_args
     */
    public function index( $args, $assoc_args ) {
        if ( !empty( $assoc_args['network-wide'] ) ) {
            WP_CLI::line( __( 'Indexing posts network-wide...', 'elasticpress' ) );

            $sites = wp_get_sites();

            foreach ( $sites as $site ) {
                switch_to_blog( $site['blog_id'] );

                $result = $this->_index_helper( isset( $assoc_args['no-bulk'] ) );

                WP_CLI::line( sprintf( __( 'Number of posts synced on site %d: %d', 'elasticpress' ), get_current_blog_id(), $site['blog_id'], $result['synced'] ) );

                if ( !empty( $errors ) ) {
                    WP_CLI::error( sprintf( __( 'Number of post sync errors on site %d: %d', 'elasticpress' ), get_current_blog_id(), count( $result['errors'] ) ) );
                }

                restore_current_blog();
            }

            WP_CLI::line( __( 'Recreating network alias...' ) );
            $this->_create_network_alias();

        } else {
            WP_CLI::line( __( 'Indexing posts...', 'elasticpress' ) );

            $result = $this->_index_helper( isset( $assoc_args['no-bulk'] ) );

            WP_CLI::line( sprintf( __( 'Number of posts synced on site %d: %d', 'elasticpress' ), get_current_blog_id(), $result['synced'] ) );

            if ( !empty( $errors ) ) {
                WP_CLI::error( sprintf( __( 'Number of post sync errors on site %d: %d', 'elasticpress' ), get_current_blog_id(), count( $result['errors'] ) ) );
            }
        }

        WP_CLI::success( __( 'Done!', 'elasticpress' ) );
    }

    /**
     * Helper method for indexing posts
     *
     * @since 0.9
     * @return array
     */
    private function _index_helper( $no_bulk = false ) {
        $synced = 0;
        $errors = array();
        $offset = 0;

        while ( true ) {

            $args = array(
                'posts_per_page'      => 500,
                'post_type'           => ep_get_indexable_post_types(),
                'post_status'         => 'publish',
                'offset'              => $offset,
                'ignore_sticky_posts' => true
            );

            $query = new WP_Query( $args );

            if ( $query->have_posts() ) {

                while ( $query->have_posts() ) {
                    $query->the_post();

                    if ( $no_bulk ) {
                        // index the posts one-by-one. not sure someone may want to do this.
                        $result = ep_sync_post( get_the_ID() );
                    } else {
                        $result = $this->queue_post( get_the_ID(), $query->found_posts );
                    }
                    if ( !$result ) {
                        $errors[] = get_the_ID();
                    } else {
                        $synced++;
                    }
                }
            } else {
                break;
            }

            $offset += 500;

            usleep( 500 );
        }

        if ( !$no_bulk ) {
            $this->send_bulk_errors();
        }

        wp_reset_postdata();

        return array( 'synced' => $synced, 'errors' => $errors );
    }

    private function queue_post( $post_id, $bulk_trigger ) {
        static $post_count = 0;

        // put the post into the queue
        $this->posts[ $post_id ][] = '{ "index": { "_id": "' . absint( $post_id ) . '" } }';
        $this->posts[ $post_id ][] = addcslashes( json_encode( ep_prepare_post( $post_id ) ), "\n" );

        // augment the counter
        ++$post_count;

        // if we have hit the trigger, initiate the bulk request
        if ( $post_count === absint( $bulk_trigger ) ) {
            $this->bulk_index();

            // reset the post count
            $post_count = 0;

            // reset the posts
            $this->posts = array();
        }
        return true;
    }

    private function bulk_index() {
        // monitor how many times we attempt to add this particular bulk request
        static $attempts = 0;

        // augment the attempts
        ++$attempts;

        // make sure we actually have something to index
        if ( empty( $this->posts ) ) {
            WP_CLI::error( 'There are no posts to index.' );
        }

        $flatten = array();

        foreach ( $this->posts as $post ) {
            $flatten[] = $post[0];
            $flatten[] = $post[1];
        }

        // make sure to add a new line at the end or the request will fail
        $body = rtrim( implode( "\n", $flatten ) ) . "\n";

        // show the content length in bytes if in debug
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            WP_CLI::line( WP_CLI::colorize( '%MRequest size:%N' ) . ' ' . mb_strlen( $body, '8bit' ) );
        }

        // decode the response
        $response = $this->bulk_index_posts( $body );

        // if we did have errors, try to add the documents again
        if ( isset( $response['errors'] ) && $response['errors'] === true ) {
            if ( $attempts < 5 ) {
                foreach ( $response['items'] as $item ) {
                    if ( empty( $item['index']['error'] ) ) {
                        unset( $this->posts[ $item['index']['_id'] ] );
                    }
                }
                $this->bulk_index();
            } else {
                foreach ( $response['items'] as $item ) {
                    if ( !empty( $item['index']['_id'] ) ) {
                        $this->failed_posts[] = $item['index']['_id'];
                    }
                }
                $attempts = 0;
            }
        } else {
            // there were no errors, all the posts were added
            $attempts = 0;
        }
    }

    private function bulk_index_posts( $body ) {
        // create the url with index name and type so that we don't have to repeat it over and over in the request (thereby reducing the request size)
        $url     = trailingslashit( EP_HOST ) . trailingslashit( ep_get_index_name() ) . 'post/_bulk';
        $request = wp_remote_request( $url, array( 'method' => 'POST', 'body' => $body ) );

        // kill it on an error and show the message readout
        if ( is_wp_error( $request ) ) {
            WP_CLI::error( implode( "\n", $request->get_error_messages() ) );
        }

        return json_decode( wp_remote_retrieve_body( $request ), true );
    }

    private function send_bulk_errors() {
        if ( !empty( $this->failed_posts ) ) {
            $email_text = __( "The following posts failed to index:\r\n\r\n", 'elasticpress' );
            foreach ( $this->failed_posts as $failed ) {
                $email_text .= "- {$failed}: " . get_post( $failed )->post_title . "\r\n";
            }
            $send_mail = wp_mail( get_option( 'admin_email' ), wp_specialchars_decode( get_option( 'blogname' ) ) . __( ': ElasticPress Index Errors', 'elasticpress' ), $email_text );

            if ( !$send_mail ) {
                fwrite( STDOUT, __( 'Failed to send bulk error email. Print on screen? [y/n] ' ) );
                $answer = trim( fgets( STDIN ) );
                if ( 'y' == $answer ) {
                    WP_CLI::line( $email_text );
                }
            }
        }
    }

    /**
     * Ping the Elasticsearch server and retrieve a status.
     */
    public function status() {
        $request = wp_remote_get( trailingslashit( EP_HOST ) . '_status/?pretty' );
        if ( is_wp_error( $request ) ) {
            WP_CLI::error( implode( "\n", $request->get_error_messages() ) );
        }
        $body = wp_remote_retrieve_body( $request );
        WP_CLI::line( '' );
        WP_CLI::line( '====== Status ======' );
        WP_CLI::line( print_r( $body, true ) );
        WP_CLI::line( '====== End Status ======' );
    }
}