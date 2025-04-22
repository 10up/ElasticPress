<?php
/**
 * WP_CLI Mock
 *
 * phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
 * phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped
 *
 * @package elasticpress
 */

/**
 * Various utilities for WP-CLI commands.
 */
class WP_CLI {

	/**
	 * Display a log message
	 *
	 * @param string $message Message to display to the end user.
	 */
	public static function log( $message ) {
		print $message;
	}

	/**
	 * Display a success message.
	 *
	 * @param string $message Message to display to the end user.
	 */
	public static function success( $message ) {
		print $message;
	}

	/**
	 * Display debug message.
	 *
	 * @param string $message Message to display to the end user.
	 */
	public static function debug( $message ) {
		print $message;
	}

	/**
	 * Display warning message
	 *
	 * @param string $message Message to display to the end user.
	 */
	public static function warning( $message ) {
		print $message;
	}

	/**
	 * Display error message.
	 *
	 * @param string  $message Message to display to the end user.
	 * @param boolean $should_exit    Whether exit or not.
	 * @return void
	 * @throws Exception Exception
	 */
	public static function error( $message, $should_exit = true ) {

		if ( ! $should_exit ) {
			print $message;
			return;
		}

		throw new Exception( $message );
	}

	/**
	 * Colorize a string for output.
	 *
	 * @param string $message Message to display to the end user.
	 */
	public static function colorize( $message ) {
		print $message;
	}

	/**
	 * Display informational message without prefix.
	 *
	 * @param string $message Message to display to the end user.
	 */
	public static function line( $message ) {
		print $message . "\n";
	}

	/**
	 * Ask for confirmation before running a destructive operation.
	 *
	 * @param string $message Question to display before the prompt.
	 * @param array  $args Skips prompt if 'yes' is provided.
	 * @return void
	 * @throws Exception Exception
	 */
	public static function confirm( $message, $args = [] ) {

		if ( isset( $args['yes'] ) && true === $args['yes'] ) {
			return;
		}

		throw new Exception( $message );
	}

	/**
	 * Halt script execution
	 *
	 * @param integer $return_code Exit code to return.
	 */
	public static function halt( $return_code ) {
	}

	/**
	 * Return mocked runner object.
	 *
	 * @return object
	 */
	public static function get_runner() {
		$runner             = new stdClass();
		$runner->arguments  = [];
		$runner->assoc_args = [];
		return $runner;
	}
}
