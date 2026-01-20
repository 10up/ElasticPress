<?php
/**
 * Feature test B class
 *
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress;

/**
 * Feature test B class
 */
class FeatureTestB extends ElasticPress\Feature {
	/**
	 * Track if setup was called
	 *
	 * @var bool
	 */
	public $setup_called = false;

	/**
	 * Initial status code
	 *
	 * @var int
	 */
	public $initial_status_code = 1;

	/**
	 * Create feature test class
	 *
	 * @param int $initial_status_code The initial status code
	 */
	public function __construct( $initial_status_code = 1 ) {
		$this->slug  = 'test-b';
		$this->title = 'Test B';

		$this->initial_status_code = $initial_status_code;

		parent::__construct();
	}

	/**
	 * Return requirement status
	 *
	 * @return ElasticPress\FeatureRequirementsStatus
	 */
	public function requirements_status() {
		$status = new ElasticPress\FeatureRequirementsStatus( $this->initial_status_code, null, $this );

		return $status;
	}

	/**
	 * Do nothing
	 */
	public function setup() {
		$this->setup_called = true;
	}
}
