<?php
/**
 * Test Elasticsearch methods
 *
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress;
use ElasticPress\Utils;

/**
 * Elasticsearch test class
 */
class TestElasticsearch extends BaseTestCase {
	/**
	 * Cluster status
	 *
	 * Test cluster status.
	 *
	 * @since 0.1.0
	 * @group elasticsearch
	 */
	public function testGetClusterStatus() {

		$status_indexed = ElasticPress\Elasticsearch::factory()->get_cluster_status();

		ElasticPress\Elasticsearch::factory()->delete_all_indices();

		$status_unindexed = ElasticPress\Elasticsearch::factory()->get_cluster_status();

		$this->set_up();

		if ( is_array( $status_indexed ) ) {

			$this->assertTrue( $status_indexed['status'] );

		} else {

			$this->assertTrue( isset( $status_indexed->cluster_name ) );

		}

		if ( is_array( $status_unindexed ) ) {

			$this->assertTrue( $status_unindexed['status'] );

		} else {

			$this->assertTrue( isset( $status_unindexed->cluster_name ) );

		}
	}

	/**
	 * Test get documents
	 *
	 * @since 3.6.0
	 * @group elasticsearch
	 */
	public function testGetDocuments() {

		$post_ids   = array();
		$post_ids[] = $this->ep_factory->post->create();
		$post_ids[] = $this->ep_factory->post->create();

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$index_name = ElasticPress\Indexables::factory()->get( 'post' )->get_index_name();

		$documents = ElasticPress\Elasticsearch::factory()->get_documents( $index_name, 'post', $post_ids );

		$this->assertIsArray( $documents );
		$this->assertEquals( 2, count( $documents ) );
		$this->assertArrayHasKey( $post_ids[0], $documents );
		$this->assertArrayHasKey( $post_ids[1], $documents );

		$post_ids[] = 99999999; // Adding an id that doesn't exist

		$documents = ElasticPress\Elasticsearch::factory()->get_documents( $index_name, 'post', $post_ids );

		$this->assertIsArray( $documents );
		$this->assertEquals( 2, count( $documents ) );
		$this->assertArrayHasKey( $post_ids[0], $documents );
		$this->assertArrayHasKey( $post_ids[1], $documents );

		// Trying to get a document that doesn't exist
		$documents = ElasticPress\Elasticsearch::factory()->get_documents( $index_name, 'post', [ 99999999 ] );

		$this->assertIsArray( $documents );
		$this->assertEmpty( $documents );

		$documents = ElasticPress\Elasticsearch::factory()->get_documents( $index_name, 'post', [] );

		$this->assertIsArray( $documents );
		$this->assertEmpty( $documents );
	}

	/**
	 * Test update_index_settings
	 *
	 * @since 4.4.0
	 * @group elasticsearch
	 */
	public function testUpdateIndexSettings() {
		$index_name = 'lorem-ipsum';
		$settings   = [ 'test' ];

		add_action(
			'ep_update_index_settings',
			function( $index_name, $settings ) {
				$this->assertSame( $index_name, 'lorem-ipsum' );
				$this->assertSame( $settings, [ 'test' ] );
			},
			10,
			2
		);

		ElasticPress\Elasticsearch::factory()->update_index_settings( $index_name, $settings );

		$this->assertSame( 1, did_action( 'ep_update_index_settings' ) );

		$this->markTestIncomplete( 'This test should also test the index settings update.' );
	}

	/**
	 * Test the `get_index_settings` method
	 *
	 * @since 4.7.0
	 * @group elasticsearch
	 */
	public function test_get_index_settings() {
		$index_name            = 'test-index';
		$cache_key             = 'ep_index_settings_' . $index_name;
		$transient_filter_name = defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ?
			'pre_site_transient_' . $cache_key :
			'pre_transient_' . $cache_key;

		$wrong_settings = [
			'response' => [ 'code' => 500 ],
		];

		$test_settings  = [
			$index_name => [
				'settings' => [
					'index.mapping.total_fields.limit' => 123,
				],
			],
		];
		$right_settings = [
			'response' => [ 'code' => 200 ],
			'body'     => wp_json_encode( $test_settings ),
		];

		$elasticsearch_mock = $this->getMockBuilder( \ElasticPress\Elasticsearch::class )
			->setMethods( [ 'remote_request' ] )
			->getMock();

		/**
		 * We call get_index_settings 4 times:
		 * 1. Fake cache, so remote_request is not called
		 * 2. Fake cache but force refresh, so remote_request is called
		 * 3. remote_request returns a WP_Error
		 * 4. remote_request returns a settings array that does not match what we expect
		 * 5. remote_request returns what we expect
		 */
		$elasticsearch_mock->expects( $this->exactly( 4 ) )
			->method( 'remote_request' )
			->willReturnOnConsecutiveCalls(
				new \WP_Error(),
				new \WP_Error(),
				$wrong_settings,
				$right_settings
			);

		/**
		 * Test when cached
		 */
		$set_cached_value = function() {
			return 'cached';
		};
		add_filter( $transient_filter_name, $set_cached_value );
		$settings = $elasticsearch_mock->get_index_settings( $index_name );
		$this->assertSame( 'cached', $settings );

		/**
		 * Test cached but force-refresh (so cache is not used)
		 */
		$settings = $elasticsearch_mock->get_index_settings( $index_name, force_refresh: true );
		$this->assertInstanceOf( 'WP_Error', $settings );
		Utils\delete_transient( $cache_key );

		remove_filter( $transient_filter_name, $set_cached_value );

		/**
		 * Test when the request errors out
		 */
		$settings = $elasticsearch_mock->get_index_settings( $index_name );
		$this->assertInstanceOf( 'WP_Error', $settings );
		Utils\delete_transient( $cache_key );

		/**
		 * Test when the request returns something we do not expect
		 */
		$settings = $elasticsearch_mock->get_index_settings( $index_name );
		$this->assertInstanceOf( 'WP_Error', $settings );
		$this->assertSame( 500, $settings->get_error_data()['response']['code'] );
		Utils\delete_transient( $cache_key );

		/**
		 * Test when the request returns something we do expect
		 */
		$settings = $elasticsearch_mock->get_index_settings( $index_name );
		$this->assertSame( $test_settings, $settings );
		$this->assertSame( $test_settings, Utils\get_transient( $cache_key ) );
		Utils\delete_transient( $cache_key );
	}

	/**
	 * Test the `get_index_setting` method
	 *
	 * @since 4.7.0
	 * @group elasticsearch
	 */
	public function test_get_index_setting() {
		$index_name = 'test-index';

		$elasticsearch_mock = $this->getMockBuilder( \ElasticPress\Elasticsearch::class )
			->setMethods( [ 'get_index_settings' ] )
			->getMock();
		$elasticsearch_mock->expects( $this->exactly( 3 ) )
			->method( 'get_index_settings' )
			->willReturnOnConsecutiveCalls(
				new \WP_Error(),
				[ $index_name => [] ],
				[
					$index_name => [
						'settings' => [ 'test_setting' => 1 ],
					],
				]
			);

		// WP_Error
		$this->assertNull( $elasticsearch_mock->get_index_setting( $index_name, 'test_setting' ) );
		// Empty settings array
		$this->assertNull( $elasticsearch_mock->get_index_setting( $index_name, 'test_setting' ) );
		// Correct value
		$this->assertSame( 1, $elasticsearch_mock->get_index_setting( $index_name, 'test_setting' ) );
	}

	/**
	 * Test the `get_index_total_fields_limit` method
	 *
	 * @since 4.7.0
	 * @group elasticsearch
	 */
	public function test_get_index_total_fields_limit() {
		$index_name = 'test-index';

		$elasticsearch_mock = $this->getMockBuilder( \ElasticPress\Elasticsearch::class )
			->setMethods( [ 'get_index_setting' ] )
			->getMock();
		$elasticsearch_mock->expects( $this->exactly( 1 ) )
			->method( 'get_index_setting' )
			->willReturn( 1 );

		$this->assertSame( 1, $elasticsearch_mock->get_index_total_fields_limit( $index_name ) );
	}

	/**
	 * Test the format_request_headers method
	 *
	 * @since 4.5.0
	 */
	public function testFormatRequestHeaders() {
		/**
		 * Test the default behavior
		 */
		$default_headers = ElasticPress\Elasticsearch::factory()->format_request_headers();

		$this->assertCount( 2, $default_headers );
		$this->assertSame( 'application/json', $default_headers['Content-Type'] );
		$this->assertNotEmpty( $default_headers['X-ElasticPress-Request-ID'] );

		/**
		 * Test the addition of `X-ElasticPress-API-Key` if `EP_API_KEY` is defined
		 */
		define( 'EP_API_KEY', 'custom_key' );
		$new_headers = ElasticPress\Elasticsearch::factory()->format_request_headers();

		$this->assertCount( 3, $new_headers );
		$this->assertSame( 'custom_key', $new_headers['X-ElasticPress-API-Key'] );

		/**
		 * Test the addition of `Authorization` if `ES_SHIELD` is defined
		 */
		define( 'ES_SHIELD', 'custom_shield' );
		$new_headers = ElasticPress\Elasticsearch::factory()->format_request_headers();

		$this->assertCount( 4, $new_headers );
		$this->assertSame( 'Basic ' . base64_encode( 'custom_shield' ), $new_headers['Authorization'] ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

		/**
		 * Test if an empty request ID removes `X-ElasticPress-Request-ID`
		 */
		add_filter( 'ep_request_id', '__return_empty_string' );
		$new_headers = ElasticPress\Elasticsearch::factory()->format_request_headers();
		$this->assertArrayNotHasKey( 'X-ElasticPress-Request-ID', $new_headers );

		/**
		 * Test the `ep_format_request_headers` filter
		 */
		$change_headers = function( $headers ) {
			$headers['X-Custom'] = 'totally custom';
			return $headers;
		};
		add_filter( 'ep_format_request_headers', $change_headers );
		$new_headers = ElasticPress\Elasticsearch::factory()->format_request_headers();

		$this->assertCount( 4, $new_headers ); // 3 old + 1 new
		$this->assertSame( 'totally custom', $new_headers['X-Custom'] );
	}

	/**
	 * Test the get_indices_comparison method
	 *
	 * @since 4.6.0
	 */
	public function testGetIndicesComparison() {
		ElasticPress\Features::factory()->activate_feature( 'terms' );
		ElasticPress\Features::factory()->setup_features();

		$post_indexable = ElasticPress\Indexables::factory()->get( 'post' );
		$term_indexable = ElasticPress\Indexables::factory()->get( 'term' );

		$post_indexable->put_mapping();
		$term_indexable->put_mapping();

		/**
		 * All indices are present
		 */
		$expected = [
			'missing_indices' => [],
			'present_indices' => [
				$post_indexable->get_index_name(),
				$term_indexable->get_index_name(),
			],
		];
		$this->assertEqualsCanonicalizing( $expected, \ElasticPress\Elasticsearch::factory()->get_indices_comparison() );

		/**
		 * One missing index
		 */
		$term_indexable->delete_index();

		$expected = [
			'missing_indices' => [
				$term_indexable->get_index_name(),
			],
			'present_indices' => [
				$post_indexable->get_index_name(),
			],
		];
		$this->assertEqualsCanonicalizing( $expected, \ElasticPress\Elasticsearch::factory()->get_indices_comparison() );

		/**
		 * All indices are missing
		 */
		ElasticPress\Elasticsearch::factory()->delete_all_indices();

		$expected = [
			'missing_indices' => [
				$post_indexable->get_index_name(),
				$term_indexable->get_index_name(),
			],
			'present_indices' => [],
		];
		$this->assertEqualsCanonicalizing( $expected, \ElasticPress\Elasticsearch::factory()->get_indices_comparison() );
	}
}
