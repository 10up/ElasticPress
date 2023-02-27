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
class Block {
	/**
	 * Hook block funcionality.
	 */
	public function setup() {
		add_action( 'init', [ $this, 'register_block' ] );
		add_action( 'rest_api_init', [ $this, 'setup_endpoints' ] );
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
			'ep-facets-meta-range-block-script',
			EP_URL . 'dist/js/facets-meta-range-block-script.js',
			Utils\get_asset_info( 'facets-meta-block-script', 'dependencies' ),
			Utils\get_asset_info( 'facets-meta-block-script', 'version' ),
			true
		);

		wp_set_script_translations( 'ep-facets-meta-range-block-script', 'elasticpress' );

		register_block_type_from_metadata(
			EP_PATH . 'assets/js/blocks/facets/meta-range',
			[
				'render_callback' => [ $this, 'render_block' ],
			]
		);

		wp_localize_script( 'ep-facets-meta-range-block-script', 'facetMetaBlock', [ 'syncUrl' => Utils\get_sync_url() ] );
	}


	/**
	 * Setup REST endpoints for the feature.
	 */
	public function setup_endpoints() {
		register_rest_route(
			'elasticpress/v1',
			'facets/meta-range/keys',
			[
				'methods'             => 'GET',
				'permission_callback' => [ $this, 'check_facets_meta_rest_permission' ],
				'callback'            => [ $this, 'get_rest_registered_metakeys' ],
			]
		);
		register_rest_route(
			'elasticpress/v1',
			'facets/meta-range/block-preview',
			[
				'methods'             => 'GET',
				'permission_callback' => [ $this, 'check_facets_meta_rest_permission' ],
				'callback'            => [ $this, 'render_block_preview' ],
				'args'                => [
					'facet' => [
						'sanitize_callback' => 'sanitize_text_field',
					],
				],

			]
		);
	}

	/**
	 * Check permissions of the /facets/taxonomies REST endpoint.
	 *
	 * @return WP_Error|true
	 */
	public function check_facets_meta_rest_permission() {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return new \WP_Error( 'ep_rest_forbidden', esc_html__( 'Sorry, you cannot view this resource.', 'elasticpress' ), array( 'status' => 401 ) );
		}

		return true;
	}

	/**
	 * Render the block.
	 *
	 * @param array $attributes Block attributes.
	 */
	public function render_block( $attributes ) {
		$attributes = $this->parse_attributes( $attributes );

		/** This filter is documented in includes/classes/Feature/Facets/Types/Taxonomy/Block.php */
		$renderer_class = apply_filters( 'ep_facet_renderer_class', __NAMESPACE__ . '\Renderer', 'meta-range', 'block', $attributes );
		$renderer       = new $renderer_class();

		ob_start();
		?>
		<div class="wp-block-elasticpress-facet">
			<?php $renderer->render( [], $attributes ); ?>
		</div>
		<?php
		return ob_get_clean();
	}


	/**
	 * Outputs the block preview
	 *
	 * @param \WP_REST_Request $request REST request
	 * @return string
	 */
	public function render_block_preview( $request ) {
		$search = \ElasticPress\Features::factory()->get_registered_feature( 'search' );

		$attributes = $this->parse_attributes(
			[
				'facet'      => $request->get_param( 'facet' ),
				'is_preview' => true,
			]
		);

		add_filter(
			'ep_facet_meta_fields',
			function ( $meta_fields ) use ( $attributes ) {
				$meta_fields = [ $attributes['facet'] ];
				return $meta_fields;
			}
		);

		$query = new \WP_Query(
			[
				'ep_is_facetable' => true,
				'post_type'       => $search->get_searchable_post_types(),
				'per_page'        => 1,
			]
		);

		/** This filter is documented in includes/classes/Feature/Facets/Types/Taxonomy/Block.php */
		$renderer_class = apply_filters( 'ep_facet_renderer_class', __NAMESPACE__ . '\Renderer', 'meta-block', 'block', $attributes );
		$renderer       = new $renderer_class();

		ob_start();
		$renderer->render( [], $attributes );
		$block_content = ob_get_clean();

		if ( empty( $block_content ) ) {
			return sprintf(
				/* translators: Meta field name */
				esc_html__( 'Preview for %s not available', 'elasticpress' ),
				esc_html( $request->get_param( 'facet' ) )
			);
		}

		$block_content = preg_replace( '/href="(.*?)"/', 'href="#"', $block_content );
		return '<div class="wp-block-elasticpress-facet">' . $block_content . '</div>';
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
	 * Utilitary method to set default attributes.
	 *
	 * @param array $attributes Attributes passed
	 * @return array
	 */
	protected function parse_attributes( $attributes ) {
		$attributes = wp_parse_args(
			$attributes,
			[
				'facet'      => '',
				'is_preview' => false,
			]
		);
		if ( empty( $attributes['facet'] ) ) {
			$registered_metakeys = $this->get_rest_registered_metakeys();
			if ( ! empty( $registered_metakeys ) ) {
				$attributes['facet'] = reset( $registered_metakeys );
			}
		}
		return $attributes;
	}
}
