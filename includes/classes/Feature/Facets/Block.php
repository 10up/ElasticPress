<?php
/**
 * Abstract Facet Block class.
 *
 * @since 4.7.0
 * @package elasticpress
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
	 */
	abstract public function register_block();

	/**
	 * Setup REST endpoints for facet feature.
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
	 * Check if the current user has permission to view the facets REST endpoint.
	 *
	 * @return true|\WP_Error
	 */
	public function check_facets_rest_permission() {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return new \WP_Error( 'ep_rest_forbidden', esc_html__( 'Sorry, you cannot view this resource.', 'elasticpress' ), array( 'status' => 401 ) );
		}
		return true;
	}
}
