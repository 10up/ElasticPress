<?php

class EP_WP_Query_Integration {

	/**
	 * Is set only when we are within a multisite loop
	 *
	 * @var bool|WP_Query
	 */
	private $query_stack = array();

	/**
	 * Placeholder method
	 *
	 * @since 0.9
	 */
	public function __construct() { }

	public function setup() {

		// Ensure we aren't on the admin (unless overridden)
		if ( is_admin() && ! apply_filters( 'ep_admin_wp_query_integration', false ) ) {
			return;
		}

		// Ensure that we are currently allowing ElasticPress to override the normal WP_Query search
		if ( ! ep_is_activated() ) {
			return;
		}

		// If we can't reach the Elasticsearch service, don't bother with the rest of this
		if ( ! ep_index_exists() ) {
			return;
		}

		// Make sure we return nothing for MySQL posts query
		add_filter( 'posts_request', array( $this, 'filter_posts_request' ), 10, 2 );

		add_action( 'pre_get_posts', array( $this, 'action_pre_get_posts' ), 5 );

		// Nukes the FOUND_ROWS() database query
		add_filter( 'found_posts_query', array( $this, 'filter_found_posts_query' ), 5, 2 );

		// Search and filter in EP_Posts to WP_Query
		add_filter( 'the_posts', array( $this, 'filter_the_posts' ), 10, 2 );

		// Ensure we're in a loop before we allow blog switching
		add_action( 'loop_start', array( $this, 'action_loop_start' ), 10, 1 );

		// Properly restore blog if necessary
		add_action( 'loop_end', array( $this, 'action_loop_end' ), 10, 1 );

		// Properly switch to blog if necessary
		add_action( 'the_post', array( $this, 'action_the_post' ), 10, 1 );
	}

	public function action_pre_get_posts( $query ) {
		if ( ! ep_elasticpress_enabled( $query ) || apply_filters( 'ep_skip_query_integration', false, $query ) ) {
			return;
		}

		$query->set( 'cache_results', false );
	}

	/**
	 * Switch to the correct site if the post site id is different than the actual one
	 *
	 * @param array $post
	 * @since 0.9
	 */
	public function action_the_post( $post ) {
		if ( ! is_multisite() ) {
			return;
		}

		if ( empty( $this->query_stack ) ) {
			return;
		}

		if ( ! ep_elasticpress_enabled( $this->query_stack[0] ) || apply_filters( 'ep_skip_query_integration', false, $this->query_stack[0] ) ) {
			return;
		}

		if ( ! empty( $post->site_id ) && get_current_blog_id() != $post->site_id ) {
			restore_current_blog();

			switch_to_blog( $post->site_id );
			
			remove_action( 'the_post', array( $this, 'action_the_post' ), 10, 1 );
			setup_postdata( $post );
			add_action( 'the_post', array( $this, 'action_the_post' ), 10, 1 );
		}

	}

	/**
	 * Ensure we've started a loop before we allow ourselves to change the blog
	 *
	 * @since 0.9.2
	 */
	public function action_loop_start( $query ) {
		if ( ! is_multisite() ) {
			return;
		}

		array_unshift( $this->query_stack, $query );
	}

	/**
	 * Make sure the correct blog is restored
	 *
	 * @since 0.9
	 */
	public function action_loop_end( $query ) {
		if ( ! is_multisite() ) {
			return;
		}

		array_pop( $this->query_stack );

		if ( ! ep_elasticpress_enabled( $query ) || apply_filters( 'ep_skip_query_integration', false, $query )  ) {
			return;
		}

		if ( ! empty( $GLOBALS['switched'] ) ) {
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
	public function filter_the_posts( $posts, &$query ) {
		if ( ! ep_elasticpress_enabled( $query ) || apply_filters( 'ep_skip_query_integration', false, $query )  ) {
			return $posts;
		}

		$posts = EP_Query::from_wp_query($query)->get_posts();

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
		if ( ! ep_elasticpress_enabled( $query ) || apply_filters( 'ep_skip_query_integration', false, $query )  ) {
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
		if ( ! ep_elasticpress_enabled( $query ) || apply_filters( 'ep_skip_query_integration', false, $query ) ) {
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