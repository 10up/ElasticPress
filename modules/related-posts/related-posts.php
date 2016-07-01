<?php
/**
 * ElasticPress related posts module
 *
 * @since  2.1
 * @package elasticpress
 */

function ep_related_posts_formatted_args( $formatted_args, $args ) {
	if ( ! empty( $args[ 'more_like' ] ) ) {
		$formatted_args[ 'query' ] = array(
			'more_like_this' => array(
				'ids'				 => is_array( $args[ 'more_like' ] ) ? $args[ 'more_like' ] : array( $args[ 'more_like' ] ),
				'fields'			 => apply_filters( 'ep_related_posts_fields', array( 'post_title', 'post_content', 'terms.post_tag.name' ) ),
				'min_term_freq'		 => 1,
				'max_query_terms'	 => 12,
				'min_doc_freq'		 => 1,
			)
		);
	}
	
	return $formatted_args;
}

/**
 * Add related posts HTML to the content
 * 
 * @param  string $content
 * @since  2.1
 * @return string
 */
function ep_related_posts_filter_content( $content ) {
	if ( is_search() || is_home() || is_archive() || is_category() ) {
		return $content;
	}
	$post_id		 = get_the_ID();
	$cache_key		 = md5( 'related_posts_' . $post_id );
	$related_posts	 = wp_cache_get( $cache_key, 'ep-related-posts' );

	if ( false === $related_posts ) {
		$related_posts = ep_find_related( $post_id );
		wp_cache_set( $cache_key, $related_posts, 'ep-related-posts', 300 );
	}

	$html = ep_related_get_html( $related_posts )
	;
	return $content . "\n" . $html;
}

/**
 * Search Elasticsearch for related content
 * 
 * @param  int $post_id
 * @param  int $return
 * @since  2.1
 * @return array|bool
 */
function ep_find_related( $post_id, $return = 4 ) {
	$args = array(
		'more_like'		 => $post_id,
		'posts_per_page' => $return,
		's'				 => ''
	);

	$query = new WP_Query( $args );

	if ( ! $query->have_posts() ) {
		return false;
	}
	return $query->posts;
}

/**
 * Generate related posts html
 * 
 * @param  array $posts
 * @since  2.1
 * @return string
 */
function ep_related_get_html( $posts ) {
	if ( false === $posts ) {
		return '';
	}

	$html = '<h3>' . esc_html__( 'Related Posts', 'elasticpress' ) . '</h3>';
	$html .= '<ul>';

	foreach ( $posts as $post ) {
		$html.=sprintf(
		'<li><a href="%s">%s</a></li>', esc_url( get_permalink( $post->ID ) ), esc_html( $post->post_title )
		);
	}

	$html .= '</ul>';

	do_action( 'ep_related_html_attached', $posts );

	/**
	 * Filter the display HTML for related posts.
	 * 
	 * If developers want to customize the returned HTML for related posts or
	 * write their own HTML, they have the power to do so.
	 * 
	 * @param string $html Default Generated HTML 
	 * @param array $posts Array of WP_Post objects.
	 */
	return apply_filters( 'ep_related_html', $html, $posts );
}

/**
 * Setup all module filters
 *
 * @since  2.1
 */
function ep_related_posts_setup() {
	add_filter( 'ep_formatted_args', 'ep_related_posts_formatted_args', 10, 2 );
	add_filter( 'the_content', 'ep_related_posts_filter_content' );
}

/**
 * Output module box summary
 * 
 * @since 2.1
 */
function ep_related_posts_module_box_summary() {
	?>
	<p><?php esc_html_e( 'Show related content below each post. Related content is queried performantly and effectively.', 'elasticpress' ); ?></p>
	<?php
}

/**
 * Output module box long
 * 
 * @since 2.1
 */
function ep_related_posts_module_box_long() {
	?>
	<p><?php esc_html_e( 'Showing users related content is a quick way to improve readership and loyalty. There a number of plugins that show related content, most of which are ineffective and slow.', 'elasticpress' ); ?></p>

	<p><?php esc_html_e( 'ElasticPress has a powerful content matching algorithm that lets it find related content very effectively. This module will show three related posts after the post content.', 'elasticpress' ); ?></p>
	<?php
}

/**
 * Register the module
 */
ep_register_module( 'related_posts', array(
	'title' => 'Related Posts',
	'setup_cb' => 'ep_related_posts_setup',
	'module_box_summary_cb' => 'ep_related_posts_module_box_summary',
	'module_box_long_cb' => 'ep_related_posts_module_box_long',
	'requires_install_reindex' => false,
) );

