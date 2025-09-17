<?php
/**
 * Plugin Name: Fix Autosuggest Localhost
 * Description: The request made by epio_send_autosuggest_public_request points to "localhost". Unfortunately, for internal requests that doesn't work. This plugin overrides that value when needed.
 * Version:     1.0.0
 * Author:      10up Inc.
 * License:     GPLv2 or later
 *
 * @package ElasticPress_Tests_E2e
 */

$modify_home_url = function ( $url ) {
	if ( ! defined( 'REAL_LOCALHOST' ) ) {
		return $url;
	}

	return str_replace( 'localhost', REAL_LOCALHOST, $url );
};
add_filter( 'home_url', $modify_home_url );
