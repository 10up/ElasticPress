<?php
/**
 * Test ElasticPress admin bar.
 *
 * @since 5.3.0
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress;

/**
 * Admin bar test class
 */
class TestAdminBar extends BaseTestCase {

	/**
	 * Test the `AdminBar::add_admin_bar_status` method.
	 *
	 * @since 5.3.0
	 * @group admin-bar
	 */
	public function test_add_admin_bar_status() {
		require_once ABSPATH . WPINC . '/class-wp-admin-bar.php';

		$admin_bar    = new \WP_Admin_Bar();
		$ep_admin_bar = new ElasticPress\AdminBar();

		$ep_admin_bar->add_admin_bar_status( $admin_bar );
		$this->assertArrayHasKey( 'ep-basic-status', $admin_bar->get_nodes() );
		$this->assertArrayHasKey( 'ep-basic-status-summary', $admin_bar->get_nodes() );

		add_filter( 'ep_admin_bar_should_display', '__return_false' );
		$admin_bar = new \WP_Admin_Bar();
		$ep_admin_bar->add_admin_bar_status( $admin_bar );
		$this->assertNull( $admin_bar->get_nodes() );
	}

	/**
	 * Test the `AdminBar::update_placeholders` method.
	 *
	 * @since 5.3.0
	 * @group admin-bar
	 */
	public function test_update_placeholders() {
		$ep_admin_bar = new ElasticPress\AdminBar();

		// Regular behavior.
		ob_start();
		$ep_admin_bar->update_placeholders();
		$output = ob_get_clean();
		$this->assertStringContainsString( '<script>', $output );
		$this->assertStringContainsString( 'document.getElementById("ep-ab-indicator").', $output );

		// Not displaying, using the ep_admin_bar_should_display filter.
		add_filter( 'ep_admin_bar_should_display', '__return_false' );
		ob_start();
		$ep_admin_bar->update_placeholders();
		$output = ob_get_clean();
		$this->assertEmpty( $output );
	}

	/**
	 * Test the `ep_admin_bar_status_and_summary` filter.
	 *
	 * @since 5.3.0
	 * @group admin-bar
	 */
	public function test_ep_admin_bar_status_and_summary_filter() {
		$ep_admin_bar = new ElasticPress\AdminBar();

		$change_status_and_summary = function ( $status_and_summary, $queries, $has_main_query, $is_main_query_success ) {
			$this->assertArrayHasKey( 'status', $status_and_summary );
			$this->assertArrayHasKey( 'summary', $status_and_summary );
			$this->assertIsArray( $queries );
			$this->assertIsBool( $has_main_query );
			$this->assertIsBool( $is_main_query_success );

			return [
				'status'  => 'custom',
				'summary' => [
					'custom' => 'Custom message',
				],
			];
		};
		add_filter( 'ep_admin_bar_status_and_summary', $change_status_and_summary, 10, 4 );

		ob_start();
		$ep_admin_bar->update_placeholders();
		$output = ob_get_clean();
		$this->assertStringContainsString( '<script>', $output );
		$this->assertStringContainsString( 'add("ep-status-indicator--custom")', $output );
		$this->assertStringContainsString( 'innerHTML = "Custom message"', $output );
	}

	/**
	 * Test the `ep_admin_bar_status_and_summary_output` filter.
	 *
	 * @since 5.3.0
	 * @group admin-bar
	 */
	public function test_ep_admin_bar_status_and_summary_output_filter() {
		$ep_admin_bar = new ElasticPress\AdminBar();

		$change_output = function ( $final_output, $filtered_status_and_summary, $queries ) {
			$this->assertStringContainsString( '<script>', $final_output );
			$this->assertArrayHasKey( 'status', $filtered_status_and_summary );
			$this->assertArrayHasKey( 'summary', $filtered_status_and_summary );
			$this->assertIsArray( $queries );

			return 'test';
		};
		add_filter( 'ep_admin_bar_status_and_summary_output', $change_output, 10, 3 );

		ob_start();
		$ep_admin_bar->update_placeholders();
		$output = ob_get_clean();
		$this->assertSame( 'test', $output );
	}
}
