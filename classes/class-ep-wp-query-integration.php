<?php

class EP_WP_Query_Integration {

	/**
	 * Placeholder method
	 *
	 * @since 0.9
	 */
	public function __construct() { }

	public function setup() {
		if ( ep_is_alive() ) {
			// Make sure we return nothing for MySQL posts query
			add_filter( 'posts_request', array( $this, 'filter_posts_request' ), 10, 2 );

			// Nukes the FOUND_ROWS() database query
			add_filter( 'found_posts_query', array( $this, 'filter_found_posts_query' ), 5, 2 );

			// Since the FOUND_ROWS() query was nuked, we need to supply the total number of found posts
			add_filter( 'found_posts', array( $this, 'filter_found_posts' ), 5, 2 );

			// Search and filter in EP_Posts to WP_Query
			add_filter( 'posts_results', array( $this, 'filter_post_results' ), 10, 2 );

			// Properly restore blog if necessary
			add_action( 'loop_end', array( $this, 'action_loop_end' ) );

			// Properly switch to blog if necessary
			add_action( 'the_post', array( $this, 'action_the_post' ), 10, 1 );
		}
	}

	/**
	 * Switch to the correct site if the post site id is different than the actual one
	 *
	 * @param array $post
	 * @since 0.9
	 */
	public function action_the_post( $post ) {
		if ( is_multisite() && ! empty( $post->site_id ) && get_current_blog_id() != $post->site_id ) {
			global $authordata;

			switch_to_blog( $post->site_id );
			$authordata = get_userdata( $post->post_author );
		}

	}

	/**
	 * Make sure the correct blog is restored
	 *
	 * @since 0.9
	 */
	public function action_loop_end() {
		if ( is_multisite() ) {
			restore_current_blog();
		}
	}

	/**
	 * Filter the posts array to contain ES search results in EP_Post form.
	 *
	 * @param array $posts
	 * @param object &$query
	 * @return array
	 */
	public function filter_post_results( $posts, &$query ) {

		$s = $query->get( 's' );

		if ( empty( $s ) ) {
			return $posts;
		}

		$query_vars = $query->query_vars;
		if ( 'any' == $query_vars['post_type'] ) {
			unset( $query_vars['post_type'] );
		}

		$scope = 'current';
		if ( ! empty( $query_vars['sites'] ) ) {
			$scope = $query_vars['sites'];
		}

		$formatted_args = ep_format_args( $query_vars );

		$search = ep_search( $formatted_args, $scope );

		$query->found_posts = $search['found_posts'];
		$query->max_num_pages = ceil( $search['found_posts'] / $query->get( 'posts_per_page' ) );

		$posts = array();

		foreach ( $search['posts'] as $post ) {
			$posts[] = new EP_Post( $post );
		}

		do_action( 'ep_wp_query_search', $posts, $search, $query );

		return $posts;
	}

	/**
	 * Remove the found_rows from the SQL Query
	 *
	 * @param string $sql
	 * @param object $query
	 * @since 0.9
	 * @return string
	 */
	public function filter_found_posts_query( $sql, $query ) {
		if ( ! $query->is_main_query() || ! $query->is_search() ) {
			return $sql;
		}

		return '';
	}

	/**
	 * Filter query string used for get_posts(). Return a query that will return nothing.
	 *
	 * @param string $request
	 * @param object $query
	 * @since 0.9
	 * @return string
	 */
	public function filter_posts_request( $request, $query ) {
		$s = $query->get( 's' );

		if ( empty( $s ) ) {
			return $request;
		}

		global $wpdb;

		return "SELECT * FROM $wpdb->posts WHERE 1=0";
	}

	/**
	 * Return a singleton instance of the current class
	 *
	 * @since 0.9
	 * @return object
	 */
	public static function factory() {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new self();
			$instance->setup();
		}

		return $instance;
	}
}

EP_WP_Query_Integration::factory();