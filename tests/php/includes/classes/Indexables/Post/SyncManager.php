<?php
/**
 * Test post SyncManager functionality
 *
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress;

/**
 * Test post indexable class
 */
class TestPostSyncManager extends BaseTestCase {
	public function testPermissionCheckBypassOnCron() {
		$indexable = \ElasticPress\Indexables::factory()->get( 'post' );

		$sync_manager = $indexable->sync_manager;

		define( 'DOING_CRON', true );

		$insert_bypass_result = apply_filters( 'ep_sync_insert_permissions_bypass', false, 1 );
		$delete_bypass_result = apply_filters( 'ep_sync_delete_permissions_bypass', false, 1 );

		$this->assertEquals( true, $insert_bypass_result, 'Insert bypass filtered value is not true' );
		$this->assertEquals( true, $delete_bypass_result, 'Delete bypass filtered value is not true' );
	}

	public function testPermissionCheckBypassOnWPCLI() {
		$indexable = \ElasticPress\Indexables::factory()->get( 'post' );

		$sync_manager = $indexable->sync_manager;

		define( 'WP_CLI', true );

		$insert_bypass_result = apply_filters( 'ep_sync_insert_permissions_bypass', false, 1 );
		$delete_bypass_result = apply_filters( 'ep_sync_delete_permissions_bypass', false, 1 );

		$this->assertEquals( true, $insert_bypass_result, 'Insert bypass filtered value is not true' );
		$this->assertEquals( true, $delete_bypass_result, 'Delete bypass filtered value is not true' );
	}
}