<?php
/**
 * Facets block
 *
 * @since 4.2.0
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
		add_action( 'rest_api_init', [ $this, 'setup_endpoints' ] );

		$this->renderer = new Renderer();
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
				'permission_callback' => [ $this, 'check_facets_taxonomies_rest_permission' ],
				'callback'            => [ $this, 'get_rest_facetable_taxonomies' ],
			]
		);
		register_rest_route(
			'elasticpress/v1',
			'facets/block-preview',
			[
				'methods'             => 'GET',
				'permission_callback' => [ $this, 'check_facets_taxonomies_rest_permission' ],
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
	public function check_facets_taxonomies_rest_permission() {
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
		$taxonomies_raw = Features::factory()->get_registered_feature( 'facets' )->get_facetable_taxonomies();

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
				'label'  => $taxonomy->label,
				'plural' => $taxonomy->labels->name,
				'terms'  => $terms_sample,
			];
		}

		return $taxonomies;
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

		$wp_query = new \WP_Query(
			[
				'post_type' => $search->get_searchable_post_types(),
				'per_page'  => 1,
			]
		);

		$attributes = $this->parse_attributes(
			[
				'facet'   => $request->get_param( 'facet' ),
				'orderby' => $request->get_param( 'orderby' ),
				'order'   => $request->get_param( 'order' ),
			]
		);

		ob_start();
		$this->renderer->render( [], $attributes );
		$block_content = ob_get_clean();

		if ( empty( $block_content ) ) {
			$taxonomy = get_taxonomy( $attributes['facet'] );
			if ( ! $taxonomy ) {
				return esc_html__( 'Invalid taxonomy selected.', 'elasticpress' );
			}
			return sprintf(
				/* translators: Taxonomy name */
				esc_html__( 'Term preview for %s not available', 'elasticpress' ),
				esc_html( $taxonomy->labels->name )
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
		if ( empty( $attributes['facet'] ) ) {
			$taxonomies = Features::factory()->get_registered_feature( 'facets' )->get_facetable_taxonomies();
			if ( ! empty( $taxonomies ) ) {
				$attributes['facet'] = key( $taxonomies );
			}
		}
		return $attributes;
	}
}
