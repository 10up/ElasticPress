<?php
/**
 * ElasticPress search feature
 *
 * @since  2.1
 * @package elasticpress
 */

/**
 * Output feature box summary
 * 
 * @since 2.1
 */
function ep_search_feature_box_summary() {
	?>
	<p><?php esc_html_e( 'Instantly find the content you’re looking for. The first time.', 'elasticpress' ); ?></p>
	<?php
}

/**
 * Output feature box long
 * 
 * @since 2.1
 */
function ep_search_feature_box_long() {
	?>
	<p><?php esc_html_e( 'Overcome higher-end performance and functional limits posed by the traditional WordPress structured (SQL) database to deliver superior keyword search, instantly. ElasticPress indexes custom fields, tags, and other metadata to improve search results. Fuzzy matching accounts for misspellings and verb tenses.', 'elasticpress' ); ?></p>
	
	<?php
}

/**
 * We need to delay search setup up since it will fire after protected content and protected
 * content filters into the search setup
 * 
 * @since 2.2
 */
function ep_delay_search_setup() {
	add_action( 'init', 'ep_search_setup' );
}

/**
 * Setup all feature filters
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
	add_action( 'pre_get_posts', 'ep_improve_default_search', 10, 1 );
}

/**
 * By default search authors, taxonomies, and post stuff
 *
 * @since  2.1
 * @param  WP_Query $query
 */
function ep_improve_default_search( $query ) {
	if ( is_admin() ) {
		return;
	}

	/**
	 * Make sure this is an ElasticPress search query
	 */
	if ( ! ep_elasticpress_enabled( $query ) || ! $query->is_search() ) {
		return;
	}
	
	$search_fields = $query->get( 'search_fields' );
	
	// Set search fields if they are not set
	if( empty( $search_fields ) ) {
		$query->set( 'search_fields', array(
			'post_title',
			'post_content',
			'post_excerpt',
			'author_name',
			'taxonomies' => array(
				'post_tag',
				'category',
			),
		) );
	}
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
						'scale' => apply_filters( 'epwr_scale', '14d', $formatted_args, $args ),
						'decay' => apply_filters( 'epwr_decay', .25, $formatted_args, $args ),
						'offset' => apply_filters( 'epwr_offset', '7d', $formatted_args, $args ),
					),
				),
				'score_mode' => 'avg',
				'boost_mode' => 'sum'
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
	if ( isset( $query->query_vars['ep_integrate'] ) && false === $query->query_vars['ep_integrate'] ) {
		$enabled = false;
	} else if ( method_exists( $query, 'is_search' ) && $query->is_search() && ! empty( $query->query_vars['s'] ) ) {
		$enabled = true;

		$fields = $query->get( 'fields' );

		if ( ! empty( $fields ) ) {
			$enabled = false;
		}
	}

	return $enabled;
}

/**
 * Register the feature
 */
ep_register_feature( 'search', array(
	'title' => 'Search',
	'setup_cb' => 'ep_delay_search_setup',
	'feature_box_summary_cb' => 'ep_search_feature_box_summary',
	'feature_box_long_cb' => 'ep_search_feature_box_long',
	'requires_install_reindex' => false,
) );
