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

		ep_full_sync();
	}
}