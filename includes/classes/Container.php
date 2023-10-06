<?php
/**
 * Container class
 *
 * @since 4.7.0
 * @package elasticpress
 * @see https://github.com/php-fig/container
 */

namespace ElasticPress;

use \ElasticPress\Vendor_Prefixed\Psr\Container\ContainerInterface;

use \ElasticPress\Exception\NotFoundException;

/**
 * PSR11 compliant container class
 */
final class Container implements ContainerInterface {
	/**
	 * Hold all instances
	 *
	 * @var array<object>
	 */
	private $instances = [];

	/**
	 * Finds an entry of the container by its identifier and returns it.
	 *
	 * @param string $id Identifier of the entry to look for.
	 *
	 * @throws NotFoundException No entry was found for **this** identifier.
	 *
	 * @return mixed Entry.
	 */
	public function get( $id ) {
		if ( ! isset( $this->instances[ $id ] ) ) {
			throw new NotFoundException( 'Class not found' );
		}

		return $this->instances[ $id ];
	}

	/**
	 * Returns true if the container can return an entry for the given identifier.
	 * Returns false otherwise.
	 *
	 * @param string $id Identifier of the entry to look for.
	 *
	 * @return bool
	 */
	public function has( $id ) : bool {
		return isset( $this->instances[ $id ] );
	}

	/**
	 * Register an instance.
	 *
	 * @param string  $id       Identifier of the entry.
	 * @param object  $instance The new instance.
	 * @param boolean $setup    Whether the setup() method should be called or not.
	 * @return object The instance.
	 */
	public function set( string $id, $instance, bool $setup = false ) {
		/**
		 * Filter an instance before it is added to the container
		 *
		 * @since 4.7.0
		 * @hook ep_container_set
		 * @param {object} $instance Object instance
		 * @param {string} $id       Id
		 * @return {object} New object
		 */
		$instance = apply_filters( 'ep_container_set', $instance, $id );

		$this->instances[ $id ] = $instance;

		if ( $setup && method_exists( $instance, 'setup' ) ) {
			$instance->setup();
		}

		return $instance;
	}
}
