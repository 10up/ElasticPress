<?php
/**
 * SettingsSchemaFeature feature
 *
 * @since 5.3.0
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress\Indexables;

/**
 * SettingsSchemaFeature class
 */
class SettingsSchemaFeature extends \ElasticPress\Feature {
	/**
	 * Initialize feature setting it's config
	 */
	public function __construct() {
		$this->slug  = 'test_settings_schema';
		$this->title = 'Test Settings Schema';

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
				'test-select'       => [
					'key'     => 'test-select',
					'label'   => 'Test Select',
					'type'    => 'select',
					'options' => [
						[
							'label' => 'Option 1',
							'value' => 'option_1',
						],
						[
							'label' => 'Option 2',
							'value' => 'option_2',
						],
					],
				],
				'test-radio'        => [
					'key'     => 'test-radio',
					'label'   => 'Test Radio',
					'type'    => 'radio',
					'options' => [
						[
							'label' => 'Radio 1',
							'value' => 'radio_1',
						],
						[
							'label' => 'Radio 2',
							'value' => 'radio_2',
						],
					],
				],
				'test-toggle'       => [
					'key'   => 'test-toggle',
					'label' => 'Test Toggle',
					'type'  => 'toggle',
				],
				'test-number'       => [
					'key'   => 'test-number',
					'label' => 'Test Number',
					'type'  => 'number',
				],
				'test-url'          => [
					'key'   => 'test-url',
					'label' => 'Test URL',
					'type'  => 'url',
				],
				'test-text'         => [
					'key'   => 'test-text',
					'label' => 'Test Text',
					'type'  => 'text',
				],
				'test-non-existent' => [
					'key'   => 'test-non-existent',
					'label' => 'Test Non Existent',
					'type'  => 'non-existent',
				],
				'test-string'       => [
					'key'   => 'test-string',
					'label' => 'Test String',
					'type'  => 'string',
				],
			]
		);
	}
}
