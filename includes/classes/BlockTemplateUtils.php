<?php
/**
 * Block Template Utils class
 *
 * @since 4.7.0
 * @package elasticpress
 */

namespace ElasticPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Block Template Utils class
 */
class BlockTemplateUtils {
	/**
	 * Given a block name, return all its instances across all block templates
	 *
	 * @param string $block_name The block name, e.g., `elasticpress/facet-meta`
	 * @return array
	 */
	public function get_specific_block_in_all_templates( string $block_name ) : array {
		$blocks = array_filter(
			$this->get_all_blocks_in_all_templates(),
			function ( $block ) use ( $block_name ) {
				return ( $block['blockName'] === $block_name );
			}
		);

		return $blocks;
	}

	/**
	 * Get all blocks in all block templates
	 *
	 * It returns a flat list of all blocks, including innerBlocks.
	 *
	 * @return array
	 */
	public function get_all_blocks_in_all_templates() : array {
		$all_blocks = [];

		$template_types = [ 'wp_template', 'wp_template_part' ];

		foreach ( $template_types as $template_type ) {
			$block_templates = get_block_templates( [], $template_type );
			foreach ( $block_templates as $block_template ) {
				$template_blocks = parse_blocks( $block_template->content );

				foreach ( $template_blocks as $block ) {
					$all_blocks[] = [
						'blockName' => $block['blockName'],
						'attrs'     => $block['attrs'],
					];

					$all_blocks = $this->recursively_get_inner_blocks( $all_blocks, $block );
				}
			}
		}

		return $all_blocks;
	}

	/**
	 * Get all inner blocks recursively
	 *
	 * @param array $all_blocks All blocks analyzed so far
	 * @param array $block      Block to be analyzed now
	 * @return array
	 */
	protected function recursively_get_inner_blocks( array $all_blocks, array $block ) : array {
		if ( empty( $block['innerBlocks'] ) ) {
			return $all_blocks;
		}

		foreach ( $block['innerBlocks'] as $inner_block ) {
			$all_blocks[] = [
				'blockName' => $inner_block['blockName'],
				'attrs'     => $inner_block['attrs'],
			];

			$all_blocks = $this->recursively_get_inner_blocks( $all_blocks, $inner_block );
		}

		return $all_blocks;
	}
}
