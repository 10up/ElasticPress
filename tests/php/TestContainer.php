<?php
/**
 * Test the Container class methods
 *
 * @since 4.7.0
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress\Container;

/**
 * TestElasticPress test class
 */
class TestContainer extends BaseTestCase {
	/**
	 * Test the `get` method
	 *
	 * @group container
	 */
	public function test_get() {
		$container = new Container();

		$new_object = new \stdClass();
		$container->set( 'present', $new_object );

		$this->assertSame( $new_object, $container->get( 'present' ) );

		$this->expectException( '\ElasticPress\Vendor_Prefixed\Psr\Container\NotFoundExceptionInterface' );
		$container->get( 'absent' );
	}

	/**
	 * Test the `has` method
	 *
	 * @group container
	 */
	public function test_has() {
		$container = new Container();

		$new_object = new \stdClass();
		$container->set( 'present', $new_object );

		$this->assertTrue( $container->has( 'present' ) );
		$this->assertFalse( $container->has( 'absent' ) );
	}

	/**
	 * Test the `set` method
	 *
	 * @group container
	 */
	public function test_set() {
		$mock = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'setup' ] )
			->getMock();

		$mock->expects( $this->exactly( 1 ) )->method( 'setup' );

		$container = new Container();

		$container->set( 'mock', $mock );
		$container->set( 'mock', $mock, true );

		$this->assertSame( $mock, $container->get( 'mock' ) );
	}

	/**
	 * Test the `ep_container_set` filter
	 *
	 * @group container
	 */
	public function test_set_ep_container_set_filter() {
		$mock = $this->getMockBuilder( \stdClass::class )
			->addMethods( [ 'setup' ] )
			->getMock();

		$container = new Container();
		$object    = new \stdClass();

		$change_instance = function ( $instance, $id ) use ( $mock, $object ) {
			$this->assertSame( 'mock', $id );
			$this->assertSame( $instance, $object );
			return $mock;
		};
		add_filter( 'ep_container_set', $change_instance, 10, 2 );

		$container->set( 'mock', $object );
		$this->assertSame( $mock, $container->get( 'mock' ) );
	}
}
