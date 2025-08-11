<?php
/**
 * Plugin Name: Add Meta Fields
 * Description: Adds meta fields to the weighting configuration
 * Author:      10up Inc.
 * License:     GPLv2 or later
 *
 * @package ElasticPress_Tests_E2e
 */

add_filter(
	'ep_prepare_meta_allowed_protected_keys',
	function ( $fields ) {
		$fields[] = 'meta_field_1';
		$fields[] = 'meta_field_2';
		$fields[] = 'numeric_meta_field';
		$fields[] = 'non_numeric_meta_field';
		return $fields;
	}
);
