<?php
/**
 * Utility class to count how many times a regular function was called
 *
 * @since 5.3.0
 * @package elasticpress
 */

namespace ElasticPressTest;

/**
 * FunctionsCallCounter class
 */
class FunctionsCallCounter {
	/**
	 * Call Counters
	 *
	 * @var integer
	 */
	protected $counters = [];

	/**
	 * Class instance
	 *
	 * @var self
	 */
	protected static $instance;

	/**
	 * Get the counter
	 *
	 * @param string $key The key to get the counter
	 * @return integer
	 */
	public function get_counter( $key ) {
		return $this->counters[ $key ] ?? 0;
	}

	/**
	 * Update the counter
	 *
	 * @param string $key The key to get the counter
	 * @return void
	 */
	public function update_counter( $key ) {
		if ( ! isset( $this->counters[ $key ] ) ) {
			$this->counters[ $key ] = 0;
		}
		++$this->counters[ $key ];
	}

	/**
	 * Reset the counter
	 *
	 * @param string $key The key to get the counter
	 * @return void
	 */
	public function reset_counter( $key ) {
		$this->counters[ $key ] = 0;
	}

	/**
	 * Reset all counters
	 */
	public function reset_all_counters() {
		$this->counters = [];
	}

	/**
	 * Singleton. Get the class instance
	 *
	 * @return self
	 */
	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
}
