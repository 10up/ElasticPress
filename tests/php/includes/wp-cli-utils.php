<?php

// Utilities that do NOT depend on WordPress code.

namespace WP_CLI\Utils;

/**
 * Return the flag value or, if it's not set, the $default value.
 *
 * Because flags can be negated (e.g. --no-quiet to negate --quiet), this
 * function provides a safer alternative to using
 * `isset( $assoc_args['quiet'] )` or similar.
 *
 * @access public
 * @category Input
 *
 * @param array  $assoc_args  Arguments array.
 * @param string $flag        Flag to get the value.
 * @param mixed  $default     Default value for the flag. Default: NULL
 * @return mixed
 */
function get_flag_value( $assoc_args, $flag, $default = null ) {
	return isset( $assoc_args[ $flag ] ) ? $assoc_args[ $flag ] : $default;
}
