<?php
/**
 * Related Posts REST API Controller
 *
 * @since 5.0.0
 * @package elasticpress
 */

namespace ElasticPress\REST;

use ElasticPress\Features;

/**
 * Related Posts API controller class.
 *
 * @since 5.0.0
 * @package elasticpress
 */
class RelatedPosts {

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			'wp/v2',
			'/posts/(?P<id>[0-9]+)/related',
			[
				'args'                => $this->get_args_schema(),
				'callback'            => [ $this, 'get_items' ],
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
	public function get_args_schema() {
		return [
			'id'     => [
				'description' => __( 'Post ID.', 'elasticpress' ),
				'required'    => true,
				'type'        => 'integer',
			],
			'number' => [
				'default'     => 5,
				'description' => __( 'Number of posts', 'elasticpress' ),
				'required'    => false,
				'type'        => 'integer',
			],
		];
	}

	/**
	 * Get comments,
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return array Comments.
	 */
	public function get_items( \WP_REST_Request $request ) {
		$id     = $request->get_param( 'id' );
		$number = $request->get_param( 'number' );
		$posts  = Features::factory()->get_registered_feature( 'related_posts' )->find_related( $id, $number );

		$prepared_posts = [];

		if ( ! empty( $posts ) ) {
			foreach ( $posts as $post ) {
				$prepared_post = [];

				$prepared_post['id']           = $post->ID;
				$prepared_post['link']         = get_permalink( $post->ID );
				$prepared_post['status']       = $post->post_status;
				$prepared_post['title']        = [
					'raw'      => $post->post_title,
					'rendered' => get_the_title( $post->ID ),
				];
				$prepared_post['author']       = (int) $post->post_author;
				$prepared_post['parent']       = (int) $post->post_parent;
				$prepared_post['menu_order']   = (int) $post->menu_order;
				$prepared_post['content']      = [
					'rendered' => post_password_required( $post ) ? '' : apply_filters( 'the_content', $post->post_content ),
				];
				$prepared_post['date']         = $post->post_date;
				$prepared_post['date_gmt']     = $post->post_date_gmt;
				$prepared_post['modified']     = $post->post_modified;
				$prepared_post['modified_gmt'] = $post->post_modified_gmt;

				$prepared_posts[] = $prepared_post;
			}
		}

		$response = new \WP_REST_Response();
		$response->set_data( $prepared_posts );

		return $response;
	}
}
