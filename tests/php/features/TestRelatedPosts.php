<?php
/**
 * Test related posts feature
 *
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress;

/**
 * Related post test class
 */
class TestRelatedPosts extends BaseTestCase {

	/**
	 * Setup each test.
	 *
	 * @since 2.1
	 * @group related_posts
	 */
	public function setUp() {
		global $wpdb;
		parent::setUp();
		$wpdb->suppress_errors();

		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );

		wp_set_current_user( $admin_id );

		ElasticPress\Elasticsearch::factory()->delete_all_indices();
		ElasticPress\Indexables::factory()->get( 'post' )->put_mapping();

		ElasticPress\Indexables::factory()->get( 'post' )->sync_manager->sync_queue = [];

		$this->setup_test_post_type();

		delete_option( 'ep_active_features' );
	}

	/**
	 * Clean up after each test. Reset our mocks
	 *
	 * @since 2.1
	 */
	public function tearDown() {
		parent::tearDown();

		// make sure no one attached to this
		remove_filter( 'ep_sync_terms_allow_hierarchy', array( $this, 'ep_allow_multiple_level_terms_sync' ), 100 );
		$this->fired_actions = array();
	}

	/**
	 * Log action usage for tests
	 *
	 * @since  2.1
	 */
	public function action_ep_related_html_attached() {
		$this->fired_actions['ep_related_html_attached'] = true;
	}

	/**
	 * Test for related post args filter
	 *
	 * @group related_posts
	 */
	public function testFindRelatedPostFilter() {
		$post_id = Functions\create_and_sync_post( array( 'post_content' => 'findme test 1' ) );
		Functions\create_and_sync_post( array( 'post_content' => 'findme test 2' ) );
		Functions\create_and_sync_post(
			array(
				'post_content' => 'findme test 3',
				'post_type'    => 'page',
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		ElasticPress\Features::factory()->activate_feature( 'related_posts' );

		ElasticPress\Features::factory()->setup_features();

		$related = ElasticPress\Features::factory()->get_registered_feature( 'related_posts' )->find_related( $post_id );
		$this->assertEquals( 1, count( $related ) );
		$this->assertTrue( isset( $related[0] ) && isset( $related[0]->elasticsearch ) );

		add_filter( 'ep_find_related_args', array( $this, 'find_related_posts_filter' ), 10, 1 );
		$related = ElasticPress\Features::factory()->get_registered_feature( 'related_posts' )->find_related( $post_id );
		$this->assertEquals( 2, count( $related ) );
		$this->assertTrue( isset( $related[0] ) && isset( $related[0]->elasticsearch ) );
		remove_filter( 'ep_find_related_args', array( $this, 'find_related_posts_filter' ), 10, 1 );
	}

	/**
	 * Test for related posts query
	 *
	 * @group related_posts
	 */
	public function testGetRelatedQuery() {
		$post_id = Functions\create_and_sync_post( array( 'post_content' => 'findme test 1' ) );

		$related_post_title = 'related post test';
		Functions\create_and_sync_post( array(
				'post_title'   => $related_post_title,
				'post_content' => 'findme test 2'
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();
		ElasticPress\Features::factory()->activate_feature( 'related_posts' );
		ElasticPress\Features::factory()->setup_features();

		$query = ElasticPress\Features::factory()->get_registered_feature( 'related_posts' )->get_related_query( $post_id, 1 );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertNotEmpty( $query->posts );
		$this->assertEquals( '1', $query->post_count );
		$this->assertEquals( $related_post_title, $query->posts[0]->post_title );
	}

	/**
	 * Detect EP fire
	 *
	 * @param array $args Query args
	 * @return mixed
	 */
	public function find_related_posts_filter( $args ) {
		$args['post_type'] = array( 'post', 'page' );

		return $args;
	}
}
