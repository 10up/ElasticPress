<?php

class EP_WP_Query_Integration {
	private $found_posts = 0;

	/**
	 * Placeholder method
	 *
	 * @since 0.9
	 */
	public function __construct() { }

	public function setup() {
		if ( ep_is_alive() ) {
			add_filter( 'posts_request', array( $this, 'filter_posts_request' ), 10, 2 );

			// Nukes the FOUND_ROWS() database query
			add_filter( 'found_posts_query', array( $this, 'filter_found_posts_query' ), 5, 2 );

			// Since the FOUND_ROWS() query was nuked, we need to supply the total number of found posts
			add_filter( 'found_posts', array( $this, 'filter_found_posts' ), 5, 2 );
		}
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
	 * Return the found posts
	 *
	 * @param int $found_posts
	 * @param object $query
	 * @since 0.9
	 * @return int
	 */
	public function filter_found_posts( $found_posts, $query ) {
		if ( ! $query->is_search() ) {
			return $found_posts;
		}

		return $this->found_posts;
	}

	/**
	 * Filter query string used for get_posts()
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

		$query_vars = $query->query_vars;
		if ( 'any' == $query_vars['post_type'] ) {
			unset( $query_vars['post_type'] );
		}

		$formatted_args = ep_format_args( $query_vars );

		$search = ep_search( $formatted_args );

		$this->found_posts = $search['found_posts'];

		if ( empty( $search['posts'] ) ) {
			return "SELECT * FROM $wpdb->posts WHERE 1=0";
		}

		$post_ids = wp_list_pluck( $search['posts'], 'post_id' );
		$post_ids_string = implode( $post_ids, ',' );

		$sql_query = "SELECT * FROM {$wpdb->posts} WHERE {$wpdb->posts}.ID IN( {$post_ids_string} ) ORDER BY FIELD( {$wpdb->posts}.ID, {$post_ids_string} )";

		do_action( 'ep_wp_query_search', $sql_query, $search, $query );

		return $sql_query;
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