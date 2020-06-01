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

	public function testCommentQueryOrderCommentContent() {
		$this->createComments();

		$args = [
			'ep_integrate' => true,
			'orderby' => 'comment_content',
			'order' => 'ASC',
		];

		$comments_query = new \WP_Comment_Query( $args );
		$comments = $comments_query->query( $args );

		foreach ( $comments as $comment ) {
			$this->assertTrue( $comment->elasticsearch );
		}

		$this->assertAttributeEquals( 'Test comment 4', 'comment_content', $comments[3] );

		$args = [
			'ep_integrate' => true,
			'orderby' => 'comment_content',
			'order' => 'DESC',
		];

		$comments_query = new \WP_Comment_Query( $args );
		$comments = $comments_query->query( $args );

		$this->assertAttributeEquals( 'Test comment 1', 'comment_content', $comments[3] );
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

		$args = [
			'ep_integrate' => true,
			'orderby' => 'comment_author_email',
			'order' => 'ASC'
		];

		$comments_query = new \WP_Comment_Query( $args );
		$comments = $comments_query->query( $args );

		foreach ( $comments as $comment ) {
			$this->assertTrue( $comment->elasticsearch );
		}
		$this->assertAttributeEquals( 'doe@example.com', 'comment_author_email', $comments[0] );
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

		$args = [
			'ep_integrate' => true,
			'orderby' => 'comment_author_url',
			'order' => 'ASC',
		];

		$comments_query = new \WP_Comment_Query( $args );
		$comments = $comments_query->query( $args );

		foreach ( $comments as $comment ) {
			$this->assertTrue( $comment->elasticsearch );
		}

		$this->assertAttributeEquals( 'http://example.com', 'comment_author_url', $comments[0] );
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

	public function testCommentQueryAuthorIn() {
		$current_user_id = get_current_user_id();
		$another_author_id = $this->factory->user->create( array( 'role' => 'administrator' ) );

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
			'user_id' => $another_author_id,
		] );

		Functions\create_and_sync_comment( [
			'comment_content' => 'Test comment 4',
			'comment_post_ID' => $post_id,
		] );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = [
			'ep_integrate' => true,
			'author__in' => [ $current_user_id, $another_author_id ],
		];

		$comments_query = new \WP_Comment_Query( $args );
		$comments = $comments_query->query( $args );

		foreach ( $comments as $comment ) {
			$this->assertTrue( $comment->elasticsearch );
			$this->assertTrue( in_array( $comment->user_id, [ $current_user_id, $another_author_id ] ) );
		}

		$this->assertEquals( 3, count( $comments ) );
	}

	public function testCommentQueryAuthorNotIn() {
		$current_user_id = get_current_user_id();
		$another_author_id = $this->factory->user->create( array( 'role' => 'administrator' ) );

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
			'user_id' => $another_author_id,
		] );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = [
			'ep_integrate' => true,
			'author__not_in' => [ $another_author_id ],
		];

		$comments_query = new \WP_Comment_Query( $args );
		$comments = $comments_query->query( $args );

		foreach ( $comments as $comment ) {
			$this->assertTrue( $comment->elasticsearch );
			$this->assertAttributeEquals( $current_user_id, 'user_id', $comment );
		}

		$this->assertEquals( 2, count( $comments ) );
	}

	public function testCommentQueryCommentIn() {
		$created_comments = $this->createComments();

		$test_comments = [ $created_comments['comment_ids'][0], $created_comments['comment_ids'][1] ];

		$args = [
			'ep_integrate' => true,
			'comment__in' => $test_comments,
			'number' => 2,
		];

		$comments_query = new \WP_Comment_Query( $args );
		$comments = $comments_query->query( $args );

		foreach ( $comments as $comment ) {
			$this->assertTrue( $comment->elasticsearch );
			$this->assertTrue( in_array( $comment->comment_ID, $test_comments ) );
		}

		$this->assertEquals( 2, count( $comments ) );
	}

	public function testCommentQueryCommentNotIn() {
		$created_comments = $this->createComments( 5 );

		$test_comments = [ $created_comments['comment_ids'][0], $created_comments['comment_ids'][1] ];

		$args = [
			'ep_integrate' => true,
			'comment__not_in' => $test_comments,
		];

		$comments_query = new \WP_Comment_Query( $args );
		$comments = $comments_query->query( $args );

		foreach ( $comments as $comment ) {
			$this->assertTrue( $comment->elasticsearch );
			$this->assertFalse( in_array( $comment->comment_ID, $test_comments ) );
		}

		$this->assertEquals( 3, count( $comments ) );
	}

	public function testCommentQueryDateQuery() {

		$post_id = Functions\create_and_sync_post();
		$in_range = [];
		$out_range = [];

		$in_range[] = Functions\create_and_sync_comment( [
			'comment_content' => 'Test comment',
			'comment_post_ID' => $post_id,
			'comment_date_gmt' => '2020-05-21',
			'comment_date' => '2020-05-21',
		] );

		$out_range[] = Functions\create_and_sync_comment( [
			'comment_content' => 'Test comment',
			'comment_post_ID' => $post_id,
			'comment_date_gmt' => '2020-05-19',
			'comment_date' => '2020-05-19',
		] );

		$in_range[] = Functions\create_and_sync_comment( [
			'comment_content' => 'Test comment',
			'comment_post_ID' => $post_id,
			'comment_date_gmt' => '2020-05-25',
			'comment_date' => '2020-05-25',
		] );

		$out_range[] = Functions\create_and_sync_comment( [
			'comment_content' => 'Test comment',
			'comment_post_ID' => $post_id,
			'comment_date_gmt' => '2020-05-29',
			'comment_date' => '2020-05-29',
		] );

		$out_range[] = Functions\create_and_sync_comment( [
			'comment_content' => 'Test comment',
			'comment_post_ID' => $post_id,
			'comment_date_gmt' => '2020-06-15',
			'comment_date' => '2020-06-15',
		] );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$date_query = array(
			'relation' => 'AND',
			array(
				'column' => 'comment_date',
				'after' => '2020-05-20',
				'before' => '2020-05-27',
			),
		);

		$args = [
			'ep_integrate' => true,
			'date_query' => $date_query,
		];

		$comments_query = new \WP_Comment_Query( $args );
		$comments = $comments_query->query( $args );

		foreach ( $comments as $comment ) {
			$this->assertTrue( $comment->elasticsearch );
			$this->assertTrue( in_array( $comment->comment_ID, $in_range ) );
			$this->assertFalse( in_array( $comment->comment_ID, $out_range ) );
		}

		$this->assertEquals( 2, count( $comments ) );

		$args = [
			'ep_integrate' => true,
			'orderby' => 'comment_date',
			'order' => 'ASC',
		];

		$comments_query = new \WP_Comment_Query( $args );
		$comments = $comments_query->query( $args );

		foreach ( $comments as $comment ) {
			$this->assertTrue( $comment->elasticsearch );
		}

		$this->assertAttributeEquals( '2020-05-19 00:00:00', 'comment_date', $comments[0] );
		$this->assertAttributeEquals( '2020-05-21 00:00:00', 'comment_date', $comments[1] );
		$this->assertAttributeEquals( '2020-06-15 00:00:00', 'comment_date', $comments[4] );

		$args = [
			'ep_integrate' => true,
			'orderby' => 'comment_date',
			'order' => 'DESC',
		];

		$comments_query = new \WP_Comment_Query( $args );
		$comments = $comments_query->query( $args );

		$this->assertAttributeEquals( '2020-05-19 00:00:00', 'comment_date', $comments[4] );
		$this->assertAttributeEquals( '2020-05-21 00:00:00', 'comment_date', $comments[3] );
		$this->assertAttributeEquals( '2020-06-15 00:00:00', 'comment_date', $comments[0] );
	}

	public function testCommentQueryKarma() {

		$post_id = Functions\create_and_sync_post();
		$match = [];
		$not_match = [];

		$match[] = Functions\create_and_sync_comment( [
			'comment_content' => 'Test comment',
			'comment_post_ID' => $post_id,
			'comment_karma' => 9,
		] );

		$not_match[] = Functions\create_and_sync_comment( [
			'comment_content' => 'Test comment',
			'comment_post_ID' => $post_id,
			'comment_karma' => 3,
		] );

		$match[] = Functions\create_and_sync_comment( [
			'comment_content' => 'Test comment',
			'comment_post_ID' => $post_id,
			'comment_karma' => 9,
		] );

		$not_match[] = Functions\create_and_sync_comment( [
			'comment_content' => 'Test comment',
			'comment_post_ID' => $post_id,
			'comment_karma' => 1,
		] );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = [
			'ep_integrate' => true,
			'karma' => 9,
		];

		$comments_query = new \WP_Comment_Query( $args );
		$comments = $comments_query->query( $args );

		foreach ( $comments as $comment ) {
			$this->assertTrue( $comment->elasticsearch );
			$this->assertTrue( in_array( $comment->comment_ID, $match ) );
			$this->assertFalse( in_array( $comment->comment_ID, $not_match ) );
		}

		$this->assertEquals( 2, count( $comments ) );
	}

	public function testCommentQueryMeta() {

		$post_id = Functions\create_and_sync_post();
		$match = [];

		$match[] = Functions\create_and_sync_comment( [
			'comment_content' => 'Test comment',
			'comment_post_ID' => $post_id,
			'comment_meta' => [
				'test_meta' => 'test_value'
			]
		] );

		$not_match = Functions\create_and_sync_comment( [
			'comment_content' => 'Test comment',
			'comment_post_ID' => $post_id,
		] );

		$match[] = Functions\create_and_sync_comment( [
			'comment_content' => 'Test comment',
			'comment_post_ID' => $post_id,
			'comment_meta' => [
				'test_meta' => 'test_value'
			]
		] );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = [
			'ep_integrate' => true,
			'meta_key' => 'test_meta',
			'meta_value' => 'test_value',
		];

		$comments_query = new \WP_Comment_Query( $args );
		$comments = $comments_query->query( $args );

		foreach ( $comments as $comment ) {
			$this->assertTrue( $comment->elasticsearch );
			$this->assertTrue( in_array( $comment->comment_ID, $match ) );
			$this->assertNotEquals( $comment->comment_ID, $not_match );
		}

		$this->assertEquals( 2, count( $comments ) );
	}

	public function testCommentQueryMetaQuery() {

		$post_id = Functions\create_and_sync_post();
		$match = [];

		$not_match = Functions\create_and_sync_comment( [
			'comment_content' => 'Test comment',
			'comment_post_ID' => $post_id,
			'comment_meta' => [
				'weight' => 10
			]
		] );

		$match[] = Functions\create_and_sync_comment( [
			'comment_content' => 'Test comment',
			'comment_post_ID' => $post_id,
			'comment_meta' => [
				'weight' => 20
			]
		] );

		$match[] = Functions\create_and_sync_comment( [
			'comment_content' => 'Test comment',
			'comment_post_ID' => $post_id,
			'comment_meta' => [
				'weight' => 50
			]
		] );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = [
			'ep_integrate' => true,
			'meta_query' => [
				[
					'key'   => 'weight',
					'value' => 15,
					'compare' => '>',
				]
			]
		];

		$comments_query = new \WP_Comment_Query( $args );
		$comments = $comments_query->query( $args );

		foreach ( $comments as $comment ) {
			$this->assertTrue( $comment->elasticsearch );
			$this->assertTrue( in_array( $comment->comment_ID, $match ) );
			$this->assertNotEquals( $comment->comment_ID, $not_match );
		}

		$this->assertEquals( 2, count( $comments ) );
	}

	public function testCommentQueryParentIn() {
		$created_comments = $this->createComments( 3, true );

		$args = [
			'ep_integrate' => true,
			'parent__in' => [ $created_comments['parent_comment_id'] ],
		];

		$comments_query = new \WP_Comment_Query( $args );
		$comments = $comments_query->query( $args );

		foreach ( $comments as $comment ) {
			$this->assertTrue( $comment->elasticsearch );
			$this->assertEquals( $created_comments['child_comment_id'], $comment->comment_ID );
		}

		$this->assertEquals( 1, count( $comments ) );
	}

	public function testCommentQueryParentNotIn() {
		$created_comments = $this->createComments( 3, true );

		$args = [
			'ep_integrate' => true,
			'parent__not_in' => [ $created_comments['parent_comment_id'] ],
		];

		$comments_query = new \WP_Comment_Query( $args );
		$comments = $comments_query->query( $args );

		foreach ( $comments as $comment ) {
			$this->assertTrue( $comment->elasticsearch );
			$this->assertNotEquals( $created_comments['child_comment_id'], $comment->comment_ID );
		}

		$this->assertEquals( 4, count( $comments ) );
	}

	public function testCommentQueryPostAuthor() {

		$user_id_1 = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$user_id_2 = $this->factory->user->create( array( 'role' => 'administrator' ) );
		$user_id_3 = $this->factory->user->create( array( 'role' => 'administrator' ) );

		$post_id_1 = Functions\create_and_sync_post( [ 'post_author' => $user_id_1 ] );
		$post_id_2 = Functions\create_and_sync_post( [ 'post_author' => $user_id_1 ] );
		$post_id_3 = Functions\create_and_sync_post( [ 'post_author' => $user_id_2 ] );
		$post_id_4 = Functions\create_and_sync_post( [ 'post_author' => $user_id_3 ] );

		Functions\create_and_sync_comment( [
			'comment_content' => 'Test comment',
			'comment_post_ID' => $post_id_1,
		] );

		Functions\create_and_sync_comment( [
			'comment_content' => 'Test comment',
			'comment_post_ID' => $post_id_1,
		] );

		Functions\create_and_sync_comment( [
			'comment_content' => 'Test comment',
			'comment_post_ID' => $post_id_2,
		] );

		Functions\create_and_sync_comment( [
			'comment_content' => 'Test comment',
			'comment_post_ID' => $post_id_2,
		] );

		Functions\create_and_sync_comment( [
			'comment_content' => 'Test comment',
			'comment_post_ID' => $post_id_3,
		] );

		Functions\create_and_sync_comment( [
			'comment_content' => 'Test comment',
			'comment_post_ID' => $post_id_4,
		] );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = [
			'ep_integrate' => true,
			'post_author' => $user_id_1,
		];

		$comments_query = new \WP_Comment_Query( $args );
		$comments = $comments_query->query( $args );

		foreach ( $comments as $comment ) {
			$this->assertTrue( $comment->elasticsearch );
			$this->assertTrue( in_array( $comment->comment_post_ID, [ $post_id_1, $post_id_2 ] ) );
		}

		$this->assertEquals( 4, count( $comments ) );

		$args = [
			'ep_integrate' => true,
			'post_author__in' => [ $user_id_1, $user_id_2 ],
		];

		$comments_query = new \WP_Comment_Query( $args );
		$comments = $comments_query->query( $args );

		foreach ( $comments as $comment ) {
			$this->assertTrue( $comment->elasticsearch );
			$this->assertTrue( in_array( $comment->comment_post_ID, [ $post_id_1, $post_id_2, $post_id_3 ] ) );
		}

		$this->assertEquals( 5, count( $comments ) );

		$args = [
			'ep_integrate' => true,
			'post_author__not_in' => [ $user_id_1 ],
		];

		$comments_query = new \WP_Comment_Query( $args );
		$comments = $comments_query->query( $args );

		foreach ( $comments as $comment ) {
			$this->assertTrue( $comment->elasticsearch );
			$this->assertTrue( in_array( $comment->comment_post_ID, [ $post_id_3, $post_id_4 ] ) );
		}

		$this->assertEquals( 2, count( $comments ) );
	}

	public function testCommentQueryPostId() {
		$this->createComments();
		$created_comments = $this->createComments( 3 );

		$args = [
			'ep_integrate' => true,
			'post_id' => $created_comments['post_id'],
		];

		$comments_query = new \WP_Comment_Query( $args );
		$comments = $comments_query->query( $args );

		foreach ( $comments as $comment ) {
			$this->assertTrue( $comment->elasticsearch );
			$this->assertTrue( in_array( $comment->comment_ID, $created_comments['comment_ids'] ) );
		}

		$this->assertEquals( 3, count( $comments ) );

		$args = [
			'ep_integrate' => true,
			'post__in' => [ $created_comments['post_id'] ],
		];

		$comments_query = new \WP_Comment_Query( $args );
		$comments = $comments_query->query( $args );

		foreach ( $comments as $comment ) {
			$this->assertTrue( $comment->elasticsearch );
			$this->assertTrue( in_array( $comment->comment_ID, $created_comments['comment_ids'] ) );
		}

		$this->assertEquals( 3, count( $comments ) );

		$args = [
			'ep_integrate' => true,
			'post__not_in' => [ $created_comments['post_id'] ],
		];

		$comments_query = new \WP_Comment_Query( $args );
		$comments = $comments_query->query( $args );

		foreach ( $comments as $comment ) {
			$this->assertTrue( $comment->elasticsearch );
			$this->assertFalse( in_array( $comment->comment_post_ID, $created_comments['comment_ids'] ) );
		}

		$this->assertEquals( 4, count( $comments ) );
	}

	public function testCommentQueryPostStatus() {

		$post_id_1 = Functions\create_and_sync_post( [ 'post_status' => 'publish' ] );
		$post_id_2 = Functions\create_and_sync_post( [ 'post_status' => 'draft' ] );

		Functions\create_and_sync_comment( [
			'comment_content' => 'Test comment',
			'comment_post_ID' => $post_id_1,
		] );

		Functions\create_and_sync_comment( [
			'comment_content' => 'Test comment',
			'comment_post_ID' => $post_id_1,
		] );

		Functions\create_and_sync_comment( [
			'comment_content' => 'Test comment',
			'comment_post_ID' => $post_id_1,
		] );

		Functions\create_and_sync_comment( [
			'comment_content' => 'Test comment',
			'comment_post_ID' => $post_id_2,
		] );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = [
			'ep_integrate' => true,
			'post_status' => 'publish',
		];

		$comments_query = new \WP_Comment_Query( $args );
		$comments = $comments_query->query( $args );

		foreach ( $comments as $comment ) {
			$this->assertTrue( $comment->elasticsearch );
			$this->assertEquals( $comment->comment_post_ID, $post_id_1  );
		}

		$this->assertEquals( 3, count( $comments ) );

		$args = [
			'ep_integrate' => true,
			'post_status' => [ 'draft', 'publish' ],
		];

		$comments_query = new \WP_Comment_Query( $args );
		$comments = $comments_query->query( $args );

		foreach ( $comments as $comment ) {
			$this->assertTrue( $comment->elasticsearch );
		}

		$this->assertEquals( 4, count( $comments ) );
	}

	public function testCommentQueryPostType() {

		$post_id_1 = Functions\create_and_sync_post( [ 'post_type' => 'post' ] );
		$post_id_2 = Functions\create_and_sync_post( [ 'post_type' => 'page' ] );
		$post_id_3 = Functions\create_and_sync_post( [ 'post_type' => 'post' ] );

		Functions\create_and_sync_comment( [
			'comment_content' => 'Test comment',
			'comment_post_ID' => $post_id_1,
		] );

		Functions\create_and_sync_comment( [
			'comment_content' => 'Test comment',
			'comment_post_ID' => $post_id_2,
		] );

		Functions\create_and_sync_comment( [
			'comment_content' => 'Test comment',
			'comment_post_ID' => $post_id_2,
		] );

		Functions\create_and_sync_comment( [
			'comment_content' => 'Test comment',
			'comment_post_ID' => $post_id_3,
		] );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = [
			'ep_integrate' => true,
			'post_type' => 'post',
		];

		$comments_query = new \WP_Comment_Query( $args );
		$comments = $comments_query->query( $args );

		foreach ( $comments as $comment ) {
			$this->assertTrue( $comment->elasticsearch );
			$this->assertTrue( in_array( $comment->comment_post_ID, [ $post_id_1, $post_id_3 ] ) );
		}

		$this->assertEquals( 2, count( $comments ) );
	}

	public function testCommentQueryPostParent() {

		$post_id_1 = Functions\create_and_sync_post( [ 'post_type' => 'page' ]);
		$post_id_2 = Functions\create_and_sync_post( [ 'post_type' => 'page', 'post_parent' => $post_id_1 ] );

		Functions\create_and_sync_comment( [
			'comment_content' => 'Test comment',
			'comment_post_ID' => $post_id_1,
		] );

		Functions\create_and_sync_comment( [
			'comment_content' => 'Test comment',
			'comment_post_ID' => $post_id_2,
		] );

		Functions\create_and_sync_comment( [
			'comment_content' => 'Test comment',
			'comment_post_ID' => $post_id_2,
		] );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = [
			'ep_integrate' => true,
			'post_parent' => $post_id_1,
		];

		$comments_query = new \WP_Comment_Query( $args );
		$comments = $comments_query->query( $args );

		foreach ( $comments as $comment ) {
			$this->assertTrue( $comment->elasticsearch );
			$this->assertEquals( $post_id_2, $comment->comment_post_ID );
		}

		$this->assertEquals( 2, count( $comments ) );
	}

	public function testCommentQuerySearch() {
		$post_id = Functions\create_and_sync_post();

		Functions\create_and_sync_comment( [
			'comment_content' => 'Test comment 1',
			'comment_post_ID' => $post_id,
		] );

		Functions\create_and_sync_comment( [
			'comment_content' => 'Test comment 2',
			'comment_post_ID' => $post_id,
		] );

		$comment_id = Functions\create_and_sync_comment( [
			'comment_content' => 'Test comment 3',
			'comment_post_ID' => $post_id,
		] );

		update_comment_meta( $comment_id, 'test_meta_key', 'start here' );

		ElasticPress\Indexables::factory()->get( 'comment' )->index( $comment_id, true );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = [
			'search' => 'test comment',
		];

		$comments_query = new \WP_Comment_Query( $args );
		$comments = $comments_query->query( $args );

		foreach ( $comments as $comment ) {
			$this->assertTrue( $comment->elasticsearch );
		}

		$this->assertEquals( 3, count( $comments ) );

		$args = [
			'search' => 'start',
			'search_fields' => [
				'meta' => [
					'test_meta_key',
				]
			],
		];

		$comments_query = new \WP_Comment_Query( $args );
		$comments = $comments_query->query( $args );

		foreach ( $comments as $comment ) {
			$this->assertTrue( $comment->elasticsearch );
			$this->assertEquals( $comment_id, $comment->comment_ID );
		}

		$this->assertEquals( 1, count( $comments ) );
	}

	public function testCommentQueryStatus() {

		$post_id = Functions\create_and_sync_post();
		$user_id = $this->factory->user->create( array( 'role' => 'administrator' ) );

		Functions\create_and_sync_comment( [
			'comment_content' => 'Test comment 1',
			'comment_post_ID' => $post_id,
			'comment_approved' => 1,
		] );

		Functions\create_and_sync_comment( [
			'comment_content' => 'Test comment 2',
			'comment_post_ID' => $post_id,
			'comment_approved' => 0,
			'user_id' => $user_id,
		] );

		Functions\create_and_sync_comment( [
			'comment_content' => 'Test comment 3',
			'comment_post_ID' => $post_id,
			'comment_approved' => 0,
			'comment_author_email' => 'joe@example.com',
		] );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = [
			'ep_integrate' => true,
			'status' => 'approve',
		];

		$comments_query = new \WP_Comment_Query( $args );
		$comments = $comments_query->query( $args );

		foreach ( $comments as $comment ) {
			$this->assertTrue( $comment->elasticsearch );
		}

		$this->assertEquals( 1, count( $comments ) );

		$args = [
			'ep_integrate' => true,
			'status' => 'hold',
		];

		$comments_query = new \WP_Comment_Query( $args );
		$comments = $comments_query->query( $args );

		foreach ( $comments as $comment ) {
			$this->assertTrue( $comment->elasticsearch );
		}

		$this->assertEquals( 2, count( $comments ) );

		$args = [
			'ep_integrate' => true,
			'status' => 'approve',
			'include_unapproved' => [
				'joe@example.com',
				$user_id,
			],
		];

		$comments_query = new \WP_Comment_Query( $args );
		$comments = $comments_query->query( $args );

		foreach ( $comments as $comment ) {
			$this->assertTrue( $comment->elasticsearch );
		}

		$this->assertEquals( 3, count( $comments ) );

		$args = [
			'ep_integrate' => true,
			'status' => 'all',
			'orderby' => 'comment_approved',
		];

		$comments_query = new \WP_Comment_Query( $args );
		$comments = $comments_query->query( $args );

		foreach ( $comments as $comment ) {
			$this->assertTrue( $comment->elasticsearch );
		}

		$this->assertAttributeEquals( '1', 'comment_approved', $comments[0] );

		$args = [
			'ep_integrate' => true,
			'status' => 'all',
			'orderby' => 'comment_approved',
			'order' => 'ASC',
		];

		$comments_query = new \WP_Comment_Query( $args );
		$comments = $comments_query->query( $args );

		foreach ( $comments as $comment ) {
			$this->assertTrue( $comment->elasticsearch );
		}

		$this->assertAttributeEquals( '1', 'comment_approved', $comments[2] );
	}

	public function testCommentQueryType() {

		$post_id = Functions\create_and_sync_post();

		Functions\create_and_sync_comment( [
			'comment_content' => 'Test comment 1',
			'comment_post_ID' => $post_id,
			'comment_type' => 'pingback',
		] );

		Functions\create_and_sync_comment( [
			'comment_content' => 'Test comment 2',
			'comment_post_ID' => $post_id,
		] );

		Functions\create_and_sync_comment( [
			'comment_content' => 'Test comment 3',
			'comment_post_ID' => $post_id,
			'comment_type' => 'trackback',
		] );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = [
			'ep_integrate' => true,
			'type' => 'comment',
		];

		$comments_query = new \WP_Comment_Query( $args );
		$comments = $comments_query->query( $args );

		foreach ( $comments as $comment ) {
			$this->assertTrue( $comment->elasticsearch );
		}

		$this->assertEquals( 1, count( $comments ) );

		$args = [
			'ep_integrate' => true,
			'type' => 'trackback,pingback',
		];

		$comments_query = new \WP_Comment_Query( $args );
		$comments = $comments_query->query( $args );

		foreach ( $comments as $comment ) {
			$this->assertTrue( $comment->elasticsearch );
		}

		$this->assertEquals( 2, count( $comments ) );

		$args = [
			'ep_integrate' => true,
			'type__in' => [ 'trackback', 'pingback', 'comment' ],
		];

		$comments_query = new \WP_Comment_Query( $args );
		$comments = $comments_query->query( $args );

		foreach ( $comments as $comment ) {
			$this->assertTrue( $comment->elasticsearch );
		}

		$this->assertEquals( 3, count( $comments ) );

		$args = [
			'ep_integrate' => true,
			'type__not_in' => [ 'trackback', 'pingback' ],
		];

		$comments_query = new \WP_Comment_Query( $args );
		$comments = $comments_query->query( $args );

		foreach ( $comments as $comment ) {
			$this->assertTrue( $comment->elasticsearch );
		}

		$this->assertEquals( 1, count( $comments ) );
	}

	public function testCommentQueryPostName() {

		$post_id = Functions\create_and_sync_post( [
			'post_name' => 'start-here'
		] );

		$another_post_id = Functions\create_and_sync_post( [
			'post_name' => 'about-us'
		] );

		Functions\create_and_sync_comment( [
			'comment_content' => 'Test comment 1',
			'comment_post_ID' => $post_id,
		] );

		Functions\create_and_sync_comment( [
			'comment_content' => 'Test comment 2',
			'comment_post_ID' => $post_id,
		] );

		Functions\create_and_sync_comment( [
			'comment_content' => 'Test comment 3',
			'comment_post_ID' => $another_post_id,
		] );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = [
			'ep_integrate' => true,
			'post_name' => 'start-here',
		];

		$comments_query = new \WP_Comment_Query( $args );
		$comments = $comments_query->query( $args );

		foreach ( $comments as $comment ) {
			$this->assertTrue( $comment->elasticsearch );
			$this->assertEquals( $post_id, $comment->comment_post_ID );
		}

		$this->assertEquals( 2, count( $comments ) );
	}
}
