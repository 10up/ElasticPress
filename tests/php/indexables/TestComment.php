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

}
