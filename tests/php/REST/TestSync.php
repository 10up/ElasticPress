<?php
/**
 * Tests for dashboard sync site selection.
 *
 * @package elasticpress
 */

namespace ElasticPressTest\REST;

use ElasticPress\REST\Sync;
use ElasticPress\Utils;

/**
 * Verify target validation before any sync work starts.
 */
class TestSync extends \ElasticPressTest\BaseTestCase {
	/**
	 * Controller under test.
	 *
	 * @var Sync
	 */
	private $controller;

	/**
	 * Prepare an authenticated network administrator.
	 */
	public function set_up() {
		parent::set_up();

		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Network sync requires multisite.' );
		}

		$admin = self::factory()->user->create( [ 'role' => 'administrator' ] );
		grant_super_admin( $admin );
		wp_set_current_user( $admin );
		$this->controller = new Sync();
		Utils\delete_option( 'ep_index_meta' );
		add_action( 'rest_api_init', [ $this->controller, 'register_routes' ] );
		do_action( 'rest_api_init', rest_get_server() );
		remove_action( 'rest_api_init', [ $this->controller, 'register_routes' ] );
	}

	/**
	 * Invalid targets must not create a job or clear network notices.
	 *
	 * @group rest-sync
	 */
	public function test_invalid_site_ids_do_not_start_sync() {
		Utils\update_option( 'ep_need_upgrade_sync', true );
		$last_sync = Utils\get_option( 'ep_last_sync' );

		foreach ( [ 0, -1, '', 'missing', 1.5, null, 999999 ] as $site_id ) {
			$request = new \WP_REST_Request( 'POST', '/elasticpress/v1/sync' );
			$request->set_param( 'site_id', $site_id );
			$response = rest_do_request( $request );

			$this->assertSame( 400, $response->get_status() );
			$this->assertFalse( Utils\get_indexing_status() );
			$this->assertSame( $last_sync, Utils\get_option( 'ep_last_sync' ) );
			$this->assertTrue( Utils\get_option( 'ep_need_upgrade_sync' ) );
		}
	}

	/**
	 * All four network eligibility exclusions apply to explicit targets.
	 *
	 * @group rest-sync
	 */
	public function test_ineligible_sites_are_rejected() {
		$site_id = self::factory()->blog->create();

		foreach ( [ 'disabled', 'archived', 'spam', 'deleted' ] as $state ) {
			if ( 'disabled' === $state ) {
				update_site_meta( $site_id, 'ep_indexable', 'no' );
			} else {
				wp_update_site( $site_id, [ $state => 1 ] );
			}

			$request = new \WP_REST_Request( 'POST', '/elasticpress/v1/sync' );
			$request->set_param( 'site_id', $site_id );
			$response = rest_do_request( $request );

			$this->assertSame( 400, $response->get_status(), $state );
			$this->assertSame( 'ep_invalid_sync_site', $response->get_data()['code'] );
			$this->assertFalse( Utils\get_indexing_status() );
			$this->assertNotContains( $site_id, array_map( 'intval', wp_list_pluck( Utils\get_sync_sites(), 'blog_id' ) ) );

			if ( 'disabled' === $state ) {
				delete_site_meta( $site_id, 'ep_indexable' );
			} else {
				wp_update_site( $site_id, [ $state => 0 ] );
			}
		}
	}

	/**
	 * Existing network site filters also constrain explicit selections.
	 *
	 * @group rest-sync
	 */
	public function test_filtered_site_is_rejected() {
		$site_id = self::factory()->blog->create();
		$exclude = static function ( $sites ) use ( $site_id ) {
			return array_filter( $sites, static fn( $site ) => (int) $site['blog_id'] !== $site_id );
		};
		add_filter( 'ep_indexable_sites', $exclude );

		try {
			$request = new \WP_REST_Request( 'POST', '/elasticpress/v1/sync' );
			$request->set_param( 'site_id', $site_id );
			$response = rest_do_request( $request );

			$this->assertSame( 400, $response->get_status() );
			$this->assertFalse( Utils\get_indexing_status() );
		} finally {
			remove_filter( 'ep_indexable_sites', $exclude );
		}
	}

	/**
	 * An otherwise eligible site in another network is not a target.
	 *
	 * @group rest-sync
	 */
	public function test_other_network_site_is_rejected() {
		$network_id = self::factory()->network->create();
		$site_id    = self::factory()->blog->create( [ 'site_id' => $network_id ] );
		$request    = new \WP_REST_Request( 'POST', '/elasticpress/v1/sync' );
		$request->set_param( 'site_id', $site_id );
		$response = rest_do_request( $request );

		$this->assertSame( 400, $response->get_status() );
		$this->assertFalse( Utils\get_indexing_status() );
	}

	/**
	 * A stale or conflicting selection must not change the existing job.
	 *
	 * @group rest-sync
	 */
	public function test_active_target_is_preserved_and_revalidated() {
		$site_id = self::factory()->blog->create();
		$job     = [
			'method'     => 'dashboard',
			'site_id'    => $site_id,
			'sync_stack' => [],
		];
		Utils\update_option( 'ep_index_meta', $job );

		$request = new \WP_REST_Request( 'POST', '/elasticpress/v1/sync' );
		$request->set_param( 'site_id', get_current_blog_id() );
		$response = rest_do_request( $request );

		$this->assertSame( 409, $response->get_status() );
		$this->assertSame( $job, Utils\get_option( 'ep_index_meta' ) );

		update_site_meta( $site_id, 'ep_indexable', 'no' );
		$response = rest_do_request( new \WP_REST_Request( 'POST', '/elasticpress/v1/sync' ) );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( $job, Utils\get_option( 'ep_index_meta' ) );
		Utils\delete_option( 'ep_index_meta' );
	}

	/**
	 * Targeted queue creation records exactly the selected site's post task.
	 *
	 * @group rest-sync
	 */
	public function test_targeted_queue_and_omitted_target_behavior() {
		$site_id = self::factory()->blog->create();
		$queues  = [];
		$capture = static function ( $meta ) use ( &$queues ) {
			$queues[] = $meta;
			throw new \RuntimeException( 'Queue captured before indexing.' );
		};
		add_action( 'ep_sync_start_index', $capture );

		try {
			foreach ( [ $site_id, null ] as $target ) {
				Utils\delete_option( 'ep_index_meta' );
				$request = new \WP_REST_Request( 'POST', '/elasticpress/v1/sync' );
				$request->set_param( 'indexables', [ 'post' ] );

				if ( null !== $target ) {
					$request->set_param( 'site_id', $target );
				}

				try {
					$this->controller->sync( $request );
					$this->fail( 'The validated request should build a queue.' );
				} catch ( \RuntimeException $exception ) {
					$this->assertSame( 'Queue captured before indexing.', $exception->getMessage() );
				}
			}
		} finally {
			remove_action( 'ep_sync_start_index', $capture );
			Utils\delete_option( 'ep_index_meta' );
		}

		$this->assertSame( $site_id, $queues[0]['site_id'] );
		$this->assertSame( [ $site_id ], wp_list_pluck( $queues[0]['sync_stack'], 'blog_id' ) );
		$this->assertNull( $queues[1]['site_id'] );
		$this->assertContains( $site_id, wp_list_pluck( $queues[1]['sync_stack'], 'blog_id' ) );
		$this->assertContains( get_main_site_id(), wp_list_pluck( $queues[1]['sync_stack'], 'blog_id' ) );
	}

	/**
	 * Explicit shared-only selections cannot become an empty successful site sync.
	 *
	 * @group rest-sync
	 */
	public function test_targeted_global_only_selection_is_rejected_before_side_effects() {
		$site_id = self::factory()->blog->create();
		Utils\update_option( 'ep_need_upgrade_sync', true );
		$last_sync = Utils\get_option( 'ep_last_sync' );

		foreach ( [ false, true ] as $fresh ) {
			$request = new \WP_REST_Request( 'POST', '/elasticpress/v1/sync' );
			$request->set_param( 'site_id', $site_id );
			$request->set_param( 'indexables', [ 'global' ] );
			$request->set_param( 'put_mapping', $fresh );
			$response = rest_do_request( $request );

			$this->assertSame( 400, $response->get_status() );
			$this->assertSame( 'ep_invalid_sync_indexables', $response->get_data()['code'] );
			$this->assertFalse( Utils\get_indexing_status() );
			$this->assertSame( $last_sync, Utils\get_option( 'ep_last_sync' ) );
			$this->assertTrue( Utils\get_option( 'ep_need_upgrade_sync' ) );
		}
	}

	/**
	 * Targeted queues omit active and inactive globals; network and CLI keep them.
	 *
	 * @group rest-sync
	 */
	public function test_global_indexable_scope_in_sync_queues() {
		$registry = \ElasticPress\Indexables::factory();
		$registry->activate( 'global' );
		$inactive_global       = new \ElasticPressTest\GlobalIndexable\Indexable();
		$inactive_global->slug = 'inactive_global';
		$registry->register( $inactive_global, false );
		$site_id = self::factory()->blog->create();
		$queues  = [];
		$capture = static function ( $meta ) use ( &$queues ) {
			$queues[] = $meta;
			throw new \RuntimeException( 'Queue captured before indexing.' );
		};
		add_action( 'ep_sync_start_index', $capture );

		$cases = [
			[ 'site_id' => $site_id ],
			[
				'site_id'     => $site_id,
				'put_mapping' => true,
			],
			[
				'site_id'    => $site_id,
				'indexables' => [ 'post', 'global', 'inactive_global' ],
			],
			[ 'indexables' => [ 'global' ] ],
			[
				'indexables'  => [ 'global' ],
				'put_mapping' => true,
			],
		];

		try {
			foreach ( $cases as $args ) {
				Utils\delete_option( 'ep_index_meta' );
				$request = new \WP_REST_Request( 'POST', '/elasticpress/v1/sync' );
				$request->set_body_params( $args );

				try {
					$this->controller->sync( $request );
					$this->fail( 'Expected a validated sync queue.' );
				} catch ( \RuntimeException $exception ) {
					$this->assertSame( 'Queue captured before indexing.', $exception->getMessage() );
				}
			}

			Utils\delete_option( 'ep_index_meta' );

			try {
				\ElasticPress\IndexHelper::factory()->full_index(
					[
						'method'        => 'cli',
						'indexables'    => [ 'global' ],
						'output_method' => static function () {},
					]
				);
				$this->fail( 'Expected the existing CLI shared queue.' );
			} catch ( \RuntimeException $exception ) {
				$this->assertSame( 'Queue captured before indexing.', $exception->getMessage() );
			}
		} finally {
			remove_action( 'ep_sync_start_index', $capture );
			$registry->deactivate( 'global' );
			$registry->unregister( 'inactive_global' );
			Utils\delete_option( 'ep_index_meta' );
		}

		foreach ( array_slice( $queues, 0, 3 ) as $queue ) {
			$this->assertSame( $site_id, $queue['site_id'] );
			$this->assertNotEmpty( $queue['sync_stack'] );
			$this->assertNotContains( 'global', wp_list_pluck( $queue['sync_stack'], 'indexable' ) );
			$this->assertNotContains( 'inactive_global', wp_list_pluck( $queue['sync_stack'], 'indexable' ) );
			$this->assertSame( [ $site_id ], array_values( array_unique( wp_list_pluck( $queue['sync_stack'], 'blog_id' ) ) ) );
		}

		foreach ( array_slice( $queues, 3 ) as $queue ) {
			$this->assertSame( [ 'global' ], wp_list_pluck( $queue['sync_stack'], 'indexable' ) );
		}
	}
}
