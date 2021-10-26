<?php
/**
 * Test protected content feature
 *
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress;

/**
 * Protected content test class
 */
class TestProtectedContent extends BaseTestCase {

	/**
	 * Setup each test.
	 *
	 * @since 2.1
	 * @group protected-content
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
	 * @group protected-content
	 */
	public function tearDown() {
		parent::tearDown();

		// make sure no one attached to this
		remove_filter( 'ep_sync_terms_allow_hierarchy', array( $this, 'ep_allow_multiple_level_terms_sync' ), 100 );
		$this->fired_actions = array();

		set_current_screen( 'front' );
	}

	/**
	 * Test main query isn't integrated when feature isn't on
	 *
	 * @since 2.1
	 * @group protected-content
	 */
	public function testAdminNotOn() {
		set_current_screen( 'edit.php' );

		ElasticPress\Features::factory()->setup_features();

		Functions\create_and_sync_post();

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		add_action( 'ep_wp_query_search', array( $this, 'action_wp_query_search' ), 10, 0 );

		$query = new \WP_Query();

		global $wp_the_query;

		$wp_the_query = $query;

		$query->query( array() );

		$this->assertTrue( empty( $this->fired_actions['ep_wp_query_search'] ) );
	}

	/**
	 * Test main query is integrated with feature on
	 *
	 * @since 2.1
	 * @group protected-content
	 */
	public function testAdminOn() {
		set_current_screen( 'edit.php' );

		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		Functions\create_and_sync_post();

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		add_action( 'ep_wp_query_search', array( $this, 'action_wp_query_search' ), 10, 0 );

		$query = new \WP_Query();

		global $wp_the_query;

		$wp_the_query = $query;

		$wp_the_query->query( array() );

		$this->assertTrue( ! empty( $this->fired_actions['ep_wp_query_search'] ) );
	}

	/**
	 * Test main query on is integrated on drafts with feature on
	 *
	 * @since 2.1
	 * @group protected-content
	 */
	public function testAdminOnDraft() {
		set_current_screen( 'edit.php' );

		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		Functions\create_and_sync_post();
		Functions\create_and_sync_post( array( 'post_status' => 'draft' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		add_action( 'ep_wp_query_search', array( $this, 'action_wp_query_search' ), 10, 0 );

		$query = new \WP_Query();

		global $wp_the_query;

		$wp_the_query = $query;

		$args = array(
			'post_status' => 'draft',
		);

		$query->query( $args );

		$this->assertTrue( ! empty( $this->fired_actions['ep_wp_query_search'] ) );
		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, $query->found_posts );
	}

	/**
	 * Check post updated to draft shows up
	 *
	 * @since 2.1
	 * @group protected-content
	 */
	public function testAdminOnDraftUpdated() {
		set_current_screen( 'edit.php' );

		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		Functions\create_and_sync_post();
		$post_id = Functions\create_and_sync_post();

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'draft',
			)
		);

		ElasticPress\Indexables::factory()->get( 'post' )->index( $post_id, true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		add_action( 'ep_wp_query_search', array( $this, 'action_wp_query_search' ), 10, 0 );

		$query = new \WP_Query();

		global $wp_the_query;

		$wp_the_query = $query;

		$args = array(
			'post_status' => 'draft',
		);

		$query->query( $args );

		$this->assertTrue( ! empty( $this->fired_actions['ep_wp_query_search'] ) );
		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, $query->found_posts );
	}

	/**
	 * Check posts filter by category in dashboard
	 */
	public function testAdminCategories() {
		set_current_screen( 'edit.php' );

		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$cat1 = wp_create_category( 'category one' );
		$cat2 = wp_create_category( 'category two' );

		Functions\create_and_sync_post( array( 'post_category' => array( $cat1 ) ) );
		Functions\create_and_sync_post( array( 'post_category' => array( $cat2 ) ) );
		Functions\create_and_sync_post( array( 'post_category' => array( $cat1 ) ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		add_action( 'ep_wp_query_search', array( $this, 'action_wp_query_search' ), 10, 0 );

		$query = new \WP_Query();

		global $wp_the_query;

		$wp_the_query = $query;

		$args = array(
			'category_name' => 'category one',
		);

		$query->query( $args );

		$this->assertTrue( ! empty( $this->fired_actions['ep_wp_query_search'] ) );
		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );
	}

	/**
	 * Check if passwords on posts are synced when feature not active
	 *
	 * @since 3.7
	 * @group protected-content
	 */
	public function testNoSyncPasswordedPost() {
		add_filter( 'ep_post_sync_args', array( $this, 'filter_post_sync_args' ), 10, 1 );

		$post_id = Functions\create_and_sync_post( array( 'post_password' => 'test' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		// Check if ES post sync filter has been triggered
		$this->assertTrue( ! empty( $this->applied_filters['ep_post_sync_args'] ) );

		// Check if password was synced
		$post = ElasticPress\Indexables::factory()->get( 'post' )->get( $post_id );
		$this->assertTrue( ! isset( $post['post_password'] ) );
	}

	/**
	 * Check if passwords on posts are synced when feature active
	 *
	 * @since 3.7
	 * @group protected-content
	 */
	public function testSyncPasswordedPost() {
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		add_filter( 'ep_post_sync_args', array( $this, 'filter_post_sync_args' ), 10, 1 );

		$post_id = Functions\create_and_sync_post( array( 'post_password' => 'test' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		// Check if ES post sync filter has been triggered
		$this->assertTrue( ! empty( $this->applied_filters['ep_post_sync_args'] ) );

		// Check if password was synced
		$post = ElasticPress\Indexables::factory()->get( 'post' )->get( $post_id );
		$this->assertEquals( 'test', $post['post_password'] );

		// Remove password from post
		wp_update_post(
			array(
				'ID'            => $post_id,
				'post_password' => '',
		) );

		ElasticPress\Indexables::factory()->get( 'post' )->index( $post_id, true );
		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$post = ElasticPress\Indexables::factory()->get( 'post' )->get( $post_id );

		// Check if password was removed on sync
		$this->assertEmpty( $post['post_password'] );

		// Add back password on post
		wp_update_post(
			array(
				'ID'            => $post_id,
				'post_password' => 'test',
		) );

		ElasticPress\Indexables::factory()->get( 'post' )->index( $post_id, true );
		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$post = ElasticPress\Indexables::factory()->get( 'post' )->get( $post_id );

		// Check if password was added back on sync
		$this->assertEquals( 'test', $post['post_password'] );
	}

	/**
	 * Check if password protected post shows up in admin
	 *
	 * @since 3.7
	 * @group protected-content
	 */
	public function testAdminPasswordedPost() {
		set_current_screen( 'edit.php' );

		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$post_id = Functions\create_and_sync_post(
			array(
				'post_content' => 'findme 123',
				'post_password' => 'test'
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		add_action( 'ep_wp_query_search', array( $this, 'action_wp_query_search' ), 10, 0 );

		$query = new \WP_Query();

		global $wp_the_query;

		$wp_the_query = $query;

		$args = array(
			's' => 'findme',
		);

		$query->query( $args );

		$this->assertTrue( ! empty( $this->fired_actions['ep_wp_query_search'] ) );
		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, $query->found_posts );
	}

	/**
	 * Check password protected post in front-end
	 *
	 * @since 3.7
	 * @group protected-content
	 */
	public function testFrontEndSearchPasswordedPost() {
		set_current_screen( 'front' );

		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->activate_feature( 'search' );
		ElasticPress\Features::factory()->setup_features();

		// Need to call this since it's hooked to init
		ElasticPress\Features::factory()->get_registered_feature( 'search' )->search_setup();

		$post_id = Functions\create_and_sync_post(
			array(
				'post_content'  => 'findme 123',
				'post_password' => 'test',
			)
		);
		ElasticPress\Elasticsearch::factory()->refresh_indices();

		add_filter( 'ep_post_formatted_args', array( $this, 'catch_ep_post_formatted_args' ), 20 );

		$query = new \WP_Query(
			array(
				's' => 'findme',
			)
		);

		// Check to ensure query is being modified
		$this->assertEquals(
			'post_password',
			$this->fired_actions['ep_post_formatted_args']['post_filter']['bool']['must_not'][0]['exists']['field']
		);

		// Check for no results
		$this->assertEquals( 0, $query->post_count );
		$this->assertEquals( 0, $query->found_posts );

		// Remove password from post
		wp_update_post(
			array(
				'ID' => $post_id,
				'post_password' => '',
		) );

		ElasticPress\Indexables::factory()->get( 'post' )->index( $post_id, true );
		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$query = new \WP_Query(
			array(
				's' => 'findme',
			)
		);

		// Check for a result now that password was removed
		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, $query->found_posts );
	}

	/**
	 * Catch ES query args.
	 *
	 * @since 3.7
	 * @group protected-content
	 * @param array $args ES query args.
	 */
	public function catch_ep_post_formatted_args( $args ) {
		$this->fired_actions['ep_post_formatted_args'] = $args;
		return $args;
	}
}
