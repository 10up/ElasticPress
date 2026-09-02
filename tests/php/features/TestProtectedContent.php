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
	 *
	 * @group protected-content
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
	 * Check if password protected post is not synced when feature is disabled
	 *
	 * @since 4.0.0
	 * @group protected-content
	 */
	public function test_password_protected_post_is_not_synced_when_feature_is_disabled() {
		add_filter( 'ep_post_sync_args', array( $this, 'filter_post_sync_args' ), 10, 1 );

		$post_id = $this->ep_factory->post->create( array( 'post_password' => 'test' ) );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		// Check if ES post sync filter has been triggered
		$this->assertNotEmpty( $this->applied_filters['ep_post_sync_args'] );

		// Check if password was synced
		$post = ElasticPress\Indexables::factory()->get( 'post' )->get( $post_id );
		$this->assertEmpty( $post );
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
	 * @group protected-content
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

	/**
	 * Test the `maybe_change_sort` method.
	 *
	 * @since 5.1.4
	 * @group protected-content
	 */
	public function test_maybe_change_sort() {
		set_current_screen( 'edit.php' );
		$this->assertTrue( is_admin() );

		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$exact_match_id       = $this->ep_factory->post->create(
			[
				'post_title' => 'exact match - beautiful',
				'post_date'  => '2021-12-31 23:59:59',
			]
		);
		$not_so_good_match_id = $this->ep_factory->post->create(
			[
				'post_title' => 'not so good match - beautful',
				'post_date'  => '2022-12-31 23:59:59',
			]
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		// By default, display the best match first
		$query = new \WP_Query( [ 's' => 'beautiful' ] );
		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 2, $query->found_posts );
		$this->assertEquals( $exact_match_id, $query->posts[0]->ID );

		$filter = function ( $value ) {
			$value['protected_content']['use_default_wp_sort'] = '1';
			return $value;
		};
		add_filter( 'site_option_ep_feature_settings', $filter );
		add_filter( 'option_ep_feature_settings', $filter );

		// With the option enabled, order by date
		$query = new \WP_Query( [ 's' => 'beautiful' ] );
		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 2, $query->found_posts );
		$this->assertEquals( $not_so_good_match_id, $query->posts[0]->ID );
	}

	/**
	 * Test that user can only see their own private posts.
	 *
	 * @since 5.3.0
	 * @group protected-content
	 */
	public function test_user_can_only_see_their_own_private_posts() {
		set_current_screen( 'edit.php' );
		$this->assertTrue( is_admin() );

		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$author_1_id = $this->factory->user->create();
		$author_2_id = $this->factory->user->create();
		$admin_id    = get_current_user_id();

		$public_post_1_id = $this->ep_factory->post->create(
			[
				'post_title' => 'Public Post 1',
				'post_type'  => 'post',
			]
		);

		$public_page_1_id = $this->ep_factory->post->create(
			[
				'post_title' => 'Public Page 1',
				'post_type'  => 'page',
			]
		);

		$private_post_1_id = $this->ep_factory->post->create(
			[
				'post_title'  => 'Private Post 1 (Author 1)',
				'post_type'   => 'post',
				'post_status' => 'private',
				'post_author' => $author_1_id,
			]
		);

		$private_page_1_id = $this->ep_factory->post->create(
			[
				'post_title'  => 'Private Page 1 (Author 2)',
				'post_type'   => 'page',
				'post_status' => 'private',
				'post_author' => $author_2_id,
			]
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		wp_set_current_user( $author_1_id );

		$query = new \WP_Query(
			[
				'ep_integrate' => true,
				'orderby'      => 'date',
			]
		);
		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 2, $query->found_posts );
		$this->assertEqualsCanonicalizing(
			[ $public_post_1_id, $private_post_1_id ],
			wp_list_pluck( $query->posts, 'ID' )
		);

		$query = new \WP_Query(
			[
				'post_type'    => [ 'post', 'page' ] ,
				'ep_integrate' => true,
				'orderby'      => 'date',
			]
		);
		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 3, $query->found_posts );
		$this->assertEqualsCanonicalizing(
			[ $public_post_1_id, $public_page_1_id, $private_post_1_id ],
			wp_list_pluck( $query->posts, 'ID' )
		);

		wp_set_current_user( $author_2_id );

		$query = new \WP_Query(
			[
				'post_type'    => [ 'post', 'page' ] ,
				'ep_integrate' => true,
				'orderby'      => 'date',
			]
		);
		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 3, $query->found_posts );
		$this->assertEqualsCanonicalizing(
			[ $public_post_1_id, $public_page_1_id, $private_page_1_id ],
			wp_list_pluck( $query->posts, 'ID' )
		);

		// Admin can see all posts.
		wp_set_current_user( $admin_id );

		$query = new \WP_Query(
			[
				'post_type'    => [ 'post', 'page' ] ,
				'ep_integrate' => true,
				'orderby'      => 'date',
			]
		);
		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 4, $query->found_posts );
		$this->assertEqualsCanonicalizing(
			[ $public_post_1_id, $public_page_1_id, $private_post_1_id, $private_page_1_id ],
			wp_list_pluck( $query->posts, 'ID' )
		);
	}

	/**
	 * Tests post statuses for admin.
	 *
	 * @since 5.3.0
	 * @group protected-content
	 */
	public function test_post_statuses_for_admin() {
		set_current_screen( 'edit.php' );
		$this->assertTrue( is_admin() );

		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$post = new \ElasticPress\Indexable\Post\Post();

		// This will include statuses besides publish.
		$args     = $post->format_args( [ 'post_type' => [ 'post' ] ], new \WP_Query() );
		$statuses = $args['post_filter']['bool']['should'][0]['bool']['must'][1]['terms']['post_status'];

		$this->assertContains( 'publish', $statuses );
		$this->assertContains( 'future', $statuses );
		$this->assertContains( 'draft', $statuses );
		$this->assertContains( 'pending', $statuses );
		$this->assertContains( 'private', $statuses );
	}

	/**
	 * Tests post statuses for admin with multiple statuses.
	 *
	 * @since 5.3.2
	 * @group protected-content
	 */
	public function test_post_statuses_for_admin_with_multiple_statuses() {
		set_current_screen( 'edit.php' );
		$this->assertTrue( is_admin() );

		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$post_1_id = $this->ep_factory->post->create(
			[
				'post_status' => 'inherit',
			]
		);
		$post_2_id = $this->ep_factory->post->create(
			[
				'post_status' => 'private',
			]
		);
		$this->ep_factory->post->create(
			[
				'post_status' => 'draft',
			]
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$query = new \WP_Query(
			[
				'post_status'  => 'inherit,private',
				'ep_integrate' => true,
				'orderby'      => 'date',
			]
		);

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 2, $query->found_posts );
		$this->assertEqualsCanonicalizing(
			[ $post_1_id, $post_2_id ],
			wp_list_pluck( $query->posts, 'ID' )
		);

		$query = new \WP_Query(
			[
				'post_status'  => [ 'inherit', 'private' ],
				'ep_integrate' => true,
				'orderby'      => 'date',
			]
		);
		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 2, $query->found_posts );
		$this->assertEqualsCanonicalizing(
			[ $post_1_id, $post_2_id ],
			wp_list_pluck( $query->posts, 'ID' )
		);
	}

	/**
	 * Authors filtering by private status must not see other authors' private posts.
	 *
	 * @since 5.3.5
	 * @group protected-content
	 */
	public function test_author_cannot_see_other_authors_private_posts_when_filtering_by_private_status() {
		set_current_screen( 'edit.php' );
		$this->assertTrue( is_admin() );

		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$author_1_id = $this->factory->user->create( [ 'role' => 'author' ] );
		$author_2_id = $this->factory->user->create( [ 'role' => 'author' ] );
		$admin_id    = get_current_user_id();

		$author_1_private_id = $this->ep_factory->post->create(
			[
				'post_title'  => 'Author 1 Private Post',
				'post_status' => 'private',
				'post_author' => $author_1_id,
			]
		);
		$author_2_private_id = $this->ep_factory->post->create(
			[
				'post_title'  => 'Author 2 Secret Private Title',
				'post_status' => 'private',
				'post_author' => $author_2_id,
			]
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		wp_set_current_user( $author_1_id );
		$this->assertFalse( current_user_can( 'read_private_posts' ) );

		$query = new \WP_Query(
			[
				'post_status'  => 'private',
				'ep_integrate' => true,
				'orderby'      => 'date',
			]
		);
		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 1, $query->found_posts );
		$this->assertEquals( [ $author_1_private_id ], wp_list_pluck( $query->posts, 'ID' ) );

		$query = new \WP_Query(
			[
				'post_status'  => 'private',
				's'            => 'Author 2 Secret Private Title',
				'ep_integrate' => true,
			]
		);
		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 0, $query->found_posts );

		wp_set_current_user( $admin_id );

		$query = new \WP_Query(
			[
				'post_status'  => 'private',
				'ep_integrate' => true,
				'orderby'      => 'date',
			]
		);
		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 2, $query->found_posts );
		$this->assertEqualsCanonicalizing(
			[ $author_1_private_id, $author_2_private_id ],
			wp_list_pluck( $query->posts, 'ID' )
		);
	}

	/**
	 * Private status must not appear in the all-authors clause for users without the cap.
	 *
	 * @since 5.3.5
	 * @group protected-content
	 */
	public function test_private_status_is_not_in_all_authors_clause_for_users_without_capability() {
		set_current_screen( 'edit.php' );
		$this->assertTrue( is_admin() );

		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$author_id = $this->factory->user->create( [ 'role' => 'author' ] );
		wp_set_current_user( $author_id );

		$post = new \ElasticPress\Indexable\Post\Post();
		$args = $post->format_args(
			[
				'post_type'   => [ 'post' ],
				'post_status' => 'private',
			],
			new \WP_Query()
		);

		$should = $args['post_filter']['bool']['should'];
		$this->assertCount( 2, $should );

		$all_authors_statuses = $should[0]['bool']['must'][1]['terms']['post_status'];
		$this->assertNotContains( 'private', $all_authors_statuses );
		$this->assertContains( 'publish', $all_authors_statuses );

		$author_clause_statuses = $should[1]['bool']['must'][1]['terms']['post_status'];
		$this->assertContains( 'private', $author_clause_statuses );
		$this->assertSame( $author_id, $should[1]['bool']['must'][2]['term']['post_author.id'] );
	}

	/**
	 * Authors must see their own custom-private posts, but not other authors'.
	 *
	 * WordPress author-restricts every status from get_post_stati( [ 'private' => true ] ),
	 * not only the built-in `private` status.
	 *
	 * @since 5.3.5
	 * @group protected-content
	 */
	public function test_author_can_see_own_custom_private_status_posts_but_not_others() {
		set_current_screen( 'edit.php' );
		$this->assertTrue( is_admin() );

		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$custom_status = 'ep_custom_private';
		register_post_status(
			$custom_status,
			[
				'label'                     => 'Custom Private',
				'private'                   => true,
				'public'                    => false,
				'protected'                 => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
			]
		);

		try {
			$author_1_id = $this->factory->user->create( [ 'role' => 'author' ] );
			$author_2_id = $this->factory->user->create( [ 'role' => 'author' ] );
			$admin_id    = get_current_user_id();

			$author_1_custom_id = $this->ep_factory->post->create(
				[
					'post_title'  => 'Author 1 Custom Private Post',
					'post_status' => $custom_status,
					'post_author' => $author_1_id,
				]
			);
			$author_2_custom_id = $this->ep_factory->post->create(
				[
					'post_title'  => 'Author 2 Custom Private Secret',
					'post_status' => $custom_status,
					'post_author' => $author_2_id,
				]
			);

			ElasticPress\Elasticsearch::factory()->refresh_indices();

			wp_set_current_user( $author_1_id );
			$this->assertFalse( current_user_can( 'read_private_posts' ) );

			$post = new \ElasticPress\Indexable\Post\Post();
			$args = $post->format_args(
				[
					'post_type'   => [ 'post' ],
					'post_status' => $custom_status,
				],
				new \WP_Query()
			);

			$should               = $args['post_filter']['bool']['should'];
			$all_authors_statuses = $should[0]['bool']['must'][1]['terms']['post_status'];
			$author_statuses      = $should[1]['bool']['must'][1]['terms']['post_status'];
			$this->assertNotContains( $custom_status, $all_authors_statuses );
			$this->assertContains( $custom_status, $author_statuses );
			$this->assertContains( 'private', $author_statuses );

			$query = new \WP_Query(
				[
					'ep_integrate' => true,
					'orderby'      => 'date',
				]
			);
			$this->assertTrue( $query->elasticsearch_success );
			$this->assertContains( $author_1_custom_id, wp_list_pluck( $query->posts, 'ID' ) );
			$this->assertNotContains( $author_2_custom_id, wp_list_pluck( $query->posts, 'ID' ) );

			$query = new \WP_Query(
				[
					'post_status'  => $custom_status,
					'ep_integrate' => true,
					'orderby'      => 'date',
				]
			);
			$this->assertTrue( $query->elasticsearch_success );
			$this->assertEquals( 1, $query->found_posts );
			$this->assertEquals( [ $author_1_custom_id ], wp_list_pluck( $query->posts, 'ID' ) );

			$query = new \WP_Query(
				[
					'post_status'  => $custom_status,
					's'            => 'Author 2 Custom Private Secret',
					'ep_integrate' => true,
				]
			);
			$this->assertTrue( $query->elasticsearch_success );
			$this->assertEquals( 0, $query->found_posts );

			wp_set_current_user( $admin_id );

			$query = new \WP_Query(
				[
					'post_status'  => $custom_status,
					'ep_integrate' => true,
					'orderby'      => 'date',
				]
			);
			$this->assertTrue( $query->elasticsearch_success );
			$this->assertEquals( 2, $query->found_posts );
			$this->assertEqualsCanonicalizing(
				[ $author_1_custom_id, $author_2_custom_id ],
				wp_list_pluck( $query->posts, 'ID' )
			);
		} finally {
			global $wp_post_statuses;
			unset( $wp_post_statuses[ $custom_status ] );
		}
	}
}
