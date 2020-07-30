<?php
/**
 * Integration class to be initiated for all integrations.
 *
 * All integrations extend this class.
 *
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
	 */
	abstract public function setup();

	/**
	 * Determine if this integration is active.
	 * Mostly check if the required plugins are activated.
	 */
	abstract public function is_active();

	/**
	 * Integration constructor.
	 */
	public function __construct() {
		if ( ! $this->is_active() ) {
			return;
		}

		$this->setup();
	}
}
