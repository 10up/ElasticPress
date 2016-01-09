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
		$this->manager->register_object($object_index);
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
