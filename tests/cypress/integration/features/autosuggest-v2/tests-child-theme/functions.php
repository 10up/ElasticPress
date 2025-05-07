<?php
/**
 * Functions.php
 *
 * @package childtheme
 */

/**
 * Enqueue scripts and styles built with @wordpress/scripts.
 */
function mytheme_enqueue_assets() {
	wp_enqueue_script(
		'mytheme-scripts',
		'/wp-content/plugins/autosuggest-v2-test-hooks/autosuggest-v2-test-hooks.js',
		array( 'wp-element', 'wp-hooks' ),
		null,
		true
	);

	// Ensure wp.hooks is properly initialized
	global $wp_scripts;
	if ( ! isset( $wp_scripts->registered['wp-hooks']->extra['data'] ) ) {
		wp_add_inline_script(
			'wp-hooks',
			'window.wp = window.wp || {}; window.wp.hooks = window.wp.hooks || {};',
			'before'
		);
	}

	// Map JSX runtime functions correctly
	wp_add_inline_script(
		'mytheme-scripts',
		'window.ReactJSXRuntime = {
            jsx: wp.element.createElement,
            jsxs: wp.element.createElement,
            Fragment: wp.element.Fragment
        };',
		'before'
	);
}
add_action( 'wp_enqueue_scripts', 'mytheme_enqueue_assets' );
