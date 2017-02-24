<?php
/**
 * ElasticPress Protected Content feature
 *
 * @since  2.2
 * @package elasticpress
 */


/**
 * Setup all feature filters
 *
 * @since  2.1
 */
function ep_pc_setup() {
	add_filter( 'ep_indexable_post_status', 'ep_pc_get_statuses' );
	add_filter( 'ep_indexable_post_types', 'ep_pc_post_types', 10, 1 );

	if ( is_admin() ) {
		add_filter( 'ep_admin_wp_query_integration', '__return_true' );
		add_action( 'pre_get_posts', 'ep_pc_integrate' );
	}
}

/**
 * Index all post types
 *
 * @param   array $post_types Existing post types.
 * @since   2.2
 * @return  array
 */
function ep_pc_post_types( $post_types ) {
	$all_post_types = get_post_types();

	// We don't want to deal with nav menus
	unset( $all_post_types['nav_menu_item'] );

	return array_unique( array_merge( $post_types, $all_post_types ) );
}

/**
 * Integrate EP into proper queries
 * 
 * @param  WP_Query $query
 * @since  2.1
 */
function ep_pc_integrate( $query ) {

	// Lets make sure this doesn't interfere with the CLI
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		return;
	}

	if ( ! $query->is_main_query() ) {
		return;
	}

	/**
	 * We limit to these post types to not conflict with other features like WooCommerce
	 *
	 * @since  2.1
	 * @var array
	 */
	$post_types = array(
		'post' => 'post',
	);

	// Backwards compat
	$supported_post_types = apply_filters( 'ep_admin_supported_post_types', $post_types );

	$supported_post_types = apply_filters( 'ep_pc_supported_post_types', $supported_post_types );

	$post_type = $query->get( 'post_type' );

	if ( empty( $post_type ) ) {
		$post_type = 'post';
	}

	if ( is_array( $post_type ) ) {
		foreach ( $post_type as $pt ) {
			if ( empty( $supported_post_types[$pt] ) ) {
				return;
			}
		}

		$query->set( 'ep_integrate', true );
	} else {
		if ( ! empty( $supported_post_types[$post_type] ) ) {
			$query->set( 'ep_integrate', true );
		}
	}
}

/**
 * Output feature box summary
 * 
 * @since 2.1
 */
function ep_pc_feature_box_summary() {
	?>
	<p><?php esc_html_e( 'Power your admin with ElasticPress. Please note this syncs unpublished content to Elasticsearch. You want to make sure your Elasticsearch instance is properly secured.', 'elasticpress' ); ?></p>
	<?php
}

/**
 * Output feature box long
 * 
 * @since 2.1
 */
function ep_pc_feature_box_long() {
	?>
	<p><?php _e( 'This feature compliments other features turning on all the ElasticPress goodness in the admin.', 'elasticpress' ); ?></p>

	<p><?php _e( 'The Protected Content feature also will speed up your post table queries. After activating an SEO plugin, increasing post per pages, and making other modifications, that table view loads very slowly. No longer will you have to wait 60 seconds to do things that should be easy such as viewing 200 posts at once.', 'elasticpress' ); ?></p>

	<?php
}

/**
 * Fetches all post statuses we need to index
 *
 * @since  2.1
 * @param  array $statuses
 * @return array
 */
function ep_pc_get_statuses( $statuses ) {
	$post_statuses = get_post_stati();

	unset( $post_statuses['auto-draft'] );

	return array_unique( array_merge( $statuses, array_values( $post_statuses ) ) );
}

/**
 * Determine WC feature reqs status
 *
 * @param  EP_Feature_Requirements_Status $status
 * @since  2.2
 * @return EP_Feature_Requirements_Status
 */
function ep_pc_requirements_status( $status ) {
	$host = ep_get_host();

	if ( ! preg_match( '#elasticpress\.io#i', $host ) ) {
		$status->code = 1;
		$status->message = __( "You aren't using <a href='https://elasticpress.io'>ElasticPress.io</a> so we can't be sure your Elasticsearch instance is secure.", 'elasticpress' );
	}

	return $status;
}

/**
 * Register the feature
 */
ep_register_feature( 'protected_content', array(
	'title' => esc_html__( 'Protected Content', 'elasticpress' ),
	'setup_cb' => 'ep_pc_setup',
	'requirements_status_cb' => 'ep_pc_requirements_status',
	'feature_box_summary_cb' => 'ep_pc_feature_box_summary',
	'feature_box_long_cb' => 'ep_pc_feature_box_long',
	'requires_install_reindex' => true,
) );

