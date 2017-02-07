<?php
/**
 * Manage syncing of content between WP and Elasticsearch
 *
 * @since  1.0
 * @package elasticpress
 */

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
	 * Save posts for indexing later
	 * 
	 * @since  2.0
	 * @var    array
	 */
	public $sync_post_queue = array();

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
		add_action( 'make_spam_blog', array( $this, 'action_delete_blog_from_index') );
		add_action( 'archive_blog', array( $this, 'action_delete_blog_from_index') );
		add_action( 'deactivate_blog', array( $this, 'action_delete_blog_from_index') );
		add_action( 'updated_postmeta', array( $this, 'action_queue_meta_sync' ), 10, 4 );
		add_action( 'added_post_meta', array( $this, 'action_queue_meta_sync' ), 10, 4 );
		add_action( 'shutdown', array( $this, 'action_index_sync_queue' ) );
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
		remove_action( 'make_spam_blog', array( $this, 'action_delete_blog_from_index') );
		remove_action( 'archive_blog', array( $this, 'action_delete_blog_from_index') );
		remove_action( 'deactivate_blog', array( $this, 'action_delete_blog_from_index') );
		remove_action( 'updated_postmeta', array( $this, 'action_queue_meta_sync' ), 10, 4 );
		remove_action( 'added_post_meta', array( $this, 'action_queue_meta_sync' ), 10, 4 );
		remove_action( 'shutdown', array( $this, 'action_index_sync_queue' ) );
	}

	/**
	 * Sync queued posts on shutdown. We do this in case a post is updated multiple times.
	 *
	 * @since  2.0
	 */
	public function action_index_sync_queue() {
		if ( empty( $this->sync_post_queue ) ) {
			return;
		}

		foreach ( $this->sync_post_queue as $post_id => $value ) {
			do_action( 'ep_sync_on_meta_update', $post_id );

			$this->sync_post( $post_id, false );
		}
	}

	/**
	 * When whitelisted meta is updated, queue the post for reindex
	 * 
	 * @param  int $meta_id
	 * @param  int $object_id
	 * @param  string $meta_key
	 * @param  string $meta_value
	 * @since  2.0
	 */
	public function action_queue_meta_sync( $meta_id, $object_id, $meta_key, $meta_value ) {
		global $importer;

		if ( ! ep_get_elasticsearch_version() ) {
			return;
		}

		// If we have an importer we must be doing an import - let's abort
		if ( ! empty( $importer ) ) {
			return;
		}
		
		$indexable_post_statuses = ep_get_indexable_post_status();
		$post_type               = get_post_type( $object_id );

		// Allow inherit as post status if post type is attachment
		if ( $post_type === 'attachment' ) {
			$indexable_post_statuses[] = 'inherit';
		}

		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || 'revision' === $post_type ) {
			// Bypass saving if doing autosave or post type is revision
			return;
		}

		$post = get_post( $object_id );

		// If the post is an auto-draft - let's abort.
		if ( 'auto-draft' == $post->post_status ) {
			return;
		}

		if ( in_array( $post->post_status, $indexable_post_statuses ) ) {
			$indexable_post_types = ep_get_indexable_post_types();

			if ( in_array( $post_type, $indexable_post_types ) ) {

				// Using this function to hook in after all the meta applicable filters
				$prepared_post = ep_prepare_post( $object_id );

				// Make sure meta key that was changed is actually relevant
				if ( ! isset( $prepared_post['meta'][$meta_key] ) ) {
					return;
				}

				$this->sync_post_queue[$object_id] = true;
			}
		}
	}

	/**
	 * Remove blog from index when a site is deleted, archived, or deactivated
	 *
	 * @param $blog_id
	 */
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

		if ( ( ! current_user_can( 'edit_post', $post_id ) && ! apply_filters( 'ep_sync_delete_permissions_bypass', false, $post_id ) ) || 'revision' === get_post_type( $post_id ) ) {
			return;
		}

		do_action( 'ep_delete_post', $post_id );

		ep_delete_post( $post_id, false );
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
		
		if ( 'attachment' === $post_type ) {
			$indexable_post_statuses[] = 'inherit';
		}

		$post_type               = get_post_type( $post_ID );

		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || 'revision' === $post_type ) {
			// Bypass saving if doing autosave or post type is revision
			return;
		}

		if ( ! apply_filters( 'ep_sync_insert_permissions_bypass', false, $post_ID ) ) {
			if ( ! current_user_can( 'edit_post', $post_ID ) && ( ! defined( 'DOING_CRON' ) || ! DOING_CRON ) ) {
				// Bypass saving if user does not have access to edit post and we're not in a cron process
				return;
			}
		}

		$post = get_post( $post_ID );

		// If the post is an auto-draft - let's abort.
		if ( 'auto-draft' == $post->post_status ) {
			return;
		}

		// Our post was published, but is no longer, so let's remove it from the Elasticsearch index
		if ( ! in_array( $post->post_status, $indexable_post_statuses ) ) {
			$this->action_delete_post( $post_ID );
		} else {
			$post_type = get_post_type( $post_ID );

			$indexable_post_types = ep_get_indexable_post_types();

			if ( in_array( $post_type, $indexable_post_types ) ) {

				do_action( 'ep_sync_on_transition', $post_ID );

				$this->sync_post( $post_ID, false );
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
	 * @param bool $blocking
	 * @since 0.1.0
	 * @return bool|array
	 */
	public function sync_post( $post_id, $blocking = true ) {

		$post = get_post( $post_id );

		if ( empty( $post ) ) {
			return false;
		}

		$post_args = ep_prepare_post( $post_id );

		if ( apply_filters( 'ep_post_sync_kill', false, $post_args, $post_id ) ) {
			return false;
		}

		$response = ep_index_post( $post_args, $blocking );

		return $response;
	}
}

$ep_sync_manager = EP_Sync_Manager::factory();

/**
 * Accessor functions for methods in above class. See doc blocks above for function details.
 */

function ep_sync_post( $post_id, $blocking = true ) {
	return EP_Sync_Manager::factory()->sync_post( $post_id, $blocking );
}
