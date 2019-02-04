<?php
/**
 * Simple class for tracking a features requirement status
 *
 * @since  2.1
 * @package elasticpress
 */

namespace ElasticPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Just an easy way to represent a feature requirements status
 */
class FeatureRequirementsStatus {

	/**
	 * Initialize class
	 *
	 * @param int          $code Status code.
	 * @param string|array $message Message describing status.
	 * @since  2.2
	 */
	public function __construct( $code, $message = null ) {
		$this->code = $code;

		$this->message = $message;
	}

	/**
	 * Returns the status of a feature
	 *
	 * 0 is no issues
	 * 1 is usable but there are warnngs
	 * 2 is not usable
	 *
	 * @var    int
	 * @since  2.2
	 */
	public $code;

	/**
	 * Optional message to describe status code
	 *
	 * @var    string|array
	 * @since  2.2
	 */
	public $message;
}
