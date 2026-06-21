<?php
/**
 * Test post sync when Post Search feature is disabled
 *
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress;

/**
 * Test post sync without search feature class
 */
class TestPostSyncWithoutSearch extends BaseTestCase {

	/**
	 * Setup each test.
	 *
	 * @since 5.4.0
	 */
	public function set_up() {
		global $wpdb;
		parent::set_up();
		$wpdb->suppress_errors();

		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		ElasticPress\Elasticsearch::factory()->delete_all_indices();

		/**
		 * The post indexable should be activated by the plugin bootstrap
		 * regardless of the Post Search feature state.
		 */
		ElasticPress\Indexables::factory()->get( 'post' )->put_mapping();
		ElasticPress\Indexables::factory()->get( 'post' )->sync_manager->reset_sync_queue();

		$this->setup_test_post_type();

		// Disable Post Search and set up remaining features.
		ElasticPress\Features::factory()->deactivate_feature( 'search' );
		ElasticPress\Features::factory()->setup_features();
	}

	/**
	 * Test a simple post sync with Post Search disabled
	 *
	 * @since 5.4.0
	 * @group post
	 */
	public function testPostSyncWithSearchDisabled() {
		add_action( 'ep_sync_on_transition', array( $this, 'action_sync_on_transition' ), 10, 0 );

		$post_id = $this->ep_factory->post->create();

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$this->assertTrue( ! empty( $this->fired_actions['ep_sync_on_transition'] ) );

		$post = ElasticPress\Indexables::factory()->get( 'post' )->get( $post_id );
		$this->assertTrue( ! empty( $post ) );
	}
}
