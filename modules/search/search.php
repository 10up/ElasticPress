<?php
/**
 * ElasticPress search module
 *
 * @since  2.1
 * @package elasticpress
 */

/**
 * Output module box summary
 * 
 * @since 2.1
 */
function ep_search_module_box_summary() {
	?>
	<p><?php esc_html_e( 'Dramatically improve the relevancy of search results and performance of searches. Weight search results by recency.', 'elasticpress' ); ?></p>
	<?php
}

/**
 * Output module box long
 * 
 * @since 2.1
 */
function ep_search_module_box_long() {
	?>
	<p><?php esc_html_e( 'Search is a long neglected piece of WordPress. Result relevancy is poor; performance is poor; there is no handling of misspellings; there is no way to search categories, tags, or custom taxonomies as WordPress by default only searches post content, excerpt, and title.', 'elasticpress' ); ?></p>

	<p>
		<?php esc_html_e( 'The search module allows you to do all these things and more. Just activating the module will make your search experience much better. Your users will be able to more effectively browse your website and find the content they desire. Misspellings will be accounted for, categories searched, and results weighted by recency. If activated in conjunction with the admin module, admin search will be improved as well.', 'elasticpress' ); ?>
	</p>

	<p>
		<?php _e( "This module is a <strong>must have</strong> for all websites which is why it's activated by default.", 'elasticpress' ); ?>
	</p>
	
	<?php
}

/**
 * Setup all module filters
 *
 * @since  2.1
 */
function ep_search_setup() {
	/**
	 * By default EP will not integrate on admin or ajax requests. Since admin-ajax.php is
	 * technically an admin request, there is some weird logic here. If we are doing ajax
	 * and ep_ajax_wp_query_integration is filtered true, then we skip the next admin check.
	 */
	$admin_integration = apply_filters( 'ep_admin_wp_query_integration', false );

	if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
		if ( ! apply_filters( 'ep_ajax_wp_query_integration', false ) ) {
			return;
		} else {
			$admin_integration = true;
		}
	}

	if ( is_admin() && ! $admin_integration ) {
		return;
	}

	add_filter( 'ep_elasticpress_enabled', 'ep_integrate_search_queries', 10, 2 );
	add_filter( 'ep_formatted_args', 'ep_weight_recent', 10, 2 );
	add_filter( 'ep_query_post_type', 'ep_filter_query_post_type_for_search', 10, 2 );
}

/**
 * Make sure we don't search for "any" on a search query
 * 
 * @param  string $post_type
 * @param  WP_Query $query
 * @return string|array
 */
function ep_filter_query_post_type_for_search( $post_type, $query ) {
	if ( 'any' === $post_type && $query->is_search() ) {
		$searchable_post_types = ep_get_searchable_post_types();

		// If we have no searchable post types, there's no point going any further
		if ( empty( $searchable_post_types ) ) {

			// Have to return something or it improperly calculates the found_posts
			return false;
		}

		// Conform the post types array to an acceptable format for ES
		$post_types = array();

		foreach( $searchable_post_types as $type ) {
			$post_types[] = $type;
		}

		// These are now the only post types we will search
		$post_type = $post_types;
	}

	return $post_type;
}

/**
 * Returns searchable post types for the current site
 *
 * @since 1.9
 * @return mixed|void
 */
function ep_get_searchable_post_types() {
	$post_types = get_post_types( array( 'exclude_from_search' => false ) );

	return apply_filters( 'ep_searchable_post_types', $post_types );
}


/**
 * Weight more recent content in searches
 * 
 * @param  array $formatted_args
 * @param  array $args
 * @since  2.1
 * @return array
 */
function ep_weight_recent( $formatted_args, $args ) {
	if ( ! empty( $args['s'] ) ) {
		$date_score = array(
			'function_score' => array(
				'query' => $formatted_args['query'],
				'exp' => array(
					'post_date_gmt' => array(
						'scale' => apply_filters( 'epwr_scale', '4w', $formatted_args, $args ),
						'decay' => apply_filters( 'epwr_decay', .25, $formatted_args, $args ),
						'offset' => apply_filters( 'epwr_offset', '1w', $formatted_args, $args ),
					),
				),
			),
		);

		$formatted_args['query'] = $date_score;
	}

	return $formatted_args;
}

/**
 * Make sure we search all relevant post types
 * 
 * @param  string $post_type
 * @param  WP_Query $query
 * @since  2.1
 * @return bool|string
 */
function ep_use_searchable_post_types_on_any( $post_type, $query ) {
	if ( $query->is_search() && 'any' === $post_type ) {

		/*
		 * This is a search query
		 * To follow WordPress conventions,
		 * make sure we only search 'searchable' post types
		 */
		$searchable_post_types = ep_get_searchable_post_types();

		// If we have no searchable post types, there's no point going any further
		if ( empty( $searchable_post_types ) ) {

			// Have to return something or it improperly calculates the found_posts
			return false;
		}

		// Conform the post types array to an acceptable format for ES
		$post_types = array();

		foreach( $searchable_post_types as $type ) {
			$post_types[] = $type;
		}

		// These are now the only post types we will search
		$post_type = $post_types;
	}

	return $post_type;
}

/**
 * Enable integration on search queries
 * 
 * @param  bool $enabled
 * @param  WP_Query $query
 * @since  2.1
 * @return bool
 */
function ep_integrate_search_queries( $enabled, $query ) {
	if ( method_exists( $query, 'is_search' ) && $query->is_search() ) {
		$enabled = true;
	}

	return $enabled;
}

/**
 * Register the module
 */
ep_register_module( 'search', array(
	'title' => 'Search',
	'setup_cb' => 'ep_search_setup',
	'module_box_summary_cb' => 'ep_search_module_box_summary',
	'module_box_long_cb' => 'ep_search_module_box_long',
	'requires_install_reindex' => false,
) );
