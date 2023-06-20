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
	public function set_up() {
		global $wpdb;
		parent::set_up();
		$wpdb->suppress_errors();

		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );

		wp_set_current_user( $admin_id );

		ElasticPress\Elasticsearch::factory()->delete_all_indices();
		ElasticPress\Indexables::factory()->get( 'post' )->put_mapping();

		ElasticPress\Indexables::factory()->get( 'post' )->sync_manager->reset_sync_queue();

		$this->setup_test_post_type();
	}

	/**
	 * Clean up after each test. Reset our mocks
	 *
	 * @since 2.1
	 * @group protected-content
	 */
	public function tear_down() {
		parent::tear_down();

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

		$this->ep_factory->post->create();

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$query = new \WP_Query();

		global $wp_the_query;

		$wp_the_query = $query;

		$query->query( array() );

		$this->assertNull( $query->elasticsearch_success );
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

		$this->ep_factory->post->create();

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$query = new \WP_Query();

		global $wp_the_query;

		$wp_the_query = $query;

		$wp_the_query->query( array() );

		$this->assertTrue( $query->elasticsearch_success );
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

		$this->ep_factory->post->create();
		$this->ep_factory->post->create( array( 'post_status' => 'draft' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$query = new \WP_Query();

		global $wp_the_query;

		$wp_the_query = $query;

		$args = array(
			'post_status' => 'draft',
		);

		$query->query( $args );

		$this->assertTrue( $query->elasticsearch_success );
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

		$this->ep_factory->post->create();
		$post_id = $this->ep_factory->post->create();

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'draft',
			)
		);

		ElasticPress\Indexables::factory()->get( 'post' )->index( $post_id, true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$query = new \WP_Query();

		global $wp_the_query;

		$wp_the_query = $query;

		$args = array(
			'post_status' => 'draft',
		);

		$query->query( $args );

		$this->assertTrue( $query->elasticsearch_success );
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

		$cat1 = $this->factory->category->create( array( 'name' => 'category one' ) );
		$cat2 = $this->factory->category->create( array( 'name' => 'category two' ) );

		$this->ep_factory->post->create( array( 'post_category' => array( $cat1 ) ) );
		$this->ep_factory->post->create( array( 'post_category' => array( $cat2 ) ) );
		$this->ep_factory->post->create( array( 'post_category' => array( $cat1 ) ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$query = new \WP_Query();

		global $wp_the_query;

		$wp_the_query = $query;

		$args = array(
			/**
			 * Despite its name, per WP docs `category_name` actually uses the cat slug.
			 *
			 * @see https://developer.wordpress.org/reference/classes/wp_query/#category-parameters
			 */
			'category_name' => 'category-one',
		);

		$query->query( $args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 2, $query->post_count );
		$this->assertEquals( 2, $query->found_posts );
	}

	/**
	 * Check if passwords on posts are synced when feature not active
	 *
	 * @since 4.0.0
	 * @group protected-content
	 */
	public function testNoSyncPasswordedPost() {
		add_filter( 'ep_post_sync_args', array( $this, 'filter_post_sync_args' ), 10, 1 );

		$post_id = $this->ep_factory->post->create( array( 'post_password' => 'test' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		// Check if ES post sync filter has been triggered
		$this->assertNotEmpty( $this->applied_filters['ep_post_sync_args'] );

		// Check if password was synced
		$post = ElasticPress\Indexables::factory()->get( 'post' )->get( $post_id );

		$this->assertArrayNotHasKey( 'post_password', $post );
	}

	/**
	 * Check if passwords on posts are synced when feature active
	 *
	 * @since 4.0.0
	 * @group protected-content
	 */
	public function testSyncPasswordedPost() {
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		add_filter( 'ep_post_sync_args', array( $this, 'filter_post_sync_args' ), 10, 1 );

		$post_id = $this->ep_factory->post->create( array( 'post_password' => 'test' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		// Check if ES post sync filter has been triggered
		$this->assertNotEmpty( $this->applied_filters['ep_post_sync_args'] );

		// Check if password was synced
		$post = ElasticPress\Indexables::factory()->get( 'post' )->get( $post_id );
		$this->assertEquals( 'test', $post['post_password'] );

		// Remove password from post
		wp_update_post(
			array(
				'ID'            => $post_id,
				'post_password' => '',
			)
		);

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
			)
		);

		ElasticPress\Indexables::factory()->get( 'post' )->index( $post_id, true );
		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$post = ElasticPress\Indexables::factory()->get( 'post' )->get( $post_id );

		// Check if password was added back on sync
		$this->assertEquals( 'test', $post['post_password'] );
	}

	/**
	 * Check if password protected post shows up in admin
	 *
	 * @since 4.0.0
	 * @group protected-content
	 */
	public function testAdminPasswordedPost() {
		set_current_screen( 'edit.php' );

		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		// Post title is indexed but content is not.
		$this->ep_factory->post->create(
			array(
				'post_title'    => 'findmetitle 123',
				'post_content'  => 'findmecontent 123',
				'post_password' => 'test',
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$query = new \WP_Query();

		global $wp_the_query;

		$wp_the_query = $query;

		$args = array(
			's' => 'findmetitle',
		);

		$query->query( $args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, $query->found_posts );

		$new_query = new \WP_Query(
			[
				's' => 'findmecontent',
			]
		);

		$this->assertTrue( $new_query->elasticsearch_success );
		$this->assertEquals( 0, $new_query->post_count );
		$this->assertEquals( 0, $new_query->found_posts );
	}

	/**
	 * Check password protected post in front-end
	 *
	 * @since 4.0.0
	 * @group protected-content
	 */
	public function testFrontEndSearchPasswordedPost() {
		set_current_screen( 'front' );

		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->activate_feature( 'search' );
		ElasticPress\Features::factory()->setup_features();

		// Need to call this since it's hooked to init
		ElasticPress\Features::factory()->get_registered_feature( 'search' )->search_setup();

		$this->ep_factory->post->create(
			array(
				'post_title'    => 'findmetitle 123',
				'post_password' => 'test',
			)
		);
		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$query = new \WP_Query(
			array(
				's' => 'findmetitle',
			)
		);

		$this->assertTrue( $query->elasticsearch_success );

		// Password post is expected to return as we are logged in.
		$this->assertEquals( 1, $query->post_count );
		$this->assertEquals( 1, $query->found_posts );

		// Log out and try again.
		wp_set_current_user( 0 );

		$query = new \WP_Query(
			array(
				's' => 'findmetitle',
			)
		);

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 0, $query->post_count );
		$this->assertEquals( 0, $query->found_posts );
	}

	/**
	 * Check admin comment query are powered by Elasticsearch
	 *
	 * @since 4.4.1
	 */
	public function testAdminCommentQuery() {

		set_current_screen( 'edit-comments.php' );
		$this->assertTrue( is_admin() );

		ElasticPress\Features::factory()->activate_feature( 'comments' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		ElasticPress\Indexables::factory()->get( 'comment' )->put_mapping();
		ElasticPress\Indexables::factory()->get( 'comment' )->sync_manager->reset_sync_queue();

		// Need to call this since it's hooked to init.
		ElasticPress\Features::factory()->get_registered_feature( 'comments' )->search_setup();

		$this->ep_factory->comment->create(
			[
				'comment_content' => 'findme',
				'comment_post_ID' => $this->ep_factory->post->create(),
			]
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$comments_query = new \WP_Comment_Query(
			[
				'type' => 'comment',
			]
		);

		$this->assertTrue( $comments_query->elasticsearch_success );
		$this->assertEquals( 1, $comments_query->found_comments );
	}
}
