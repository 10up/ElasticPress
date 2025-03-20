<?php
/**
 * Test the BlockTemplateUtils class methods
 *
 * @since 4.7.0
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress\BlockTemplateUtils;

/**
 * TestBlockTemplateUtils test class
 */
class TestBlockTemplateUtils extends BaseTestCase {
	/**
	 * Test the `regenerate_cache` method
	 *
	 * @group block_template_utils
	 */
	public function test_regenerate_cache() {
		$block_template_utils = new BlockTemplateUtils();

		set_transient( $block_template_utils::CACHE_KEY, 'test' );
		$block_template_utils->regenerate_cache();

		$this->assertIsArray( get_transient( $block_template_utils::CACHE_KEY ) );
	}

	/**
	 * Test the `get_specific_block_in_all_templates` method
	 *
	 * @group block_template_utils
	 */
	public function test_get_specific_block_in_all_templates() {
		$block_template_utils = new BlockTemplateUtils();

		$meta_block = [
			'blockName' => 'elasticpress/facet-meta',
			'attrs'     => [ 'facet' => '_price' ],
		];

		$meta_range_block = [
			'blockName' => 'elasticpress/facet-meta-range',
			'attrs'     => [ 'facet' => '_sale_price' ],
		];

		$blocks = [
			[
				'blockName' => 'core/search',
				'attrs'     => [ 'label' => 'Search' ],
			],
			$meta_block,
			$meta_range_block,
		];

		$set_blocks = function () use ( $blocks ) {
			return $blocks;
		};
		add_filter( 'ep_blocks_pre_all_blocks', $set_blocks );

		$this->assertEqualsCanonicalizing(
			[ $meta_block ],
			$block_template_utils->get_specific_block_in_all_templates( 'elasticpress/facet-meta' )
		);
		$this->assertEqualsCanonicalizing(
			[ $meta_range_block ],
			$block_template_utils->get_specific_block_in_all_templates( 'elasticpress/facet-meta-range' )
		);
	}

	/**
	 * Test the `get_all_blocks_in_all_templates` method
	 *
	 * @group block_template_utils
	 */
	public function test_get_all_blocks_in_all_templates() {
		$this->markTestIncomplete( 'This test should also test the real returns from get_block_templates(), etc.' );
	}

	/**
	 * Test the `ep_blocks_pre_all_blocks` filter
	 *
	 * @group block_template_utils
	 */
	public function test_get_all_blocks_in_all_templates_ep_blocks_pre_all_blocks() {
		$block_template_utils = new BlockTemplateUtils();

		$times_called = did_filter( 'pre_transient_' . $block_template_utils::CACHE_KEY );

		$set_blocks = function ( $pre_all_blocks ) {
			$this->assertNull( $pre_all_blocks );
			return [ 'test' ];
		};
		add_filter( 'ep_blocks_pre_all_blocks', $set_blocks );

		$this->assertSame( [ 'test' ], $block_template_utils->get_all_blocks_in_all_templates() );

		// This filter should not have been called once more
		$this->assertSame( $times_called, did_filter( 'pre_transient_' . $block_template_utils::CACHE_KEY ) );
	}

	/**
	 * Test the `get_all_blocks_in_all_templates` transient
	 *
	 * @group block_template_utils
	 */
	public function test_get_all_blocks_in_all_templates_transient() {
		$block_template_utils = new BlockTemplateUtils();
		delete_transient( $block_template_utils::CACHE_KEY );

		$times_called = did_filter( 'pre_set_transient_' . $block_template_utils::CACHE_KEY );

		// This filter should be called once to set the transient
		$block_template_utils->get_all_blocks_in_all_templates();
		$expected_times_called = $times_called + 1;
		$this->assertSame( $expected_times_called, did_filter( 'pre_set_transient_' . $block_template_utils::CACHE_KEY ) );

		// This filter should not be called again, because the transient was already set
		$block_template_utils->get_all_blocks_in_all_templates();
		$this->assertSame( $expected_times_called, did_filter( 'pre_set_transient_' . $block_template_utils::CACHE_KEY ) );
	}
}
