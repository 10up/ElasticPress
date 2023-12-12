<?php
/**
 * Facets block
 *
 * @since 4.2.0
 * @package elasticpress
 */

namespace ElasticPress\Feature\Facets\Types\Taxonomy;

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
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_editor_assets' ] );
	}

	/**
	 * Register the block.
	 *
	 * @return void
	 */
	public function register_block() {
		register_block_type_from_metadata(
			EP_PATH . 'assets/js/blocks/facets/taxonomy',
			[
				'render_callback' => [ $this, 'render_block' ],
			]
		);
	}

	/**
	 * Enqueue block editor assets.
	 *
	 * The block script is registered here to work around an issue with translations.
	 *
	 * @see https://core.trac.wordpress.org/ticket/54797#comment:20
	 * @return void
	 */
	public function enqueue_editor_assets() {
		wp_register_script(
			'ep-facets-block-script',
			EP_URL . 'dist/js/facets-block-script.js',
			Utils\get_asset_info( 'facets-block-script', 'dependencies' ),
			Utils\get_asset_info( 'facets-block-script', 'version' ),
			true
		);

		wp_set_script_translations( 'ep-facets-block-script', 'elasticpress' );
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

		/**
		 * Filter the class name to be used to render the Facet.
		 *
		 * @since 4.3.0
		 * @hook ep_facet_renderer_class
		 * @param {string} $classname  The name of the class to be instantiated and used as a renderer.
		 * @param {string} $facet_type The type of the facet.
		 * @param {string} $context    Context where the renderer will be used: `block` or `widget`, for example.
		 * @param {array} $attributes Element attributes.
		 * @return {string} The name of the class
		 */
		$renderer_class = apply_filters( 'ep_facet_renderer_class', __NAMESPACE__ . '\Renderer', 'taxonomy', 'block', $attributes );
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
