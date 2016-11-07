<?php
/**
 * ElasticPress admin module
 *
 * @since  2.1
 * @package elasticpress
 */


/**
 * Setup all module filters
 *
 * @since  2.1
 */
function ep_user_setup() {
	add_filter( 'ep_user_indexing_active', '__return_true' );
}


/**
 * Output module box summary
 *
 * @since 2.1
 */
function ep_user_module_box_summary() {
	?>
	<p><?php esc_html_e( 'Index user data', 'elasticpress' ); ?></p>
	<?php
}


/**
 * Output module box long
 *
 * @since 2.1
 */
function ep_user_module_box_long() {
	?>
	<p><?php _e( '', 'elasticpress' ); ?></p>
	<?php
}


/**
 * Register the module
 */
ep_register_module( 'user', array(
	'title' => 'User',
	'setup_cb' => 'ep_user_setup',
	'module_box_summary_cb' => 'ep_user_module_box_summary',
	'module_box_long_cb' => 'ep_user_module_box_long',
	'requires_install_reindex' => false,
) );
