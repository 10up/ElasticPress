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
	 * Render the block.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	abstract public function render_block( $attributes );

	/**
	 * Check if facets should be enabled in the editor.
	 *
	 * @since 5.3.0
	 * @return boolean
	 */
	public function is_facet_enabled_in_editor(): bool {
		global $pagenow;

		$in_editor = in_array( $pagenow, [ 'post-new.php', 'post.php' ], true );

		/**
		 * Filter if facet should be enabled in the editor. Default: false
		 *
		 * @hook  ep_facet_enabled_in_editor
		 * @since 5.1.0
		 * @param {bool}  $enabled
		 * @return {bool} If enabled or not
		 */
		return ! ( $in_editor && ! apply_filters( 'ep_facet_enabled_in_editor', false ) );
	}
}
