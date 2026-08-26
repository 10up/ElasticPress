<?php
/**
 * Test the ElementorUtils class methods
 *
 * @since 5.3.0
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress\ElementorUtils;

/**
 * TestElementorUtils test class
 */
class TestElementorUtils extends BaseTestCase {

	/**
	 * Setup the test environment
	 */
	public function set_up(): void {
		register_post_type( 'elementor_library' );

		parent::set_up();
	}

	/**
	 * Test the `regenerate_cache` method.
	 *
	 * @group elementor_utils
	 */
	public function test_regenerate_cache() {
		$elementor_utils = new ElementorUtils();

		set_transient( $elementor_utils::CACHE_KEY, 'test' );
		$elementor_utils->regenerate_cache();

		$this->assertIsArray( get_transient( $elementor_utils::CACHE_KEY ) );
	}

	/**
	 * Test the `get_all_widgets_in_all_templates` method.
	 *
	 * @group elementor_utils
	 */
	public function test_get_all_widgets_in_all_templates() {
		$elementor_utils = new ElementorUtils();
		$this->ep_factory->post->create(
			[
				'post_type'  => 'elementor_library',
				'meta_input' => [
					'_elementor_data' => $this->get_elementor_template_content(),
				],
			]
		);

		$all_widgets = $elementor_utils->get_all_widgets_in_all_templates();
		$this->assertCount( 2, $all_widgets );
	}

	/**
	 * Test the `get_specific_widget_in_all_templates` method
	 *
	 * @group elementor_utils
	 */
	public function test_get_specific_widget_in_all_templates() {
		$elementor_utils = new ElementorUtils();
		$this->ep_factory->post->create(
			[
				'post_type'  => 'elementor_library',
				'meta_input' => [
					'_elementor_data' => $this->get_elementor_template_content(),
				],
			]
		);

		$meta_widget = $elementor_utils->get_specific_widget_in_all_templates( 'wp-widget-ep-facet-meta' );
		$this->assertCount( 1, $meta_widget );
		$this->assertEquals( 'wp-widget-ep-facet-meta', $meta_widget[0]['widgetType'] );

		$meta_range_widget = $elementor_utils->get_specific_widget_in_all_templates( 'wp-widget-ep-facet-meta-range' );
		$this->assertCount( 1, $meta_range_widget );
		$this->assertEquals( 'wp-widget-ep-facet-meta-range', $meta_range_widget[0]['widgetType'] );
	}

	/**
	 * Get the elementor template content.
	 *
	 * @return string
	 */
	protected function get_elementor_template_content(): string {
		$content = [
			[
				'id'       => '1',
				'elType'   => 'container',
				'elements' => [
					[
						'id'       => '2',
						'elType'   => 'container',
						'settings' => [
							'_column_size' => '25',
						],
						'elements' => [
							[
								'id'         => '3',
								'elType'     => 'widget',
								'settings'   => [
									'wp' => [
										'title'         => '',
										'facet'         => '_regular_price',
										'searchPlaceholder' => 'Search',
										'orderby_order' => 'count/desc',
									],
								],
								'elements'   => [],
								'widgetType' => 'wp-widget-ep-facet-meta',
							],
							[
								'id'       => '4',
								'elType'   => 'container',
								'settings' => [
									'container_type' => 'grid',
									'presetTitle'    => 'Grid',
								],
								'elements' => [
									[
										'id'         => '5',
										'elType'     => 'widget',
										'settings'   => [
											'wp' => [
												'title'  => '',
												'facet'  => '_regular_price',
												'prefix' => '',
												'suffix' => '',
											],
										],
										'elements'   => [],
										'widgetType' => 'wp-widget-ep-facet-meta-range',
									],
								],
								'isInner'  => true,
							],
						],
						'isInner'  => true,
					],
				],
				'isInner'  => false,
			],
		];

		return wp_json_encode( $content );
	}
}
