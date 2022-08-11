<?php
/**
 * Test abstract SearchAlgorithm
 *
 * @since 4.3.0
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress\SearchAlgorithm;

/**
 * Test abstract SearchAlgorithm class
 */
class TestSearchAlgorithm extends \ElasticPressTest\BaseTestCase {
	/**
	 * "Concrete" stub for the abstract class
	 *
	 * @var \PHPUnit\Framework\MockObject\MockObject
	 */
	private $stub;

	/**
	 * Setup each test.
	 */
	public function setUp() {
		$this->stub = $this->getMockForAbstractClass( SearchAlgorithm::class );
		$this->stub->expects( $this->any() )
			->method( 'get_raw_query' )
			->will( $this->returnValue( [] ) );

		parent::setUp();
	}

	/**
	 * Test filters
	 *
	 * @group searchAlgorithms
	 */
	public function testFilters() {
		$test_filter = function() {
			return [ 'changed' ];
		};

		/**
		 * Test the `ep_{$indexable_slug}_formatted_args_query` filter.
		 */
		add_filter( 'ep_indexable_formatted_args_query', $test_filter );

		$query = $this->stub->get_query( 'indexable', '', [], [] );
		$this->assertEquals( [ 'changed' ], $query );

		remove_filter( 'ep_indexable_formatted_args_query', $test_filter );
	}

	/**
	 * Test deprecated/legacy filters
	 *
	 * @expectedDeprecated ep_formatted_args_query
	 * @group searchAlgorithms
	 */
	public function testLegacyFilters() {
		$test_filter = function() {
			return [ 'changed' ];
		};

		/**
		 * Test the `ep_formatted_args_query` filter.
		 */
		add_filter( 'ep_formatted_args_query', $test_filter );

		$query = $this->stub->get_query( 'post', '', [], [] );
		$this->assertEquals( [ 'changed' ], $query );

		remove_filter( 'ep_formatted_args_query', $test_filter );
	}
}
