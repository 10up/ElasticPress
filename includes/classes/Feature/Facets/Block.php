<?php
/**
 * Facets widget
 *
 * @package elasticpress
 */

namespace ElasticPress\Feature\Facets;

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
					'number' => [
						'type'    => 'number',
						'default' => 5,
					],
					'align'  => [
						'type' => 'string',
						'enum' => [ 'left', 'center', 'right', 'wide', 'full' ],
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
	 */
	public function render_block() {
		$this->renderer->render( [], [] );
	}
}
