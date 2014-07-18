<?php

WP_CLI::add_command( 'elasticpress', 'ElasticPress_CLI_Command' );

/**
 * CLI Commands for ElasticPress
 */
class ElasticPress_CLI_Command extends WP_CLI_Command {

	/**
	 * Add the document mapping
	 *
	 * @subcommand put-mapping
	 */
	public function put_mapping() {
		WP_CLI::line( "Adding mapping..." );
		$result = EWP_Config()->create_mapping();
		if ( '200' == EWP_API()->last_request['response_code'] ) {
			WP_CLI::success( "Successfully added mapping\n" );
		} else {
			print_r( EWP_API()->last_request );
			print_r( $result );
			WP_CLI::error( "Could not add post mapping!" );
		}
	}
}