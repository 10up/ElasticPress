<?php

/**
 * @group users
 */
class Test_EP_Object_Manager extends EP_Test_Base {

	/** @var EP_Object_Manager */
	private $manager;

	public function setUp() {
		parent::setUp();
		$this->manager = new EP_Object_Manager();
	}

	public function testRegisterObject() {
		$object_index = $this->getObjectIndexMock( 'foobar' );
		$object_index
			->expects( $this->once() )
			->method( 'sync_setup' );
		/** @var EP_Object_Index $object_index */
		$this->manager->register_object( $object_index );
	}

	public function testUnregisterObject() {
		$object_index = $this->getObjectIndexMock( 'post' );
		$object_index
			->expects( $this->once() )
			->method( 'sync_teardown' );
		/** @var EP_Object_Index $object_index */
		$this->manager->register_object( $object_index );
		$this->manager->unregister_object( $object_index );
	}

	public function testRegisteringTwiceUnregistersFirstObject() {
		$first_index  = $this->getObjectIndexMock( 'user' );
		$second_index = $this->getObjectIndexMock( 'user' );
		$first_index
			->expects( $this->once() )
			->method( 'sync_teardown' );
		/** @var EP_Object_Index $first_index */
		/** @var EP_Object_Index $second_index */
		$this->manager->register_object( $first_index );
		$this->manager->register_object( $second_index );
	}

	public function testGetObject() {
		$object_index = $this->getObjectIndexMock( 'post' );
		/** @var EP_Object_Index $object_index */
		$this->manager->register_object( $object_index );
		$this->assertSame( $object_index, $this->manager->get_object( 'post' ) );
	}

	public function testGetRegisteredObjectName() {
		$names = array( 'test', 'post', 'comment', 'user' );
		shuffle( $names );
		foreach ( $names as $name ) {
			$this->manager->register_object( $this->getObjectIndexMock( $name ) );
		}
		$this->assertEqualSetsWithIndex( $names, $this->manager->get_registered_object_names() );
	}

	/**
	 * @group users-indexing-inactive
	 */
	public function testFactoryReturnsStaticInstance() {
		$this->assertSame( EP_Object_Manager::factory(), EP_Object_Manager::factory() );
	}

	/**
	 * @return PHPUnit_Framework_MockObject_MockObject
	 */
	private function getObjectIndexMock( $name ) {
		$object_index = $this->getMock(
			'EP_Object_Index',
			array(
				'active',
				'sync_setup',
				'sync_teardown',
				'get_name',
				'set_name',
				'get_settings',
				'get_mappings',
				'index_document',
				'get_document',
				'delete_document',
				'prepare_object',
				'search',
				'bulk_index',
			)
		);
		$object_index
			->expects( $this->any() )
			->method( 'get_name' )
			->will( $this->returnValue( $name ) );

		return $object_index;
	}

}
