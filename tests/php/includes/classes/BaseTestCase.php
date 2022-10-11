<?php
/**
 * ElasticPress base test class
 *
 * @package  elasticpress
 */

namespace ElasticPressTest;

use WP_UnitTestCase;

/**
 * Base test class
 */
class BaseTestCase extends WP_UnitTestCase {

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
	 * Holds the factory object
	 *
	 * @var obj
	 * @since 4.4.0
	 */
	protected $ep_factory;

	/**
	 * Set up the test case.
	 *
	 * @var obj
	 * @since 4.4.0
	 */
	public function setup() {

		$this->ep_factory           = new \stdClass();
		$this->ep_factory->category = new CategoryFactory();
		$this->ep_factory->comment  = new CommentFactory();
		parent::setup();
	}

	/**
	 * Helper function to test whether a post sync has happened
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
	 * Helper function to check post sync args
	 *
	 * @param  array $post_args Post arguments
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
			'public'     => true,
			'taxonomies' => array( 'post_tag', 'category' ),
		);

		register_post_type( 'ep_test', $args );

		// Post type that is excluded from search.
		$args = array(
			'taxonomies'          => array( 'post_tag', 'category' ),
			'exclude_from_search' => true,
		);

		register_post_type( 'ep_test_excluded', $args );

				// Post type that is excluded from search.
		$args = array(
			'taxonomies' => array( 'post_tag', 'category' ),
			'public'     => false,
		);

		register_post_type( 'ep_test_not_public', $args );
	}

	public function is_network_activate() {
		return defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK;
	}

	/**
	 * Filter hook to set the search algorithm to 3.4.
	 *
	 * @return string
	 */
	public function set_algorithm_34() {
		return '3.4';
	}
}
