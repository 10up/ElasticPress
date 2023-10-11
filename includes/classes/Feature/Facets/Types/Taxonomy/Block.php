<?php
/**
 * Facets block
 *
 * @since 4.2.0
 * @package elasticpress
 */

namespace ElasticPress\Feature\Facets\Types\Taxonomy;

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
			'facets/taxonomies',
			[
				'methods'             => 'GET',
				'permission_callback' => [ $this, 'check_facets_rest_permission' ],
				'callback'            => [ $this, 'get_rest_facetable_taxonomies' ],
			]
		);
	}

	/**
	 * DEPRECATED Check permissions of the /facets/taxonomies and facets/block-preview REST endpoints.
	 *
	 * @deprecated 4.7.0
	 * @return WP_Error|true
	 */
	public function check_facets_taxonomies_rest_permission() {
		_deprecated_function( __FUNCTION__, '4.7.0', '$this->check_facets_rest_permission()' );

		return $this->check_facets_rest_permission();
	}

	/**
	 * Check permissions of the /facets/taxonomies and facets/block-preview REST endpoints.
	 *
	 * @return true|\WP_Error
	 */
	public function check_facets_rest_permission() {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error( 'ep_rest_forbidden', esc_html__( 'Sorry, you cannot view this resource.', 'elasticpress' ), array( 'status' => 401 ) );
		}

		return true;
	}

	/**
	 * Return an array of taxonomies, their name, plural label, and a sample of terms.
	 *
	 * @return array
	 */
	public function get_rest_facetable_taxonomies() {
		$taxonomies_raw = Features::factory()->get_registered_feature( 'facets' )->types['taxonomy']->get_facetable_taxonomies();

		$taxonomies = [];
		foreach ( $taxonomies_raw as $slug => $taxonomy ) {
			$terms_sample = get_terms(
				[
					'taxonomy' => $slug,
					'number'   => 20,
				]
			);
			if ( is_array( $terms_sample ) ) {
				// This way we make sure it will be an array in the outputted JSON.
				$terms_sample = array_values( $terms_sample );
			} else {
				$terms_sample = [];
			}

			$taxonomies[ $slug ] = [
				'label'  => $taxonomy->labels->singular_name,
				'plural' => $taxonomy->labels->name,
				'terms'  => $terms_sample,
			];
		}

		return $taxonomies;
	}

	/**
	 * Register the block.
	 *
	 * @return void
	 */
	public function register_block() {
		register_block_type_from_metadata(
			EP_PATH . 'assets/js/blocks/facets/taxonomy',
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
			'ep-facets-block-script',
			EP_URL . 'dist/js/facets-block-script.js',
			Utils\get_asset_info( 'facets-block-script', 'dependencies' ),
			Utils\get_asset_info( 'facets-block-script', 'version' ),
			true
		);

		wp_set_script_translations( 'ep-facets-block-script', 'elasticpress' );
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

			$search = Features::factory()->get_registered_feature( 'search' );

			$wp_query->query(
				[
					'posts_per_page' => 1,
					'post_type'      => $search->get_searchable_post_types(),
				]
			);
		}

		/**
		 * Filter the class name to be used to render the Facet.
		 *
		 * @since 4.3.0
		 * @hook ep_facet_renderer_class
		 * @param {string} $classname  The name of the class to be instantiated and used as a renderer.
		 * @param {string} $facet_type The type of the facet.
		 * @param {string} $context    Context where the renderer will be used: `block` or `widget`, for example.
		 * @param {array} $attributes Element attributes.
		 * @return {string} The name of the class
		 */
		$renderer_class = apply_filters( 'ep_facet_renderer_class', __NAMESPACE__ . '\Renderer', 'taxonomy', 'block', $attributes );
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
		_deprecated_function( __METHOD__, '4.7.0', '\ElasticPress\Feature\Facets\Types\Taxonomy\render_block()' );

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
}
