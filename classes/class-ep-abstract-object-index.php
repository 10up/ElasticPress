<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // exit if accessed directly
}

abstract class EP_Abstract_Object_Index implements EP_Object_Index {

	protected $name = '';

	public function __construct( $name ) {
		$this->name = $name;
	}

	public function get_name() {
		return $this->name;
	}

	public function set_name( $name ) {
		$this->name = $name;
	}


}
