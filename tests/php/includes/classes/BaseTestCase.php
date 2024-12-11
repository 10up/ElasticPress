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
	public function set_up() {

		$this->setup_factory();

		\ElasticPress\setup_roles();

		parent::set_up();
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
		register_post_type( 'ep_test_2', $args );

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

	/**
	 * Utilitary function to check if EP is network activated or not.
	 *
	 * @return boolean
	 */
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

	/**
	 * Setup factory
	 *
	 * @since 4.4.0
	 */
	protected function setup_factory() {

		$this->ep_factory           = new \stdClass();
		$this->ep_factory->post     = new PostFactory();
		$this->ep_factory->user     = new UserFactory();
		$this->ep_factory->term     = new TermFactory();
		$this->ep_factory->comment  = new CommentFactory();
		$this->ep_factory->category = new TermFactory( $this, 'category' );
		$this->ep_factory->product  = new ProductFactory();
	}

	/**
	 * Catch ES query args.
	 *
	 * @param array $args ES query args.
	 */
	public function catch_ep_formatted_args( $args ) {
		$this->fired_actions['ep_formatted_args'] = $args;
		return $args;
	}

	/**
	 * Assert function to check if Decay is enabled
	 *
	 * @param array $query ES query
	 */
	public function assertDecayEnabled( $query ) {
		$this->assertTrue(
			isset(
				$query['function_score'],
				$query['function_score']['functions'],
				$query['function_score']['functions'][0],
				$query['function_score']['functions'][0]['exp'],
				$query['function_score']['functions'][0]['exp']['post_date_gmt'],
				$query['function_score']['functions'][0]['exp']['post_date_gmt']['scale'],
				$query['function_score']['functions'][0]['exp']['post_date_gmt']['decay'],
				$query['function_score']['functions'][0]['exp']['post_date_gmt']['offset']
			)
		);
		$this->assertFalse(
			isset(
				$query['bool'],
				$query['bool']['should']
			)
		);
	}
	/**
	 * Assert function to check if Decay is disabled
	 *
	 * @param array $query ES query
	 */
	public function assertDecayDisabled( $query ) {
		$this->assertFalse(
			isset(
				$query,
				$query['function_score'],
				$query['function_score']['functions'],
				$query['function_score']['functions'][0],
				$query['function_score']['functions'][0]['exp'],
				$query['function_score']['functions'][0]['exp']['post_date_gmt'],
				$query['function_score']['functions'][0]['exp']['post_date_gmt']['scale'],
				$query['function_score']['functions'][0]['exp']['post_date_gmt']['decay'],
				$query['function_score']['functions'][0]['exp']['post_date_gmt']['offset']
			)
		);
		$this->assertTrue(
			isset(
				$query['bool'],
				$query['bool']['should']
			)
		);
	}

	/**
	 * Forces tests to use EP.io
	 *
	 * @since 5.1.0
	 */
	protected function force_epio() {
		update_site_option( 'ep_host', 'https://prefix.elasticpress.io/' );
	}
}
