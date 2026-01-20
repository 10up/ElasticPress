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
	const AUTO_ENABLED         = 0;
	const MANUALLY_ENABLED     = 1;
	const FORCE_DISABLED       = 2;
	const TEMPORARILY_DISABLED = 3;

	/**
	 * Returns the status of a feature
	 *
	 * 0 is no issues
	 * 1 is usable but there are warnings
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

	/**
	 * Optional feature object
	 *
	 * @var    Feature|null
	 * @since  5.3.3
	 */
	public $feature;

	/**
	 * Initialize class
	 *
	 * @param int          $code Status code.
	 * @param string|array $message Message describing status.
	 * @param Feature      $feature Feature object.
	 * @since  2.2
	 */
	public function __construct( $code, $message = null, $feature = null ) {
		$this->code    = $code;
		$this->message = $message;
		$this->feature = $feature;
	}

	/**
	 * Get the message for the feature requirements status
	 *
	 * @return array The message to display
	 * @since  5.3.3
	 */
	public function get_message() {
		/**
		 * Filter the feature requirements status message
		 *
		 * @hook ep_feature_requirements_status_message
		 * @param {array} $message The message to display
		 * @param {FeatureRequirementsStatus} $status The feature requirements status object
		 * @since  5.3.3
		 * @return array The message to display
		 */
		return apply_filters( 'ep_feature_requirements_status_message', (array) $this->message, $this );
	}

	/**
	 * Get the code for the feature requirements status
	 *
	 * @return int The code to display
	 * @since  5.3.3
	 */
	public function get_code() {
		/**
		 * Filter the feature requirements status code
		 *
		 * @hook ep_feature_requirements_status_code
		 * @param {int} $code The code to display
		 * @param {FeatureRequirementsStatus} $status The feature requirements status object
		 * @since  5.3.3
		 * @return int The code to display
		 */
		return apply_filters( 'ep_feature_requirements_status_code', (int) $this->code, $this );
	}

	/**
	 * Get the feature object
	 *
	 * @return Feature|null The feature object
	 * @since  5.3.3
	 */
	public function get_feature() {
		/**
		 * Filter the feature object
		 *
		 * @hook ep_feature_requirements_status_feature
		 * @param {Feature|null} $feature The feature object
		 * @param {FeatureRequirementsStatus} $status The feature requirements status object
		 * @since  5.3.3
		 * @return Feature|null The feature object
		 */
		return apply_filters( 'ep_feature_requirements_status_feature', $this->feature, $this );
	}
}
