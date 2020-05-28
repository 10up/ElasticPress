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
	 * @since 3.5
	 */
	public function setUp() {
		global $wpdb;
		parent::setUp();
		$wpdb->suppress_errors();

		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );

		wp_set_current_user( $admin_id );

		ElasticPress\Features::factory()->activate_feature( 'comments' );
		ElasticPress\Features::factory()->setup_features();

		ElasticPress\Elasticsearch::factory()->delete_all_indices();
		ElasticPress\Indexables::factory()->get( 'comment' )->put_mapping();

		ElasticPress\Indexables::factory()->get( 'comment' )->sync_manager->sync_queue = [];

		// Need to call this since it's hooked to init.
		ElasticPress\Features::factory()->get_registered_feature( 'comments' )->search_setup();
	}

	/**
	 * Clean up after each test. Reset our mocks
	 *
	 * @since 3.5
	 */
	public function tearDown() {
		parent::tearDown();

		$this->deleteAllComments();

		// Make sure no one attached to this.
		remove_filter( 'ep_sync_comments_allow_hierarchy', array( $this, 'ep_allow_multiple_level_comments_sync' ), 100 );
		$this->fired_actions = array();
	}

	/**
	 * Deletes all comments from the database.
	 *
	 * @return void
	 */
	public function deleteAllComments() {

		foreach( get_comments() as $comment ) {
			wp_delete_comment( $comment->comment_ID, true );
		}
	}

	/**
	 * Create test comments.
	 */
	public function createComments( $number = 4, $has_child = false ) {
		$parent_comment_id = $child_comment_id = 0;
		$comment_ids = [];

		$post_id = Functions\create_and_sync_post();

		if( $number > 0 ) {
			for( $i = 1; $i <= $number; $i++ ) {
				$comment_ids[] = Functions\create_and_sync_comment( [
					'comment_content' => 'Test comment ' . $i,
					'comment_post_ID' => $post_id
				] );
			}
		}

		if( $has_child ) {
			$parent_comment_id = Functions\create_and_sync_comment( [
				'comment_content' => 'Test parent comment ',
				'comment_post_ID' => $post_id
			] );
			$child_comment_id  = Functions\create_and_sync_comment( [
				'comment_content' => 'Test child comment ',
				'comment_post_ID' => $post_id,
				'comment_parent' => $parent_comment_id,
			] );
		}

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		return [
			'post_id'           => $post_id,
			'parent_comment_id' =>  $parent_comment_id,
			'child_comment_id'  => $child_comment_id,
			'comment_ids'       => $comment_ids,
		];
	}

	/**
	 * Test a simple comment sync
	 *
	 * @since 3.5
	 * @group post
	 */
	public function testCommentSync() {
		add_action(
			'ep_sync_comment_on_transition',
			function() {
				$this->fired_actions['ep_sync_comment_on_transition'] = true;
			}
		);

		$post_id = Functions\create_and_sync_post();

		$comment_id = wp_insert_comment( [
			'comment_content' => 'Test comment',
			'comment_post_ID' => $post_id,
		] );

		$this->assertEquals( 1, count( ElasticPress\Indexables::factory()->get( 'comment' )->sync_manager->sync_queue ) );

		ElasticPress\Indexables::factory()->get( 'comment' )->index( $comment_id );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$this->assertArrayHasKey( 'ep_sync_comment_on_transition', $this->fired_actions );

		$this->assertTrue( ! empty( $this->fired_actions['ep_sync_comment_on_transition'] ) );

		$comment = ElasticPress\Indexables::factory()->get( 'comment' )->get( $comment_id );

		$this->assertnotEmpty( $comment );
	}

	/**
	 * Test a simple comment sync with meta
	 *
	 * @since 3.5
	 * @group comment
	 */
	public function testCommentSyncMeta() {
		$post_id = Functions\create_and_sync_post();

		$comment_id = wp_insert_comment( [
			'comment_content' => 'Test comment',
			'comment_post_ID' => $post_id,
		] );

		update_comment_meta( $comment_id, 'new_meta', 'test' );

		ElasticPress\Indexables::factory()->get( 'comment' )->index( $comment_id );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$comment = ElasticPress\Indexables::factory()->get( 'comment' )->get( $comment_id );

		$this->assertEquals( 'test', $comment['meta']['new_meta'][0]['value'] );
	}

	/**
	 * Test a simple comment sync on meta update
	 *
	 * @since 3.5
	 * @group comment
	 */
	public function testCommentSyncOnMetaUpdate() {
		$post_id = Functions\create_and_sync_post();

		$comment_id = wp_insert_comment( [
			'comment_content' => 'Test comment',
			'comment_post_ID' => $post_id,
		] );

		update_comment_meta( $comment_id, 'test_key', true );

		$this->assertEquals( 1, count( ElasticPress\Indexables::factory()->get( 'comment' )->sync_manager->sync_queue ) );
		$this->assertnotEmpty( ElasticPress\Indexables::factory()->get( 'comment' )->sync_manager->add_to_queue( $comment_id ) );
	}

	/**
	 * Test comment sync kill.
	 *
	 * @since 3.5
	 * @group comment
	 */
	public function testCommentSyncKill() {
		$post_id = Functions\create_and_sync_post();

		$created_comment_id = wp_insert_comment( [
			'comment_content' => 'Test comment',
			'comment_post_ID' => $post_id,
		] );

		add_action(
			'ep_sync_comment_on_transition',
			function() {
				$this->fired_actions['ep_sync_comment_on_transition'] = true;
			}
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
	 * @since 3.3
	 * @group comment
	 */
	public function testBasicCommentQuery() {

		$this->createComments( 3 );

		// First try without ES and make sure everything is right.
		$comments = (new \WP_Comment_Query())->query( [] );

		foreach ( $comments as $comment ) {
			$this->assertTrue( empty( $comment->elasticsearch ) );
		}

		$this->assertEquals( 3, count( $comments ) );

		// Now try with Elasticsearch.
		$comments = (new \WP_Comment_Query())->query( [
			'ep_integrate' => true,
		] );

		foreach ( $comments as $comment ) {
			$this->assertTrue( $comment->elasticsearch );
		}

		$this->assertEquals( 3, count( $comments ) );

		// Test some of the filters and defaults.
		$return_2 = function() {
			return 2;
		};

		add_filter( 'ep_max_results_window', $return_2 );

		// Now try with Elasticsearch.
		$comments = (new \WP_Comment_Query())->query( [
			'ep_integrate' => true,
		] );

		$this->assertEquals( 2, count( $comments ) );

		remove_filter( 'ep_max_results_window', $return_2 );
	}

	public function testCommentQueryNumber() {
		$this->createComments();

		$comments = (new \WP_Comment_Query())->query( [
			'ep_integrate' => true,
			'number' => 2,
		] );

		foreach ( $comments as $comment ) {
			$this->assertTrue( $comment->elasticsearch );
		}

		$this->assertEquals( 2, count( $comments ) );
	}

	public function testCommentQueryOffset() {
		$this->createComments( 6 );

		$comments = (new \WP_Comment_Query())->query( [
			'ep_integrate' => true,
			'offset' => 3,
		] );

		foreach ( $comments as $comment ) {
			$this->assertTrue( $comment->elasticsearch );
		}

		$this->assertEquals( 3, count( $comments ) );
	}

	public function testCommentQueryOrderCommentID() {
		$this->createComments();

		$comments = (new \WP_Comment_Query())->query( [
			'ep_integrate' => true,
			'orderby'      => 'comment_ID',
		] );

		foreach ( $comments as $comment ) {
			$this->assertTrue( $comment->elasticsearch );
		}

		$ids = wp_list_pluck( $comments, 'comment_ID' );
		$this->assertGreaterThan( $ids[1], $ids[0] );
		$this->assertGreaterThan( $ids[2], $ids[1] );
		$this->assertGreaterThan( $ids[3], $ids[2] );

		$comments = (new \WP_Comment_Query())->query( [
			'ep_integrate' => true,
			'orderby'      => 'comment_ID',
			'order'        => 'ASC',
		] );

		$ids = wp_list_pluck( $comments, 'comment_ID' );
		$this->assertLessThan( $ids[1], $ids[0] );
		$this->assertLessThan( $ids[2], $ids[1] );
		$this->assertLessThan( $ids[3], $ids[2] );
	}

	public function testCommentQueryOrderCommentPostID() {
		$post_id_1 = Functions\create_and_sync_post();
		$post_id_2 = Functions\create_and_sync_post();

		$comment_ids[] = Functions\create_and_sync_comment( [
			'comment_content' => 'Test comment 1',
			'comment_post_ID' => $post_id_1,
		] );
		$comment_ids[] = Functions\create_and_sync_comment( [
			'comment_content' => 'Test comment 2',
			'comment_post_ID' => $post_id_2
		] );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$comments = (new \WP_Comment_Query())->query( [
			'ep_integrate' => true,
			'orderby'      => 'comment_post_ID',
		] );

		$this->assertEquals( 'Test comment 2', $comments[0]->comment_content );
		$this->assertEquals( 'Test comment 1', $comments[1]->comment_content );

		$comments = (new \WP_Comment_Query())->query( [
			'ep_integrate' => true,
			'orderby'      => 'comment_post_ID',
			'order'        => 'ASC',
		] );

		$this->assertEquals( 'Test comment 2', $comments[1]->comment_content );
		$this->assertEquals( 'Test comment 1', $comments[0]->comment_content );
	}

	public function testCommentQueryIds() {

		$created_comments = $this->createComments( 3 );

		$comments = (new \WP_Comment_Query())->query( [
			'ep_integrate' => true,
			'fields' => 'ids',
		] );

		foreach ( $comments as $comment ) {
			$this->assertContains( $comment, $created_comments['comment_ids'] );
		}

		$this->assertEquals( 3, count( $comments ) );
	}

	public function testCommentQueryCount() {

		$this->createComments( 3 );

		$comments = (new \WP_Comment_Query())->query( [
			'ep_integrate' => true,
			'fields' => 'count',
		] );

		$this->assertEquals( 3, $comments );
	}

	public function testCommentQueryHierarchical() {
		$this->createComments( 0, true );

		$args = [
			'hierarchical' => 'threaded',
			'ep_integrate' => true,
		];

		$comments_query = new \WP_Comment_Query( $args );

		$comments = $comments_query->query( $args );

		$this->assertEquals( 1, count( $comments ) );
		$this->assertObjectHasAttribute( 'children', reset( $comments ) );

		$args = [
			'hierarchical' => 'flat',
			'ep_integrate' => true,
		];

		$comments_query = new \WP_Comment_Query( $args );

		$comments = $comments_query->query( $args );

		$this->assertEquals( 2, count( $comments ) );
		foreach( $comments as $comment ) {
			$this->assertObjectNotHasAttribute( 'children', $comment );
		}
	}

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

		$post_id = Functions\create_and_sync_post();

		$comment_id = wp_insert_comment( [
			'comment_content' => 'Test comment',
			'comment_post_ID' => $post_id,
		] );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$this->assertEquals( 1, count( ElasticPress\Indexables::factory()->get( 'comment' )->sync_manager->sync_queue ) );

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

	public function testCommentQueryPaged() {

		$this->createComments( 7 );

		$args = [
			'ep_integrate' => true,
			'paged' => 2,
			'number' => 4,
		];

		$comments_query = new \WP_Comment_Query( $args );
		$comments = $comments_query->query( $args );

		foreach ( $comments as $comment ) {
			$this->assertTrue( $comment->elasticsearch );
		}

		$this->assertEquals( 3, count( $comments ) );
	}

	public function testCommentQueryAuthorEmail() {
		$post_id = Functions\create_and_sync_post();

		Functions\create_and_sync_comment( [
			'comment_content' => 'Test comment 1',
			'comment_post_ID' => $post_id,
			'comment_author_email' => 'joe@example.com',
		] );

		Functions\create_and_sync_comment( [
			'comment_content' => 'Test comment 2',
			'comment_post_ID' => $post_id,
			'comment_author_email' => 'doe@example.com',
		] );

		Functions\create_and_sync_comment( [
			'comment_content' => 'Test comment 3',
			'comment_post_ID' => $post_id,
			'comment_author_email' => 'joe@example.com',
		] );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = [
			'ep_integrate' => true,
			'author_email' => 'joe@example.com',
		];

		$comments_query = new \WP_Comment_Query( $args );
		$comments = $comments_query->query( $args );

		foreach ( $comments as $comment ) {
			$this->assertTrue( $comment->elasticsearch );
			$this->assertAttributeEquals( 'joe@example.com', 'comment_author_email', $comment );
		}

		$this->assertEquals( 2, count( $comments ) );
	}

	public function testCommentQueryAuthorUrl() {
		$post_id = Functions\create_and_sync_post();

		Functions\create_and_sync_comment( [
			'comment_content' => 'Test comment 1',
			'comment_post_ID' => $post_id,
			'comment_author_email' => 'joe@example.com',
			'comment_author_url' => 'http://example.com',
		] );

		Functions\create_and_sync_comment( [
			'comment_content' => 'Test comment 2',
			'comment_post_ID' => $post_id,
			'comment_author_email' => 'doe@example.com',
			'comment_author_url' => 'http://example.com',
		] );

		Functions\create_and_sync_comment( [
			'comment_content' => 'Test comment 3',
			'comment_post_ID' => $post_id,
			'comment_author_email' => 'hoe@example.com',
			'comment_author_url' => 'http://example.org',
		] );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = [
			'ep_integrate' => true,
			'author_url' => 'http://example.com',
		];

		$comments_query = new \WP_Comment_Query( $args );
		$comments = $comments_query->query( $args );

		foreach ( $comments as $comment ) {
			$this->assertTrue( $comment->elasticsearch );
			$this->assertAttributeEquals( 'http://example.com', 'comment_author_url', $comment );
		}

		$this->assertEquals( 2, count( $comments ) );
	}

	public function testCommentQueryUserId() {
		$current_user_id = get_current_user_id();

		$post_id = Functions\create_and_sync_post();

		Functions\create_and_sync_comment( [
			'comment_content' => 'Test comment 1',
			'comment_post_ID' => $post_id,
			'user_id' => $current_user_id,
		] );

		Functions\create_and_sync_comment( [
			'comment_content' => 'Test comment 2',
			'comment_post_ID' => $post_id,
			'user_id' => $current_user_id,
		] );

		Functions\create_and_sync_comment( [
			'comment_content' => 'Test comment 3',
			'comment_post_ID' => $post_id,
			'user_id' => $current_user_id,
		] );

		Functions\create_and_sync_comment( [
			'comment_content' => 'Test comment 4',
			'comment_post_ID' => $post_id,
		] );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = [
			'ep_integrate' => true,
			'user_id' => $current_user_id,
		];

		$comments_query = new \WP_Comment_Query( $args );
		$comments = $comments_query->query( $args );

		foreach ( $comments as $comment ) {
			$this->assertTrue( $comment->elasticsearch );
			$this->assertAttributeEquals( $current_user_id, 'user_id', $comment );
		}

		$this->assertEquals( 3, count( $comments ) );
	}
}
