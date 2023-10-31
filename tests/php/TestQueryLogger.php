<?php
/**
 * Test the Query Logger
 *
 * @phpcs:disable WordPress.DateTime.CurrentTimeTimestamp.Requested
 *
 * @since 4.4.0
 * @package elasticpress
 */

namespace ElasticPressTest;

use \ElasticPress\QueryLogger;

/**
 * Test the Query Logger class
 */
class TestQueryLogger extends BaseTestCase {
	/**
	 * Setup each test
	 */
	public function set_up() {
		update_site_option( 'ep_last_sync', time() );

		parent::set_up();
	}

	/**
	 * Clean up after each test
	 */
	public function tear_down() {
		parent::tear_down();

		delete_site_option( 'ep_last_sync' );
	}

	/**
	 * Test the log_query method before a sync is performed
	 *
	 * @group queryLogger
	 */
	public function testLogQueryBeforeFirstSync() {
		$query_logger = $this->getMockBuilder( QueryLogger::class )
			->setMethods( [ 'get_logs' ] )
			->getMock();

		// Call the log_query method twice but it should call get_logs only in the second call, after setting ep_last_sync
		$query_logger->expects( $this->exactly( 1 ) )->method( 'get_logs' );

		$query_logger->log_query( [], '' );

		delete_site_option( 'ep_last_sync', time() );
		$query_logger->log_query( [], '' );
	}

	/**
	 * Test the ep_query_logger_queries_to_keep filter in the log_query method
	 *
	 * @group queryLogger
	 */
	public function testLogQueryQueriesToKeepFilter() {
		$query_logger = $this->getMockBuilder( QueryLogger::class )
			->setMethods( [ 'get_logs', 'should_log_query_type', 'format_log_entry', 'update_logs' ] )
			->getMock();

		$query_logger->method( 'get_logs' )->willReturn( [ 1, 2, 3, 4, 5 ] );
		$query_logger->expects( $this->exactly( 1 ) )->method( 'should_log_query_type' )->willReturn( true );
		$query_logger->expects( $this->exactly( 1 ) )->method( 'format_log_entry' )->willReturn( [ 'entry' ] );
		$query_logger->expects( $this->exactly( 1 ) )->method( 'update_logs' )->willReturn( '{somejson}' );

		$query_logger->log_query( [ 'query' ], 'type' );
		$this->assertEquals( 0, did_action( 'ep_query_logger_logged_query' ) );

		add_filter(
			'ep_query_logger_queries_to_keep',
			function( $keep, $query, $type ) {
				$this->assertSame( 5, $keep );
				$this->assertSame( [ 'query' ], $query );
				$this->assertSame( 'type', $type );
				return 10;
			},
			10,
			3
		);

		$query_logger->log_query( [ 'query' ], 'type' );
		$this->assertGreaterThanOrEqual( 1, did_filter( 'ep_query_logger_queries_to_keep' ) );
		$this->assertGreaterThanOrEqual( 1, did_action( 'ep_query_logger_logged_query' ) );
	}

	/**
	 * Test the ep_query_logger_logged_query action in the log_query method
	 *
	 * @group queryLogger
	 */
	public function testLogQueryLoggedQueryAction() {
		$query_logger = $this->getMockBuilder( QueryLogger::class )
			->setMethods( [ 'get_logs', 'should_log_query_type', 'format_log_entry', 'update_logs' ] )
			->disableOriginalConstructor()
			->disableOriginalClone()
			->disableArgumentCloning()
			->disallowMockingUnknownTypes()
			->getMock();

		$query_logger->method( 'get_logs' )->willReturn( [] );
		$query_logger->method( 'should_log_query_type' )->willReturn( true );
		$query_logger->method( 'format_log_entry' )->willReturn( [ 'entry' ] );
		$query_logger->method( 'update_logs' )->willReturn( '{somejson}' );

		add_action(
			'ep_query_logger_logged_query',
			function( $logs_json_str, $query, $type ) {
				$this->assertSame( '{somejson}', $logs_json_str );
				$this->assertSame( [ 'query' ], $query );
				$this->assertSame( 'type', $type );
			},
			10,
			3
		);

		$query_logger->log_query( [ 'query' ], 'type' );
		$this->assertGreaterThanOrEqual( 1, did_action( 'ep_query_logger_logged_query' ) );
	}

	/**
	 * Test the get_logs method
	 *
	 * @group queryLogger
	 */
	public function testGetLogs() {
		$current_time = current_time( 'timestamp' );
		$test_logs    = [
			[ 'timestamp' => $current_time - DAY_IN_SECONDS - 5 ],
			[ 'timestamp' => $current_time - DAY_IN_SECONDS + 5 ],
		];

		add_filter(
			defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ? 'pre_site_transient_ep_query_log' : 'pre_transient_ep_query_log',
			function() use ( $test_logs ) {
				return wp_json_encode( $test_logs );
			}
		);

		$query_logger = new QueryLogger();

		$this->assertCount( 1, $query_logger->get_logs() );
		$this->assertCount( 2, $query_logger->get_logs( false ) );

		/**
		 * Test the ep_query_logger_time_to_keep filter
		 */
		$change_time_limit = function( $limit ) {
			$this->assertSame( $limit, DAY_IN_SECONDS );
			return 25 * HOUR_IN_SECONDS;
		};
		add_filter( 'ep_query_logger_time_to_keep', $change_time_limit );

		$this->assertCount( 2, $query_logger->get_logs() );
		$this->assertGreaterThanOrEqual( 1, did_filter( 'ep_query_logger_time_to_keep' ) );

		/**
		 * Test the ep_query_logger_logs filter
		 */
		$change_logs = function( $logs ) use ( $test_logs ) {
			$this->assertSame( $logs, $test_logs );
			return [ 'custom-logs' ];
		};
		add_filter( 'ep_query_logger_logs', $change_logs );

		$this->assertSame( [ 'custom-logs' ], $query_logger->get_logs() );
		$this->assertGreaterThanOrEqual( 1, did_filter( 'ep_query_logger_logs' ) );
	}

	/**
	 * Test the testUpdateLogs method
	 *
	 * @group queryLogger
	 */
	public function testUpdateLogs() {
		$query_logger = new QueryLogger();

		/**
		 * Test the ep_query_logger_max_cache_size filter
		 */
		add_filter(
			'ep_query_logger_max_cache_size',
			function ( $size ) {
				$this->assertSame( MB_IN_BYTES, $size );
				return $size;
			}
		);

		$updated_logs = $query_logger->update_logs( [ 'test' ] );
		$this->assertGreaterThanOrEqual( 1, did_filter( 'ep_query_logger_max_cache_size' ) );
		$this->assertSame( wp_json_encode( [ 'test' ] ), $updated_logs );

		$this->markTestIncomplete( 'This test should also test the removal of data based on the cache size.' );
	}

	/**
	 * Test the clear_logs method
	 *
	 * @group queryLogger
	 */
	public function testClearLogs() {
		$query_logger = new QueryLogger();
		$query_logger->clear_logs();
		$this->assertEquals( 1, did_action( 'ep_query_logger_cleared_logs' ) );
	}

	/**
	 * Test the maybe_add_notice method
	 *
	 * @group queryLogger
	 */
	public function testMaybeAddNotice() {
		/**
		 * Initial setup
		 */
		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		grant_super_admin( $admin_id );
		wp_set_current_user( $admin_id );

		$query_logger = new QueryLogger();

		$add_fake_log = function() {
			return [ 'fake-log' ];
		};
		add_filter( 'ep_query_logger_logs', $add_fake_log );

		\ElasticPress\Screen::factory()->set_current_screen( 'features' );

		/**
		 * Check messages when no indices are found
		 */
		\ElasticPress\Elasticsearch::factory()->delete_all_indices();
		$notices = $query_logger->maybe_add_notice( [] );
		$this->assertArrayHasKey( 'has_failed_queries', $notices );
		$this->assertStringStartsWith( 'Your site&#039;s content is not synced with your', $notices['has_failed_queries']['html'] );
		if ( \ElasticPress\Utils\is_epio() ) {
			$this->assertStringContainsString( 'ElasticPress account', $notices['has_failed_queries']['html'] );
		} else {
			$this->assertStringContainsString( 'Elasticsearch server', $notices['has_failed_queries']['html'] );
		}

		/**
		 * Generic check (we have at least one index present)
		 */
		\ElasticPress\Indexables::factory()->get( 'post' )->put_mapping();
		$notices = $query_logger->maybe_add_notice( [] );
		$this->assertArrayHasKey( 'has_failed_queries', $notices );
		$this->assertStringStartsWith( 'Some ElasticPress queries failed in the last 24 hours.', $notices['has_failed_queries']['html'] );

		/**
		 * No message when no failed queries
		 */
		remove_filter( 'ep_query_logger_logs', $add_fake_log );
		$notices = $query_logger->maybe_add_notice( [] );
		$this->assertEmpty( $notices );
		add_filter( 'ep_query_logger_logs', $add_fake_log );

		$notices = $query_logger->maybe_add_notice( [] );
		$this->assertArrayHasKey( 'has_failed_queries', $notices );

		/**
		 * No messages when dismissed
		 */
		add_filter( 'pre_site_option_ep_hide_has_failed_queries_notice', '__return_true' );
		add_filter( 'pre_option_ep_hide_has_failed_queries_notice', '__return_true' );
		$notices = $query_logger->maybe_add_notice( [] );
		$this->assertEmpty( $notices );
		remove_filter( 'pre_site_option_ep_hide_has_failed_queries_notice', '__return_true' );
		remove_filter( 'pre_option_ep_hide_has_failed_queries_notice', '__return_true' );

		$notices = $query_logger->maybe_add_notice( [] );
		$this->assertArrayHasKey( 'has_failed_queries', $notices );

		/**
		 * No message when on status-report page
		 */
		\ElasticPress\Screen::factory()->set_current_screen( 'status-report' );
		$notices = $query_logger->maybe_add_notice( [] );
		$this->assertEmpty( $notices );
		\ElasticPress\Screen::factory()->set_current_screen( 'features' );

		$notices = $query_logger->maybe_add_notice( [] );
		$this->assertArrayHasKey( 'has_failed_queries', $notices );

		/**
		 * No message for users without the capability
		 */
		$author_id = $this->factory->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $author_id );

		$notices = $query_logger->maybe_add_notice( [] );
		$this->assertEmpty( $notices );

		wp_set_current_user( $admin_id );
		$notices = $query_logger->maybe_add_notice( [] );
		$this->assertArrayHasKey( 'has_failed_queries', $notices );

		// Reset current screen
		\ElasticPress\Screen::factory()->set_current_screen( null );
	}

	/**
	 * Test the format_log_entry method
	 *
	 * @group queryLogger
	 */
	public function testFormatLogEntry() {
		$query_logger = new QueryLogger();

		parse_str( 'query-string=test', $_GET );
		$query = [
			'time_start'  => 1,
			'time_finish' => 2,
			'args'        => [
				'method' => 'GET',
				'body'   => 'request body plain text',
			],
			'request'     => null,
			'url'         => 'ep-url',
			'query_args'  => [ 'post_type' => 'test' ],
		];

		$class  = new \ReflectionClass( $query_logger );
		$method = $class->getMethod( 'format_log_entry' );
		$method->setAccessible( true );
		$formatted_log = $method->invokeArgs( $query_logger, [ $query, 'type' ] );

		$this->assertStringContainsString( 'query-string=test', $formatted_log['wp_url'] );
		$this->assertSame( 'GET ep-url', $formatted_log['es_req'] );
		$this->assertSame( current_time( 'timestamp' ), $formatted_log['timestamp'] );
		$this->assertSame( 1000, $formatted_log['query_time'] );
		$this->assertSame( [ 'post_type' => 'test' ], $formatted_log['wp_args'] );
		$this->assertSame( 'request body plain text', $formatted_log['body'] );
		$this->assertGreaterThanOrEqual( 1, did_filter( 'ep_query_logger_formatted_query' ) );

		$this->markTestIncomplete( 'This test still needs to test different bodies and result status code and body' );
	}

	/**
	 * Test the `format_log_entry` method when the request is a WP_Error object
	 *
	 * @since 5.0.0
	 * @group queryLogger
	 */
	public function test_format_log_entry_with_wp_error() {
		$query_logger = new QueryLogger();
		$query = [
			'time_start'  => 1,
			'time_finish' => 2,
			'args'        => [
				'method' => 'GET',
				'body'   => 'request body plain text',
			],
			'request'     => new \WP_Error( 123, 'Custom message', 'additional data' ),
			'url'         => 'ep-url',
			'query_args'  => [ 'post_type' => 'test' ],
		];

		$class  = new \ReflectionClass( $query_logger );
		$method = $class->getMethod( 'format_log_entry' );
		$method->setAccessible( true );
		$formatted_log = $method->invokeArgs( $query_logger, [ $query, 'type' ] );

		$this->assertSame(
			[
				'is_wp_error' => true,
				'code'        => 123,
				'message'     => 'Custom message',
				'data'        => 'additional data',
			],
			$formatted_log['result']
		);
	}

	/**
	 * Test the should_log_query_type method
	 *
	 * @group queryLogger
	 */
	public function testShouldLogQueryType() {
		$query_logger = new QueryLogger();

		$class  = new \ReflectionClass( $query_logger );
		$method = $class->getMethod( 'should_log_query_type' );
		$method->setAccessible( true );

		/**
		 * Test the `ep_query_logger_allowed_log_types` filter
		 */
		add_filter(
			'ep_query_logger_allowed_log_types',
			function ( $callable_map, $query, $type ) {
				$this->assertSame(
					[ 'put_mapping', 'delete_network_alias', 'create_network_alias', 'bulk_index', 'delete_index', 'create_pipeline', 'get_pipeline', 'query' ],
					array_keys( $callable_map )
				);
				$this->assertSame( [], $query );
				$this->assertContains( $type, [ 'type-should-log', 'type-should-not-log' ] );
				$callable_map['type-should-log']     = '__return_true';
				$callable_map['type-should-not-log'] = '__return_false';
				return $callable_map;
			},
			10,
			3
		);

		$this->assertTrue( $method->invokeArgs( $query_logger, [ [], 'type-should-log' ] ) );
		$this->assertFalse( $method->invokeArgs( $query_logger, [ [], 'type-should-not-log' ] ) );

		/**
		 * Test the `ep_query_logger_should_log_query` filter
		 *
		 * Even though the `type-should-not-log` type should NOT log, this will return true
		 */
		add_filter(
			'ep_query_logger_should_log_query',
			function( $should_log, $query, $type ) {
				$this->assertSame( [], $query );
				$this->assertSame( $type, 'type-should-not-log' );

				return true;
			},
			10,
			3
		);
		$this->assertTrue( $method->invokeArgs( $query_logger, [ [], 'type-should-not-log' ] ) );
	}

	/**
	 * Test the is_bulk_index_error method
	 *
	 * @group queryLogger
	 */
	public function testIsBulkIndexError() {
		$this->markTestIncomplete();
	}

	/**
	 * Test the maybe_log_delete_index method
	 *
	 * @param bool $expected    Expected maybe_log_delete_index return
	 * @param int  $status_code HTTP Status Code
	 * @dataProvider maybeDeleteIndexDataProvider
	 * @group queryLogger
	 */
	public function testMaybeLogDeleteIndex( $expected, $status_code ) {
		$query_logger = new QueryLogger();

		$class  = new \ReflectionClass( $query_logger );
		$method = $class->getMethod( 'maybe_log_delete_index' );
		$method->setAccessible( true );

		$query = [
			'request' => [
				'response' => [
					'code' => $status_code,
				],
			],
		];

		$this->assertSame( $expected, $method->invokeArgs( $query_logger, [ $query ] ) );
	}

	/**
	 * Test the is_query_error method with a WP_Error
	 *
	 * @group queryLogger
	 */
	public function testIsQueryErrorWithWPError() {
		$query_logger = new QueryLogger();

		$class  = new \ReflectionClass( $query_logger );
		$method = $class->getMethod( 'is_query_error' );
		$method->setAccessible( true );

		$this->assertTrue( $method->invokeArgs( $query_logger, [ [ 'request' => new \WP_Error() ] ] ) );
	}

	/**
	 * Test the is_query_error method with a request status code
	 *
	 * @param bool $expected    Expected maybe_log_delete_index return
	 * @param int  $status_code HTTP Status Code
	 * @dataProvider isQueryErrorWithStatusCodeDataProvider
	 * @group queryLogger
	 */
	public function testIsQueryErrorWithStatusCode( $expected, $status_code ) {
		$query_logger = new QueryLogger();

		$class  = new \ReflectionClass( $query_logger );
		$method = $class->getMethod( 'is_query_error' );
		$method->setAccessible( true );

		$query = [
			'request' => [
				'response' => [
					'code' => $status_code,
				],
			],
		];

		$this->assertSame( $expected, $method->invokeArgs( $query_logger, [ $query ] ) );
	}

	/**
	 * Data provider for the testMaybeLogDeleteIndex method
	 *
	 * @return array
	 */
	public function maybeDeleteIndexDataProvider() : array {
		return [
			[ true, 199 ],
			[ false, 200 ],
			[ false, 299 ],
			[ true, 300 ],
			[ false, 404 ],
		];
	}

	/**
	 * Data provider for the testIsQueryErrorWithStatusCode method
	 *
	 * @return array
	 */
	public function isQueryErrorWithStatusCodeDataProvider() : array {
		return [
			[ true, 199 ],
			[ false, 200 ],
			[ false, 299 ],
			[ true, 300 ],
			[ true, 404 ],
		];
	}
}
