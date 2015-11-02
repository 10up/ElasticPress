<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class EP_User_Query_Integration {

	/** @var EP_Object_Index */
	private $user_index;

	/**
	 * EP_User_Query_Integration constructor.
	 *
	 * @param EP_Object_Index $user_index
	 */
	public function __construct( $user_index = null ) {
		$this->user_index = $user_index ? $user_index : ep_get_object_type( 'user' );
	}

	public function setup() {
		/**
		 * By default EP will not integrate on admin or ajax requests. Since admin-ajax.php is
		 * technically an admin request, there is some weird logic here. If we are doing ajax
		 * and ep_ajax_user_query_integration is filtered true, then we skip the next admin check.
		 */
		$admin_integration = apply_filters( 'ep_admin_user_query_integration', false );

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			if ( ! apply_filters( 'ep_ajax_user_query_integration', false ) ) {
				return;
			} else {
				$admin_integration = true;
			}
		}

		if ( is_admin() && ! $admin_integration ) {
			return;
		}

		if ( $this->is_user_indexing_active() ) {
			return;
		}
		add_action( 'pre_get_users', array( $this, 'action_pre_get_users' ), 99999 );
	}

	/**
	 * @param WP_User_Query $wp_user_query
	 */
	public function action_pre_get_users( $wp_user_query ) {
		if ( $this->is_query_basic_enough_to_skip( $wp_user_query ) ) {
			// The User query MUST hit the database, so if this query is so basic that it wouldn't even join any tables
			// then we should just skip it outright
			return;
		}

		$results = ep_search( $this->format_args( $wp_user_query->query_vars ), null, 'user' );

		if ( $results['found_objects'] < 1 ) {
			$wp_user_query->query_vars = array(
				'blog_id'             => null,
				'role'                => '',
				'meta_key'            => '',
				'meta_value'          => '',
				'meta_compare'        => '',
				'include'             => array(),
				'exclude'             => array(),
				'search'              => '',
				'search_columns'      => array(),
				'orderby'             => 'login',
				'order'               => 'ASC',
				'offset'              => '',
				'number'              => '',
				'count_total'         => false,
				'fields'              => 'all',
				'who'                 => '',
				'has_published_posts' => null,
			);
			add_action( 'pre_user_query', array( $this, 'kill_query' ), 99999 );
		}
	}

	/**
	 * @param array $arguments
	 *
	 * @return array
	 */
	public function format_args( $arguments ) {
	}

	/**
	 * @param WP_User_Query $wp_user_query
	 */
	public function kill_query( $wp_user_query ) {
		global $wpdb;
		remove_action( 'pre_user_query', array( $this, 'kill_query' ), 99999 );
		$wp_user_query->query_fields  = "{$wpdb->users}.ID";
		$wp_user_query->query_from    = "FROM {$wpdb->users}";
		$wp_user_query->query_where   = 'WHERE 1=0';
		$wp_user_query->query_orderby = $wp_user_query->query_limit = '';
	}

	/**
	 * @return EP_User_Query_Integration|null
	 */
	public static function factory() {
		static $instance;
		if ( $instance ) {
			return $instance;
		}
		$user = ep_get_object_type( 'user' );
		if ( ! $user ) {
			return null;
		}
		if ( false === $instance ) {
			return null;
		}
		if (
			! method_exists( $user, 'active' ) ||
			! $user->active()
		) {
			$instance = false;

			return null;
		}
		$instance = new self;
		$instance->setup();

		return $instance;
	}

	/**
	 * @return bool
	 */
	private function is_user_indexing_active() {
		return (
			( ep_is_activated() || ( defined( 'WP_CLI' ) && WP_CLI ) ) &&
			method_exists( $this->user_index, 'active' ) &&
			$this->user_index->active()
		);
	}

	/**
	 * @param WP_User_Query $wp_user_query
	 *
	 * @return bool
	 */
	private function is_query_basic_enough_to_skip( $wp_user_query ) {
		$args      = $wp_user_query->query_vars;
		$safe_args = array( 'include', 'order', 'offset', 'number', 'count_total', 'fields', );
		if ( ! is_multisite() ) {
			$safe_args[] = 'blog_id';
		}
		if ( in_array( $args['orderby'], array( 'login', 'nicename', 'user_login', 'user_nicename', 'ID', 'id' ) ) ) {
			$safe_args[] = 'order';
		}
		if ( ! array_diff( array_keys( array_filter( $args ) ), $safe_args ) ) {
			return true;
		}

		return false;
	}

}

add_action( 'plugins_loaded', array( 'EP_User_Query_Integration', 'factory' ), 20 );
