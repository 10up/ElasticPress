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

	public function index_document( $object ) {
		/**
		 * Filter the object prior to indexing
		 *
		 * Allows for last minute indexing of object information.
		 *
		 * @since 1.7
		 *
		 * @param         array Array of post information to index.
		 */
		$object = apply_filters( "ep_pre_index_{$this->name}", $object );

		$index = untrailingslashit( ep_get_index_name() );

		$path = implode( '/', array( $index, $this->name, $this->get_object_identifier( $object ) ) );

		$request_args = array(
			'body'    => json_encode( $object ),
			'method'  => 'PUT',
			'timeout' => 15,
		);

		$request = ep_remote_request(
			$path,
			apply_filters( "ep_index_{$this->name}_request_args", $request_args, $object )
		);

		do_action( "ep_index_{$this->name}_retrieve_raw_response", $request, $object, $path );

		if ( ! is_wp_error( $request ) ) {
			$response_body = wp_remote_retrieve_body( $request );

			return json_decode( $response_body );
		}

		return false;
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
	abstract protected function get_object_identifier( $object );

}
