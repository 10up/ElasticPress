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

	/**
	 * Native discovery is capability protected and rejects wrong-site delivery.
	 *
	 * @group rest-sync
	 */
	public function test_native_discovery_permissions_and_context() {
		$request = new \WP_REST_Request( 'GET', '/elasticpress/v1/sync/post-types' );
		$request->set_param( 'site_id', get_current_blog_id() );
		$request->set_header( 'X-ElasticPress-Site', (string) ( get_current_blog_id() + 1 ) );
		$response = rest_do_request( $request );
		$this->assertSame( 409, $response->get_status() );
		$this->assertSame( 'ep_sync_site_context', $response->get_data()['code'] );

		$request->set_header( 'X-ElasticPress-Site', (string) get_current_blog_id() );
		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertContains( 'post', array_column( $response->get_data(), 0 ) );

		wp_set_current_user( 0 );
		$response = rest_do_request( $request );
		$this->assertSame( 401, $response->get_status() );
	}

	/**
	 * Forwarding sends only existing authentication to the trusted site's URL.
	 *
	 * @group rest-sync
	 */
	public function test_native_forwarding_is_bounded_and_fails_closed() {
		$site_id = self::factory()->blog->create();
		$request = new \WP_REST_Request( 'GET', '/elasticpress/v1/sync/post-types' );
		$request->set_param( 'site_id', $site_id );
		$request->set_header( 'X-WP-Nonce', 'test-rest-nonce' );
		$request->set_header( 'Authorization', 'must-not-forward' );
		$previous_cookie             = $_COOKIE[ LOGGED_IN_COOKIE ] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Preserve the test process cookie unchanged.
		$_COOKIE[ LOGGED_IN_COOKIE ] = 'test-login-cookie';
		$captured                    = [];
		$http_response               = [
			'response' => [ 'code' => 200 ],
			'body'     => '[ [ "ep_book", "Books" ] ]',
		];
		$intercept                   = static function ( $pre, $args, $url ) use ( &$captured, &$http_response ) {
			$captured = [ $args, $url ];
			return $http_response;
		};
		add_filter( 'pre_http_request', $intercept, 10, 3 );

		try {
			$response = rest_do_request( $request );
			$this->assertSame( 200, $response->get_status() );
			$this->assertSame( [ [ 'ep_book', 'Books' ] ], $response->get_data() );
			$this->assertSame( add_query_arg( 'site_id', $site_id, get_rest_url( $site_id, 'elasticpress/v1/sync/post-types' ) ), $captured[1] );
			$this->assertSame( 0, $captured[0]['redirection'] );
			$this->assertSame( 'test-rest-nonce', $captured[0]['headers']['X-WP-Nonce'] );
			$this->assertArrayNotHasKey( 'Authorization', $captured[0]['headers'] );
			$this->assertCount( 1, $captured[0]['cookies'] );
			$this->assertSame( LOGGED_IN_COOKIE, $captured[0]['cookies'][0]->name );

			$main_id = get_main_site_id();
			update_site_meta( $main_id, 'ep_indexable', 'no' );
			switch_to_blog( $site_id );

			try {
				$dispatch = new \ReflectionMethod( Sync::class, 'route_to_site' );
				$dispatch->setAccessible( true );
				$result = $dispatch->invoke( $this->controller, $request, $main_id, 'sync' );
				$this->assertSame( 200, $result->get_status(), 'An indexing-disabled main site can still coordinate global work.' );
			} finally {
				restore_current_blog();
				delete_site_meta( $main_id, 'ep_indexable' );
			}

			foreach ( [
				new \WP_Error( 'connection_failed' ),
				[
					'response' => [ 'code' => 302 ],
					'body'     => '{}',
				],
				[
					'response' => [ 'code' => 200 ],
					'body'     => '<html>Login</html>',
				],
			] as $failure ) {
				$http_response = $failure;
				$response      = rest_do_request( $request );
				$this->assertSame( 502, $response->get_status() );
				$this->assertFalse( Utils\get_indexing_status() );
			}
		} finally {
			remove_filter( 'pre_http_request', $intercept );

			if ( null === $previous_cookie ) {
				unset( $_COOKIE[ LOGGED_IN_COOKIE ] );
			} else {
				$_COOKIE[ LOGGED_IN_COOKIE ] = $previous_cookie;
			}
		}
	}

	/**
	 * Forwarded sync output retains the dashboard's existing prefix handling.
	 *
	 * @group rest-sync
	 */
	public function test_forwarded_sync_tolerates_prefix_but_rejects_invalid_json() {
		$site_id = self::factory()->blog->create();
		$job     = [
			'method'         => 'dashboard',
			'site_id'        => $site_id,
			'site_context'   => true,
			'origin_site_id' => get_main_site_id(),
			'sync_stack'     => [
				[
					'blog_id'   => $site_id,
					'indexable' => 'post',
				],
			],
		];
		Utils\update_option( 'ep_index_meta', $job );
		$request = new \WP_REST_Request( 'POST', '/elasticpress/v1/sync' );
		$request->set_param( 'site_id', $site_id );
		$request->set_header( 'X-WP-Nonce', 'test-rest-nonce' );
		$previous_cookie             = $_COOKIE[ LOGGED_IN_COOKIE ] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Preserve the test process cookie unchanged.
		$_COOKIE[ LOGGED_IN_COOKIE ] = 'test-login-cookie';
		$expected                    = [
			'data' => [
				'message'    => 'Processed posts',
				'index_meta' => $job,
				'status'     => 'info',
			],
		];
		$body                        = 'Shortcode output' . wp_json_encode( $expected );
		$intercept                   = static function () use ( &$body ) {
			return [
				'response' => [ 'code' => 200 ],
				'body'     => $body,
			];
		};
		add_filter( 'pre_http_request', $intercept );

		try {
			$response = $this->controller->sync( $request );
			$this->assertSame( 200, $response->get_status() );
			$this->assertSame( $expected, $response->get_data() );
			$this->assertSame( $job, Utils\get_option( 'ep_index_meta' ) );

			$discovery = new \WP_REST_Request( 'GET', '/elasticpress/v1/sync/post-types' );
			$discovery->set_param( 'site_id', $site_id );
			$discovery->set_header( 'X-WP-Nonce', 'test-rest-nonce' );
			$response = $this->controller->get_post_types( $discovery );
			$this->assertWPError( $response, 'Discovery does not normalize prefixed responses.' );
			$this->assertSame( 502, $response->get_error_data()['status'] );

			$body     = 'Shortcode output{"data":invalid JSON}';
			$response = $this->controller->sync( $request );
			$this->assertWPError( $response );
			$this->assertSame( 502, $response->get_error_data()['status'] );
			$this->assertSame( $job, Utils\get_option( 'ep_index_meta' ), 'A rejected response preserves the resumable job.' );
		} finally {
			remove_filter( 'pre_http_request', $intercept );
			Utils\delete_option( 'ep_index_meta' );

			if ( null === $previous_cookie ) {
				unset( $_COOKIE[ LOGGED_IN_COOKIE ] );
			} else {
				$_COOKIE[ LOGGED_IN_COOKIE ] = $previous_cookie;
			}
		}
	}

	/**
	 * Preparation and continuation choose a native site before indexing.
	 *
	 * @group rest-sync
	 */
	public function test_native_context_selection_and_preparation() {
		$site_id = self::factory()->blog->create();
		$main_id = get_main_site_id();
		$meta    = [
			'origin_site_id' => $main_id,
			'sync_stack'     => [
				[
					'blog_id'     => $main_id,
					'indexable'   => 'post',
					'found_items' => 3,
				],
				[
					'blog_id'   => $site_id,
					'indexable' => 'post',
				],
			],
		];
		$this->assertSame( $site_id, \ElasticPress\IndexHelper::get_sync_site_id( $meta ) );
		$meta['sync_stack'][1]['found_items'] = 4;
		$this->assertSame( $main_id, \ElasticPress\IndexHelper::get_sync_site_id( $meta ) );
		$meta['current_sync_item'] = [
			'blog_id'   => $site_id,
			'indexable' => 'post',
		];
		$this->assertSame( $site_id, \ElasticPress\IndexHelper::get_sync_site_id( $meta ) );
		$meta['current_sync_item'] = [ 'indexable' => 'global' ];
		$this->assertSame( $main_id, \ElasticPress\IndexHelper::get_sync_site_id( $meta ) );

		$captured = null;
		$capture  = static function ( $queue ) use ( &$captured ) {
			$captured = $queue;
			throw new \RuntimeException( 'Queue captured.' );
		};
		add_action( 'ep_sync_start_index', $capture );

		try {
			$request = new \WP_REST_Request( 'POST', '/elasticpress/v1/sync' );
			$request->set_param( 'site_id', $site_id );
			$request->set_param( 'indexables', [ 'post' ] );
			$request->set_param( 'post_type', [ 'native_book' ] );
			$this->controller->sync( $request );
		} catch ( \RuntimeException $exception ) {
			$this->assertSame( 'Queue captured.', $exception->getMessage() );
		} finally {
			remove_action( 'ep_sync_start_index', $capture );
		}

		$this->assertTrue( $captured['site_context'] );
		$this->assertArrayNotHasKey( 'found_items', $captured['sync_stack'][0] );
		$this->assertSame( [ 'native_book' ], $captured['sync_args']['post_type'] );
		$this->assertSame( $main_id, get_current_blog_id(), 'Queue creation restores the original site.' );
	}
}
