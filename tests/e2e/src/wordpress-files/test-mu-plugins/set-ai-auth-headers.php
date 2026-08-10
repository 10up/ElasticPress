<?php
/**
 * Plugin Name: Set AI Auth Headers
 * Description: Set the AI Auth Headers for the AI-Related features
 * Version:     1.0.0
 * Author:      10up Inc.
 * License:     GPLv2 or later
 *
 * @package ElasticPress_Tests_E2e
 */

/**
 * Add the Litellm headers to the AI-Related requests
 *
 * @param array $options Current options
 * @return array
 */
function add_litellm_headers( $options ) {
	if ( empty( $options['headers'] ) ) {
		$options['headers'] = [];
	}

	$options['headers']['CF-Access-Client-Id']     = defined( 'CF_ACCESS_CLIENT_ID' ) ? CF_ACCESS_CLIENT_ID : '';
	$options['headers']['CF-Access-Client-Secret'] = defined( 'CF_ACCESS_CLIENT_SECRET' ) ? CF_ACCESS_CLIENT_SECRET : '';

	return $options;
}
add_filter( 'ep_embeddings_options', __NAMESPACE__ . '\add_litellm_headers' );
add_filter( 'ep_ai_search_summary_request_options', __NAMESPACE__ . '\add_litellm_headers' );
