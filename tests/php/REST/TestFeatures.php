<?php
/**
 * Test the Features REST controller
 *
 * @since 5.3.0
 * @package elasticpress
 */

namespace ElasticPressTest\REST;

use ElasticPress\REST\Features;
use ElasticPressTest\SanitizeCallbackFeature;

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

		unset( $features_instance->registered_features['test_settings_schema'] );
	}

	/**
	 * Test that sanitize_callback is properly assigned when feature has sanitize_settings_callback method.
	 *
	 * @group rest
	 * @group rest-features
	 */
	public function test_get_args_with_sanitize_callback() {
		$features_rest     = new Features();
		$features_instance = \ElasticPress\Features::factory();

		// Register a feature with sanitize_settings_callback method
		$sanitize_feature = new SanitizeCallbackFeature();
		$features_instance->register_feature( $sanitize_feature );

		$args = $features_rest->get_args();

		// Verify that the sanitize_callback is properly assigned
		$this->assertArrayHasKey( 'test_sanitize_callback', $args );
		$this->assertArrayHasKey( 'sanitize_callback', $args['test_sanitize_callback'] );
		$this->assertEquals( [ $sanitize_feature, 'sanitize_settings_callback' ], $args['test_sanitize_callback']['sanitize_callback'] );

		// Verify the callback is callable
		$this->assertTrue( is_callable( $args['test_sanitize_callback']['sanitize_callback'] ) );

		// Test that the callback actually works
		$test_value      = 'Testing';
		$sanitized_value = call_user_func( $args['test_sanitize_callback']['sanitize_callback'], $test_value );
		$this->assertEquals( 'New value', $sanitized_value );

		unset( $features_instance->registered_features['test_sanitize_callback'] );
	}
}
