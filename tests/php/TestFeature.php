<?php
/**
 * Test Feature methods
 *
 * @since 5.0.0
 * @package elasticpress
 */

namespace ElasticPressTest;

/**
 * Feature test class
 */
class TestFeature extends BaseTestCase {
	/**
	 * Test get_json.
	 *
	 * @group feature
	 */
	public function test_get_json() {
		$stub                   = $this->getMockForAbstractClass( '\ElasticPress\Feature' );
		$stub->slug             = 'slug';
		$stub->title            = 'title';
		$stub->short_title      = 'short_title';
		$stub->summary          = 'summary';
		$stub->docs_url         = 'https://elasticpress.io/';
		$stub->default_settings = [];
		$stub->order            = 1;

		add_filter(
			'ep_feature_requirements_status',
			function() {
				return new \ElasticPress\FeatureRequirementsStatus( 2, 'Testing' );
			}
		);

		$expected = [
			'slug'              => 'slug',
			'title'             => 'title',
			'shortTitle'        => 'short_title',
			'summary'           => 'summary',
			'docsUrl'           => 'https://elasticpress.io/',
			'defaultSettings'   => [],
			'order'             => 1,
			'isAvailable'       => false, // Set by status code 2
			'reqStatusCode'     => 2,
			'reqStatusMessages' => 'Testing',
			'settingsSchema'    => [
				[
					'default'       => false,
					'key'           => 'active',
					'label'         => __( 'Enable', 'elasticpress' ),
					'requires_sync' => false,
					'type'          => 'toggle',
				],
			],
		];

		$this->assertSame( $expected, $stub->get_json() );
	}

	/**
	 * Test get_settings_schema.
	 *
	 * @group feature
	 */
	public function test_get_settings_schema() {
		$stub = $this->getMockForAbstractClass( '\ElasticPress\Feature' );

		$reflection          = new \ReflectionClass( $stub );
		$reflection_property = $reflection->getProperty( 'settings_schema' );
		$reflection_property->setAccessible( true );

		$reflection_property->setValue( $stub, [ [ 'key' => 'test_1' ], [ 'key' => 'test_2' ] ] );

		$settings_schema = $stub->get_settings_schema();

		$this->assertIsArray( $settings_schema );
		$this->assertSame(
			[
				[
					'default'       => false,
					'key'           => 'active',
					'label'         => __( 'Enable', 'elasticpress' ),
					'requires_sync' => false,
					'type'          => 'toggle',
				],
				[ 'key' => 'test_1' ],
				[ 'key' => 'test_2' ],
			],
			$settings_schema
		);
	}

	/**
	 * Test the ep_feature_settings_schema filter.
	 *
	 * @group feature
	 */
	public function test_ep_feature_settings_schema_filter() {
		$stub       = $this->getMockForAbstractClass( '\ElasticPress\Feature' );
		$stub->slug = 'slug';

		$change_settings_schema = function( $settings_schema, $feature_slug, $feature ) use ( $stub ) {
			$this->assertSame( $feature_slug, 'slug' );
			$this->assertSame( $feature, $stub );
			$settings_schema[] = [ 'key' => 'new_field' ];
			return $settings_schema;
		};
		add_filter( 'ep_feature_settings_schema', $change_settings_schema, 10, 3 );

		$settings_schema = $stub->get_settings_schema();
		$this->assertSame(
			[
				[
					'default'       => false,
					'key'           => 'active',
					'label'         => __( 'Enable', 'elasticpress' ),
					'requires_sync' => false,
					'type'          => 'toggle',
				],
				[ 'key' => 'new_field' ],
			],
			$settings_schema
		);
	}
}
