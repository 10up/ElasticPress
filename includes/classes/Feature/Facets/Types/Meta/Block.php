<?php
/**
 * Facets block
 *
 * @since 4.2.0
 * @package elasticpress
 */

namespace ElasticPress\Feature\Facets\Types\Meta;

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
		add_action( 'rest_api_init', [ $this, 'setup_endpoints' ] );

		/** This filter is documented in includes/classes/Feature/Facets/Types/Taxonomy/Block.php */
		$renderer_class = apply_filters( 'ep_facet_renderer_class', __NAMESPACE__ . '\Renderer', 'meta', 'block' );

		$this->renderer = new $renderer_class();
	}

	/**
	 * Setup REST endpoints for the feature.
	 */
	public function setup_endpoints() {
		register_rest_route(
			'elasticpress/v1',
			'facets/block-meta-preview',
			[
				'methods'             => 'GET',
				'permission_callback' => [ $this, 'check_facets_meta_rest_permission' ],
				'callback'            => [ $this, 'render_block_preview' ],
				'args'                => [
					'facet'   => [
						'sanitize_callback' => 'sanitize_text_field',
					],
					'orderby' => [
						'sanitize_callback' => 'sanitize_text_field',
					],
					'order'   => [
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
		if ( ! is_user_logged_in() ) {
			return new \WP_Error( 'ep_rest_forbidden', esc_html__( 'Sorry, you cannot view this resource.', 'elasticpress' ), array( 'status' => 401 ) );
		}

		return true;
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
	 * Render the block.
	 *
	 * @param array $attributes Block attributes.
	 */
	public function render_block( $attributes ) {
		$attributes = $this->parse_attributes( $attributes );
		ob_start();
		?>
		<div class="wp-block-elasticpress-facet">
			<?php $this->renderer->render( [], $attributes ); ?>
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
				'facet'   => $request->get_param( 'facet' ),
				'orderby' => $request->get_param( 'orderby' ),
				'order'   => $request->get_param( 'order' ),
			]
		);

		add_filter(
			'ep_facet_meta_fields',
			function ( $meta_fields ) use ( $attributes ) {
				$meta_fields = [ $attributes['facet'] ];
				return $meta_fields;
			}
		);

		$wp_query = new \WP_Query(
			[
				'post_type' => $search->get_searchable_post_types(),
				'per_page'  => 1,
			]
		);

		ob_start();
		$this->renderer->render( [], $attributes );
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
	 * Utilitary method to set default attributes.
	 *
	 * @param array $attributes Attributes passed
	 * @return array
	 */
	protected function parse_attributes( $attributes ) {
		$attributes = wp_parse_args(
			$attributes,
			[
				'facet'   => '',
				'orderby' => 'count',
				'order'   => 'desc',

			]
		);
		return $attributes;
	}
}
