<?php

class EP_Test_Base extends WP_UnitTestCase {

	private $_test_groups = array();

	/**
	 * Prevents weird MySQLi error.
	 *
	 * @since 1.0
	 */
	public function __construct() {
		if ( property_exists( __CLASS__, 'ignore_files' ) ) {
			self::$ignore_files = true;
		}
		$this->plugin_path = str_replace( '/tests/includes', '', dirname( __FILE__ ) );
	}

	public function setUp() {
		parent::setUp();
		$this->maybe_set_up_user_index_tests();
	}

	public function tearDown() {
		parent::tearDown();
		$this->maybe_tear_down_user_index_tests();
	}

	/**
	 * Stores the root path for the plugin
	 *
	 * @var string
	 */
	protected $plugin_path = '';

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
	 * Helper function to test whether a meta sync has happened
	 *
	 * @since 2.0
	 */
	public function action_sync_on_meta_update() {
		$this->fired_actions['ep_sync_on_meta_update'] = true;
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

	private function maybe_set_up_user_index_tests() {
		$this->_test_groups = PHPUnit_Util_Test::getGroups( get_class( $this ), $this->getName( false ) );
		if ( ! in_array( 'users', $this->_test_groups ) ) {
			return;
		}
		if ( in_array( 'users-index-not-registered', $this->_test_groups ) ) {
			$user = ep_get_object_type( 'user' );
			if ( $user ) {
				EP_Object_Manager::factory()->unregister_object( $user );
			}
		} else {
			if ( ! ep_get_object_type( 'user' ) ) {
				ep_register_object_type( new EP_User_Index() );
			}
		}
		if ( ! in_array( 'users-indexing-inactive', $this->_test_groups ) ) {
      ep_activate_module( 'user' );
  		EP_Modules::factory()->setup_modules();
		}
	}

	private function maybe_tear_down_user_index_tests(){
		remove_all_filters( 'ep_user_indexing_active' );
		if ( ! in_array( 'users', (array) $this->_test_groups ) ) {
			return;
		}
		$user = ep_get_object_type( 'user' );
		if ( $user ) {
			EP_Object_Manager::factory()->unregister_object( $user );
		}
	}

	public function assertEqualSetsWithIndex( $expected, $actual ) {
		if ( method_exists( 'WP_UnitTestCase', 'assertEqualSetsWithIndex' ) ) {
			parent::assertEqualSetsWithIndex( $expected, $actual );

			return;
		}
		ksort( $expected );
		ksort( $actual );
		$this->assertEquals( $expected, $actual );
	}

	/**
	 * @return WP_UnitTest_Factory
	 */
	protected function getFactory() {
		if ( method_exists( $this, 'factory' ) ) {
			return $this->factory();
		} else {
			return $this->factory;
		}
	}

}
