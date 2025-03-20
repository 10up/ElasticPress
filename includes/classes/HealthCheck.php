<?php
/**
 * HealthCheck class.
 *
 * All health checkers extend this class.
 *
 * @since  3.6.0
 * @package elasticpress
 */

namespace ElasticPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * HealthCheck abstract class
 */
abstract class HealthCheck {
	/**
	 * The name of the test.
	 *
	 * @var string
	 */
	protected $test_name = '';

	/**
	 * Test should run via Ajax calls after page load.
	 *
	 * @var bool True when is async, default false.
	 */
	protected $async = false;

	/**
	 * Runs the test and returns the result.
	 */
	abstract public function run();

	/**
	 * Gets the test name.
	 *
	 * @return string The test name.
	 */
	protected function get_test_name() {
		return $this->test_name;
	}

	/**
	 * Checks if the health check is async.
	 *
	 * @return bool True when check is async.
	 */
	protected function is_async() {
		return ! empty( $this->async );
	}

	/**
	 * Registers the test to WordPress.
	 */
	public function register_test() {
		if ( $this->is_async() ) {
			add_filter( 'site_status_tests', [ $this, 'add_async_test' ] );

			add_action( 'wp_ajax_health-check-' . $this->get_test_name(), [ $this, 'get_test_result' ] );

			return;
		}

		add_filter( 'site_status_tests', [ $this, 'add_direct_test' ] );
	}

	/**
	 * Adds to the direct tests list.
	 *
	 * @param array $tests Array with the current tests.
	 *
	 * @return array
	 */
	public function add_direct_test( $tests ) {
		$tests['direct'][ $this->get_test_name() ] = [
			'test' => [ $this, 'get_test_result' ],
		];

		return $tests;
	}

	/**
	 * Adds to the async tests list.
	 *
	 * @param array $tests Array with the current tests.
	 *
	 * @return array
	 */
	public function add_async_test( $tests ) {
		$tests['async'][ $this->get_test_name() ] = [
			'test' => $this->get_test_name(),
		];

		return $tests;
	}

	/**
	 * Gets the result of test.
	 *
	 * @return array|void
	 */
	public function get_test_result() {
		$result = $this->run();

		if ( $this->is_async() ) {
			wp_send_json_success( $result );
		} else {
			return $result;
		}
	}
}
