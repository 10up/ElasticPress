<?php
/**
 * Plugin Name: Temporarily Disable Autosuggest
 * Description: Temporarily disable the Autosuggest feature for test purposes.
 * Version:     1.0.0
 * Author:      10up Inc.
 * License:     GPLv2 or later
 *
 * @package ElasticPress_Tests_E2e
 */

namespace ElasticPress\Tests\E2E;

add_filter(
	'ep_feature_requirements_status_code',
	function ( $code, $status ) {
		if ( $status->get_feature()->slug === 'autosuggest' ) {
			return 3;
		}
		return $code;
	},
	10,
	2
);
