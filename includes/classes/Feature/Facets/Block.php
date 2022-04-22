<?php
/**
 * Facets widget
 *
 * @package elasticpress
 */

namespace ElasticPress\Feature\Facets;

use ElasticPress\Features;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Facets block class
 */
class Block {
	/**
	 * Hook block funcionality.
	 */
	public function setup() {
		add_action( 'init', [ $this, 'register_block' ] );

		$this->renderer = new Renderer();
	}

	/**
	 * Register the block.
	 */
	public function register_block() {
		$feature = Features::factory()->get_registered_feature( 'facets' );

		wp_register_script(
			'elasticpress-facets-block',
			EP_URL . 'dist/js/facets-block-script.min.js',
			[
				'wp-blocks',
				'wp-element',
				'wp-editor',
				'wp-api-fetch',
			],
			EP_VERSION,
			true
		);

		// The wp-edit-blocks style dependency is not needed on the front end of the site.
		$style_dependencies = is_admin() ? [ 'wp-edit-blocks' ] : [];

		wp_register_style(
			'elasticpress-related-posts-block',
			EP_URL . 'dist/css/facets-block-styles.min.css',
			$style_dependencies,
			EP_VERSION
		);

		register_block_type(
			'elasticpress/facet',
			[
				'attributes'      => [
					'facet'   => [
						'type' => 'string',
						'enum' => wp_list_pluck( $feature->get_facetable_taxonomies(), 'name' ),
					],
					'orderby' => [
						'type'    => 'string',
						'default' => 'count',
						'enum'    => [ 'count', 'name' ],
					],
					'order'   => [
						'type' => 'string',
						'enum' => [ 'desc', 'asc' ],
					],
				],
				'editor_script'   => 'elasticpress-facets-block',
				'editor_style'    => 'elasticpress-facets-block',
				'style'           => 'elasticpress-facets-block',
				'render_callback' => [ $this, 'render_block' ],
			]
		);
	}

	/**
	 * Render the block.
	 *
	 * @param array $attributes Block attributes.
	 */
	public function render_block( $attributes ) {
		$this->renderer->render( [], [] );
	}
}
