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
class Block {
	/**
	 * Hook block functionality.
	 */
	public function setup() {
		add_action( 'init', [ $this, 'register_block' ] );
		add_action( 'rest_api_init', [ $this, 'setup_endpoints' ] );
	}

	/**
	 * Setup REST endpoints for the feature.
	 */
	public function setup_endpoints() {
		register_rest_route(
			'elasticpress/v1',
			'facets/post-type/block-preview',
			[
				'methods'             => 'GET',
				'permission_callback' => [ $this, 'check_facets_rest_permission' ],
				'callback'            => [ $this, 'render_block_preview' ],
				'args'                => [
					'searchPlaceholder' => [
						'sanitize_callback' => 'sanitize_text_field',
					],
					'displayCount'      => [
						'sanitize_callback' => 'rest_sanitize_boolean',
					],
					'facet'             => [
						'sanitize_callback' => 'sanitize_text_field',
					],
					'orderby'           => [
						'sanitize_callback' => 'sanitize_text_field',
					],
					'order'             => [
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
	public function check_facets_rest_permission() {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return new \WP_Error( 'ep_rest_forbidden', esc_html__( 'Sorry, you cannot view this resource.', 'elasticpress' ), array( 'status' => 401 ) );
		}

		return true;
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
	 */
	public function render_block( $attributes ) {
		$attributes = $this->parse_attributes( $attributes );

		$renderer_class = apply_filters( 'ep_facet_renderer_class', __NAMESPACE__ . '\Renderer', 'post-type', 'block', $attributes );
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
		global $wp_query;

		add_filter( 'ep_is_facetable', '__return_true' );

		$search = Features::factory()->get_registered_feature( 'search' );

		$attributes = $this->parse_attributes(
			[
				'searchPlaceholder' => $request->get_param( 'searchPlaceholder' ),
				'displayCount'      => $request->get_param( 'displayCount' ),
				'facet'             => $request->get_param( 'facet' ),
				'orderby'           => $request->get_param( 'orderby' ),
				'order'             => $request->get_param( 'order' ),
			]
		);

		$args = [
			'post_type'      => $search->get_searchable_post_types(),
			'posts_per_page' => 1,
		];

		$wp_query->query( $args );

		/** This filter is documented in includes/classes/Feature/Facets/Types/Taxonomy/Block.php */
		$renderer_class = apply_filters( 'ep_facet_renderer_class', __NAMESPACE__ . '\Renderer', 'post-type', 'block', $attributes );
		$renderer       = new $renderer_class();

		ob_start();
		$renderer->render( [], $attributes );
		$block_content = ob_get_clean();

		if ( empty( $block_content ) ) {
			if ( empty( $attributes['facet'] ) ) {
				return esc_html__( 'Preview not available', 'elasticpress' );
			}

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
	 * Utilitary method to set default attributes.
	 *
	 * @param array $attributes Attributes passed
	 * @return array
	 */
	protected function parse_attributes( $attributes ) {
		$attributes = wp_parse_args(
			$attributes,
			[
				'searchPlaceholder' => esc_html_x( 'Search', 'Facet by meta search placeholder', 'elasticpress' ),
				'facet'             => '',
				'displayCount'      => false,
				'orderby'           => 'count',
				'order'             => 'desc',
			]
		);

		return $attributes;
	}
}
