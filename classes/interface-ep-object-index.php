<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // exit if accessed directly
}

/**
 * Interface EP_Object_Index
 *
 * @since 1.7
 */
interface EP_Object_Index {

	/**
	 * Check whether user indexing is active. Indexing users is off by default
	 *
	 * @since 1.7.0
	 *
	 * @return bool
	 */
	public function active();

	/**
	 * Set up any necessary syncing operations for this object type
	 *
	 * @since 1.7
	 */
	public function sync_setup();

	/**
	 * Undo any syncing operations (e.g. actions/filters added) for this object type
	 *
	 * @since 1.7
	 */
	public function sync_teardown();

	/**
	 * Get the object type name
	 *
	 * @since 1.7
	 *
	 * @return string
	 */
	public function get_name();

	/**
	 * Set the object type name
	 *
	 * @since 1.7
	 *
	 * @param string $name
	 */
	public function set_name( $name );

	/**
	 * Get the settings needed by this type's mapping
	 *
	 * @since 1.7
	 *
	 * @return array
	 */
	public function get_settings();

	/**
	 * Get the mapping for this type
	 *
	 * @since 1.7
	 *
	 * @return array
	 */
	public function get_mappings();

	/**
	 * Index a document of this type
	 *
	 * Returns the response body if available, false otherwise.
	 *
	 * @since 1.7
	 *
	 * @param array $object   The object data
	 * @param bool  $blocking Whether to make a blocking request. Defaults to true
	 *
	 * @return array|object|bool The response body if available, otherwise false
	 */
	public function index_document( $object, $blocking = true );

	/**
	 * Get a document from ES
	 *
	 * Returns the document data if successful, false otherwise.
	 *
	 * @since 1.7
	 *
	 * @param mixed $object An object identifier
	 *
	 * @return array|bool The document data or false on failur
	 */
	public function get_document( $object );

	/**
	 * Delete a document from ES
	 *
	 * @since 1.7
	 *
	 * @param mixed $object   An object identifier
	 * @param bool  $blocking Whether the request should be blocking
	 *
	 * @return bool True for success, false for failure
	 */
	public function delete_document( $object, $blocking = true );

	/**
	 * Prepare the object for indexing
	 *
	 * @since 1.7
	 *
	 * @param mixed $object
	 *
	 * @return array
	 */
	public function prepare_object( $object );

	/**
	 * Search for objects under a specific site index or the global index
	 *
	 * @since 1.7
	 *
	 * @param array $args
	 * @param mixed $scope
	 *
	 * @return array|bool
	 */
	public function query( $args, $query_args, $scope = 'current' );


	/**
	 * Bulk index data of this type
	 *
	 * @since 1.7
	 *
	 * @param array $body
	 *
	 * @return array|WP_Error
	 */
	public function bulk_index( $body );

}
