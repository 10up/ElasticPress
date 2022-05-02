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
		register_block_type_from_metadata(
			EP_PATH . 'assets/js/blocks/facets',
			[
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
		ob_start();
		?>
		<div class="wp-block-elasticpress-facet">
			<?php $this->renderer->render( [], $attributes ); ?>
		</div>
		<?php
		return ob_get_clean();
	}
}
