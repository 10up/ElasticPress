<?php

class EP_Sync_Manager {

	/**
	 * Placeholder method
	 *
	 * @since 0.1.0
	 */
	public function __construct() { }

	/**
	 * Setup actions and filters
	 *
	 * @since 0.1.2
	 */
	public function setup() {
		add_action( 'transition_post_status', array( $this, 'action_sync_on_transition' ), 10, 3 );
		add_action( 'delete_post', array( $this, 'action_delete_post' ) );
	}

	/**
	 * Delete ES post when WP post is deleted
	 *
	 * @param int $post_id
	 * @since 0.1.0
	 */
	public function action_delete_post( $post_id ) {

		if ( ! current_user_can( 'edit_post', $post_id ) || 'revision' === get_post_type( $post_id ) ) {
			return;
		}

		do_action( 'ep_delete_post', $post_id );

		ep_delete_post( $post_id );
	}

	/**
	 * Sync ES index with what happened to the post being saved
	 *
	 * @param string $new_status
	 * @param string $old_status
	 * @param object $post
	 * @since 0.1.0
	 */
	public function action_sync_on_transition( $new_status, $old_status, $post ) {
		global $importer;

		// If we have an importer we must be doing an import - let's abort
		if ( ! empty( $importer ) ) {
			return;
		}

		if ( 'publish' !== $new_status && 'publish' !== $old_status ) {
			return;
		}

		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post->ID ) || 'revision' === get_post_type( $post->ID ) ) {
			return;
		}

		// Our post was published, but is no longer, so let's remove it from the Elasticsearch index
		if ( 'publish' !== $new_status ) {
			$this->action_delete_post( $post->ID );
		} else {
			$post_type = get_post_type( $post->ID );

			$indexable_post_types = ep_get_indexable_post_types();

			if ( in_array( $post_type, $indexable_post_types ) ) {

				do_action( 'ep_sync_on_transition', $post->ID );

				$this->sync_post( $post->ID );
			}
		}
	}

	/**
	 * Return a singleton instance of the current class
	 *
	 * @since 0.1.0
	 * @return EP_Sync_Manager
	 */
	public static function factory() {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new self();
			$instance->setup();
		}

		return $instance;
	}

	/**
	 * Sync a post for a specific site or globally.
	 *
	 * @param int $post_id
	 * @since 0.1.0
	 * @return bool|array
	 */
	public function sync_post( $post_id ) {

		$post_args = apply_filters( 'ep_post_sync_args', ep_prepare_post( $post_id ), $post_id );

		$response = ep_index_post( $post_args );

		return $response;
	}
}

$ep_sync_manager = EP_Sync_Manager::factory();

/**
 * Accessor functions for methods in above class. See doc blocks above for function details.
 */

function ep_sync_post( $post_id ) {
	return EP_Sync_Manager::factory()->sync_post( $post_id );
}