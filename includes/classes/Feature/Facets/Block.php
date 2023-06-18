<?php
/**
 * Abstract Facet Block class.
 *
 * @since  4.7
 * @package  elasticpress
 */

namespace ElasticPress\Feature\Facets;

/**
 * Abstract Facet Block class.
 */
abstract class Block {

	/**
	 * Setup hooks and filters for facet block.
	 */
	abstract public function setup();

	/**
	 * Register facet block.
	 *
	 * @return mixed
	 */
	abstract public function register_block();

	/**
	 * Setup REST endpoints for ƒacet feature.
	 */
	abstract public function setup_endpoints();

	/**
	 * Render the block.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	abstract  public function render_block( $attributes );

	/**
	 * Outputs the block preview
	 *
	 * @param \WP_REST_Request $request REST request
	 * @return string
	 */
	abstract public function render_block_preview( $request );

	/**
	 * Get the facet block name.
	 *
	 * @return string|\WP_Error The block name.
	 */
	public function check_facets_rest_permission() {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return new \WP_Error( 'ep_rest_forbidden', esc_html__( 'Sorry, you cannot view this resource.', 'elasticpress' ), array( 'status' => 401 ) );
		}
		return true;
	}
}
