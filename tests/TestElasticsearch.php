<?php
/**
 * Test Elasticsearch methods
 *
 * @group  elasticpress
 */

namespace ElasticPressTest;

use ElasticPress;

class TestElasticsearch extends BaseTestCase {
	/**
	 * Cluster status
	 *
	 * Test cluster status.
	 *
	 * @since 0.1.0
	 * @group post-multisite
	 */
	public function testGetClusterStatus() {

		$status_indexed = ElasticPress\Elasticsearch::factory()->get_cluster_status();

		ElasticPress\Elasticsearch::factory()->delete_all_indices();

		$status_unindexed = lasticPress\Elasticsearch::factory()->get_cluster_status();


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
}
