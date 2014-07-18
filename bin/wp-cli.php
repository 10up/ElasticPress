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
		WP_CLI::line( "Adding mapping..." );

		// @todo add command to support which site to flush
		$site_id = null;

		$result = ep_put_mapping( $site_id );

		// @todo Add check to confirm flushing worked
		WP_CLI::success( 'Mapping Sent' );
	}

	public function flush() {
		WP_CLI::line( "Flushing Index..." );

		// @todo add command to support which site to flush
		$site_id = null;

		$result = ep_flush( $site_id );

		// @todo Add check to confirm flushing worked
		WP_CLI::success( 'Flushed' );
	}
}