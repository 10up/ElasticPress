<?php
/**
 * Facets block
 *
 * @since 4.2.0
 * @package elasticpress
 */

namespace ElasticPress\Feature\Facets\Types\Meta;

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
	 * Hook block funcionality.
	 */
	public function setup() {
		add_action( 'init', [ $this, 'register_block' ] );
		add_action( 'rest_api_init', [ $this, 'setup_endpoints' ] );
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_editor_assets' ] );
	}

	/**
	 * Setup REST endpoints for the feature.
	 */
	public function setup_endpoints() {
		register_rest_route(
			'elasticpress/v1',
			'facets/meta/keys',
			[
				'methods'             => 'GET',
				'permission_callback' => [ $this, 'check_facets_rest_permission' ],
				'callback'            => [ $this, 'get_rest_registered_metakeys' ],
			]
		);
	}

	/**
	 * Return an array of registered meta keys.
	 *
	 * @return array
	 */
	public function get_rest_registered_metakeys() {
		$post_indexable = \ElasticPress\Indexables::factory()->get( 'post' );

		try {
			$meta_keys = $post_indexable->get_distinct_meta_field_keys();
		} catch ( \Throwable $th ) {
			$meta_keys = [];
		}

		return $meta_keys;
	}

	/**
	 * Register the block.
	 */
	public function register_block() {
		register_block_type_from_metadata(
			EP_PATH . 'assets/js/blocks/facets/meta',
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
			'ep-facets-meta-block-script',
			EP_URL . 'dist/js/facets-meta-block-script.js',
			Utils\get_asset_info( 'facets-meta-block-script', 'dependencies' ),
			Utils\get_asset_info( 'facets-meta-block-script', 'version' ),
			true
		);

		wp_set_script_translations( 'ep-facets-meta-block-script', 'elasticpress' );
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

			add_filter(
				'ep_facet_meta_fields',
				function ( $meta_fields ) use ( $attributes ) {
					$meta_fields = [ $attributes['facet'] ];
					return $meta_fields;
				}
			);

			$search = Features::factory()->get_registered_feature( 'search' );

			$args = [
				'posts_per_page' => 1,
				'post_type'      => $search->get_searchable_post_types(),
			];

			$wp_query->query( $args );
		}

		/** This filter is documented in includes/classes/Feature/Facets/Types/Taxonomy/Block.php */
		$renderer_class = apply_filters( 'ep_facet_renderer_class', __NAMESPACE__ . '\Renderer', 'meta', 'block', $attributes );
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

	/**
	 * Outputs the block preview
	 *
	 * @param \WP_REST_Request $request REST request
	 * @return string
	 */
	public function render_block_preview( $request ) {
		_deprecated_function( __METHOD__, '4.7.0', '\ElasticPress\Feature\Facets\Types\Meta\render_block()' );

		$attributes = $request->get_params();

		return $this->render_block( $attributes );
	}

	/**
	 * Utilitary method to set default attributes.
	 *
	 * @param array $attributes Attributes passed
	 * @return array
	 */
	protected function parse_attributes( $attributes ) {
		_doing_it_wrong(
			__METHOD__,
			esc_html__( 'Attribute parsing is now left to block.json.', 'elasticpress' ),
			'4.7.0'
		);

		return $attributes;
	}

	/**
	 * DEPRECATED. Check permissions of the /facets/meta/* REST endpoints.
	 *
	 * @return WP_Error|true
	 */
	public function check_facets_meta_rest_permission() {
		_deprecated_function( __METHOD__, '4.7.0', '\ElasticPress\Feature\Facets\Types\Meta\Block::check_facets_rest_permission()' );

		return $this->check_facets_rest_permission();
	}
}
