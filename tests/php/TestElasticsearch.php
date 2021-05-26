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

		$this->setUp();

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
		$post_ids[] = Functions\create_and_sync_post();
		$post_ids[] = Functions\create_and_sync_post();

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
}
