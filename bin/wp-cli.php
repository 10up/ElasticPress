<?php

WP_CLI::add_command( 'elasticpress', 'ElasticPress_CLI_Command' );

/**
 * CLI Commands for ElasticPress
 *
 * @todo add sync command
 */
class ElasticPress_CLI_Command extends WP_CLI_Command {

	/**
	 * Add the document mapping
	 *
	 * @subcommand put-mapping
	 */
	public function put_mapping() {
		WP_CLI::line( "Adding mapping" );

		// @todo add command to support which site to map
		$site_id = null;

		// Flushes index first
		$this->flush();

		$result = ep_put_mapping( $site_id );

		if ( $result ) {
			WP_CLI::success( 'Mapping sent' );
		} else {
			WP_CLI::error( 'Mapping failed' );
		}
	}

	/**
	 * Flush the current index. !!Warning!! This empties your elasticsearch index for the entire site.
	 *
	 * @todo replace this function with one that updates all rows with a --force option
	 */
	public function flush() {
		WP_CLI::line( "Flushing index..." );

		// @todo add command to support which site to flush
		$site_id = null;

		$result = ep_flush( $site_id );

		if ( $result ) {
			WP_CLI::success( 'Index flushed' );
		} else {
			WP_CLI::error( 'Flush failed' );
		}
	}

	/**
	 * Index the current site or individual posts in Elasticsearch, optionally flushing any existing data and adding the document mapping.
	 *
	 * ## OPTIONS
	 *
	 * [--flush]
	 * : Flushes out the current data
	 *
	 * [--put-mapping]
	 * : Adds the document mapping in EWP_Config()
	 *
	 * [--bulk=<num>]
	 * : Process this many posts as a time. Defaults to 2,000, which seems to
	 * be the fastest on average.
	 *
	 * [--limit=<num>]
	 * : How many posts to process. Defaults to all posts.
	 *
	 * [--page=<num>]
	 * : Which page to start on. This is helpful if you encountered an error on
	 * page 145/150 or if you want to have multiple processes running at once
	 *
	 * [<post-id>]
	 * : By default, this subcommand will query posts based on ID and pagination.
	 * Instead, you can specify one or more individual post IDs to process. Multiple
	 * post IDs should be space-delimited (see examples)
	 * If present, the --bulk, --limit, and --page arguments are ignored.
	 *
	 * ## EXAMPLES
	 *
	 *      # Flush the current document index, add the mapping, and index the whole site
	 *      wp elasticsearch index --flush --put-mapping
	 *
	 *      # Index the first 10 posts in the database
	 *      wp elasticsearch index --bulk=10 --limit=10
	 *
	 *      # Index the whole site starting on page 145
	 *      wp elasticsearch index --page=145
	 *
	 *      # Index a single post (post ID 12345)
	 *      wp elasticsearch index 12345
	 *
	 *      # Index six specific posts
	 *      wp elasticsearch index 12340 12341 12342 12343 12344 12345
	 *
	 *
	 * @synopsis [--flush] [--put-mapping] [--bulk=<num>] [--limit=<num>] [--page=<num>] [<post-id>]
	 */
	public function index( $args, $assoc_args ) {
		$timestamp_start = microtime( true );

		// @todo add command to support which site to index
		$site_id = null;

		if ( $assoc_args['flush'] ) {
			$this->flush();
		}

		if ( $assoc_args['put-mapping'] ) {
			$this->put_mapping();
		}

		if ( ! empty( $args ) ) {
			# Individual post indexing
			$num_posts = count( $args );
			WP_CLI::line( sprintf( _n( "Indexing %d post", "Indexing %d posts", $num_posts ), $num_posts ) );

			foreach ( $args as $post_id ) {
				$post_id = intval( $post_id );
				if ( ! $post_id )
					continue;

				WP_CLI::line( "Indexing post {$post_id}" );
				ep_index_post( $post_id, $site_id );
			}
			WP_CLI::success( "Index complete!" );

		} else {
			# Bulk indexing

			$assoc_args = array_merge( array(
				'bulk'  => 500,
				'limit' => 0,
				'page'  => 1
			), $assoc_args );

			if ( $assoc_args['limit'] && $assoc_args['limit'] < $assoc_args['bulk'] ) {
				$assoc_args['bulk'] = $assoc_args['limit'];
			}

			// @todo implement a custom config limit setting, requires building a method to count how many posts we need to index
//			$limit_number = $assoc_args['limit'] > 0 ? $assoc_args['limit'] : EWP_Sync_Manager()->count_posts();
//			$limit_text = sprintf( _n( '%s post', '%s posts', $limit_number ), number_format( $limit_number ) );
//			\WP_CLI::line( "Indexing {$limit_text}, " . number_format( $assoc_args['bulk'] ) . " at a time, starting on page {$assoc_args['page']}" );

			// @todo implement tracking of current index count, and how many left to go
//			# Keep tabs on where we are and what we've done
//			$sync_meta = EWP_Sync_Meta();
//			$sync_meta->page = intval( $assoc_args['page'] ) - 1;
//			$sync_meta->bulk = $assoc_args['bulk'];
//			$sync_meta->limit = $assoc_args['limit'];
//			$sync_meta->running = true;
//
//			$total_pages = $limit_number / $sync_meta->bulk;
//			$total_pages_ceil = ceil( $total_pages );
//			$start_page = $sync_meta->page;

			do {
				$lap = microtime( true );

				// @todo need to convert the EWP way of indexing posts
				EWP_Sync_Manager()->do_index_loop();

				$seconds_per_page = ( microtime( true ) - $timestamp_start ) / ( $sync_meta->page - $start_page + 1 );
				WP_CLI::line( "Completed page {$sync_meta->page}/{$total_pages_ceil} (" . number_format( ( microtime( true ) - $lap), 2 ) . 's / ' . round( memory_get_usage() / 1024 / 1024, 2 ) . 'M current / ' . round( memory_get_peak_usage() / 1024 / 1024, 2 ) . 'M max), ' . $this->time_format( ( $total_pages - $sync_meta->page ) * $seconds_per_page ) . ' remaining' );

				$this->contain_memory_leaks();

				if ( $assoc_args['limit'] > 0 && $sync_meta->processed >= $assoc_args['limit'] ) {
					break;
				}
			} while ( $sync_meta->page < $total_pages_ceil );


			\WP_CLI::success( "Index complete!\n{$sync_meta->processed}\tposts processed\n{$sync_meta->success}\tposts added\n{$sync_meta->error}\tposts skipped" );

			$this->activate();
		}

		$this->finish( $timestamp_start );
	}
}