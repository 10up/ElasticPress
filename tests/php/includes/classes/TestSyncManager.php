<?php
/**
 * Test base SyncManager functionality
 *
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress;

/**
 * Test post indexable class
 */
class TestSyncManager extends BaseTestCase {
	public function getTestRemoveFromQueueData() {
		return array(
			array(
				// Starting queue
				[ 1 => true, 2 => true, 3 => true, 4 => true, 5 => true ],

				// Object to remove
				4,

				// Expected queue
				[ 1 => true, 2 => true, 3 => true, 5 => true ],
			),

			// Invalid id
			array(
				// Starting queue
				[ 1 => true, 2 => true, 3 => true, 4 => true, 5 => true ],

				// Object to remove
				'foo',

				// Expected queue
				[ 1 => true, 2 => true, 3 => true, 4 => true, 5 => true ],
			),

			// ID not present
			array(
				// Starting queue
				[ 1 => true, 2 => true, 3 => true, 4 => true, 5 => true ],

				// Object to remove
				6,

				// Expected queue
				[ 1 => true, 2 => true, 3 => true, 4 => true, 5 => true ],
			),
		);
	}

	/**
	 * @dataProvider getTestRemoveFromQueueData
	 */
	public function testRemoveFromQueue( $queue, $object_id, $expected_queue ) {
		$manager = $this->getMockBuilder( \ElasticPress\SyncManager::class )
			->disableOriginalConstructor() // B/c we can't pass an arg to it and it expects one
			->getMockForAbstractClass();

		$manager->sync_queue = $queue;

		$manager->remove_from_queue( $object_id );

		$this->assertEquals( $expected_queue, $manager->sync_queue );
	}
}