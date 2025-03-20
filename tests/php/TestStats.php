<?php
/**
 * Test stats functionality
 *
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress;
use ElasticPress\Elasticsearch;
use ElasticPress\Indexables;
use ElasticPress\Stats;

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

		Elasticsearch::factory()->delete_all_indices();
		Indexables::factory()->get( 'post' )->put_mapping();

		Indexables::factory()->get( 'post' )->sync_manager->reset_sync_queue();

		$this->setup_test_post_type();

		$this->current_host = get_option( 'ep_host' );

		global $hook_suffix;
		$hook_suffix = 'sites.php';
		set_current_screen();

		Stats::factory()->clear_failed_queries();
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
		Elasticsearch::factory()->refresh_indices();

		Stats::factory()->build_stats();

		$totals = Stats::factory()->get_totals();

		$this->assertEquals( 1, $totals['docs'] );
		$this->assertTrue( ! empty( $totals['size'] ) );
		$this->assertTrue( isset( $totals['memory'] ) );

		$this->assertEmpty( Stats::factory()->get_failed_queries() );

		$this->markTestIncomplete( 'Memory numbers are always 0 with Elasticsearch 8.x' );
	}

	/**
	 * Test health
	 *
	 * @since 3.2
	 * @group stats
	 */
	public function testHealth() {
		$this->ep_factory->post->create();
		Elasticsearch::factory()->refresh_indices();

		Stats::factory()->build_stats();

		$health = Stats::factory()->get_health();

		$this->assertEquals( 1, count( $health ) );
		$this->assertEquals( 'exampleorg-post-1', array_keys( $health )[0] );
	}

	/**
	 * Test if a failed query is registered if a request returns a WP_Error
	 *
	 * @since 5.0.1
	 * @group stats
	 */
	public function test_failed_queries_wp_error() {
		add_filter( 'ep_intercept_remote_request', '__return_true' );

		$return_wp_error = function () {
			return new \WP_Error( 'code', 'Message' );
		};
		add_filter( 'ep_do_intercept_request', $return_wp_error );

		Stats::factory()->build_stats( true );
		$failed_queries = Stats::factory()->get_failed_queries();
		$this->assertSame(
			[
				[
					'path'  => '_stats?format=json',
					'error' => 'Message',
				],
			],
			$failed_queries
		);
	}

	/**
	 * Test if a failed query is registered if a request returns an Elasticsearch error
	 *
	 * @since 5.0.1
	 * @group stats
	 */
	public function test_failed_queries_es_error() {
		add_filter( 'ep_intercept_remote_request', '__return_true' );

		$return_es_error = function () {
			return [
				'body' => wp_json_encode( [ 'errors' => [ 'some error data' ] ] ),
			];
		};
		add_filter( 'ep_do_intercept_request', $return_es_error );

		Stats::factory()->build_stats( true );
		$failed_queries = Stats::factory()->get_failed_queries();
		$this->assertSame(
			[
				[
					'path'  => '_stats?format=json',
					'error' => '["some error data"]',
				],
			],
			$failed_queries
		);
	}
}
