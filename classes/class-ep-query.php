<?php

/**
 * Unfortunately we cannot filter into WP_Query since we need to pass around a construct that
 * contains a site ID. Therefore we pass around an array and convert it to a WP_Post object at
 * the last second. WP_Query cannot be filtered or hooked into to do this. The goal of this class
 * is to mimic WP_Query behavior but simply hit the ES index before the posts table.
 */
class EP_Query {

	public $posts = array();

	public $post;

	public $post_count = 0;

	public $found_posts = 0;

	public $in_the_loop = false;

	public $current_post = -1;

	public $max_num_pages = 0;

	/**
	 * Setup new EP query
	 *
	 * @param array $args
	 * @since 0.1.0
	 */
	public function __construct( $args ) {
		$this->query( $args );
	}

	/**
	 * Query our Elasticsearch instance
	 *
	 * @param array $args
	 * @since 0.1.0
	 * @return array
	 */
	public function query( $args ) {

		$formatted_args = ep_format_args( $args );

		$posts_per_page = ( isset( $args['posts_per_page'] ) ) ? $args['posts_per_page'] : get_option( 'posts_per_page' );

		$search = ep_search( $formatted_args, true );

		$this->found_posts = $search['found_posts'];

		$this->max_num_pages = ceil( $this->found_posts / $posts_per_page );

		$this->post_count = count( $search['posts'] );

		$this->posts = $search['posts'];

		return $this->posts;
	}

	/**
	 * Check if the query has posts
	 *
	 * @since 0.1.0
	 * @return bool
	 */
	public function have_posts() {
		if ( is_multisite() ) {
			restore_current_blog();
		}

		if ( $this->current_post + 1 < $this->post_count ) {
			return true;
		} elseif ( $this->current_post + 1 == $this->post_count && $this->post_count > 0 ) {
			// loop has ended
		}

		$this->in_the_loop = false;
		return false;
	}

	/**
	 * Setup the current post in the $post global
	 *
	 * @since 0.1.0
	 */
	public function the_post() {
		global $post;

		$this->in_the_loop = true;

		$ep_post = $this->next_post();

		if ( is_multisite() && $ep_post['site_id'] != get_current_blog_id() ) {
			switch_to_blog( $ep_post['site_id'] );
		}

		$post = get_post( $ep_post['post_id'] );

		setup_postdata( $post );
	}

	/**
	 * Move to the next post in the query
	 *
	 * @since 0.1.0
	 * @return mixed
	 */
	function next_post() {
		$this->current_post++;

		$this->post = $this->posts[$this->current_post];
		return $this->post;
	}
}