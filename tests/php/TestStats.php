<?php
/**
 * Test stats functionality
 *
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress;

/**
 * Stats test class
 */
class TestStats extends BaseTestCase {

	/**
	 * Setup each test.
	 *
	 * @since 3.2
	 */
	public function set_up() {
		global $wpdb;
		parent::set_up();
		$wpdb->suppress_errors();

		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		grant_super_admin( $admin_id );

		wp_set_current_user( $admin_id );

		ElasticPress\Elasticsearch::factory()->delete_all_indices();
		ElasticPress\Indexables::factory()->get( 'post' )->put_mapping();

		ElasticPress\Indexables::factory()->get( 'post' )->sync_manager->reset_sync_queue();

		$this->setup_test_post_type();

		$this->current_host = get_option( 'ep_host' );

		global $hook_suffix;
		$hook_suffix = 'sites.php';
		set_current_screen();
	}

	/**
	 * Clean up after each test.
	 *
	 * @since 3.2
	 */
	public function tear_down() {
		parent::tear_down();

		// Update since we are deleting to test notifications
		update_site_option( 'ep_host', $this->current_host );

		ElasticPress\Screen::factory()->set_current_screen( null );
	}

	/**
	 * Test totals
	 *
	 * @since 3.2
	 * @group stats
	 */
	public function testTotals() {
		$this->ep_factory->post->create();
		ElasticPress\Elasticsearch::factory()->refresh_indices();

		ElasticPress\Stats::factory()->build_stats();

		$totals = ElasticPress\Stats::factory()->get_totals();

		$this->assertEquals( 1, $totals['docs'] );
		$this->assertTrue( ! empty( $totals['size'] ) );
		$this->assertTrue( ! empty( $totals['memory'] ) );
	}

	/**
	 * Test health
	 *
	 * @since 3.2
	 * @group stats
	 */
	public function testHealth() {
		$this->ep_factory->post->create();
		ElasticPress\Elasticsearch::factory()->refresh_indices();

		ElasticPress\Stats::factory()->build_stats();

		$health = ElasticPress\Stats::factory()->get_health();

		$this->assertEquals( 1, count( $health ) );
		$this->assertEquals( 'exampleorg-post-1', array_keys( $health )[0] );
	}
}
