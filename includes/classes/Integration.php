<?php
/**
 * Integration class to be initiated for all integrations.
 *
 * All integrations extend this class.
 *
 * @since
 * @package elasticpress
 */

namespace ElasticPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Feature abstract class
 */
abstract class Integration {
	/**
	 * Setup actions and filters.
	 *
	 * @since  2.1
	 */
	abstract public function setup();

	/**
	 * Determine if this integration is active.
	 * Mostly check if the required plugins are activated.
	 */
	abstract public function is_active();

	/**
	 * Create feature
	 *
	 * @since  3.0
	 */
	public function __construct() {
		if ( ! $this->is_active() ) {
			return;
		}

		$this->setup();
	}
}
