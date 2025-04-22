<?php
/**
 * Facets meta range block
 *
 * @since 4.5.0
 * @package elasticpress
 */

namespace ElasticPress\Feature\Facets\Types\MetaRange;

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
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Register the block.
	 */
	public function register_block() {
		register_block_type_from_metadata(
			EP_PATH . 'assets/js/blocks/facets/meta-range',
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
			'ep-facets-meta-range-block-script',
			EP_URL . 'dist/js/facets-meta-range-block-script.js',
			Utils\get_asset_info( 'facets-meta-block-script', 'dependencies' ),
			Utils\get_asset_info( 'facets-meta-block-script', 'version' ),
			true
		);

		wp_set_script_translations( 'ep-facets-meta-range-block-script', 'elasticpress' );
	}

	/**
	 * Enqueue block assets.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		wp_register_script(
			'ep-facets-meta-range-block-view-script',
			EP_URL . 'dist/js/facets-meta-range-block-view-script.js',
			Utils\get_asset_info( 'facets-meta-range-block-view-script', 'dependencies' ),
			Utils\get_asset_info( 'facets-meta-range-block-view-script', 'version' ),
			true
		);
	}

	/**
	 * Render the block.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public function render_block( $attributes ) {
		/** This filter is documented in includes/classes/Feature/Facets/Types/Taxonomy/Block.php */
		$renderer_class = apply_filters( 'ep_facet_renderer_class', __NAMESPACE__ . '\Renderer', 'meta-range', 'block', $attributes );
		$renderer       = new $renderer_class();

		/**
		 * Prior to WP 6.1, if you set `viewScript` while using a `render_callback` function,
		 * the script was not enqueued.
		 *
		 * @see https://core.trac.wordpress.org/changeset/54367
		 */
		if ( version_compare( get_bloginfo( 'version' ), '6.1', '<' ) ) {
			wp_enqueue_script( 'ep-facets-meta-range-block-view-script' );
		}

		ob_start();

		$wrapper_attributes = get_block_wrapper_attributes( [ 'class' => 'wp-block-elasticpress-facet' ] );
		?>
		<div <?php echo wp_kses_data( $wrapper_attributes ); ?>>
			<?php $renderer->render( [], $attributes ); ?>
		</div>
		<?php
		return ob_get_clean();
	}
}
