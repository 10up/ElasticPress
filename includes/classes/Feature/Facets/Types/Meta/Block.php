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
class Block {
	/**
	 * Hook block funcionality.
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
			'facets/meta/keys',
			[
				'methods'             => 'GET',
				'permission_callback' => [ $this, 'check_facets_meta_rest_permission' ],
				'callback'            => [ $this, 'get_rest_registered_metakeys' ],
			]
		);
		register_rest_route(
			'elasticpress/v1',
			'facets/meta/block-preview',
			[
				'methods'             => 'GET',
				'permission_callback' => [ $this, 'check_facets_meta_rest_permission' ],
				'callback'            => [ $this, 'render_block_preview' ],
				'args'                => [
					'searchPlaceholder' => [
						'sanitize_callback' => 'sanitize_text_field',
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
	public function check_facets_meta_rest_permission() {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return new \WP_Error( 'ep_rest_forbidden', esc_html__( 'Sorry, you cannot view this resource.', 'elasticpress' ), array( 'status' => 401 ) );
		}

		return true;
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
		/**
		 * Registering it here so translation works
		 *
		 * @see https://core.trac.wordpress.org/ticket/54797#comment:20
		 */
		wp_register_script(
			'ep-facets-meta-block-script',
			EP_URL . 'dist/js/facets-meta-block-script.js',
			Utils\get_asset_info( 'facets-meta-block-script', 'dependencies' ),
			Utils\get_asset_info( 'facets-meta-block-script', 'version' ),
			true
		);

		wp_set_script_translations( 'ep-facets-meta-block-script', 'elasticpress' );

		register_block_type_from_metadata(
			EP_PATH . 'assets/js/blocks/facets/meta',
			[
				'render_callback' => [ $this, 'render_block' ],
			]
		);

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			$sync_url = admin_url( 'network/admin.php?page=elasticpress-sync' );
		} else {
			$sync_url = admin_url( 'admin.php?page=elasticpress-sync' );
		}

		wp_localize_script( 'ep-facets-meta-block-script', 'facetMetaBlock', [ 'syncUrl' => $sync_url ] );
	}

	/**
	 * Render the block.
	 *
	 * @param array $attributes Block attributes.
	 */
	public function render_block( $attributes ) {
		$attributes = $this->parse_attributes( $attributes );

		/** This filter is documented in includes/classes/Feature/Facets/Types/Taxonomy/Block.php */
		$renderer_class = apply_filters( 'ep_facet_renderer_class', __NAMESPACE__ . '\Renderer', 'meta', 'block', $attributes );
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
				'facet'             => $request->get_param( 'facet' ),
				'orderby'           => $request->get_param( 'orderby' ),
				'order'             => $request->get_param( 'order' ),
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

		/** This filter is documented in includes/classes/Feature/Facets/Types/Taxonomy/Block.php */
		$renderer_class = apply_filters( 'ep_facet_renderer_class', __NAMESPACE__ . '\Renderer', 'meta', 'block', $attributes );
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
				'orderby'           => 'count',
				'order'             => 'desc',

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
