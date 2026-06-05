<?php
/**
 * Test LogRequest trait
 *
 * @since 5.4.0
 * @package ElasticPress
 */

namespace ElasticPressTest;

use ElasticPress\Traits\LogRequest;

/**
 * LogRequest trait test class
 */
class TestLogRequest extends \WP_UnitTestCase {
	/**
	 * Setup each test
	 */
	public function set_up() {
		parent::set_up();
		// Ensure no external HTTP requests are made during tests.
		add_filter( 'pre_http_request', [ $this, 'mock_http_response' ], 10, 3 );
	}

	/**
	 * Teardown each test
	 */
	public function tear_down() {
		remove_filter( 'pre_http_request', [ $this, 'mock_http_response' ], 10 );
		parent::tear_down();
	}

	/**
	 * Provide a deterministic HTTP response for wp_remote_post
	 *
	 * @param false|array|\WP_Error $preempt A preempted response.
	 * @param array                 $args    HTTP request arguments.
	 * @param string                $url     The request URL.
	 * @return array
	 */
	public function mock_http_response( $preempt, $args, $url ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		return [
			'response' => [
				'code'    => 200,
				'message' => 'OK',
			],
			'body'     => wp_json_encode( [ 'ok' => true ] ),
		];
	}

	/**
	 * Test that send_request_and_log performs request, sets formatted data, and fires hooks
	 *
	 * @group log-request
	 */
	public function test_send_request_and_log_fires_hooks_and_logs() {
		$requester = new class() {
			use LogRequest;

			/**
			 * Proxy to call trait method.
			 *
			 * @param string $url URL.
			 * @param array  $options Options.
			 * @param string $type Type.
			 * @param string $slug Slug.
			 * @return array
			 */
			public function call( $url, $options, $type, $slug ) {
				return $this->send_request_and_log( $url, $options, $type, $slug );
			}
		};

		$url     = 'https://example.org/embeddings';
		$options = [ 'body' => wp_json_encode( [ 'text' => 'hello' ] ) ];
		$type    = 'vector_embeddings';
		$slug    = 'vector-embeddings';

		$before   = did_action( 'ep_remote_request' );
		$response = $requester->call( $url, $options, $type, $slug );

		$this->assertTrue( is_array( $response ) );
		$this->assertSame( 200, $response['response']['code'] );

		// Action ep_remote_request should have increased exactly by one.
		$this->assertSame( $before + 1, did_action( 'ep_remote_request' ) );

		// The filter ep_get_query_log should include the formatted request appended by the trait.
		$log = apply_filters( 'ep_get_query_log', [] );

		$this->assertTrue( is_array( $log ) );
		$this->assertCount( 1, $log );

		$entry = $log[0];
		$this->assertArrayHasKey( 'args', $entry );
		$this->assertArrayHasKey( 'url', $entry );
		$this->assertArrayHasKey( 'time_start', $entry );
		$this->assertArrayHasKey( 'time_finish', $entry );
		$this->assertSame( 'POST', $entry['args']['method'] );
		$this->assertSame( $type, $entry['args']['ep_query_type'] );
		$this->assertSame( $url, $entry['url'] );
		$this->assertGreaterThan( $entry['time_start'], $entry['time_finish'] );
	}

	/**
	 * Test add_vector_embeddings_to_query_log appends formatted request to provided log
	 *
	 * @group log-request
	 */
	public function test_add_vector_embeddings_to_query_log_appends_entry() {
		$requester = new class() {
			use LogRequest;

			/**
			 * Proxy to call trait method.
			 *
			 * @param string $url URL.
			 * @param array  $options Options.
			 * @param string $type Type.
			 * @param string $slug Slug.
			 * @return array
			 */
			public function call( $url, $options, $type, $slug ) {
				return $this->send_request_and_log( $url, $options, $type, $slug );
			}
		};

		// First perform a call to populate formatted_request in the trait.
		$url     = 'https://example.org/embeddings';
		$options = [ 'body' => wp_json_encode( [ 'text' => 'world' ] ) ];
		$type    = 'vector_embeddings';
		$slug    = 'vector-embeddings';
		$requester->call( $url, $options, $type, $slug );

		// Now apply the filter to get current log with the appended entry.
		$log = apply_filters( 'ep_get_query_log', [ [ 'existing' => true ] ] );

		$this->assertCount( 2, $log );
		$this->assertArrayHasKey( 'existing', $log[0] );
		$this->assertArrayHasKey( 'url', $log[1] );
		$this->assertSame( $url, $log[1]['url'] );
	}
}
