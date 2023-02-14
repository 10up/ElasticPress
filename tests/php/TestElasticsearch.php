<?php
/**
 * Test Elasticsearch methods
 *
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress;

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

		$post_ids = array();
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

		$documents = ElasticPress\Elasticsearch::factory()->get_documents( $index_name, 'post', []  );

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
	 * Test get_index_total_fields_limit
	 *
	 * @since 4.4.0
	 * @group elasticsearch
	 */
	public function testGetIndexTotalFieldsLimit() {
		$index_name = 'test-index';
		$cache_key  = 'ep_total_fields_limit_' . $index_name;

		$transient_filter_name = defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ? 'pre_site_transient_' . $cache_key : 'pre_transient_' . $cache_key;

		$elasticsearch_mock = $this->getMockBuilder( \ElasticPress\Elasticsearch::class )
			->setMethods( [ 'get_index_settings' ] )
			->getMock();


		$wrong_settings = [ '' ];
		$right_settings = [
			$index_name => [
				'settings' => [
					'index.mapping.total_fields.limit' => 123,
				],
			],
		];

		/**
		 * We call get_index_total_fields_limit 4 times:
		 * 1. Fake cache, so get_index_settings is not called
		 * 2. get_index_settings returns a WP_Error
		 * 3. get_index_settings returns a settings array that does not match what we expect
		 * 4. get_index_settings returns what we expect
		 */
		$elasticsearch_mock->expects( $this->exactly( 3 ) )
             ->method( 'get_index_settings' )
			 ->willReturnOnConsecutiveCalls(
				[ new \WP_Error() ],
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
		$limit = $elasticsearch_mock->get_index_total_fields_limit( $index_name );
		$this->assertSame( 'cached', $limit );

		remove_filter( $transient_filter_name, $set_cached_value );

		/**
		 * Test when the request errors out
		 */
		$limit = $elasticsearch_mock->get_index_total_fields_limit( $index_name );
		$this->assertNull( $limit );

		/**
		 * Test when the request returns something we do not expect
		 */
		$limit = $elasticsearch_mock->get_index_total_fields_limit( $index_name );
		$this->assertNull( $limit );

		/**
		 * Test when the request returns something we do expect
		 */
		$limit = $elasticsearch_mock->get_index_total_fields_limit( $index_name );
		$this->assertSame( 123, $limit );
		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			$this->assertSame( 123, get_site_transient( $cache_key ) );
		} else {
			$this->assertSame( 123, get_transient( $cache_key ) );
		}
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
		$this->assertSame( 'Basic ' . base64_encode( 'custom_shield' ), $new_headers['Authorization'] );

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
}
