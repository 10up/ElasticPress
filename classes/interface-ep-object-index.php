<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // exit if accessed directly
}

interface EP_Object_Index {

	/**
	 * Get the object type name
	 *
	 * @return string
	 */
	public function get_name();

	/**
	 * Set the object type name
	 *
	 * @param string $name
	 */
	public function set_name( $name );

	/**
	 * Get the settings needed by this type's mapping
	 *
	 * @return array
	 */
	public function get_settings();

	/**
	 * Get the mapping for this type
	 *
	 * @return array
	 */
	public function get_mappings();

	/**
	 * Index a document of this type
	 *
	 * Returns the response body if available, false otherwise.
	 *
	 * @param array $object The object data
	 *
	 * @return array|object|bool The response body if available, otherwise false
	 */
	public function index_document( $object );

	/**
	 * Get a document from ES
	 *
	 * Returns the document data if successful, false otherwise.
	 *
	 * @param mixed $object An object identifier
	 *
	 * @return array|bool The document data or false on failur
	 */
	public function get_document( $object );

	/**
	 * Delete a document from ES
	 *
	 * @param mixed $object An object identifier
	 *
	 * @return bool True for success, false for failure
	 */
	public function delete_document( $object );

	/**
	 * Prepare the object for indexing
	 *
	 * @param mixed $object
	 *
	 * @return array
	 */
	public function prepare_object( $object );

}
