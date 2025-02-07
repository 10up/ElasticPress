<?php
/**
 * Test the Features REST controller
 *
 * @since 5.3.0
 * @package elasticpress
 */

namespace ElasticPressTest\REST;

use ElasticPress\REST\Features;

/**
 * TestFeatures test class
 */
class TestFeatures extends \ElasticPressTest\BaseTestCase {
	/**
	 * Test get_args.
	 *
	 * @group rest
	 * @group rest-features
	 */
	public function test_get_args() {
		$features_rest     = new Features();
		$features_instance = \ElasticPress\Features::factory();

		$features_instance->registered_features = [];
		$features_instance->register_feature(
			new \ElasticPressTest\SettingsSchemaFeature()
		);

		$args = $features_rest->get_args();

		$test_settings_schema = [
			'description' => 'Test Settings Schema',
			'type'        => 'object',
			'properties'  => [
				'active'            => [
					'description' => 'Enable',
					'type'        => 'boolean',
				],
				'test-select'       => [
					'description' => 'Test Select',
					'type'        => 'string',
					'enum'        => [
						'option_1',
						'option_2',
					],
				],
				'test-radio'        => [
					'description' => 'Test Radio',
					'type'        => 'string',
					'enum'        => [
						'radio_1',
						'radio_2',
					],
				],
				'test-toggle'       => [
					'description' => 'Test Toggle',
					'type'        => 'boolean',
				],
				'test-number'       => [
					'description' => 'Test Number',
					'type'        => 'number',
				],
				'test-url'          => [
					'description' => 'Test URL',
					'type'        => 'string',
					'format'      => 'uri',
				],
				'test-text'         => [
					'description' => 'Test Text',
					'type'        => 'string',
				],
				'test-non-existent' => [
					'description' => 'Test Non Existent',
					'type'        => 'string',
				],
				'test-string'       => [
					'description' => 'Test String',
					'type'        => 'string',
				],
			],
		];

		$this->assertEquals( $test_settings_schema, $args['test_settings_schema'] );
	}
}
