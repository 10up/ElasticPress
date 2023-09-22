<?php
/**
 * Facets block
 *
 * @since 4.6.0
 * @package elasticpress
 */

namespace ElasticPress\Feature\Facets\Types\PostType;

use ElasticPress\Features;
use ElasticPress\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Facets block class
 */
class Block extends \ElasticPress\Feature\Facets\Block {
	/**
	 * Hook block functionality.
	 */
	public function setup() {
		add_action( 'init', [ $this, 'register_block' ] );
	}

	/**
	 * Register the block.
	 */
	public function register_block() {
		/**
		 * Registering it here so translation works
		 *
		 * @see https://core.trac.wordpress.org/ticket/54797#comment:20
		 */
		wp_register_script(
			'ep-facets-post-type-block-script',
			EP_URL . 'dist/js/facets-post-type-block-script.js',
			Utils\get_asset_info( 'facets-post-type-block-script', 'dependencies' ),
			Utils\get_asset_info( 'facets-post-type-block-script', 'version' ),
			true
		);

		wp_set_script_translations( 'ep-facets-post-type-block-script', 'elasticpress' );

		register_block_type_from_metadata(
			EP_PATH . 'assets/js/blocks/facets/post-type',
			[
				'render_callback' => [ $this, 'render_block' ],
			]
		);
	}

	/**
	 * Render the block.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public function render_block( $attributes ) {
		global $wp_query;

		if ( $attributes['isPreview'] ) {
			add_filter( 'ep_is_facetable', '__return_true' );

			$search = Features::factory()->get_registered_feature( 'search' );

			$wp_query->query(
				[
					'posts_per_page' => 1,
					'post_type'      => $search->get_searchable_post_types(),
				]
			);
		}

		/** This filter is documented in includes/classes/Feature/Facets/Types/Taxonomy/Block.php */
		$renderer_class = apply_filters( 'ep_facet_renderer_class', __NAMESPACE__ . '\Renderer', 'post-type', 'block', $attributes );
		$renderer       = new $renderer_class();

		ob_start();

		$renderer->render( [], $attributes );

		$block_content = ob_get_clean();

		if ( empty( $block_content ) ) {
			return;
		}

		$wrapper_attributes = get_block_wrapper_attributes( [ 'class' => 'wp-block-elasticpress-facet' ] );

		return sprintf(
			'<div %1$s>%2$s</div>',
			wp_kses_data( $wrapper_attributes ),
			$block_content
		);
	}
}
