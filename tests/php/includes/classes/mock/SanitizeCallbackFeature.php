<?php
/**
 * SanitizeCallbackFeature feature
 *
 * @since 5.3.0
 * @package elasticpress
 */

namespace ElasticPressTest;

/**
 * SanitizeCallbackFeature class
 */
class SanitizeCallbackFeature extends \ElasticPress\Feature {
	/**
	 * Initialize feature setting it's config
	 */
	public function __construct() {
		$this->slug  = 'test_sanitize_callback';
		$this->title = 'Test Sanitize Callback';

		parent::__construct();
	}

	/**
	 * Required implementation
	 */
	public function setup() {}

	/**
	 * Set the `settings_schema` attribute
	 */
	protected function set_settings_schema() {
		$this->settings_schema = array_merge(
			$this->settings_schema,
			[
				'test-input' => [
					'key'   => 'test-input',
					'label' => 'Test Input',
					'type'  => 'text',
				],
			]
		);
	}

	/**
	 * Sanitize settings callback method
	 *
	 * @param mixed $value The value to sanitize
	 * @return mixed The sanitized value
	 */
	public function sanitize_settings_callback( $value ) {
		if ( 'Testing' === $value ) {
			return 'New value';
		}
		return $value;
	}
}
