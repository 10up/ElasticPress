<?php
/**
 * Test SearchAlgorithms class.
 *
 * @since 4.3.0
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress\SearchAlgorithms;
use ElasticPress\SearchAlgorithm;

/**
 * SearchAlgorithms test class
 */
class TestSearchAlgorithms extends BaseTestCase {

	/**
	 * Test registering and getting search algorithms
	 *
	 * @group search-algorithms
	 */
	public function test_register_and_get_search_algorithms() {
		/**
		 * Test registering and getting a new search algorithm
		 */
		$stub = $this->getMockForAbstractClass( SearchAlgorithm::class );
		$stub->expects( $this->any() )
			->method( 'get_slug' )
			->will( $this->returnValue( 'stub' ) );

		SearchAlgorithms::factory()->register( $stub );

		$this->assertSame( $stub, SearchAlgorithms::factory()->get( 'stub' ) );

		/**
		 * Test getting a non-existent search algorithm. `Basic` should be used.
		 */
		$this->assertSame( 'default', SearchAlgorithms::factory()->get( 'foobar' )->get_slug() );
	}

	/**
	 * Test unregistering search algorithms
	 *
	 * @depends test_register_and_get_search_algorithms
	 * @group search-algorithms
	 */
	public function test_unregister_search_algorithms() {
		$this->assertTrue( SearchAlgorithms::factory()->unregister( 'stub' ) );
		$this->assertFalse( SearchAlgorithms::factory()->unregister( 'foobar' ) );

		/**
		 * Store these search algorithms to register them back later.
		 */
		$default    = SearchAlgorithms::factory()->get( 'default' );
		$version_35 = SearchAlgorithms::factory()->get( '3.5' );

		$this->assertTrue( SearchAlgorithms::factory()->unregister( 'default' ) );
		$this->assertTrue( SearchAlgorithms::factory()->unregister( '3.5' ) );

		/**
		 * This is the last one remaining and will not be unregistered
		 */
		$this->assertFalse( SearchAlgorithms::factory()->unregister( '4.0' ) );

		SearchAlgorithms::factory()->register( $default );
		SearchAlgorithms::factory()->register( $version_35 );
	}

	/**
	 * Test getting all search algorithms
	 *
	 * @depends test_unregister_search_algorithms
	 * @group search-algorithms
	 */
	public function test_get_all() {
		$this->assertEqualsCanonicalizing( [ 'default', '3.5', '4.0' ], SearchAlgorithms::factory()->get_all( true ) );

		$search_algorithms = SearchAlgorithms::factory()->get_all();
		$this->assertCount( 3, $search_algorithms );
		foreach ( $search_algorithms as $search_algorithm ) {
			$this->assertInstanceOf( SearchAlgorithm::class, $search_algorithm );
		}
	}
}
