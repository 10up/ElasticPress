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

	public function flush() {
		WP_CLI::line( "Flushing index" );

		// @todo add command to support which site to flush
		$site_id = null;

		$result = ep_flush( $site_id );

		if ( $result ) {
			WP_CLI::success( 'Index flushed' );
		} else {
			WP_CLI::warning( 'Flush failed' );
		}
	}

	public function index() {
		WP_CLI::line( "Indexing" );

		// @todo add command to support which site to index
		$site_id = null;

//		$result = ep_index
	}
}