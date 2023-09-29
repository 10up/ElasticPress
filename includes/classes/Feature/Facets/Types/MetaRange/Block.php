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
	 * Hook block funcionality.
	 */
	public function setup() {
		add_action( 'init', [ $this, 'register_block' ] );
		add_action( 'rest_api_init', [ $this, 'setup_endpoints' ] );
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
	 * Setup REST endpoints for the feature.
	 *
	 * @return void
	 */
	public function setup_endpoints() {
		register_rest_route(
			'elasticpress/v1',
			'facets/meta-range/keys',
			[
				'methods'             => 'GET',
				'permission_callback' => [ $this, 'check_facets_rest_permission' ],
				'callback'            => [ $this, 'get_rest_registered_metakeys' ],
			]
		);

		register_rest_route(
			'elasticpress/v1',
			'facets/meta-range/block-preview',
			[
				'methods'             => 'GET',
				'permission_callback' => [ $this, 'check_facets_rest_permission' ],
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
	 * DEPRECATED. Check permissions of the /facets/meta-range/* REST endpoints.
	 *
	 * @deprecated 4.7.0
	 * @return WP_Error|true
	 */
	public function check_facets_meta_rest_permission() {
		_deprecated_function( __FUNCTION__, '4.7.0', '$this->check_facets_rest_permission()' );

		return $this->check_facets_rest_permission();
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


	/**
	 * Outputs the block preview
	 *
	 * @param \WP_REST_Request $request REST request
	 * @return string
	 */
	public function render_block_preview( $request ) {
		global $wp_query;

		add_filter( 'ep_is_facetable', '__return_true' );

		$search = \ElasticPress\Features::factory()->get_registered_feature( 'search' );
		$facets = \ElasticPress\Features::factory()->get_registered_feature( 'facets' );

		$facet = $request->get_param( 'facet' );

		add_filter(
			'ep_facet_meta_range_fields',
			function ( $meta_fields ) use ( $facet ) {
				$meta_fields = [ $facet ];

				return $meta_fields;
			}
		);

		$args = [
			'post_type'      => $search->get_searchable_post_types(),
			'posts_per_page' => 1,
		];
		$wp_query->query( $args );

		$min_field_name = $facets->types['meta-range']->get_filter_name() . $facet . '_min';
		$max_field_name = $facets->types['meta-range']->get_filter_name() . $facet . '_max';

		if ( empty( $GLOBALS['ep_facet_aggs'][ $min_field_name ] ) || empty( $GLOBALS['ep_facet_aggs'][ $max_field_name ] ) ) {
			return wp_send_json_error();
		}

		$min = $GLOBALS['ep_facet_aggs'][ $min_field_name ];
		$max = $GLOBALS['ep_facet_aggs'][ $max_field_name ];

		return wp_send_json_success(
			[
				'min' => $min,
				'max' => $max,
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
}
