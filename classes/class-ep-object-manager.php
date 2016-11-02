<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // exit if accessed directly
}

/**
 * Class EP_Object_Manager
 *
 * @since 1.7
 */
class EP_Object_Manager {

	/**
	 * @var EP_Object_Index[]
	 * @since 1.7
	 */
	private $objects = array();

	/**
	 * EP_Object_Manager constructor.
	 *
	 * @since 1.7
	 */
	public function __construct() {
	}

	/**
	 * Register an index
	 *
	 * If an index of the same name already exists, it will be unregistered and replaced by this object.
	 *
	 * @since 1.7
	 *
	 * @param EP_Object_Index $object
	 */
	public function register_object( $object ) {
		if ( isset( $this->objects[ $object->get_name() ] ) ) {
			$this->unregister_object( $this->objects[ $object->get_name() ] );
		}
		$this->objects[ $object->get_name() ] = $object;
		$object->sync_setup();
	}

	/**
	 * Unregister an index
	 *
	 * @since 1.7
	 *
	 * @param EP_Object_Index $object
	 */
	public function unregister_object( $object ) {
		$object->sync_teardown();
		unset( $this->objects[ $object->get_name() ] );
	}

	/**
	 * Get an index by name
	 *
	 * @since 1.7
	 *
	 * @param string $name
	 *
	 * @return null|EP_Object_Index
	 */
	public function get_object( $name ) {
		if ( isset( $this->objects[ $name ] ) ) {
			return $this->objects[ $name ];
		}

		return null;
	}

	/**
	 * Get a list of registed object names
	 *
	 * @since 1.7
	 *
	 * @return array
	 */
	public function get_registered_object_names() {
		return array_keys( $this->objects );
	}

	/**
	 * @since 1.7
	 *
	 * @return EP_Object_Manager
	 */
	public static function factory() {
		static $instance;
		if ( ! $instance ) {
			$instance = new self();
			$instance->register_object( new EP_Post_Index );
			$user_index = new EP_User_Index();
			if ( $user_index->active() ) {
				$instance->register_object( $user_index );
			}
		}

		return $instance;
	}

}

add_action( 'plugins_loaded', array( 'EP_Object_Manager', 'factory' ) );

/**
 * Register an object index type
 *
 * Must either be an instance of EP_Object_Index or the name of a class that is. If this function can't get an object
 * that implements EP_Object_Index it will not register anything.
 *
 * @since 1.7
 *
 * @param EP_Object_Index|string $type
 */
function ep_register_object_type( $type ) {
	if ( ! $type instanceof EP_Object_Index ) {
		if ( is_string( $type ) && class_exists( $type ) ) {
			$type = new $type( null );
			ep_register_object_type( $type );
		}

		return;
	}
	EP_Object_Manager::factory()->register_object( $type );
}

/**
 * Get the object index of the specified type
 *
 * @since 1.7
 *
 * @param string $name
 *
 * @return EP_Object_Index|null
 */
function ep_get_object_type( $name ) {
	return EP_Object_Manager::factory()->get_object( $name );
}

/**
 * Get an array of registered object type names
 *
 * @since 1.7
 *
 * @return array
 */
function ep_get_registered_object_type_names() {
	return EP_Object_Manager::factory()->get_registered_object_names();
}
