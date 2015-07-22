<?php

 if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

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
		add_action( 'wp_insert_post', array( $this, 'action_sync_on_update' ), 999, 3 );
		add_action( 'add_attachment', array( $this, 'action_sync_on_update' ), 999, 3 );
		add_action( 'edit_attachment', array( $this, 'action_sync_on_update' ), 999, 3 );
		add_action( 'delete_post', array( $this, 'action_delete_post' ) );
		add_action( 'delete_blog', array( $this, 'action_delete_blog_from_index') );
		add_action( 'archive_blog', array( $this, 'action_delete_blog_from_index') );
		add_action( 'deactivate_blog', array( $this, 'action_delete_blog_from_index') );
	}
	
	/**
	 * Remove actions and filters
	 *
	 * @since 1.4
	 */
	public function destroy() {
		remove_action( 'wp_insert_post', array( $this, 'action_sync_on_update' ), 999, 3 );
		remove_action( 'add_attachment', array( $this, 'action_sync_on_update' ), 999, 3 );
		remove_action( 'edit_attachment', array( $this, 'action_sync_on_update' ), 999, 3 );
		remove_action( 'delete_post', array( $this, 'action_delete_post' ) );
		remove_action( 'delete_blog', array( $this, 'action_delete_blog_from_index') );
		remove_action( 'archive_blog', array( $this, 'action_delete_blog_from_index') );
		remove_action( 'deactivate_blog', array( $this, 'action_delete_blog_from_index') );
	}

	public function action_delete_blog_from_index( $blog_id ) {
		if ( ep_index_exists( ep_get_index_name( $blog_id ) ) && ! apply_filters( 'ep_keep_index', false ) ) {
			ep_delete_index( ep_get_index_name( $blog_id ) );
		}
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
	 * @param $post_ID
	 * @since 0.1.0
	 */
	public function action_sync_on_update( $post_ID ) {
		global $importer;

		// If we have an importer we must be doing an import - let's abort
		if ( ! empty( $importer ) ) {
			return;
		}

		$indexable_post_statuses = ep_get_indexable_post_status();
		$post_type               = get_post_type( $post_ID );

		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_ID ) || 'revision' === $post_type ) {
			return;
		}

		$post = get_post( $post_ID );

		// Our post was published, but is no longer, so let's remove it from the Elasticsearch index
		if ( ! in_array( $post->post_status, $indexable_post_statuses ) ) {
			$this->action_delete_post( $post_ID );
		} else {
			$post_type = get_post_type( $post_ID );

			$indexable_post_types = ep_get_indexable_post_types();

			if ( in_array( $post_type, $indexable_post_types ) ) {

				do_action( 'ep_sync_on_transition', $post_ID );

				$this->sync_post( $post_ID);
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

		$post_args = ep_prepare_post( $post_id );

		if ( apply_filters( 'ep_post_sync_kill', false, $post_args, $post_id ) ) {
			return;
		}

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
