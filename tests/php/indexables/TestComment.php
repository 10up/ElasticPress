<?php // phpcs:ignore
/**
 * Test comment indexable functionality
 *
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress;

/**
 * Test comment indexable class
 */
class TestComment extends BaseTestCase {
	/**
	 * Checking if HTTP request returns 404 status code.
	 *
	 * @var boolean
	 */
	public $is_404 = false;

	/**
	 * Setup each test.
	 *
	 * @since 3.6.0
	 */
	public function set_up() {
		global $wpdb;
		parent::set_up();
		$wpdb->suppress_errors();

		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );

		wp_set_current_user( $admin_id );

		ElasticPress\Features::factory()->activate_feature( 'comments' );
		ElasticPress\Features::factory()->setup_features();

		ElasticPress\Elasticsearch::factory()->delete_all_indices();
		ElasticPress\Indexables::factory()->get( 'comment' )->put_mapping();

		ElasticPress\Indexables::factory()->get( 'comment' )->sync_manager->reset_sync_queue();

		// Need to call this since it's hooked to init.
		ElasticPress\Features::factory()->get_registered_feature( 'comments' )->search_setup();
	}

	/**
	 * Clean up after each test. Reset our mocks
	 *
	 * @since 3.6.0
	 */
	public function tear_down() {
		parent::tear_down();

		$this->deleteAllComments();

		$this->fired_actions = array();
	}

	/**
	 * Deletes all comments from the database.
	 *
	 * @since 3.6.0
	 */
	public function deleteAllComments() {

		foreach ( get_comments() as $comment ) {
			wp_delete_comment( $comment->comment_ID, true );
		}
	}

	/**
	 * Create test comments.
	 *
	 * @param int  $number The number of comments to be created.
	 * @param bool $has_child Create child comment
	 * @return array
	 * @since 3.6.0
	 * @group comments
	 */
	public function createComments( $number = 4, $has_child = false ) {
		$parent_comment_id = 0;
		$child_comment_id  = 0;
		$comment_ids       = [];

		$post_id = $this->ep_factory->post->create();

		if ( $number > 0 ) {
			for ( $i = 1; $i <= $number; $i++ ) {
				$comment_ids[] = $this->ep_factory->comment->create(
					[
						'comment_content' => 'Test comment ' . $i,
						'comment_post_ID' => $post_id,
					]
				);
			}
		}

		if ( $has_child ) {
			$parent_comment_id = $this->ep_factory->comment->create(
				[
					'comment_content' => 'Test parent comment ',
					'comment_post_ID' => $post_id,
				]
			);
			$child_comment_id  = $this->ep_factory->comment->create(
				[
					'comment_content' => 'Test child comment ',
					'comment_post_ID' => $post_id,
					'comment_parent'  => $parent_comment_id,
				]
			);
		}

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		return [
			'post_id'           => $post_id,
			'parent_comment_id' => $parent_comment_id,
			'child_comment_id'  => $child_comment_id,
			'comment_ids'       => $comment_ids,
		];
	}

	/**
	 * Test a comment sync
	 *
	 * @since 3.6.0
	 * @group comments
	 */
	public function testCommentSync() {
		add_action(
			'ep_sync_comment_on_transition',
			function() {
				$this->fired_actions['ep_sync_comment_on_transition'] = true;
			}
		);

		$post_id = $this->ep_factory->post->create();

		$comment_id = wp_insert_comment(
			[
				'comment_content' => 'Test comment',
				'comment_post_ID' => $post_id,
			]
		);

		$this->assertEquals( 1, count( ElasticPress\Indexables::factory()->get( 'comment' )->sync_manager->get_sync_queue() ) );

		ElasticPress\Indexables::factory()->get( 'comment' )->index( $comment_id );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$this->assertArrayHasKey( 'ep_sync_comment_on_transition', $this->fired_actions );

		$this->assertTrue( ! empty( $this->fired_actions['ep_sync_comment_on_transition'] ) );

		$comment = ElasticPress\Indexables::factory()->get( 'comment' )->get( $comment_id );

		$this->assertnotEmpty( $comment );
	}

	/**
	 * Test a comment sync with meta
	 *
	 * @since 3.6.0
	 * @group comment
	 */
	public function testCommentSyncMeta() {
		$post_id = $this->ep_factory->post->create();

		$comment_id = wp_insert_comment(
			[
				'comment_content' => 'Test comment',
				'comment_post_ID' => $post_id,
			]
		);

		update_comment_meta( $comment_id, 'new_meta', 'test' );

		ElasticPress\Indexables::factory()->get( 'comment' )->index( $comment_id );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$comment = ElasticPress\Indexables::factory()->get( 'comment' )->get( $comment_id );

		$this->assertEquals( 'test', $comment['meta']['new_meta'][0]['value'] );
	}

	/**
	 * Test a comment sync on meta update
	 *
	 * @since 3.6.0
	 * @group comment
	 */
	public function testCommentSyncOnMetaUpdate() {
		$post_id = $this->ep_factory->post->create();

		$comment_id = wp_insert_comment(
			[
				'comment_content' => 'Test comment',
				'comment_post_ID' => $post_id,
			]
		);

		update_comment_meta( $comment_id, 'test_key', true );

		$this->assertEquals( 1, count( ElasticPress\Indexables::factory()->get( 'comment' )->sync_manager->get_sync_queue() ) );
		$this->assertnotEmpty( ElasticPress\Indexables::factory()->get( 'comment' )->sync_manager->add_to_queue( $comment_id ) );
	}

	/**
	 * Test comment sync kill.
	 *
	 * @since 3.6.0
	 * @group comment
	 */
	public function testCommentSyncKill() {
		$post_id = $this->ep_factory->post->create();

		$created_comment_id = wp_insert_comment(
			[
				'comment_content' => 'Test comment',
				'comment_post_ID' => $post_id,
			]
		);

		add_filter(
			'ep_comment_sync_kill',
			function( $kill, $comment_id ) use ( $created_comment_id ) {
				if ( $created_comment_id === $comment_id ) {
					return true;
				}

				return $kill;
			},
			10,
			2
		);

		ElasticPress\Indexables::factory()->get( 'comment' )->sync_manager->action_sync_on_update( $created_comment_id );

		$this->assertArrayNotHasKey( 'ep_sync_comment_on_transition', $this->fired_actions );
	}

	/**
	 * Test a basic comment query with and without ElasticPress
	 *
	 * @since 3.6.0
	 * @group comment
	 */
	public function testBasicCommentQuery() {

		$this->createComments( 3 );

		// First try without ES and make sure everything is right.
		$comments_query = new \WP_Comment_Query( [] );

		$properties = get_object_vars( $comments_query );
		$this->assertArrayNotHasKey( 'elasticsearch_success', $properties );

		$comments = $comments_query->get_comments();

		$this->assertEquals( 3, count( $comments ) );

		// Now try with Elasticsearch.
		$comments_query = new \WP_Comment_Query(
			[
				'ep_integrate' => true,
			]
		);

		$this->assertTrue( $comments_query->elasticsearch_success );

		$comments = $comments_query->get_comments();

		$this->assertEquals( 3, count( $comments ) );

		// Test some of the filters and defaults.
		$return_2 = function() {
			return 2;
		};

		add_filter( 'ep_max_results_window', $return_2 );

		// Now try with Elasticsearch.
		$comments_query = new \WP_Comment_Query(
			[
				'ep_integrate' => true,
			]
		);

		$this->assertTrue( $comments_query->elasticsearch_success );

		$comments = $comments_query->get_comments();

		$this->assertEquals( 2, count( $comments ) );
	}

	/**
	 * Test a comment query with number argument
	 *
	 * @since 3.6.0
	 * @group comment
	 */
	public function testCommentQueryNumber() {
		$this->createComments();

		$comments_query = new \WP_Comment_Query(
			[
				'ep_integrate' => true,
				'number'       => 2,
			]
		);

		$this->assertTrue( $comments_query->elasticsearch_success );

		$comments = $comments_query->get_comments();

		$this->assertEquals( 2, count( $comments ) );
	}

	/**
	 * Test a comment query with offset argument
	 *
	 * @since 3.6.0
	 * @group comment
	 */
	public function testCommentQueryOffset() {
		$this->createComments( 6 );

		$comments_query = new \WP_Comment_Query(
			[
				'ep_integrate' => true,
				'offset'       => 3,
			]
		);

		$this->assertTrue( $comments_query->elasticsearch_success );

		$comments = $comments_query->get_comments();

		$this->assertEquals( 3, count( $comments ) );
	}

	/**
	 * Test comment query ordering
	 *
	 * @since 3.6.0
	 * @group comment
	 */
	public function testCommentQueryOrderCommentContent() {
		$this->createComments();

		$comments_query = new \WP_Comment_Query(
			[
				'ep_integrate' => true,
				'orderby'      => 'comment_content',
				'order'        => 'ASC',
			]
		);

		$this->assertTrue( $comments_query->elasticsearch_success );

		$comments = $comments_query->get_comments();

		$this->assertEquals( 'Test comment 4', $comments[3]->comment_content );

		$comments_query = new \WP_Comment_Query(
			[
				'ep_integrate' => true,
				'orderby'      => 'comment_content',
				'order'        => 'DESC',
			]
		);

		$this->assertTrue( $comments_query->elasticsearch_success );

		$comments = $comments_query->get_comments();

		$this->assertEquals( 'Test comment 1', $comments[3]->comment_content );
	}

	/**
	 * Test comment query ordering by comment_post_type field
	 *
	 * Ensure we are using EP when order by comment_post_type
	 *
	 * @since 3.6.0
	 * @group comment
	 */
	public function testCommentQueryOrderCommentPostType() {
		$this->createComments();

		$comments_query = new \WP_Comment_Query(
			[
				'ep_integrate' => true,
				'orderby'      => 'comment_post_type',
			]
		);
		$comments       = $comments_query->get_comments();

		$this->assertTrue( $comments_query->elasticsearch_success );
		$this->assertNotEmpty( $comments );
	}

	/**
	 * Test comment query ordering by comment id.
	 *
	 * @since 3.6.0
	 * @group comment
	 */
	public function testCommentQueryOrderCommentID() {
		$this->createComments();

		$comments_query = new \WP_Comment_Query(
			[
				'ep_integrate' => true,
				'orderby'      => 'comment_ID',
			]
		);

		$this->assertTrue( $comments_query->elasticsearch_success );

		$comments = $comments_query->get_comments();

		$ids = wp_list_pluck( $comments, 'comment_ID' );
		$this->assertGreaterThan( $ids[1], $ids[0] );
		$this->assertGreaterThan( $ids[2], $ids[1] );
		$this->assertGreaterThan( $ids[3], $ids[2] );

		$comments_query = new \WP_Comment_Query(
			[
				'ep_integrate' => true,
				'orderby'      => 'comment_ID',
				'order'        => 'ASC',
			]
		);

		$this->assertTrue( $comments_query->elasticsearch_success );

		$comments = $comments_query->get_comments();

		$ids = wp_list_pluck( $comments, 'comment_ID' );
		$this->assertLessThan( $ids[1], $ids[0] );
		$this->assertLessThan( $ids[2], $ids[1] );
		$this->assertLessThan( $ids[3], $ids[2] );
	}

	/**
	 * Test comment query ordering by comment post id.
	 *
	 * @since 3.6.0
	 * @group comment
	 */
	public function testCommentQueryOrderCommentPostID() {
		$post_id_1 = $this->ep_factory->post->create();
		$post_id_2 = $this->ep_factory->post->create();

		$comment_ids[] = $this->ep_factory->comment->create(
			[
				'comment_content' => 'Test comment 1',
				'comment_post_ID' => $post_id_1,
			]
		);
		$comment_ids[] = $this->ep_factory->comment->create(
			[
				'comment_content' => 'Test comment 2',
				'comment_post_ID' => $post_id_2,
			]
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$comments_query = new \WP_Comment_Query(
			[
				'ep_integrate' => true,
				'orderby'      => 'comment_post_ID',
			]
		);

		$this->assertTrue( $comments_query->elasticsearch_success );

		$comments = $comments_query->get_comments();

		$this->assertEquals( 'Test comment 2', $comments[0]->comment_content );
		$this->assertEquals( 'Test comment 1', $comments[1]->comment_content );

		$comments_query = new \WP_Comment_Query(
			[
				'ep_integrate' => true,
				'orderby'      => 'comment_post_ID',
				'order'        => 'ASC',
			]
		);

		$this->assertTrue( $comments_query->elasticsearch_success );

		$comments = $comments_query->get_comments();

		$this->assertEquals( 'Test comment 2', $comments[1]->comment_content );
		$this->assertEquals( 'Test comment 1', $comments[0]->comment_content );
	}

	/**
	 * Test comment query returning ids.
	 *
	 * @since 3.6.0
	 * @group comment
	 */
	public function testCommentQueryIds() {

		$created_comments = $this->createComments( 3 );

		$comments_query = new \WP_Comment_Query(
			[
				'ep_integrate' => true,
				'fields'       => 'ids',
			]
		);

		$this->assertTrue( $comments_query->elasticsearch_success );

		$comments = $comments_query->get_comments();

		foreach ( $comments as $comment ) {
			$this->assertContains( (int) $comment, $created_comments['comment_ids'] );
		}

		$this->assertEquals( 3, count( $comments ) );
	}

	/**
	 * Test comment query returning ids.
	 *
	 * @since 3.6.0
	 * @group comment
	 */
	public function testCommentQueryCount() {

		$this->createComments( 3 );

		$comments_query = new \WP_Comment_Query(
			[
				'ep_integrate' => true,
				'fields'       => 'count',
			]
		);

		$this->assertTrue( $comments_query->elasticsearch_success );

		$comments = $comments_query->get_comments();

		$this->assertEquals( 3, $comments );
	}

	/**
	 * Test comment query with hierarchical argument.
	 *
	 * @since 3.6.0
	 * @group comment
	 */
	public function testCommentQueryHierarchical() {
		$created_comments = $this->createComments( 0, true );

		$comments_query = new \WP_Comment_Query(
			[
				'hierarchical' => 'threaded',
				'ep_integrate' => true,
			]
		);

		$this->assertTrue( $comments_query->elasticsearch_success );

		$comments = $comments_query->get_comments();

		$this->assertEquals( 1, count( $comments ) );
		$parent_comment = reset( $comments );
		$this->assertNotFalse( $parent_comment->get_child( $created_comments['child_comment_id'] ) );

		$comments_query = new \WP_Comment_Query(
			[
				'hierarchical' => 'flat',
				'ep_integrate' => true,
			]
		);

		$this->assertTrue( $comments_query->elasticsearch_success );

		$comments = $comments_query->get_comments();

		$this->assertEquals( 2, count( $comments ) );
		foreach ( $comments as $comment ) {
			$this->assertFalse( $comment->get_child( $created_comments['child_comment_id'] ) );
		}
	}

	/**
	 * Test comment query after deleting a comment.
	 *
	 * @since 3.6.0
	 * @group comment
	 */
	public function testCommentDelete() {
		add_action(
			'ep_sync_comment_on_transition',
			function() {
				$this->fired_actions['ep_sync_comment_on_transition'] = true;
			}
		);

		add_action(
			'deleted_comment',
			function() {
				$this->fired_actions['deleted_comment'] = true;
			}
		);

		$post_id = $this->ep_factory->post->create();

		$comment_id = wp_insert_comment(
			[
				'comment_content' => 'Test comment',
				'comment_post_ID' => $post_id,
			]
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$this->assertEquals( 1, count( ElasticPress\Indexables::factory()->get( 'comment' )->sync_manager->get_sync_queue() ) );

		ElasticPress\Indexables::factory()->get( 'comment' )->index( $comment_id );

		$this->assertArrayHasKey( 'ep_sync_comment_on_transition', $this->fired_actions );

		$this->assertNotEmpty( $this->fired_actions['ep_sync_comment_on_transition'] );

		$comment = ElasticPress\Indexables::factory()->get( 'comment' )->get( $comment_id );

		$this->assertNotEmpty( $comment );

		wp_delete_comment( $comment_id, true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$this->assertArrayHasKey( 'deleted_comment', $this->fired_actions );

		$this->assertNotEmpty( $this->fired_actions['deleted_comment'] );

		$comment = ElasticPress\Indexables::factory()->get( 'comment' )->get( $comment_id );

		$this->assertEmpty( $comment );
	}

	/**
	 * Test comment query pagination.
	 *
	 * @since 3.6.0
	 * @group comment
	 */
	public function testCommentQueryPaged() {

		$this->createComments( 7 );

		$comments_query = new \WP_Comment_Query(
			[
				'ep_integrate' => true,
				'paged'        => 2,
				'number'       => 4,
			]
		);
		$comments       = $comments_query->get_comments();

		$this->assertTrue( $comments_query->elasticsearch_success );
		$this->assertEquals( 3, count( $comments ) );
	}

	/**
	 * Test comment query by author email.
	 *
	 * @since 3.6.0
	 * @group comment
	 */
	public function testCommentQueryAuthorEmail() {
		$post_id = $this->ep_factory->post->create();

		$this->ep_factory->comment->create(
			[
				'comment_content'      => 'Test comment 1',
				'comment_post_ID'      => $post_id,
				'comment_author_email' => 'joe@example.com',
			]
		);

		$this->ep_factory->comment->create(
			[
				'comment_content'      => 'Test comment 2',
				'comment_post_ID'      => $post_id,
				'comment_author_email' => 'doe@example.com',
			]
		);

		$this->ep_factory->comment->create(
			[
				'comment_content'      => 'Test comment 3',
				'comment_post_ID'      => $post_id,
				'comment_author_email' => 'joe@example.com',
			]
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$comments_query = new \WP_Comment_Query(
			[
				'ep_integrate' => true,
				'author_email' => 'joe@example.com',
			]
		);

		$this->assertTrue( $comments_query->elasticsearch_success );

		$comments = $comments_query->get_comments();

		foreach ( $comments as $comment ) {
			$this->assertEquals( 'joe@example.com', $comment->comment_author_email );
		}

		$this->assertEquals( 2, count( $comments ) );

		$comments_query = new \WP_Comment_Query(
			[
				'ep_integrate' => true,
				'orderby'      => 'comment_author_email',
				'order'        => 'ASC',
			]
		);

		$comments = $comments_query->get_comments();

		$this->assertTrue( $comments_query->elasticsearch_success );
		$this->assertEquals( 'doe@example.com', $comments[0]->comment_author_email );
	}

	/**
	 * Test comment query by author URL.
	 *
	 * @since 3.6.0
	 * @group comment
	 */
	public function testCommentQueryAuthorUrl() {
		$post_id = $this->ep_factory->post->create();

		$this->ep_factory->comment->create(
			[
				'comment_content'      => 'Test comment 1',
				'comment_post_ID'      => $post_id,
				'comment_author_email' => 'joe@example.com',
				'comment_author_url'   => 'http://example.com',
			]
		);

		$this->ep_factory->comment->create(
			[
				'comment_content'      => 'Test comment 2',
				'comment_post_ID'      => $post_id,
				'comment_author_email' => 'doe@example.com',
				'comment_author_url'   => 'http://example.com',
			]
		);

		$this->ep_factory->comment->create(
			[
				'comment_content'      => 'Test comment 3',
				'comment_post_ID'      => $post_id,
				'comment_author_email' => 'hoe@example.com',
				'comment_author_url'   => 'http://example.org',
			]
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$comments_query = new \WP_Comment_Query(
			[
				'ep_integrate' => true,
				'author_url'   => 'http://example.com',
			]
		);
		$comments       = $comments_query->get_comments();

		$this->assertTrue( $comments_query->elasticsearch_success );

		foreach ( $comments as $comment ) {
			$this->assertEquals( 'http://example.com', $comment->comment_author_url );
		}

		$this->assertEquals( 2, count( $comments ) );

		$comments_query = new \WP_Comment_Query(
			[
				'ep_integrate' => true,
				'orderby'      => 'comment_author_url',
				'order'        => 'ASC',
			]
		);
		$comments       = $comments_query->get_comments();

		$this->assertTrue( $comments_query->elasticsearch_success );
		$this->assertEquals( 'http://example.com', $comments[0]->comment_author_url );
	}

	/**
	 * Test comment query by user id.
	 *
	 * @since 3.6.0
	 * @group comment
	 */
	public function testCommentQueryUserId() {
		$current_user_id = get_current_user_id();

		$post_id = $this->ep_factory->post->create();

		$this->ep_factory->comment->create(
			[
				'comment_content' => 'Test comment 1',
				'comment_post_ID' => $post_id,
				'user_id'         => $current_user_id,
			]
		);

		$this->ep_factory->comment->create(
			[
				'comment_content' => 'Test comment 2',
				'comment_post_ID' => $post_id,
				'user_id'         => $current_user_id,
			]
		);

		$this->ep_factory->comment->create(
			[
				'comment_content' => 'Test comment 3',
				'comment_post_ID' => $post_id,
				'user_id'         => $current_user_id,
			]
		);

		$this->ep_factory->comment->create(
			[
				'comment_content' => 'Test comment 4',
				'comment_post_ID' => $post_id,
			]
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$comments_query = new \WP_Comment_Query(
			[
				'ep_integrate' => true,
				'user_id'      => $current_user_id,
			]
		);

		$this->assertTrue( $comments_query->elasticsearch_success );

		$comments = $comments_query->get_comments();

		foreach ( $comments as $comment ) {
			$this->assertEquals( $current_user_id, $comment->user_id );
		}

		$this->assertEquals( 3, count( $comments ) );
	}

	/**
	 * Test comment query with author__in argument.
	 *
	 * @since 3.6.0
	 * @group comment
	 */
	public function testCommentQueryAuthorIn() {
		$current_user_id   = get_current_user_id();
		$another_author_id = $this->factory->user->create( array( 'role' => 'administrator' ) );

		$post_id = $this->ep_factory->post->create();

		$this->ep_factory->comment->create(
			[
				'comment_content' => 'Test comment 1',
				'comment_post_ID' => $post_id,
				'user_id'         => $current_user_id,
			]
		);

		$this->ep_factory->comment->create(
			[
				'comment_content' => 'Test comment 2',
				'comment_post_ID' => $post_id,
				'user_id'         => $current_user_id,
			]
		);

		$this->ep_factory->comment->create(
			[
				'comment_content' => 'Test comment 3',
				'comment_post_ID' => $post_id,
				'user_id'         => $another_author_id,
			]
		);

		$this->ep_factory->comment->create(
			[
				'comment_content' => 'Test comment 4',
				'comment_post_ID' => $post_id,
			]
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$comments_query = new \WP_Comment_Query(
			[
				'ep_integrate' => true,
				'author__in'   => [ $current_user_id, $another_author_id ],
			]
		);

		$this->assertTrue( $comments_query->elasticsearch_success );

		$comments = $comments_query->get_comments();

		foreach ( $comments as $comment ) {
			$this->assertContains( (int) $comment->user_id, [ $current_user_id, $another_author_id ] );
		}

		$this->assertEquals( 3, count( $comments ) );
	}

	/**
	 * Test comment query with author__not_in.
	 *
	 * @since 3.6.0
	 * @group comment
	 */
	public function testCommentQueryAuthorNotIn() {
		$current_user_id   = get_current_user_id();
		$another_author_id = $this->factory->user->create( array( 'role' => 'administrator' ) );

		$post_id = $this->ep_factory->post->create();

		$this->ep_factory->comment->create(
			[
				'comment_content' => 'Test comment 1',
				'comment_post_ID' => $post_id,
				'user_id'         => $current_user_id,
			]
		);

		$this->ep_factory->comment->create(
			[
				'comment_content' => 'Test comment 2',
				'comment_post_ID' => $post_id,
				'user_id'         => $current_user_id,
			]
		);

		$this->ep_factory->comment->create(
			[
				'comment_content' => 'Test comment 3',
				'comment_post_ID' => $post_id,
				'user_id'         => $another_author_id,
			]
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$comments_query = new \WP_Comment_Query(
			[
				'ep_integrate'   => true,
				'author__not_in' => [ $another_author_id ],
			]
		);

		$this->assertTrue( $comments_query->elasticsearch_success );

		$comments = $comments_query->get_comments();

		foreach ( $comments as $comment ) {
			$this->assertEquals( $current_user_id, $comment->user_id );
		}

		$this->assertEquals( 2, count( $comments ) );
	}

	/**
	 * Test comment query with comment__in argument.
	 *
	 * @since 3.6.0
	 * @group comment
	 */
	public function testCommentQueryCommentIn() {
		$created_comments = $this->createComments();

		$test_comments = [ $created_comments['comment_ids'][0], $created_comments['comment_ids'][1] ];

		$comments_query = new \WP_Comment_Query(
			[
				'ep_integrate' => true,
				'comment__in'  => $test_comments,
				'number'       => 2,
			]
		);

		$this->assertTrue( $comments_query->elasticsearch_success );

		$comments = $comments_query->get_comments();

		foreach ( $comments as $comment ) {
			$this->assertContains( (int) $comment->comment_ID, $test_comments );
		}

		$this->assertEquals( 2, count( $comments ) );
	}

	/**
	 * Test comment query with comment__not_in argument.
	 *
	 * @since 3.6.0
	 * @group comment
	 */
	public function testCommentQueryCommentNotIn() {
		$created_comments = $this->createComments( 5 );

		$test_comments = [ $created_comments['comment_ids'][0], $created_comments['comment_ids'][1] ];

		$comments_query = new \WP_Comment_Query(
			[
				'ep_integrate'    => true,
				'comment__not_in' => $test_comments,
			]
		);

		$this->assertTrue( $comments_query->elasticsearch_success );

		$comments = $comments_query->get_comments();

		foreach ( $comments as $comment ) {
			$this->assertNotContains( $comment->comment_ID, $test_comments );
		}

		$this->assertEquals( 3, count( $comments ) );
	}

	/**
	 * Test comment query ordering by comment date.
	 *
	 * @since 3.6.0
	 * @group comment
	 */
	public function testCommentQueryDateQuery() {

		$post_id   = $this->ep_factory->post->create();
		$in_range  = [];
		$out_range = [];

		$in_range[] = $this->ep_factory->comment->create(
			[
				'comment_content'  => 'Test comment',
				'comment_post_ID'  => $post_id,
				'comment_date_gmt' => '2020-05-21',
				'comment_date'     => '2020-05-21',
			]
		);

		$out_range[] = $this->ep_factory->comment->create(
			[
				'comment_content'  => 'Test comment',
				'comment_post_ID'  => $post_id,
				'comment_date_gmt' => '2020-05-19',
				'comment_date'     => '2020-05-19',
			]
		);

		$in_range[] = $this->ep_factory->comment->create(
			[
				'comment_content'  => 'Test comment',
				'comment_post_ID'  => $post_id,
				'comment_date_gmt' => '2020-05-25',
				'comment_date'     => '2020-05-25',
			]
		);

		$out_range[] = $this->ep_factory->comment->create(
			[
				'comment_content'  => 'Test comment',
				'comment_post_ID'  => $post_id,
				'comment_date_gmt' => '2020-05-29',
				'comment_date'     => '2020-05-29',
			]
		);

		$out_range[] = $this->ep_factory->comment->create(
			[
				'comment_content'  => 'Test comment',
				'comment_post_ID'  => $post_id,
				'comment_date_gmt' => '2020-06-15',
				'comment_date'     => '2020-06-15',
			]
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$date_query = array(
			'relation' => 'AND',
			array(
				'column' => 'comment_date',
				'after'  => '2020-05-20',
				'before' => '2020-05-27',
			),
		);

		$comments_query = new \WP_Comment_Query(
			[
				'ep_integrate' => true,
				'date_query'   => $date_query,
			]
		);

		$this->assertTrue( $comments_query->elasticsearch_success );

		$comments = $comments_query->get_comments();

		foreach ( $comments as $comment ) {
			$this->assertContains( (int) $comment->comment_ID, $in_range );
			$this->assertNotContains( (int) $comment->comment_ID, $out_range );
		}

		$this->assertEquals( 2, count( $comments ) );

		$comments_query = new \WP_Comment_Query(
			[
				'ep_integrate' => true,
				'orderby'      => 'comment_date',
				'order'        => 'ASC',
			]
		);

		$this->assertTrue( $comments_query->elasticsearch_success );

		$comments = $comments_query->get_comments();

		$this->assertEquals( '2020-05-19 00:00:00', $comments[0]->comment_date );
		$this->assertEquals( '2020-05-21 00:00:00', $comments[1]->comment_date );
		$this->assertEquals( '2020-06-15 00:00:00', $comments[4]->comment_date );

		$comments_query = new \WP_Comment_Query(
			[
				'ep_integrate' => true,
				'orderby'      => 'comment_date',
				'order'        => 'DESC',
			]
		);

		$this->assertTrue( $comments_query->elasticsearch_success );

		$comments = $comments_query->get_comments();

		$this->assertEquals( '2020-05-19 00:00:00', $comments[4]->comment_date );
		$this->assertEquals( '2020-05-21 00:00:00', $comments[3]->comment_date );
		$this->assertEquals( '2020-06-15 00:00:00', $comments[0]->comment_date );
	}

	/**
	 * Test comment query with karm argument.
	 *
	 * @since 3.6.0
	 * @group comment
	 */
	public function testCommentQueryKarma() {

		$post_id   = $this->ep_factory->post->create();
		$match     = [];
		$not_match = [];

		$match[] = $this->ep_factory->comment->create(
			[
				'comment_content' => 'Test comment',
				'comment_post_ID' => $post_id,
				'comment_karma'   => 9,
			]
		);

		$not_match[] = $this->ep_factory->comment->create(
			[
				'comment_content' => 'Test comment',
				'comment_post_ID' => $post_id,
				'comment_karma'   => 3,
			]
		);

		$match[] = $this->ep_factory->comment->create(
			[
				'comment_content' => 'Test comment',
				'comment_post_ID' => $post_id,
				'comment_karma'   => 9,
			]
		);

		$not_match[] = $this->ep_factory->comment->create(
			[
				'comment_content' => 'Test comment',
				'comment_post_ID' => $post_id,
				'comment_karma'   => 1,
			]
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$comments_query = new \WP_Comment_Query(
			[
				'ep_integrate' => true,
				'karma'        => 9,
			]
		);

		$this->assertTrue( $comments_query->elasticsearch_success );

		$comments = $comments_query->get_comments();

		foreach ( $comments as $comment ) {
			$this->assertContains( (int) $comment->comment_ID, $match );
			$this->assertNotContains( (int) $comment->comment_ID, $not_match );
		}

		$this->assertEquals( 2, count( $comments ) );
	}

	/**
	 * Test comment query using comment meta.
	 *
	 * @since 3.6.0
	 * @group comment
	 */
	public function testCommentQueryMeta() {

		$post_id = $this->ep_factory->post->create();
		$match   = [];

		$match[] = $this->ep_factory->comment->create(
			[
				'comment_content' => 'Test comment',
				'comment_post_ID' => $post_id,
				'comment_meta'    => [
					'test_meta' => 'test_value',
				],
			]
		);

		$not_match = $this->ep_factory->comment->create(
			[
				'comment_content' => 'Test comment',
				'comment_post_ID' => $post_id,
			]
		);

		$match[] = $this->ep_factory->comment->create(
			[
				'comment_content' => 'Test comment',
				'comment_post_ID' => $post_id,
				'comment_meta'    => [
					'test_meta' => 'test_value',
				],
			]
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$comments_query = new \WP_Comment_Query(
			[
				'ep_integrate' => true,
				'meta_key'     => 'test_meta',
				'meta_value'   => 'test_value',
			]
		);

		$this->assertTrue( $comments_query->elasticsearch_success );

		$comments = $comments_query->get_comments();

		foreach ( $comments as $comment ) {
			$this->assertContains( (int) $comment->comment_ID, $match );
			$this->assertNotEquals( (int) $comment->comment_ID, $not_match );
		}

		$this->assertEquals( 2, count( $comments ) );
	}

	/**
	 * Test comment query using meta_query.
	 *
	 * @since 3.6.0
	 * @group comment
	 */
	public function testCommentQueryMetaQuery() {

		$post_id = $this->ep_factory->post->create();
		$match   = [];

		$not_match = $this->ep_factory->comment->create(
			[
				'comment_content' => 'Test comment',
				'comment_post_ID' => $post_id,
				'comment_meta'    => [
					'weight' => 10,
				],
			]
		);

		$match[] = $this->ep_factory->comment->create(
			[
				'comment_content' => 'Test comment',
				'comment_post_ID' => $post_id,
				'comment_meta'    => [
					'weight' => 20,
				],
			]
		);

		$match[] = $this->ep_factory->comment->create(
			[
				'comment_content' => 'Test comment',
				'comment_post_ID' => $post_id,
				'comment_meta'    => [
					'weight' => 50,
				],
			]
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$comments_query = new \WP_Comment_Query(
			[
				'ep_integrate' => true,
				'meta_query'   => [
					[
						'key'     => 'weight',
						'value'   => 15,
						'compare' => '>',
					],
				],
			]
		);

		$this->assertTrue( $comments_query->elasticsearch_success );

		$comments = $comments_query->get_comments();

		foreach ( $comments as $comment ) {
			$this->assertContains( (int) $comment->comment_ID, $match );
			$this->assertNotEquals( (int) $comment->comment_ID, $not_match );
		}

		$this->assertEquals( 2, count( $comments ) );
	}

	/**
	 * Test comment query with parent__in argument.
	 *
	 * @since 3.6.0
	 * @group comment
	 */
	public function testCommentQueryParentIn() {
		$created_comments = $this->createComments( 3, true );

		$comments_query = new \WP_Comment_Query(
			[
				'ep_integrate' => true,
				'parent__in'   => [ $created_comments['parent_comment_id'] ],
			]
		);

		$this->assertTrue( $comments_query->elasticsearch_success );

		$comments = $comments_query->get_comments();

		foreach ( $comments as $comment ) {
			$this->assertEquals( $created_comments['child_comment_id'], $comment->comment_ID );
		}

		$this->assertEquals( 1, count( $comments ) );
	}

	/**
	 * Test comment query with parent__not_in.
	 *
	 * @since 3.6.0
	 * @group comment
	 */
	public function testCommentQueryParentNotIn() {
		$created_comments = $this->createComments( 3, true );

		$comments_query = new \WP_Comment_Query(
			[
				'ep_integrate'   => true,
				'parent__not_in' => [ $created_comments['parent_comment_id'] ],
			]
		);

		$this->assertTrue( $comments_query->elasticsearch_success );

		$comments = $comments_query->get_comments();

		foreach ( $comments as $comment ) {
			$this->assertNotEquals( $created_comments['child_comment_id'], $comment->comment_ID );
		}

		$this->assertEquals( 4, count( $comments ) );
	}

	/**
	 * Test comment query by post author.
	 *
	 * @since 3.6.0
	 * @group comment
	 */
	public function testCommentQueryPostAuthor() {

		$user_id_1 = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$user_id_2 = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$user_id_3 = $this->factory->user->create( array( 'role' => 'administrator' ) );

		$post_id_1 = $this->ep_factory->post->create( [ 'post_author' => $user_id_1 ] );
		$post_id_2 = $this->ep_factory->post->create( [ 'post_author' => $user_id_1 ] );
		$post_id_3 = $this->ep_factory->post->create( [ 'post_author' => $user_id_2 ] );
		$post_id_4 = $this->ep_factory->post->create( [ 'post_author' => $user_id_3 ] );

		$this->ep_factory->comment->create(
			[
				'comment_content' => 'Test comment',
				'comment_post_ID' => $post_id_1,
			]
		);

		$this->ep_factory->comment->create(
			[
				'comment_content' => 'Test comment',
				'comment_post_ID' => $post_id_1,
			]
		);

		$this->ep_factory->comment->create(
			[
				'comment_content' => 'Test comment',
				'comment_post_ID' => $post_id_2,
			]
		);

		$this->ep_factory->comment->create(
			[
				'comment_content' => 'Test comment',
				'comment_post_ID' => $post_id_2,
			]
		);

		$this->ep_factory->comment->create(
			[
				'comment_content' => 'Test comment',
				'comment_post_ID' => $post_id_3,
			]
		);

		$this->ep_factory->comment->create(
			[
				'comment_content' => 'Test comment',
				'comment_post_ID' => $post_id_4,
			]
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$comments_query = new \WP_Comment_Query(
			[
				'ep_integrate' => true,
				'post_author'  => $user_id_1,
			]
		);

		$this->assertTrue( $comments_query->elasticsearch_success );

		$comments = $comments_query->get_comments();

		foreach ( $comments as $comment ) {
			$this->assertContains( (int) $comment->comment_post_ID, [ $post_id_1, $post_id_2 ] );
		}

		$this->assertEquals( 4, count( $comments ) );

		$comments_query = new \WP_Comment_Query(
			[
				'ep_integrate'    => true,
				'post_author__in' => [ $user_id_1, $user_id_2 ],
			]
		);

		$this->assertTrue( $comments_query->elasticsearch_success );

		$comments = $comments_query->get_comments();

		foreach ( $comments as $comment ) {
			$this->assertContains( (int) $comment->comment_post_ID, [ $post_id_1, $post_id_2, $post_id_3 ] );
		}

		$this->assertEquals( 5, count( $comments ) );

		$comments_query = new \WP_Comment_Query(
			[
				'ep_integrate'        => true,
				'post_author__not_in' => [ $user_id_1 ],
			]
		);

		$this->assertTrue( $comments_query->elasticsearch_success );

		$comments = $comments_query->get_comments();

		foreach ( $comments as $comment ) {
			$this->assertContains( (int) $comment->comment_post_ID, [ $post_id_3, $post_id_4 ] );
		}

		$this->assertEquals( 2, count( $comments ) );
	}

	/**
	 * Test comment query by post id.
	 *
	 * @since 3.6.0
	 * @group comment
	 */
	public function testCommentQueryPostId() {
		$this->createComments();
		$created_comments = $this->createComments( 3 );

		$comments_query = new \WP_Comment_Query(
			[
				'ep_integrate' => true,
				'post_id'      => $created_comments['post_id'],
			]
		);

		$this->assertTrue( $comments_query->elasticsearch_success );

		$comments = $comments_query->get_comments();

		foreach ( $comments as $comment ) {
			$this->assertContains( (int) $comment->comment_ID, $created_comments['comment_ids'] );
		}

		$this->assertEquals( 3, count( $comments ) );

		$comments_query = new \WP_Comment_Query(
			[
				'ep_integrate' => true,
				'post__in'     => [ $created_comments['post_id'] ],
			]
		);

		$this->assertTrue( $comments_query->elasticsearch_success );

		$comments = $comments_query->get_comments();

		foreach ( $comments as $comment ) {
			$this->assertContains( (int) $comment->comment_ID, $created_comments['comment_ids'] );
		}

		$this->assertEquals( 3, count( $comments ) );

		$comments_query = new \WP_Comment_Query(
			[
				'ep_integrate' => true,
				'post__not_in' => [ $created_comments['post_id'] ],
			]
		);

		$this->assertTrue( $comments_query->elasticsearch_success );

		$comments = $comments_query->get_comments();

		foreach ( $comments as $comment ) {
			$this->assertNotContains( $comment->comment_post_ID, $created_comments['comment_ids'] );
		}

		$this->assertEquals( 4, count( $comments ) );
	}

	/**
	 * Test comment query by post status.
	 *
	 * @since 3.6.0
	 * @group comment
	 */
	public function testCommentQueryPostStatus() {

		$post_id_1 = $this->ep_factory->post->create( [ 'post_status' => 'publish' ] );
		$post_id_2 = $this->ep_factory->post->create( [ 'post_status' => 'draft' ] );

		$this->ep_factory->comment->create(
			[
				'comment_content' => 'Test comment',
				'comment_post_ID' => $post_id_1,
			]
		);

		$this->ep_factory->comment->create(
			[
				'comment_content' => 'Test comment',
				'comment_post_ID' => $post_id_1,
			]
		);

		$this->ep_factory->comment->create(
			[
				'comment_content' => 'Test comment',
				'comment_post_ID' => $post_id_1,
			]
		);

		$this->ep_factory->comment->create(
			[
				'comment_content' => 'Test comment',
				'comment_post_ID' => $post_id_2,
			]
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$comments_query = new \WP_Comment_Query(
			[
				'ep_integrate' => true,
				'post_status'  => 'publish',
			]
		);

		$this->assertTrue( $comments_query->elasticsearch_success );

		$comments = $comments_query->get_comments();

		foreach ( $comments as $comment ) {
			$this->assertEquals( $comment->comment_post_ID, $post_id_1 );
		}

		$this->assertEquals( 3, count( $comments ) );

		$comments_query = new \WP_Comment_Query(
			[
				'ep_integrate' => true,
				'post_status'  => [ 'draft', 'publish' ],
			]
		);

		$this->assertTrue( $comments_query->elasticsearch_success );

		$comments = $comments_query->get_comments();

		$this->assertEquals( 4, count( $comments ) );
	}

	/**
	 * Test comment query by post type.
	 *
	 * @since 3.6.0
	 * @group comment
	 */
	public function testCommentQueryPostType() {

		$post_id_1 = $this->ep_factory->post->create( [ 'post_type' => 'post' ] );
		$post_id_2 = $this->ep_factory->post->create( [ 'post_type' => 'page' ] );
		$post_id_3 = $this->ep_factory->post->create( [ 'post_type' => 'post' ] );

		$this->ep_factory->comment->create(
			[
				'comment_content' => 'Test comment',
				'comment_post_ID' => $post_id_1,
			]
		);

		$this->ep_factory->comment->create(
			[
				'comment_content' => 'Test comment',
				'comment_post_ID' => $post_id_2,
			]
		);

		$this->ep_factory->comment->create(
			[
				'comment_content' => 'Test comment',
				'comment_post_ID' => $post_id_2,
			]
		);

		$this->ep_factory->comment->create(
			[
				'comment_content' => 'Test comment',
				'comment_post_ID' => $post_id_3,
			]
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$comments_query = new \WP_Comment_Query(
			[
				'ep_integrate' => true,
				'post_type'    => 'post',
			]
		);

		$this->assertTrue( $comments_query->elasticsearch_success );

		$comments = $comments_query->get_comments();

		foreach ( $comments as $comment ) {
			$this->assertContains( (int) $comment->comment_post_ID, [ $post_id_1, $post_id_3 ] );
		}

		$this->assertEquals( 2, count( $comments ) );

		$comments_query = new \WP_Comment_Query(
			[
				'ep_integrate' => true,
				'post_type'    => [ 'post', 'page' ],
			]
		);

		$this->assertTrue( $comments_query->elasticsearch_success );

		$comments = $comments_query->get_comments();

		foreach ( $comments as $comment ) {
			$this->assertContains( (int) $comment->comment_post_ID, [ $post_id_1, $post_id_2, $post_id_3 ] );
		}

		$this->assertEquals( 4, count( $comments ) );
	}

	/**
	 * Test comment query with post_parent argument.
	 *
	 * @since 3.6.0
	 * @group comment
	 */
	public function testCommentQueryPostParent() {

		$post_id_1 = $this->ep_factory->post->create( [ 'post_type' => 'page' ] );
		$post_id_2 = $this->ep_factory->post->create(
			[
				'post_type'   => 'page',
				'post_parent' => $post_id_1,
			]
		);

		$this->ep_factory->comment->create(
			[
				'comment_content' => 'Test comment',
				'comment_post_ID' => $post_id_1,
			]
		);

		$this->ep_factory->comment->create(
			[
				'comment_content' => 'Test comment',
				'comment_post_ID' => $post_id_2,
			]
		);

		$this->ep_factory->comment->create(
			[
				'comment_content' => 'Test comment',
				'comment_post_ID' => $post_id_2,
			]
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$comments_query = new \WP_Comment_Query(
			[
				'ep_integrate' => true,
				'post_parent'  => $post_id_1,
			]
		);

		$this->assertTrue( $comments_query->elasticsearch_success );

		$comments = $comments_query->get_comments();

		foreach ( $comments as $comment ) {
			$this->assertEquals( $post_id_2, $comment->comment_post_ID );
		}

		$this->assertEquals( 2, count( $comments ) );
	}

	/**
	 * Test comment query search.
	 *
	 * @since 3.6.0
	 * @group comment
	 */
	public function testCommentQuerySearch() {
		$post_id = $this->ep_factory->post->create();

		$this->ep_factory->comment->create(
			[
				'comment_content' => 'Test comment 1',
				'comment_post_ID' => $post_id,
			]
		);

		$this->ep_factory->comment->create(
			[
				'comment_content' => 'Test comment 2',
				'comment_post_ID' => $post_id,
			]
		);

		$comment_id = $this->ep_factory->comment->create(
			[
				'comment_content' => 'Test comment 3',
				'comment_post_ID' => $post_id,
			]
		);

		update_comment_meta( $comment_id, 'test_meta_key', 'start here' );

		ElasticPress\Indexables::factory()->get( 'comment' )->index( $comment_id, true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$comments_query = new \WP_Comment_Query(
			[
				'search' => 'test comment',
			]
		);

		$this->assertTrue( $comments_query->elasticsearch_success );

		$comments = $comments_query->get_comments();

		$this->assertEquals( 3, count( $comments ) );

		$comments_query = new \WP_Comment_Query(
			[
				'search'        => 'start',
				'search_fields' => [
					'meta' => [
						'test_meta_key',
					],
				],
			]
		);

		$this->assertTrue( $comments_query->elasticsearch_success );

		$comments = $comments_query->get_comments();

		foreach ( $comments as $comment ) {
			$this->assertEquals( $comment_id, $comment->comment_ID );
		}

		$this->assertEquals( 1, count( $comments ) );
	}

	/**
	 * Test comment query by status.
	 *
	 * @since 3.6.0
	 * @group comment
	 */
	public function testCommentQueryStatus() {

		$post_id = $this->ep_factory->post->create();
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );

		$this->ep_factory->comment->create(
			[
				'comment_content'  => 'Test comment 1',
				'comment_post_ID'  => $post_id,
				'comment_approved' => 1,
			]
		);

		$this->ep_factory->comment->create(
			[
				'comment_content'  => 'Test comment 2',
				'comment_post_ID'  => $post_id,
				'comment_approved' => 0,
				'user_id'          => $user_id,
			]
		);

		$this->ep_factory->comment->create(
			[
				'comment_content'      => 'Test comment 3',
				'comment_post_ID'      => $post_id,
				'comment_approved'     => 0,
				'comment_author_email' => 'joe@example.com',
			]
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$comments_query = new \WP_Comment_Query(
			[
				'ep_integrate' => true,
				'status'       => 'approve',
			]
		);

		$this->assertTrue( $comments_query->elasticsearch_success );

		$comments = $comments_query->get_comments();

		$this->assertEquals( 1, count( $comments ) );

		$comments_query = new \WP_Comment_Query(
			[
				'ep_integrate' => true,
				'status'       => 'hold',
			]
		);

		$this->assertTrue( $comments_query->elasticsearch_success );

		$comments = $comments_query->get_comments();

		$this->assertEquals( 2, count( $comments ) );

		$comments_query = new \WP_Comment_Query(
			[
				'ep_integrate'       => true,
				'status'             => 'approve',
				'include_unapproved' => [
					'joe@example.com',
					$user_id,
				],
			]
		);

		$this->assertTrue( $comments_query->elasticsearch_success );

		$comments = $comments_query->get_comments();

		$this->assertEquals( 3, count( $comments ) );

		$comments_query = new \WP_Comment_Query(
			[
				'ep_integrate' => true,
				'status'       => 'all',
				'orderby'      => 'comment_approved',
			]
		);

		$this->assertTrue( $comments_query->elasticsearch_success );

		$comments = $comments_query->get_comments();

		$this->assertEquals( '1', $comments[0]->comment_approved );

		$comments_query = new \WP_Comment_Query(
			[
				'ep_integrate' => true,
				'status'       => 'all',
				'orderby'      => 'comment_approved',
				'order'        => 'ASC',
			]
		);

		$this->assertTrue( $comments_query->elasticsearch_success );

		$comments = $comments_query->get_comments();

		$this->assertEquals( '1', $comments[2]->comment_approved );
	}

	/**
	 * Test comment query by comment type.
	 *
	 * @since 3.6.0
	 * @group comment
	 */
	public function testCommentQueryType() {

		$post_id = $this->ep_factory->post->create();

		$this->ep_factory->comment->create(
			[
				'comment_content' => 'Test comment 1',
				'comment_post_ID' => $post_id,
				'comment_type'    => 'pingback',
			]
		);

		$this->ep_factory->comment->create(
			[
				'comment_content' => 'Test comment 2',
				'comment_post_ID' => $post_id,
			]
		);

		$this->ep_factory->comment->create(
			[
				'comment_content' => 'Test comment 3',
				'comment_post_ID' => $post_id,
				'comment_type'    => 'trackback',
			]
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$comments_query = new \WP_Comment_Query(
			[
				'ep_integrate' => true,
				'type'         => 'comment',
			]
		);

		$this->assertTrue( $comments_query->elasticsearch_success );

		$comments = $comments_query->get_comments();

		$this->assertEquals( 1, count( $comments ) );

		$comments_query = new \WP_Comment_Query(
			[
				'ep_integrate' => true,
				'type'         => 'trackback,pingback',
			]
		);

		$this->assertTrue( $comments_query->elasticsearch_success );

		$comments = $comments_query->get_comments();

		$this->assertEquals( 2, count( $comments ) );

		$comments_query = new \WP_Comment_Query(
			[
				'ep_integrate' => true,
				'type__in'     => [ 'trackback', 'pingback', 'comment' ],
			]
		);

		$this->assertTrue( $comments_query->elasticsearch_success );

		$comments = $comments_query->get_comments();

		$this->assertEquals( 3, count( $comments ) );

		$comments_query = new \WP_Comment_Query(
			[
				'ep_integrate' => true,
				'type__not_in' => [ 'trackback', 'pingback' ],
			]
		);

		$this->assertTrue( $comments_query->elasticsearch_success );

		$comments = $comments_query->get_comments();

		$this->assertEquals( 1, count( $comments ) );
	}

	/**
	 * Test comment query by post name.
	 *
	 * @since 3.6.0
	 * @group comment
	 */
	public function testCommentQueryPostName() {

		$post_id = $this->ep_factory->post->create(
			[
				'post_name' => 'start-here',
			]
		);

		$another_post_id = $this->ep_factory->post->create(
			[
				'post_name' => 'about-us',
			]
		);

		$this->ep_factory->comment->create(
			[
				'comment_content' => 'Test comment 1',
				'comment_post_ID' => $post_id,
			]
		);

		$this->ep_factory->comment->create(
			[
				'comment_content' => 'Test comment 2',
				'comment_post_ID' => $post_id,
			]
		);

		$this->ep_factory->comment->create(
			[
				'comment_content' => 'Test comment 3',
				'comment_post_ID' => $another_post_id,
			]
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$comments_query = new \WP_Comment_Query(
			[
				'ep_integrate' => true,
				'post_name'    => 'start-here',
			]
		);

		$this->assertTrue( $comments_query->elasticsearch_success );

		$comments = $comments_query->get_comments();

		foreach ( $comments as $comment ) {
			$this->assertEquals( $post_id, $comment->comment_post_ID );
		}

		$this->assertEquals( 2, count( $comments ) );
	}

	/**
	 * Test WooCommerce review indexing.
	 *
	 * @since 3.6.0
	 * @group comment
	 */
	public function testWooCommerceReviewIndexing() {
		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->setup_features();

		$product_id = $this->ep_factory->post->create(
			array(
				'post_content' => 'product 1',
				'post_type'    => 'product',
			)
		);

		$this->ep_factory->comment->create(
			[
				'comment_content' => 'Test review',
				'comment_post_ID' => $product_id,
				'comment_type'    => 'review',
			]
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$comments_query = new \WP_Comment_Query(
			[
				'ep_integrate' => true,
			]
		);

		$this->assertTrue( $comments_query->elasticsearch_success );

		$comments = $comments_query->get_comments();

		foreach ( $comments as $comment ) {
			$this->assertEquals( $product_id, $comment->comment_post_ID );
			$this->assertEquals( 'Test review', $comment->comment_content );
			$this->assertEquals( 'review', $comment->comment_type );
		}
	}

	/**
	 * Test Comment Indexable query_db.
	 *
	 * @since 3.6.0
	 * @group comment
	 */
	public function testCommentIndexableQueryDb() {
		$post_id = wp_insert_post(
			[
				'post_name'   => 'start-here',
				'post_status' => 'publish',
			]
		);

		wp_insert_comment(
			[
				'comment_content' => 'Test comment 1',
				'comment_post_ID' => $post_id,
			]
		);

		$product_id = wp_insert_post(
			[
				'post_content' => 'product 1',
				'post_type'    => 'product',
				'post_status'  => 'publish',
			]
		);

		wp_insert_comment(
			[
				'comment_content' => 'Test review',
				'comment_post_ID' => $product_id,
				'comment_type'    => 'review',
			]
		);

		$comment_indexable = new \ElasticPress\Indexable\Comment\Comment();

		$results = $comment_indexable->query_db( [] );

		$this->assertArrayHasKey( 'objects', $results );
		$this->assertArrayHasKey( 'total_objects', $results );

		$this->assertEquals( 1, $results['total_objects'] );
	}

	/**
	 * Test Comment Indexable query_db with WooCommerce feature enabled.
	 *
	 * @since 3.6.0
	 * @group comment
	 */
	public function testCommentIndexableQueryDbWithWooCommerceFeatureEnabled() {
		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->setup_features();

		$post_id = wp_insert_post(
			[
				'post_content' => 'start-here',
				'post_status'  => 'publish',
			]
		);

		wp_insert_comment(
			[
				'comment_content' => 'Test comment 1',
				'comment_post_ID' => $post_id,
			]
		);

		$product_id = wp_insert_post(
			[
				'post_content' => 'product 1',
				'post_type'    => 'product',
				'post_status'  => 'publish',
			]
		);

		wp_insert_comment(
			[
				'comment_content' => 'Test review',
				'comment_post_ID' => $product_id,
				'comment_type'    => 'review',
			]
		);

		$comment_indexable = new \ElasticPress\Indexable\Comment\Comment();

		$results = $comment_indexable->query_db( [] );

		$this->assertEquals( 2, $results['total_objects'] );
	}

	/**
	 * Test Comment Indexable query_db with Order Note.
	 *
	 * We need to make sure this type of comment is not indexed.
	 *
	 * @since 3.6.0
	 * @group comment
	 */
	public function testCommentIndexableQueryDbWithOrderNote() {
		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->setup_features();

		$post_id = wp_insert_post(
			[
				'post_content' => 'start-here',
				'post_status'  => 'publish',
			]
		);

		$post_comment_id = wp_insert_comment(
			[
				'comment_content' => 'Test comment 1',
				'comment_post_ID' => $post_id,
			]
		);

		$product_id = wp_insert_post(
			[
				'post_content' => 'product 1',
				'post_type'    => 'product',
				'post_status'  => 'publish',
			]
		);

		$product_comment_id = wp_insert_comment(
			[
				'comment_content' => 'Test review',
				'comment_post_ID' => $product_id,
				'comment_type'    => 'review',
			]
		);

		$shop_order_id = wp_insert_post(
			[
				'post_content'   => 'order 1',
				'post_type'      => 'shop_order',
				'post_status'    => 'wc-pending',
				'comment_status' => 'closed',
			]
		);

		wp_insert_comment(
			[
				'comment_content' => 'Added line items',
				'comment_post_ID' => $shop_order_id,
				'comment_type'    => 'order_note',

			]
		);

		$comment_indexable = new \ElasticPress\Indexable\Comment\Comment();

		$results = $comment_indexable->query_db( [] );

		$this->assertEquals( 2, $results['total_objects'] );

		foreach ( $results['objects'] as $comment ) {
			$this->assertContains( (int) $comment->comment_ID, [ $post_comment_id, $product_comment_id ] );
		}
	}

	/**
	 * Test a comment sync on order_note
	 *
	 * Check if a not allowed comment type is not indexed
	 *
	 * @since 3.6.0
	 * @group comments
	 */
	public function testCommentSyncOrderNote() {
		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->setup_features();

		$shop_order_id = $this->ep_factory->post->create(
			[
				'post_content'   => 'order 1',
				'post_type'      => 'shop_order',
				'post_status'    => 'wc-pending',
				'comment_status' => 'closed',
			]
		);

		wp_insert_comment(
			[
				'comment_content' => 'Added line items',
				'comment_post_ID' => $shop_order_id,
				'comment_type'    => 'order_note',
			]
		);

		$this->assertEquals( 0, count( ElasticPress\Indexables::factory()->get( 'comment' )->sync_manager->get_sync_queue() ) );

		$shop_order_comment = ElasticPress\Indexables::factory()->get( 'comment' )->get( $shop_order_id );

		$this->assertEmpty( $shop_order_comment );
	}

	/**
	 * Test a comment sync on order_note with meta
	 *
	 * Check if a not allowed comment type is not indexed
	 * when meta is updated.
	 *
	 * @since 3.6.0
	 * @group comments
	 */
	public function testCommentSyncOrderNoteWithMeta() {
		ElasticPress\Features::factory()->activate_feature( 'woocommerce' );
		ElasticPress\Features::factory()->setup_features();

		$shop_order_id = $this->ep_factory->post->create(
			[
				'post_content'   => 'order 1',
				'post_type'      => 'shop_order',
				'post_status'    => 'wc-pending',
				'comment_status' => 'closed',
			]
		);

		wp_insert_comment(
			[
				'comment_content' => 'Added line items',
				'comment_post_ID' => $shop_order_id,
				'comment_type'    => 'order_note',
				'comment_meta'    => [
					'is_customer_note' => 1,
				],
			]
		);

		$this->assertEquals( 0, count( ElasticPress\Indexables::factory()->get( 'comment' )->sync_manager->get_sync_queue() ) );

		$shop_order_comment = ElasticPress\Indexables::factory()->get( 'comment' )->get( $shop_order_id );

		$this->assertEmpty( $shop_order_comment );
	}

	/**
	 * Test comment indexing with Protected Content Feature enabled
	 *
	 * When the Protected Content is enabled unapproved comments will be indexed.
	 *
	 * @since 3.6.0
	 * @group comments
	 */
	public function testCommentIndexingWithProtectedContentEnabled() {
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$post_id = $this->ep_factory->post->create();

		$this->ep_factory->comment->create(
			[
				'comment_content'  => 'Test comment 1',
				'comment_post_ID'  => $post_id,
				'comment_approved' => 1,
			]
		);

		$this->ep_factory->comment->create(
			[
				'comment_content'  => 'Test comment 2',
				'comment_post_ID'  => $post_id,
				'comment_approved' => 0,
			]
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$comments_query = new \WP_Comment_Query(
			[
				'ep_integrate' => true,
			]
		);

		$this->assertTrue( $comments_query->elasticsearch_success );

		$comments = $comments_query->get_comments();

		$this->assertCount( 2, $comments );
	}

	/**
	 * Test if the mapping applies the ep_stop filter correctly
	 *
	 * @since 4.7.0
	 * @group comments
	 */
	public function test_mapping_ep_stop_filter() {
		$indexable      = ElasticPress\Indexables::factory()->get( 'comment' );
		$index_name     = $indexable->get_index_name();
		$settings       = ElasticPress\Elasticsearch::factory()->get_index_settings( $index_name );
		$index_settings = $settings[ $index_name ]['settings'];

		$this->assertContains( 'ep_stop', $index_settings['index.analysis.analyzer.default.filter'] );
		$this->assertSame( '_english_', $index_settings['index.analysis.filter.ep_stop.stopwords'] );

		$change_lang = function( $lang, $context ) {
			return 'filter_ep_stop' === $context ? '_arabic_' : $lang;
		};
		add_filter( 'ep_analyzer_language', $change_lang, 11, 2 );

		ElasticPress\Elasticsearch::factory()->delete_all_indices();
		$indexable->put_mapping();

		$settings       = ElasticPress\Elasticsearch::factory()->get_index_settings( $index_name );
		$index_settings = $settings[ $index_name ]['settings'];
		$this->assertSame( '_arabic_', $index_settings['index.analysis.filter.ep_stop.stopwords'] );
	}
}
