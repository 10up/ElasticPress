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
	$asset_file = include get_stylesheet_directory() . '/build/index.asset.php';

	// Add wp-hooks to dependencies
	$dependencies = array_merge( $asset_file['dependencies'], array( 'wp-hooks' ) );

	wp_enqueue_script(
		'mytheme-scripts',
		get_stylesheet_directory_uri() . '/build/index.js',
		$dependencies,
		$asset_file['version'],
		true
	);

	if ( file_exists( get_stylesheet_directory() . '/build/style-index.css' ) ) {
		wp_enqueue_style(
			'mytheme-styles',
			get_stylesheet_directory_uri() . '/build/style-index.css',
			array(),
			$asset_file['version']
		);
	}

	// Ensure wp.hooks is properly initialized
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
