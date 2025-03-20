<?php
/**
 * WooCommerce Base Test Case
 *
 * @since 5.0.0
 * @package elasticpress
 */

namespace ElasticPressTest;

/**
 * WooCommerceBaseTestCase class
 */
class WooCommerceBaseTestCase extends BaseTestCase {
	/**
	 * Setup each test.
	 *
	 * @group woocommerce
	 */
	public function set_up() {
		global $wpdb;
		parent::set_up();
		$wpdb->suppress_errors();

		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );

		wp_set_current_user( $admin_id );

		\ElasticPress\Elasticsearch::factory()->delete_all_indices();
		\ElasticPress\Indexables::factory()->get( 'post' )->put_mapping();

		\ElasticPress\Indexables::factory()->get( 'post' )->sync_manager->sync_queue = [];

		$this->setup_test_post_type();
	}

	/**
	 * Clean up after each test. Reset our mocks
	 *
	 * @group woocommerce
	 */
	public function tear_down() {
		parent::tear_down();

		$this->fired_actions = array();
	}
}
