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
				'ids'			  => is_array( $args[ 'more_like' ] ) ? $args[ 'more_like' ] : array( $args[ 'more_like' ] ),
				'fields'		  => apply_filters( 'ep_related_posts_fields', array( 'post_title', 'post_content', 'terms.post_tag.name' ) ),
				'min_term_freq'	  => 1,
				'max_query_terms' => 12,
				'min_doc_freq'	  => 1,
			)
		);
	}
	
	return $formatted_args;
}

/**
 * Search Elasticsearch for related content
 * 
 * @param  int $post_id
 * @param  int $return
 * @since  2.1
 * @return array|bool
 */
function ep_find_related( $post_id, $return = 5 ) {
	$args = array(
		'more_like'		 => $post_id,
		'posts_per_page' => $return,
		'ep_integrate'   => true,
	);

	$query = new WP_Query( apply_filters( 'ep_find_related_args', $args ) );

	if ( ! $query->have_posts() ) {
		return false;
	}
	return $query->posts;
}

/**
 * Setup all module filters
 *
 * @since  2.1
 */
function ep_related_posts_setup() {
	add_action( 'widgets_init', 'ep_related_posts_register_widget' );
	add_filter( 'ep_formatted_args', 'ep_related_posts_formatted_args', 10, 2 );
}

/**
 * Register related posts widget
 *
 * @since  2.2
 */
function ep_related_posts_register_widget() {
	require_once( dirname( __FILE__ ) . '/widget.php' );

	register_widget( 'EP_Related_Posts_Widget' );
}

/**
 * Output module box summary
 * 
 * @since 2.1
 */
function ep_related_posts_module_box_summary() {
	?>
	<p><?php esc_html_e( 'Help users easily find related content with a widget that just works.', 'elasticpress' ); ?></p>
	<?php
}

/**
 * Output module box long
 * 
 * @since 2.1
 */
function ep_related_posts_module_box_long() {
	?>
	<p><?php esc_html_e( 'Showing users related content is a quick way to improve readership and loyalty. There are a number of plugins that show related content, most of which are ineffective and slow.', 'elasticpress' ); ?></p>

	<p><?php esc_html_e( 'ElasticPress has a powerful content matching algorithm that lets it find related content very effectively. This module will create a widget for you to place into any sidebar or widgetized area.', 'elasticpress' ); ?></p>
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

