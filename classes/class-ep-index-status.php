<?php
/**
 * Displays index status of current content.
 *
 * @package elasticpress
 *
 * @since   1.7
 *
 * @author  Chris Wiegman <chris.wiegman@10up.com>
 */

/**
 * Add index status to individual content
 *
 * Adds a status icon showing the current index status for the displayed content
 * on the content's edit page.
 */
class EP_Index_Status {

	/**
	 * Setup the Index Status
	 *
	 * Wires actions and filters for the index status indicators.
	 *
	 * @since 1.7
	 *
	 * @return EP_Index_Status
	 */
	public function __construct() {

		add_action( 'post_submitbox_misc_actions', array( $this, 'post_submitbox_misc_actions' ) );

	}

	/**
	 * Lists index status
	 *
	 * Shows the index status of individual posts.
	 *
	 * @since 1.7
	 *
	 * @return void
	 */
	public function post_submitbox_misc_actions() {

		global $post;

		$post_types = ep_get_indexable_post_types();

		if ( in_array( $post->post_type, $post_types ) && ep_is_activated() && ep_index_exists() ) {

			$post_status = ep_get_post( $post->ID );
			$fill        = 'red';

			if ( $post_status ) {
				$fill = '#90EE90';
			}

			echo '<div id="ep" class="misc-pub-section">';
			echo '<label for="index_status">' . esc_html__( 'Index Status', 'elasticpress' ) . '</label> ';
			echo '<svg height="15" width="15"><circle cx="8" cy="8" r="7" stroke="grey" stroke-width="1" fill="' . esc_attr( $fill ) . '" /></svg>';
			echo '</div>';

		}
	}
}
