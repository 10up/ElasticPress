<?php
 if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}
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
	 * Holds error messages for individual posts that failed to index (assuming they're available).
	 *
	 * @since 1.7
	 */
	private $failed_posts_message = array();

	/**
	 * Add the document mapping
	 *
	 * @synopsis [--network-wide]
	 * @subcommand put-mapping
	 * @since      0.9
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function put_mapping( $args, $assoc_args ) {
		$this->_connect_check();

		if ( isset( $assoc_args['network-wide'] ) && is_multisite() ) {
			if ( ! is_numeric( $assoc_args['network-wide'] ) ){
				$assoc_args['network-wide'] = 0;
			}
			$sites = ep_get_sites( $assoc_args['network-wide'] );

			foreach ( $sites as $site ) {
				switch_to_blog( $site['blog_id'] );

				WP_CLI::line( sprintf( __( 'Adding mapping for site %d...', 'elasticpress' ), (int) $site['blog_id'] ) );

				// Deletes index first
				ep_delete_index();

				$result = ep_put_mapping();

				do_action( 'ep_cli_put_mapping', $args, $assoc_args );

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

			do_action( 'ep_cli_put_mapping', $args, $assoc_args );

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
	 * @todo       replace this function with one that updates all rows with a --force option
	 * @synopsis [--network-wide]
	 * @subcommand delete-index
	 * @since      0.9
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function delete_index( $args, $assoc_args ) {
		$this->_connect_check();

		if ( isset( $assoc_args['network-wide'] ) && is_multisite() ) {
			if ( ! is_numeric( $assoc_args['network-wide'] ) ){
				$assoc_args['network-wide'] = 0;
			}
			$sites = ep_get_sites( $assoc_args['network-wide'] );

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
	 *
	 * @subcommand recreate-network-alias
	 * @since      0.9
	 *
	 * @param array $assoc_args
	 */
	public function recreate_network_alias( $args, $assoc_args ) {
		$this->_connect_check();

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
		$sites   = ep_get_sites();
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
	 * @synopsis [--setup] [--network-wide] [--posts-per-page] [--nobulk] [--offset] [--show-bulk-errors] [--post-type] [--keep-active]
	 *
	 * @param array $args
	 *
	 * @since 0.1.2
	 *
	 * @param array $assoc_args
	 */
	public function index( $args, $assoc_args ) {
		$this->_connect_check();

		if ( ! empty( $assoc_args['posts-per-page'] ) ) {
			$assoc_args['posts-per-page'] = absint( $assoc_args['posts-per-page'] );
		} else {
			$assoc_args['posts-per-page'] = 350;
		}

		if ( ! empty( $assoc_args['offset'] ) ) {
			$assoc_args['offset'] = absint( $assoc_args['offset'] );
		} else {
			$assoc_args['offset'] = 0;
		}

		if ( empty( $assoc_args['post-type'] ) ) {
			$assoc_args['post-type'] = null;
		}

		$total_indexed = 0;

		/**
		 * Prior to the index command invoking
		 * Useful for deregistering filters/actions that occur during a query request
		 *
		 * @since 1.4.1
		 */
		do_action( 'ep_wp_cli_pre_index', $args, $assoc_args );

		if ( isset( $assoc_args['network-wide'] ) && is_multisite() ) {
			update_site_option( 'ep_index_meta', array( 'wpcli' => true ) );
		} else {
			update_option( 'ep_index_meta', array( 'wpcli' => true ) );
		}

		timer_start();

		// Run setup if flag was passed
		if ( isset( $assoc_args['setup'] ) && true === $assoc_args['setup'] ) {

			// Right now setup is just the put_mapping command, as this also deletes the index(s) first
			$this->put_mapping( $args, $assoc_args );
		}

		if ( isset( $assoc_args['network-wide'] ) && is_multisite() ) {
			if ( ! is_numeric( $assoc_args['network-wide'] ) ){
				$assoc_args['network-wide'] = 0;
			}

			WP_CLI::log( __( 'Indexing posts network-wide...', 'elasticpress' ) );

			$sites = ep_get_sites( $assoc_args['network-wide'] );

			foreach ( $sites as $site ) {
				switch_to_blog( $site['blog_id'] );

				$result = $this->_index_helper( $assoc_args );

				$total_indexed += $result['synced'];

				WP_CLI::log( sprintf( __( 'Number of posts indexed on site %d: %d', 'elasticpress' ), $site['blog_id'], $result['synced'] ) );

				if ( ! empty( $result['errors'] ) ) {
					WP_CLI::error( sprintf( __( 'Number of post index errors on site %d: %d', 'elasticpress' ), $site['blog_id'], count( $result['errors'] ) ) );
				}

				restore_current_blog();
			}

			WP_CLI::log( __( 'Recreating network alias...', 'elasticpress' ) );

			$this->_create_network_alias();

			WP_CLI::log( sprintf( __( 'Total number of posts indexed: %d', 'elasticpress' ), $total_indexed ) );

		} else {

			WP_CLI::log( __( 'Indexing posts...', 'elasticpress' ) );

			$result = $this->_index_helper( $assoc_args );

			WP_CLI::log( sprintf( __( 'Number of posts indexed on site %d: %d', 'elasticpress' ), get_current_blog_id(), $result['synced'] ) );

			if ( ! empty( $result['errors'] ) ) {
				WP_CLI::error( sprintf( __( 'Number of post index errors on site %d: %d', 'elasticpress' ), get_current_blog_id(), count( $result['errors'] ) ) );
			}
		}

		WP_CLI::log( WP_CLI::colorize( '%Y' . __( 'Total time elapsed: ', 'elasticpress' ) . '%N' . timer_stop() ) );

		if ( isset( $assoc_args['network-wide'] ) && is_multisite() ) {
			delete_site_option( 'ep_index_meta' );
		} else {
			delete_option( 'ep_index_meta' );
		}

		WP_CLI::success( __( 'Done!', 'elasticpress' ) );
	}

	/**
	 * Helper method for indexing posts
	 *
	 * @param array $args
	 *
	 * @since 0.9
	 * @return array
	 */
	private function _index_helper( $args ) {
		$synced = 0;
		$errors = array();

		$no_bulk = false;

		if ( isset( $args['nobulk'] ) ) {
			$no_bulk = true;
		}

		$show_bulk_errors = false;

		if ( isset( $args['show-bulk-errors'] ) ) {
			$show_bulk_errors = true;
		}

		$posts_per_page = 350;

		if ( ! empty( $args['posts-per-page'] ) ) {
			$posts_per_page = absint( $args['posts-per-page'] );
		}

		$offset = 0;

		if ( ! empty( $args['offset'] ) ) {
			$offset = absint( $args['offset'] );
		}

		$post_type = ep_get_indexable_post_types();

		if ( ! empty( $args['post-type'] ) ) {
			$post_type = explode( ',', $args['post-type'] );
			$post_type = array_map( 'trim', $post_type );
		}

		if ( is_array( $post_type ) ) {
			$post_type = array_values( $post_type );
		}

		/**
		 * Create WP_Query here and reuse it in the loop to avoid high memory consumption.
		 */
		$query = new WP_Query();

		while ( true ) {

			$args = apply_filters( 'ep_index_posts_args', array(
				'posts_per_page'         => $posts_per_page,
				'post_type'              => $post_type,
				'post_status'            => ep_get_indexable_post_status(),
				'offset'                 => $offset,
				'ignore_sticky_posts'    => true,
				'orderby'                => 'ID',
				'order'                  => 'DESC',
				'cache_results '         => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			) );
			$query->query( $args );

			if ( $query->have_posts() ) {

				while ( $query->have_posts() ) {
					$query->the_post();

					if ( $no_bulk ) {
						// index the posts one-by-one. not sure why someone may want to do this.
						$result = ep_sync_post( get_the_ID() );

						do_action( 'ep_cli_post_index', get_the_ID() );
					} else {
						$result = $this->queue_post( get_the_ID(), $query->post_count, $show_bulk_errors );
					}

					if ( ! $result ) {
						$errors[] = get_the_ID();
					} elseif ( true === $result ) {
						$synced ++;
					}
				}
			} else {
				break;
			}

			WP_CLI::log( 'Processed ' . ( $query->post_count + $offset ) . '/' . $query->found_posts . ' entries. . .' );

			$offset += $posts_per_page;

			usleep( 500 );

			// Avoid running out of memory
			$this->stop_the_insanity();

		}

		if ( ! $no_bulk ) {
			$this->send_bulk_errors();
		}

		wp_reset_postdata();

		return array( 'synced' => $synced, 'errors' => $errors );
	}

	/**
	 * Queues up a post for bulk indexing
	 *
	 * @since 0.9.2
	 *
	 * @param $post_id
	 * @param $bulk_trigger
	 * @param bool $show_bulk_errors true to show individual post error messages for bulk errors
	 *
	 * @return bool|int true if successfully synced, false if not or 2 if post was killed before sync
	 */
	private function queue_post( $post_id, $bulk_trigger, $show_bulk_errors = false ) {
		static $post_count = 0;
		static $killed_post_count = 0;

		$killed_post = false;

		$post_args = ep_prepare_post( $post_id );

		// Mimic EP_Sync_Manager::sync_post( $post_id ), otherwise posts can slip
		// through the kill filter... that would be bad!
		if ( apply_filters( 'ep_post_sync_kill', false, $post_args, $post_id ) ) {

			$killed_post_count++;
			$killed_post = true; // Save status for return.

		} else { // Post wasn't killed so process it.

			// put the post into the queue
			$this->posts[ $post_id ][] = '{ "index": { "_id": "' . absint( $post_id ) . '" } }';

			if ( function_exists( 'wp_json_encode' ) ) {

				$this->posts[ $post_id ][] = addcslashes( wp_json_encode( $post_args ), "\n" );

			} else {

				$this->posts[ $post_id ][] = addcslashes( json_encode( $post_args ), "\n" );

			}

			// augment the counter
			++ $post_count;

		}

		// If we have hit the trigger, initiate the bulk request.
		if ( ( $post_count + $killed_post_count ) === absint( $bulk_trigger ) ) {

			// Don't waste time if we've killed all the posts.
			if ( ! empty( $this->posts ) ) {
				$this->bulk_index( $show_bulk_errors );
			}

			// reset the post count
			$post_count = 0;
			$killed_post_count = 0;

			// reset the posts
			$this->posts = array();
		}

		if ( true === $killed_post ) {
			return 2;
		}

		return true;

	}

	/**
	 * Perform the bulk index operation
	 *
	 * @param bool $show_bulk_errors true to show individual post error messages for bulk errors
	 *
	 * @since 0.9.2
	 */
	private function bulk_index( $show_bulk_errors = false ) {
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
			WP_CLI::log( 'Request string length: ' . size_format( mb_strlen( $body, '8bit' ), 2 ) );
		}

		// decode the response
		$response = ep_bulk_index_posts( $body );

		do_action( 'ep_cli_post_bulk_index', $this->posts );

		if ( is_wp_error( $response ) ) {
			WP_CLI::error( implode( "\n", $response->get_error_messages() ) );
		}

		// if we did have errors, try to add the documents again
		if ( isset( $response['errors'] ) && $response['errors'] === true ) {
			if ( $attempts < 5 ) {
				foreach ( $response['items'] as $item ) {
					if ( empty( $item['index']['error'] ) ) {
						unset( $this->posts[$item['index']['_id']] );
					}
				}
				$this->bulk_index( $show_bulk_errors );
			} else {
				foreach ( $response['items'] as $item ) {
					if ( ! empty( $item['index']['_id'] ) ) {
						$this->failed_posts[] = $item['index']['_id'];
						if ( $show_bulk_errors ) {
							$this->failed_posts_message[$item['index']['_id']] = $item['index']['error'];
						}
					}
				}
				$attempts = 0;
			}
		} else {
			// there were no errors, all the posts were added
			$attempts = 0;
		}
	}

	/**
	 * Send any bulk indexing errors
	 *
	 * @since 0.9.2
	 */
	private function send_bulk_errors() {
		if ( ! empty( $this->failed_posts ) ) {
			$error_text = __( "The following posts failed to index:\r\n\r\n", 'elasticpress' );
			foreach ( $this->failed_posts as $failed ) {
				$failed_post = get_post( $failed );
				if ( $failed_post ) {
					$error_text .= "- {$failed}: " . $failed_post->post_title . "\r\n";
					if ( array_key_exists( $failed, $this->failed_posts_message ) ) {
						$error_text .= "\t" . $this->failed_posts_message[ $failed ] . PHP_EOL;
					}
				}
			}

			WP_CLI::log( $error_text );

			// clear failed posts after printing to the screen
			$this->failed_posts = array();
			$this->failed_posts_message = array();
		}
	}

	/**
	 * Ping the Elasticsearch server and retrieve a status.
	 *
	 * @since 0.9.1
	 */
	public function status() {
		$this->_connect_check();

		$request_args = array( 'headers' => ep_format_request_headers() );

		$request = wp_remote_get( trailingslashit( ep_get_host( true ) ) . '_recovery/?pretty', $request_args );

		if ( is_wp_error( $request ) ) {
			WP_CLI::error( implode( "\n", $request->get_error_messages() ) );
		}

		$body = wp_remote_retrieve_body( $request );
		WP_CLI::line( '' );
		WP_CLI::line( '====== Status ======' );
		WP_CLI::line( print_r( $body, true ) );
		WP_CLI::line( '====== End Status ======' );
	}

	/**
	 * Get stats on the current index.
	 *
	 * @since 0.9.2
	 */
	public function stats() {
		$this->_connect_check();

		$request_args = array( 'headers' => ep_format_request_headers() );

		$request = wp_remote_get( trailingslashit( ep_get_host( true ) ) . '_stats/', $request_args );
		if ( is_wp_error( $request ) ) {
			WP_CLI::error( implode( "\n", $request->get_error_messages() ) );
		}
		$body  = json_decode( wp_remote_retrieve_body( $request ), true );
		$sites = ( is_multisite() ) ? ep_get_sites() : array( 'blog_id' => get_current_blog_id() );

		foreach ( $sites as $site ) {
			$current_index = ep_get_index_name( $site['blog_id'] );

			if (isset( $body['indices'][$current_index] ) ) {
				WP_CLI::log( '====== Stats for: ' . $current_index . " ======" );
				WP_CLI::log( 'Documents:  ' . $body['indices'][$current_index]['total']['docs']['count'] );
				WP_CLI::log( 'Index Size: ' . size_format($body['indices'][$current_index]['total']['store']['size_in_bytes'], 2 ) );
				WP_CLI::log( '====== End Stats ======' );
			} else {
				WP_CLI::warning( $current_index . ' is not currently indexed.' );
			}
		}
	}

	/**
	 * Resets some values to reduce memory footprint.
	 */
	public function stop_the_insanity() {
		global $wpdb, $wp_object_cache, $wp_actions, $wp_filter;

		$wpdb->queries = array();

		if ( is_object( $wp_object_cache ) ) {
			$wp_object_cache->group_ops = array();
			$wp_object_cache->stats = array();
			$wp_object_cache->memcache_debug = array();

			// Make sure this is a public property, before trying to clear it
			try {
				$cache_property = new ReflectionProperty( $wp_object_cache, 'cache' );
				if ( $cache_property->isPublic() ) {
					$wp_object_cache->cache = array();
				}
				unset( $cache_property );
			} catch ( ReflectionException $e ) {
			}

			/*
			 * In the case where we're not using an external object cache, we need to call flush on the default
			 * WordPress object cache class to clear the values from the cache property
			 */
			if ( ! wp_using_ext_object_cache() ) {
				wp_cache_flush();
			}

			if ( is_callable( $wp_object_cache, '__remoteset' ) ) {
				call_user_func( array( $wp_object_cache, '__remoteset' ) ); // important
			}
		}

		// Prevent wp_actions from growing out of control
		$wp_actions = array();

		// WP_Query class adds filter get_term_metadata using its own instance
		// what prevents WP_Query class from being destructed by PHP gc.
		//    if ( $q['update_post_term_cache'] ) {
		//        add_filter( 'get_term_metadata', array( $this, 'lazyload_term_meta' ), 10, 2 );
		//    }
		// It's high memory consuming as WP_Query instance holds all query results inside itself
		// and in theory $wp_filter will not stop growing until Out Of Memory exception occurs.
		if ( isset( $wp_filter['get_term_metadata'][10] ) ) {
			foreach ( $wp_filter['get_term_metadata'][10] as $hook => $content ) {
				if ( preg_match( '#^[0-9a-f]{32}lazyload_term_meta$#', $hook ) ) {
					unset( $wp_filter['get_term_metadata'][10][$hook] );
				}
			}
		}
	}

	/**
	 * Provide better error messaging for common connection errors
	 *
	 * @since 0.9.3
	 */
	private function _connect_check() {
		if ( empty( ep_get_host() ) ) {
			WP_CLI::error( __( 'There is no Elasticsearch host set up. Either add one through the dashboard or define one in wp-config.php', 'elasticpress' ) );
		} elseif ( ! ep_elasticsearch_can_connect() ) {
			WP_CLI::error( __( 'Unable to reach Elasticsearch Server! Check that service is running.', 'elasticpress' ) );
		}
	}
}