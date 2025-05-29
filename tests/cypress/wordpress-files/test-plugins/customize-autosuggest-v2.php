<?php
/**
 * Plugin Name: Customize Autosuggest V2
 * Version:     1.0.0
 * Author:      10up Inc.
 * License:     GPLv2 or later
 *
 * @package ElasticPress_Tests_E2e
 */

/**
 * Enqueue Assets
 */
function mytheme_enqueue_assets() {
	wp_enqueue_script(
		'mytheme-scripts',
		plugin_dir_url( __FILE__ ) . 'customize-autosuggest-v2.js',
		array( 'wp-element', 'wp-hooks' ),
		'1.0.0',
		true
	);

	global $wp_scripts;
	if ( ! isset( $wp_scripts->registered['wp-hooks']->extra['data'] ) ) {
			wp_add_inline_script(
				'wp-hooks',
				'window.wp = window.wp || {}; window.wp.hooks = window.wp.hooks || {};',
				'before'
			);
	}
}
add_action( 'wp_enqueue_scripts', 'mytheme_enqueue_assets' );
