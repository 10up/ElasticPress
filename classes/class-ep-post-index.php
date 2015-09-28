<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // exit if accessed directly
}

class EP_Post_Index extends EP_Abstract_Object_Index {

	/**
	 * Get the settings needed by this type's mapping
	 *
	 * @return array
	 */
	public function get_settings() {
		// TODO: Implement get_settings() method.
	}

	/**
	 * Get the mapping for this type
	 *
	 * @return array
	 */
	public function get_mappings() {
		// TODO: Implement get_mappings() method.
	}

	/**
	 * Prepare the object for indexing
	 *
	 * @param mixed $object
	 *
	 * @return array
	 */
	public function prepare_object( $object ) {
		// TODO: Implement prepare_object() method.
	}

	/**
	 * Get the primary identifier for an object
	 *
	 * This could be a slug, or an ID, or something else. It will be used as a canonical
	 * lookup for the document.
	 *
	 * @param mixed $object
	 *
	 * @return int|string
	 */
	protected function get_object_identifier( $object ) {
		// TODO: Implement get_object_identifier() method.
	}

	protected function process_found_objects( $hits ) {
		// TODO: Implement process_found_objects() method.
	}

}
