<?php
/**
 * Test the ACF Repeater Field Compatibility feature
 *
 * @see ../includes/acf-pro-functions.php for mock implementations of ACF functions
 * @since 5.3.0
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress\Features;

/**
 * ACF Repeater Field Compatibility test class
 */
class TestAcfRepeater extends BaseTestCase {
	/**
	 * AcfRepeater feature instance
	 *
	 * @var ElasticPress\Feature\AcfRepeater\AcfRepeater
	 */
	protected $feature;

	/**
	 * Setup each test.
	 */
	public function set_up() {
		$this->feature = Features::factory()->get_registered_feature( 'acf_repeater' );
		\ElasticPressTest\FunctionsCallCounter::get_instance()->reset_all_counters();

		parent::set_up();
	}

	/**
	 * Setup mock functions
	 */
	protected function setup_mock_functions() {
		require_once __DIR__ . '/../includes/acf-pro-functions.php';
	}

	/**
	 * Test the `requirements_status` method
	 *
	 * @group acf-repeater
	 */
	public function test_requirements_status() {
		$requirement_status = $this->feature->requirements_status();

		$this->assertSame( 2, $requirement_status->code );
		$this->assertSame( 'ACF Pro not installed.', $requirement_status->message );

		$this->setup_mock_functions();

		$requirement_status = $this->feature->requirements_status();

		$this->assertSame( 0, $requirement_status->code );
		$this->assertNull( $requirement_status->message );
	}

	/**
	 * Test the `render_field_settings` method
	 *
	 * @group acf-repeater
	 */
	public function test_render_field_settings() {
		$field = [
			'name' => 'test_field',
			'type' => 'not-repeater',
		];

		$this->feature->render_field_settings( $field );
		$this->assertEquals( 0, \ElasticPressTest\FunctionsCallCounter::get_instance()->get_counter( 'acf_render_field_setting' ) );

		$field['type'] = 'repeater';

		$this->feature->render_field_settings( $field );
		$this->assertEquals( 0, \ElasticPressTest\FunctionsCallCounter::get_instance()->get_counter( 'acf_render_field_setting' ) );

		$field['parent'] = $this->factory->post->create();

		$this->feature->render_field_settings( $field );
		$this->assertEquals( 1, \ElasticPressTest\FunctionsCallCounter::get_instance()->get_counter( 'acf_render_field_setting' ) );
	}

	/**
	 * Test the `ep_acf_repeater_should_display_field_setting` filter method
	 *
	 * @group acf-repeater
	 */
	public function test_ep_acf_repeater_should_display_field_setting_filter() {
		$field = [
			'name'   => 'test_field',
			'type'   => 'repeater',
			'parent' => $this->factory->post->create(),
		];

		add_filter( 'ep_acf_repeater_should_display_field_setting', '__return_false' );

		$this->feature->render_field_settings( $field );
		$this->assertEquals( 0, \ElasticPressTest\FunctionsCallCounter::get_instance()->get_counter( 'acf_render_field_setting' ) );
	}

	/**
	 * Test the `allow_meta_keys` method
	 *
	 * @group acf-repeater
	 */
	public function test_allow_meta_keys() {
		$initial_meta = [ 'meta_1', 'meta_2' ];

		$this->assertSame(
			$initial_meta,
			$this->feature->allow_meta_keys( $initial_meta, new \WP_Post( (object) [ 'ID' => 0 ] ) )
		);

		$this->assertSame(
			[ 'meta_1', 'meta_2', 'should_be_added' ],
			$this->feature->allow_meta_keys( $initial_meta, new \WP_Post( (object) [ 'ID' => 1 ] ) )
		);
	}

	/**
	 * Test the `add_meta_keys` method
	 *
	 * @group acf-repeater
	 */
	public function test_add_meta_keys() {
		$initial_meta = [
			'meta_1'        => [ 1 ],
			'meta_2'        => [ 2 ],
			'repeater_test' => [ '' ],
		];

		$this->assertSame(
			array_merge(
				$initial_meta,
				[
					'repeater_test' => '{"child_1":"value_1","child_2":"value_2"}',
				]
			),
			$this->feature->add_meta_keys( $initial_meta, new \WP_Post( (object) [] ) )
		);
	}

	/**
	 * Test the `ep_acf_repeater_meta_value` filter
	 *
	 * @group acf-repeater
	 */
	public function test_ep_acf_repeater_meta_value() {
		$initial_meta = [
			'meta_1'        => [ 1 ],
			'meta_2'        => [ 2 ],
			'repeater_test' => [ '' ],
		];

		$callback = function ( $value_encoded, $value_field, $key, $post ) {
			$this->assertSame(
				[
					'child_1' => 'value_1',
					'child_2' => 'value_2',
				],
				$value_field
			);
			$this->assertSame( $key, 'repeater_test' );
			$this->assertInstanceOf( '\WP_Post', $post );
			return $value_encoded . '_filtered';
		};
		add_filter( 'ep_acf_repeater_meta_value', $callback, 10, 4 );

		$this->assertSame(
			array_merge(
				$initial_meta,
				[
					'repeater_test' => '{"child_1":"value_1","child_2":"value_2"}_filtered',
				]
			),
			$this->feature->add_meta_keys( $initial_meta, new \WP_Post( (object) [] ) )
		);
	}
}
