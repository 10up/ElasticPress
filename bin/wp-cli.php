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
	 * Holds the users that will be bulk synced
	 *
	 * @since 1.7.0
	 */
	private $users = array();

	/**
	 * Holds all of the posts that failed to index during a bulk index.
	 *
	 * @since 0.9
	 */
	private $failed_posts = array();

	/**
	 * Holds all of the users that failed to index during a bulk index.
	 *
	 * @since 1.7.0
	 */
	private $failed_users = array();

	/**
	 * Holds error messages for individual posts that failed to index (assuming they're available).
	 *
	 * @since 1.7
	 */
	private $failed_posts_message = array();

	/**
	 * Holds error messages for individual posts that failed to index (assuming they're available).
	 *
	 * @since 1.7.0
	 */
	private $failed_users_message = array();

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
	 * Add the user mapping without deleting existing indices
	 *
	 * @synopsis [--network-wide]
	 * @subcommand put-user-mapping
	 * @since      1.7
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function put_user_mapping( $args, $assoc_args ) {
		$this->_connect_check();
		$user_type = ep_get_object_type( 'user' );
		if ( ! $this->_is_user_indexing_active( $user_type ) ) {
			return;
		}

		$settings = $user_type->get_settings();
		$name     = $user_type->get_name();
		$mapping  = array( $name => $user_type->get_mappings() );

		if ( isset( $assoc_args['network-wide'] ) && is_multisite() ) {
			if ( ! is_numeric( $assoc_args['network-wide'] ) ) {
				$assoc_args ['network-wide'] = 0;
			}
			$sites = ep_get_sites( $assoc_args['network-wide'] );

			foreach ( $sites as $site ) {
				switch_to_blog( $site['blog_id'] );

				WP_CLI::line( sprintf( __( 'Adding user mapping for site %s...', 'elasticpress' ), $site['blog_id'] ) );

				$index = trim( ep_get_index_name(), '/' );
				$this->_send_user_mapping_to_index( $index, $settings, $name, $mapping );

				restore_current_blog();
			}
		} else {
			WP_CLI::line( __( 'Adding user mapping...', 'elasticpress' ) );

			$this->_send_user_mapping_to_index( trim( ep_get_index_name(), '/' ), $settings, $name, $mapping );
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
	 * @synopsis [--setup] [--network-wide] [--posts-per-page] [--no-bulk] [--offset] [--show-bulk-errors] [--post-type]
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

		// Deactivate our search integration
		$this->deactivate();

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

				$result = $this->_index_posts_helper( $assoc_args );

				$total_indexed += $result['synced'];

				WP_CLI::log( sprintf( __( 'Number of posts indexed on site %d: %d', 'elasticpress' ), $site['blog_id'], $result['synced'] ) );

				if ( ! empty( $result['errors'] ) ) {
					WP_CLI::error( sprintf( __( 'Number of post index errors on site %d: %d', 'elasticpress' ), $site['blog_id'], count( $result['errors'] ) ) );
				}

				if ( ( $user_type = ep_get_object_type( 'user' ) ) && $this->_is_user_indexing_active( $user_type, false ) ) {
					$this->_index_users_helper(
						$assoc_args['posts-per-page'],
						$assoc_args['offset'],
						isset( $assoc_args['no-bulk'] ),
						$user_type,
						isset( $assoc_args['show-bulk-errors'] )
					);
				}

				restore_current_blog();
			}

			WP_CLI::log( __( 'Recreating network alias...', 'elasticpress' ) );

			$this->_create_network_alias();

			WP_CLI::log( sprintf( __( 'Total number of posts indexed: %d', 'elasticpress' ), $total_indexed ) );

		} else {

			WP_CLI::log( __( 'Indexing posts...', 'elasticpress' ) );

			$result = $this->_index_posts_helper( $assoc_args );

			WP_CLI::log( sprintf( __( 'Number of posts indexed on site %d: %d', 'elasticpress' ), get_current_blog_id(), $result['synced'] ) );

			if ( ! empty( $result['errors'] ) ) {
				WP_CLI::error( sprintf( __( 'Number of post index errors on site %d: %d', 'elasticpress' ), get_current_blog_id(), count( $result['errors'] ) ) );
			}

			if ( ( $user_type = ep_get_object_type( 'user' ) ) && $this->_is_user_indexing_active( $user_type, false ) ) {
				$this->_index_users_helper(
					$assoc_args['posts-per-page'],
					$assoc_args['offset'],
					isset( $assoc_args['no-bulk'] ),
					$user_type,
					isset( $assoc_args['show-bulk-errors'] )
				);
			}
		}

		WP_CLI::log( WP_CLI::colorize( '%Y' . __( 'Total time elapsed: ', 'elasticpress' ) . '%N' . timer_stop() ) );

		// Reactivate our search integration
		$this->activate();

		WP_CLI::success( __( 'Done!', 'elasticpress' ) );
	}

	/**
	 * Index all users for a site or network wide
	 *
	 * @subcommand index-users
	 *
	 * @synopsis [--setup] [--network-wide] [--users-per-page] [--no-bulk] [--offset] [--show-bulk-errors]
	 *
	 * @since 1.7.0
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function index_users( $args, $assoc_args ) {
		$this->_connect_check();

		$user_type = ep_get_object_type( 'user' );
		if ( ! $this->_is_user_indexing_active( $user_type ) ) {
			return;
		}

		if ( ! empty( $assoc_args['users-per-page'] ) ) {
			$assoc_args['users-per-page'] = absint( $assoc_args['users-per-page'] );
		} else {
			$assoc_args['users-per-page'] = 350;
		}

		if ( ! empty( $assoc_args['offset'] ) ) {
			$assoc_args['offset'] = absint( $assoc_args['offset'] );
		} else {
			$assoc_args['offset'] = 0;
		}

		$no_bulk          = ! empty( $assoc_args['no-bulk'] );
		$show_bulk_errors = ! empty( $assoc_args['show-bulk-errors'] );
		$sites            = ep_get_sites( $assoc_args['network-wide'] );
		$users_per_page   = max( min( 500, (int) $assoc_args['users-per-page'] ), 1 );
		$offset           = (int) $assoc_args['offset'];

		/**
		 * Prior to the index users command invoking
		 * Useful for deregistering filters/actions that occur during a query request
		 *
		 * @since 1.7.0
		 */
		do_action( 'ep_wp_cli_pre_user_index', $args, $assoc_args );

		// Deactivate our search integration
		$this->deactivate();

		timer_start();

		if ( isset( $assoc_args['setup'] ) && true === $assoc_args['setup'] ) {
			$this->put_user_mapping( $args, $assoc_args );
		}

		if ( ! empty( $assoc_args['network-wide'] ) && is_multisite() ) {
			if ( ! is_numeric( $assoc_args['network-wide'] ) ) {
				$assoc_args['network-wide'] = 0;
			}

			WP_CLI::log( __( 'Indexing users network-wide...', 'elasticpress' ) );

			foreach ( $sites as $site ) {
				switch_to_blog( $site['blog_id'] );

				$this->_index_users_helper( $users_per_page, $offset, $no_bulk, $user_type, $show_bulk_errors );

				restore_current_blog();
			}
		} else {
			WP_CLI::log( __( 'Indexing users...', 'elasticpress' ) );

			$this->_index_users_helper( $users_per_page, $offset, $no_bulk, $user_type, $show_bulk_errors );
		}

		WP_CLI::log( WP_CLI::colorize( '%Y' . __( 'Total time elapsed: ', 'elasticpress' ) . '%N' . timer_stop() ) );

		// Reactivate our search integration
		$this->activate();

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
	private function _index_posts_helper( $args ) {
		global $wpdb, $wp_object_cache;
		$synced = 0;
		$errors = array();

		$no_bulk = false;

		if ( isset( $args['no-bulk'] ) ) {
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

		while ( true ) {

			$args = apply_filters( 'ep_index_posts_args', array(
				'posts_per_page'      => $posts_per_page,
				'post_type'           => $post_type,
				'post_status'         => ep_get_indexable_post_status(),
				'offset'              => $offset,
				'ignore_sticky_posts' => true,
				'orderby'             => array( 'ID' => 'DESC' ),
			) );

			$query = new WP_Query( $args );

			if ( $query->have_posts() ) {

				while ( $query->have_posts() ) {
					$query->the_post();

					if ( $no_bulk ) {
						// index the posts one-by-one. not sure why someone may want to do this.
						$result = ep_sync_post( get_the_ID() );
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
			$wpdb->queries = array();

			if ( is_object( $wp_object_cache ) ) {
				$wp_object_cache->group_ops = array();
				$wp_object_cache->stats = array();
				$wp_object_cache->memcache_debug = array();
				$wp_object_cache->cache = array();

				if ( is_callable( $wp_object_cache, '__remoteset' ) ) {
					call_user_func( array( $wp_object_cache, '__remoteset' ) ); // important
				}
			}
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
	 * @param int             $per_page
	 * @param int             $offset
	 * @param bool            $no_bulk
	 * @param EP_Object_Index $user_type
	 * @param bool            $show_bulk_errors
	 */
	protected function _index_users_helper( $per_page, $offset, $no_bulk, $user_type, $show_bulk_errors ) {
		global $wpdb, $wp_object_cache;
		$site_id     = get_current_blog_id();
		$lookup_args = array(
			'blog_id' => $site_id,
			'orderby' => 'registered',
			'order'   => 'ASC',
			'number'  => $per_page,
		);

		$errors = $success = 0;
		while ( true ) {
			$loop_args           = $lookup_args;
			$loop_args['offset'] = $offset;
			$users_query         = new WP_User_Query( $loop_args );
			$users               = $users_query->get_results();
			if ( empty( $users ) ) {
				break;
			}
			foreach ( $users as $user ) {
				if ( $no_bulk ) {
					$result = $user_type->index_document( $user_type->prepare_object( $user ) );
				} else {
					$result = $this->queue_user( $user->ID, count( $users ), $show_bulk_errors );
				}

				if ( $result ) {
					$success++;
				} else {
					$errors++;
				}
			}

			WP_CLI::log( 'Processed ' . ( count( $users ) + $offset ) . '/' . $users_query->get_total() . ' users...' );

			$offset += $per_page;

			usleep( 500 );

			// Avoid running out of memory
			$wpdb->queries = array();

			if ( is_object( $wp_object_cache ) ) {
				$wp_object_cache->group_ops      = array();
				$wp_object_cache->stats          = array();
				$wp_object_cache->memcache_debug = array();
				$wp_object_cache->cache          = array();

				if ( is_callable( array( $wp_object_cache, '__remoteset' ) ) ) {
					call_user_func( array( $wp_object_cache, '__remoteset' ) ); // important
				}
			}
		}

		if ( ! $no_bulk ) {
			$this->send_bulk_errors();
		}

		WP_CLI::log( sprintf( __( 'Number of users indexed on site %d: %d', 'elasticpress' ), $site_id, $success ) );

		if ( $errors ) {
			WP_CLI::error( sprintf(
				__( 'Number of user index errors on site %d: %d', 'elasticpress' ),
				$site_id,
				$errors
			) );
		}
	}

	/**
	 * Queues up a post for bulk indexing
	 *
	 * @since 0.9.2
	 *
	 * @param $user_id
	 * @param $bulk_trigger
	 * @param bool $show_bulk_errors true to show individual user error messages for bulk errors
	 *
	 * @return bool|int true if successfully synced, false if not or 2 if post was killed before sync
	 */
	private function queue_user( $user_id, $bulk_trigger, $show_bulk_errors = false ) {
		static $user_count = 0;

		$user_args = ep_get_object_type( 'user' )->prepare_object( $user_id );

		// put the post into the queue
		$this->users[ $user_id ][] = '{ "index": { "_id": "' . absint( $user_id ) . '" } }';
		$this->users[ $user_id ][] = addcslashes( json_encode( $user_args ), "\n" );

		// increment the counter
		++$user_count;

		// If we have hit the trigger, initiate the bulk request.
		if ( $user_count === absint( $bulk_trigger ) ) {
			$this->bulk_index_users( $show_bulk_errors );

			// reset the post count
			$user_count        = 0;

			// reset the posts
			$this->users = array();
		}

		return true;
	}

	/**
	 * Perform the bulk user index operation
	 *
	 * @param bool $show_bulk_errors true to show individual user error messages for bulk errors
	 *
	 * @since 0.9.2
	 */
	private function bulk_index_users( $show_bulk_errors = false ) {
		// monitor how many times we attempt to add this particular bulk request
		static $attempts = 0;

		// augment the attempts
		++$attempts;

		// make sure we actually have something to index
		if ( empty( $this->users ) ) {
			WP_CLI::error( 'There are no users to index.' );
		}

		$flatten = array();

		foreach ( $this->users as $user ) {
			$flatten[] = $user[0];
			$flatten[] = $user[1];
		}

		// make sure to add a new line at the end or the request will fail
		$body = rtrim( implode( "\n", $flatten ) ) . "\n";

		// show the content length in bytes if in debug
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			WP_CLI::log( 'Request string length: ' . size_format( mb_strlen( $body, '8bit' ), 2 ) );
		}

		// decode the response
		$response = ep_get_object_type( 'user' )->bulk_index( $body );

		if ( is_wp_error( $response ) ) {
			WP_CLI::error( implode( "\n", $response->get_error_messages() ) );
		}

		// if we did have errors, try to add the documents again
		if ( isset( $response['errors'] ) && $response['errors'] === true ) {
			if ( $attempts < 5 ) {
				foreach ( $response['items'] as $item ) {
					if ( empty( $item['index']['error'] ) ) {
						unset( $this->users[ $item['index']['_id'] ] );
					}
				}
				$this->bulk_index_users( $show_bulk_errors );
			} else {
				foreach ( $response['items'] as $item ) {
					if ( ! empty( $item['index']['_id'] ) ) {
						$this->failed_users[] = $item['index']['_id'];
						if ( $show_bulk_errors ) {
							$this->failed_users_message[ $item['index']['_id'] ] = $item['index']['error'];
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
		if ( ! empty( $this->failed_users ) ) {
			$error_text = __( 'The following users failed to index:', 'elasticpress' ) . PHP_EOL . PHP_EOL;
			foreach ( $this->failed_users as $failed ) {
				$failed_user = get_user_by( 'id', $failed );
				if ( $failed_user ) {
					$error_text .= " {$failed}: {$failed_user->display_name}" . PHP_EOL;
					if ( array_key_exists( $failed, $this->failed_posts_message ) ) {
						$error_text .= "\t" . $this->failed_posts_message[ $failed ] . PHP_EOL;
					}
				}
			}

			WP_CLI::log( $error_text );

			$this->failed_users = $this->failed_users_message = array();
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

		$request = wp_remote_get( trailingslashit( ep_get_host( true ) ) . '_status/?pretty', $request_args );

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
	 * Activate ElasticPress
	 *
	 * @since 0.9.3
	 */
	public function activate() {
		$this->_connect_check();

		$status = ep_is_activated();

		if ( $status ) {
			WP_CLI::warning( 'ElasticPress is already activated.' );
		} else {
			WP_CLI::log( 'ElasticPress is currently deactivated, activating...' );

			$result = ep_activate();

			if ( $result ) {
				WP_CLI::Success( 'ElasticPress was activated!' );
			} else {
				WP_CLI::warning( 'ElasticPress was unable to be activated.' );
			}
		}
	}

	/**
	 * Deactivate ElasticPress
	 *
	 * @since 0.9.3
	 */
	public function deactivate() {
		$this->_connect_check();

		$status = ep_is_activated();

		if ( ! $status ) {
			WP_CLI::warning( 'ElasticPress is already deactivated.' );
		} else {
			WP_CLI::log( 'ElasticPress is currently activated, deactivating...' );

			$result = ep_deactivate();

			if ( $result ) {
				WP_CLI::Success( 'ElasticPress was deactivated!' );
			} else {
				WP_CLI::warning( 'ElasticPress was unable to be deactivated.' );
			}
		}
	}

	/**
	 * Return current status of ElasticPress
	 *
	 * @subcommand is-active
	 *
	 * @since 0.9.3
	 */
	public function is_activated() {
		$this->_connect_check();

		$active = ep_is_activated();

		if ( $active ) {
			WP_CLI::log( 'ElasticPress is currently activated.' );
		} else {
			WP_CLI::log( 'ElasticPress is currently deactivated.' );
		}
	}

	/**
	 * Provide better error messaging for common connection errors
	 *
	 * @since 0.9.3
	 */
	private function _connect_check() {
		if ( ! defined( 'EP_HOST' ) ) {
			WP_CLI::error( __( 'EP_HOST is not defined! Check wp-config.php', 'elasticpress' ) );
		}

		if ( false === ep_elasticsearch_alive() ) {
			WP_CLI::error( __( 'Unable to reach Elasticsearch Server! Check that service is running.', 'elasticpress' ) );
		}
	}

	/**
	 * @param $response
	 *
	 * @return bool
	 */
	protected function is_acknowledged( $response ) {
		return (
			( $body = json_decode( wp_remote_retrieve_body( $response ) ) ) &&
			! empty( $body->acknowledged )
		);
	}

	/**
	 * @param $index
	 * @param $settings
	 * @param $name
	 * @param $mapping
	 */
	protected function _send_user_mapping_to_index( $index, $settings, $name, $mapping ) {
		$closed = ep_remote_request( "$index/_close", array( 'method' => 'POST' ) );
		if ( ! $this->is_acknowledged( $closed ) ) {
			WP_CLI::error( __( 'User mapping failed (invalid closed response)', 'elasticpress' ) );
		}

		$settings_response    = ep_remote_request( "$index/_settings", array(
			'method' => 'PUT',
			'body'   => json_encode( $settings ),
		) );
		$mapping_acknowledged = false;
		if ( $settings_acknowledged = $this->is_acknowledged( $settings_response ) ) {
			$mapping_response     = ep_remote_request( "$index/_mappings/$name", array(
				'method' => 'PUT',
				'body'   => json_encode( $mapping )
			) );
			$mapping_acknowledged = $this->is_acknowledged( $mapping_response );
		}
		ep_remote_request( "$index/_open", array( 'method' => 'POST' ) );

		if ( $settings_acknowledged && $mapping_acknowledged ) {
			WP_CLI::success( __( 'User mapping sent', 'elasticpress' ) );
		} else {
			$sent   = _x( 'sent', 'The status of an operation', 'elasticpress' );
			$failed = _x( 'failed', 'The status of an operation', 'elasticpress' );
			WP_CLI::error( sprintf(
			/* translators: The placeholders will each be either the word 'sent' or 'failed'. The words are translated elsewhere and injected here since either placeholder could be either status. */
				__( 'User mapping failed (settings: %1$s | mapping: %2$s)', 'elasticpress' ),
				$settings_acknowledged ? $sent : $failed,
				$mapping_acknowledged ? $sent : $failed
			) );
		}
	}

	/**
	 * @param EP_Object_Index $user_type
	 * @param bool            $error
	 *
	 * @return bool
	 */
	protected function _is_user_indexing_active( $user_type, $error = true ) {
		if ( ! $user_type || ! method_exists( $user_type, 'active' ) || ! $user_type->active() ) {
			if ( $error ) {
				WP_CLI::error(
					sprintf(
					/* translators: The first placeholder is the word true and the second is the name of a WordPress filter. Because these are programming terms that must be sent in English, they should not be translated. */
						__( 'User indexing is not active! Turn it on by returning %1$s on th %2$s filter', 'elasticpress' ),
						'true',
						'ep_user_indexing_active'
					)
				);
			}
			return false;
		}
		return true;
	}
}
