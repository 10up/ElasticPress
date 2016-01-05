<?php

class EP_Test_Base extends WP_UnitTestCase {

	/**
	 * Prevents weird MySQLi error.
	 *
	 * @since 1.0
	 */
	public function __construct() {
		if ( property_exists( __CLASS__, 'ignore_files' ) ) {
			self::$ignore_files = true;
		}
	}

	public function setUp() {
		parent::setUp();
		if ( false === strpos( $this->getName(), 'UserObjectIndexNotRegistered' ) && ! ep_get_object_type( 'user' ) ) {
			ep_register_object_type( new EP_User_Index() );
		} else {
			$user = ep_get_object_type( 'user' );
			if ( $user ) {
				EP_Object_Manager::factory()->unregister_object( $user );
			}
		}
		if ( false === strpos( $this->getName(), 'UserIndexingInactive' ) ) {
			add_filter( 'ep_user_indexing_active', '__return_true' );
		}
	}

	public function tearDown() {
		parent::tearDown();
		remove_all_filters('ep_user_indexing_active');
	}

	/**
	 * Helps us keep track of actions that have fired
	 *
	 * @var array
	 * @since 1.0
	 */
	protected $fired_actions = array();

	/**
	 * Helps us keep track of applied filters
	 *
	 * @var array
	 * @since 1.0
	 */
	protected $applied_filters = array();

	/**
	 * Helper function to test whether a sync has happened
	 *
	 * @since 1.0
	 */
	public function action_sync_on_transition() {
		$this->fired_actions['ep_sync_on_transition'] = true;
	}

	/**
	 * Helper function to test whether a post has been deleted off ES
	 *
	 * @since 1.0
	 */
	public function action_delete_post() {
		$this->fired_actions['ep_delete_post'] = true;
	}

	/**
	 * Helper function to test whether a EP search has happened
	 *
	 * @since 1.0
	 */
	public function action_wp_query_search() {
		$this->fired_actions['ep_wp_query_search'] = true;
	}

	/**
	 * Helper function to check post sync args
	 *
	 * @since 1.0
	 */
	public function filter_post_sync_args( $post_args ) {
		$this->applied_filters['ep_post_sync_args'] = $post_args;

		return $post_args;
	}

	/**
	 * Setup a few post types for testing
	 *
	 * @since 1.0
	 */
	public function setup_test_post_type() {
		$args = array(
			'public' => true,
			'taxonomies' => array( 'post_tag', 'category' ),
		);

		register_post_type( 'ep_test', $args );

		// Post type that is excluded from search.
		$args = array(
			'taxonomies' => array( 'post_tag', 'category' ),
			'exclude_from_search' => true,
		);

		register_post_type( 'ep_test_excluded', $args );

				// Post type that is excluded from search.
		$args = array(
			'taxonomies' => array( 'post_tag', 'category' ),
			'public' => false,
		);

		register_post_type( 'ep_test_not_public', $args );
	}
}
