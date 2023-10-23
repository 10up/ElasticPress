<?php
/**
 * Comments REST API Controller
 *
 * @since 5.0.0
 * @package elasticpress
 */

namespace ElasticPress\REST;

use ElasticPress\Features;
use ElasticPress\Indexables;

/**
 * Comments API controller class.
 *
 * @since 5.0.0
 * @package elasticpress
 */
class Comments {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			'elasticpress/v1',
			'comments',
			[
				'args'                => $this->get_args(),
				'callback'            => [ $this, 'get_comments' ],
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
			]
		);
	}

	/**
	 * Get args schema.
	 *
	 * @return array
	 */
	public function get_args() {
		$post_types = $this->get_searchable_post_types();

		return [
			'post_type' => [
				'default'     => '',
				'description' => __( 'Post type of the posts whose comments to search.', 'elasticpress' ),
				'enum'        => $post_types,
				'required'    => false,
			],
			's'         => [
				'description'       => __( 'Search query.', 'elasticpress' ),
				'required'          => true,
				'type'              => 'string',
				'validate_callback' => fn( $param ) => ! empty( $param ),
			],
		];
	}

	/**
	 * Get comments.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return array
	 */
	public function get_comments( \WP_REST_Request $request ) {
		$search = $request->get_param( 's' );

		$post_type_filter      = explode( ',', $request->get_param( 'post_type' ) );
		$searchable_post_types = $this->get_searchable_post_types();

		if ( ! empty( $post_type_filter ) && is_array( $searchable_post_types ) ) {
			$post_type_filter = array_intersect( $post_type_filter, $searchable_post_types );
		}

		$default_args = [
			'status'      => 'approve',
			'search'      => $search,
			'type'        => Indexables::factory()->get( 'comment' )->get_indexable_comment_types(),
			'post_type'   => empty( $post_type_filter ) ? $searchable_post_types : $post_type_filter,
			'post_status' => 'publish',
			'number'      => 5,
		];

		/**
		 * Filter to args used in WP_Comment_Query in Widget Search Comment
		 *
		 * @hook ep_comment_search_widget_args
		 * @since 3.6.0
		 * @param  {array} $default_args Defaults args
		 * @return {array} New value
		 */
		$args = apply_filters( 'ep_comment_search_widget_args', $default_args );

		/**
		 * Fires before the comment query is executed.
		 *
		 * @hook ep_comment_pre_search_widget
		 * @since 3.6.0
		 * @param {array}           $args Args passed to `WP_Comment_Query`.
		 * @param {WP_REST_Request} $request Rest request.
		 */
		do_action( 'ep_comment_pre_search_widget', $args, $request );

		$comment_query = new \WP_Comment_Query( $args );

		/**
		 * Fires after the comment query is executed.
		 *
		 * @hook ep_comment_after_search_widget
		 * @since 3.6.0
		 * @param {WP_Comment_Query} $comment_query WP_Comment_Query object.
		 * @param {WP_REST_Request}  $request Rest request.
		 */
		do_action( 'ep_comment_after_search_widget', $comment_query, $request );

		$return = [];
		foreach ( $comment_query->comments as $comment ) {
			$return[ $comment->comment_ID ] = [
				'id'      => $comment->comment_ID,
				'content' => $comment->comment_content,
				'link'    => get_comment_link( $comment ),
			];
		}

		/**
		 * Filters the comments response
		 *
		 * @hook ep_comment_search_widget_response
		 * @since 3.6.0
		 * @param  {array} $return The result of fetched comments.
		 * @return {array} New value
		 */
		return apply_filters( 'ep_comment_search_widget_response', $return );
	}

	/**
	 * Get searchable post types.
	 *
	 * @return array
	 */
	protected function get_searchable_post_types() {
		$post_types = Features::factory()->get_registered_feature( 'search' )->get_searchable_post_types();
		$post_types = array_filter( $post_types, fn( $post_type ) => post_type_supports( $post_type, 'comments' ) );

		return $post_types;
	}
}
