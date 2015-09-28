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
		$mapping = require( $this->get_mapping_file() );

		return isset( $mapping['settings'] ) ? (array) $mapping['settings'] : array();
	}

	/**
	 * Get the mapping for this type
	 *
	 * @return array
	 */
	public function get_mappings() {
		$mapping = require( $this->get_mapping_file() );

		return isset( $mapping['mappings']['post'] ) ? (array) $mapping['mappings']['post'] : array();
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
		if ( $object instanceof WP_Post ) {
			return $object->ID;
		} elseif ( is_array( $object ) && isset( $object['post_id'] ) ) {
			return $object['post_id'];
		} elseif ( is_numeric( $object ) ) {
			return (int) $object;
		}

		return 0;
	}

	protected function process_found_objects( $hits ) {
		// TODO: Implement process_found_objects() method.
	}

	private function get_mapping_file() {
		return apply_filters( 'ep_config_mapping_file', dirname( __FILE__ ) . '/../includes/mappings.php' );
	}

}
