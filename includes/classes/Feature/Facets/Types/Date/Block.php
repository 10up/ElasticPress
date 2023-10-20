<?php
/**
 * Facets block
 *
 * @since 5.0.0
 * @package elasticpress
 */

namespace ElasticPress\Feature\Facets\Types\Date;

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
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
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
			'ep-facets-date-block-script',
			EP_URL . 'dist/js/facets-date-block-script.js',
			Utils\get_asset_info( 'facets-date-block-script', 'dependencies' ),
			Utils\get_asset_info( 'facets-date-block-script', 'version' ),
			true
		);

		wp_set_script_translations( 'ep-facets-date-block-script', 'elasticpress' );

		register_block_type_from_metadata(
			EP_PATH . 'assets/js/blocks/facets/date',
			[
				'render_callback' => [ $this, 'render_block' ],
			]
		);
	}

	/**
	 * Enqueue block assets.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		wp_register_script(
			'ep-facets-date-block-view-script',
			EP_URL . 'dist/js/facets-date-block-view-script.js',
			Utils\get_asset_info( 'facets-date-block-view-script', 'dependencies' ),
			Utils\get_asset_info( 'facets-date-block-view-script', 'version' ),
			true
		);

		/**
		 * Filter the data passed to the date facet script.
		 *
		 * @hook ep_facets_date_script_data
		 * @since 5.0.0
		 * @param  {array} $data Data passed to the script.
		 * $return {array} New data passed to the script.
		 */
		$data = apply_filters( 'ep_facets_date_script_data', [] );

		wp_localize_script( 'ep-facets-date-block-view-script', 'epFacetDate', $data );
	}

	/**
	 * Render the block.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public function render_block( $attributes ) {
		/** This filter is documented in includes/classes/Feature/Facets/Types/Taxonomy/Block.php */
		$renderer_class = apply_filters( 'ep_facet_renderer_class', __NAMESPACE__ . '\Renderer', 'post-type', 'block', $attributes );
		$renderer       = new $renderer_class();

		/**
		 * Prior to WP 6.1, if you set `viewScript` while using a `render_callback` function,
		 * the script was not enqueued.
		 *
		 * @see https://core.trac.wordpress.org/changeset/54367
		 */
		if ( version_compare( get_bloginfo( 'version' ), '6.1', '<' ) ) {
			wp_enqueue_script( 'ep-facets-date-block-view-script' );
		}

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
