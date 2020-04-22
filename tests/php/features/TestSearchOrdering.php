<?php
/**
 * Test search feature
 *
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress;

/**
 * Search test class
 */
class TestSearchOrdering extends BaseTestCase {

	/**
	 * Setup each test.
	 *
	 * @since 2.1
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
		ElasticPress\Features::factory()->activate_feature( 'search' );
		ElasticPress\Features::factory()->activate_feature( 'searchordering' );
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
	 * @return weighting sub-feature
	 */
	public function get_feature() {
		return ElasticPress\Features::factory()->get_registered_feature( 'searchordering' );
	}

	public function testConstruct() {
		$instance = new \ElasticPress\Feature\SearchOrdering\SearchOrdering();
		$this->assertEquals( 'searchordering', $instance->slug );
		$this->assertEquals( 'Custom Search Results', $instance->title );
	}

	public function testFilterUpdatedMessages() {
		$post_id = Functions\create_and_sync_post( array( 'post_content' => 'findme test 1' ) );
		$GLOBALS['post'] = get_post( $post_id );
		$messages = $this->get_feature()->filter_updated_messages([]);

		$this->assertArrayHasKey( 'ep-pointer', $messages );
	}

}
