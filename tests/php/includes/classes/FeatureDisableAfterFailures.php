<?php
/**
 * Test Feature for DisableAfterFailures trait
 *
 * This feature class is used for testing the DisableAfterFailures trait.
 *
 * @since 5.4.0
 * @package ElasticPress
 */

namespace ElasticPressTest\Includes\Classes;

use ElasticPress\Feature;
use ElasticPress\Traits\DisableAfterFailures;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Test feature class that uses DisableAfterFailures trait
 */
class FeatureDisableAfterFailures extends Feature {
	use DisableAfterFailures;

	/**
	 * Initialize feature setting its config
	 */
	public function __construct() {
		$this->slug  = 'test_disable_after_failures';
		$this->title = 'Test Disable After Failures';

		parent::__construct();
	}

	/**
	 * Setup all feature hooks
	 */
	public function setup() {
		// Empty setup for testing purposes.
	}

	/**
	 * Return requirement status
	 *
	 * @return \ElasticPress\FeatureRequirementsStatus
	 */
	public function requirements_status() {
		$status = parent::requirements_status();

		// If should disable after failures, update the status.
		if ( $this->should_disable_after_failures() ) {
			$status = $this->update_requirements_status( $status );
		}

		return $status;
	}
}
