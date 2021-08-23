<?php
/**
 * Manage syncing of content between WP and Elasticsearch for posts
 *
 * @since  1.0
 * @package elasticpress
 */

namespace ElasticPress\Indexable\Post;

use ElasticPress\Elasticsearch as Elasticsearch;
use ElasticPress\Indexables as Indexables;
use ElasticPress\SyncManager as SyncManagerAbstract;

if ( ! defined( 'ABSPATH' ) ) {
	// @codeCoverageIgnoreStart
	exit; // Exit if accessed directly.
	// @codeCoverageIgnoreEnd
}

/**
 * Sync manager class
 */
class SyncManager extends SyncManagerAbstract {

	/**
	 * Indexable slug
	 *
	 * @since  3.0
	 * @var    string
	 */
	public $indexable_slug = 'post';

	/**
	 * Setup actions and filters
	 *
	 * @since 0.1.2
	 */
	public function setup() {
		if ( ! Elasticsearch::factory()->get_elasticsearch_version() ) {
			return;
		}

		if ( ! $this->can_index_site() ) {
			return;
		}

		add_action( 'wp_insert_post', array( $this, 'action_sync_on_update' ), 999, 3 );
		add_action( 'add_attachment', array( $this, 'action_sync_on_update' ), 999, 3 );
		add_action( 'edit_attachment', array( $this, 'action_sync_on_update' ), 999, 3 );
		add_action( 'delete_post', array( $this, 'action_delete_post' ) );
		add_action( 'updated_post_meta', array( $this, 'action_queue_meta_sync' ), 10, 4 );
		add_action( 'added_post_meta', array( $this, 'action_queue_meta_sync' ), 10, 4 );
		add_action( 'deleted_post_meta', array( $this, 'action_queue_meta_sync' ), 10, 4 );
		add_action( 'wp_initialize_site', array( $this, 'action_create_blog_index' ) );

		add_filter( 'ep_sync_insert_permissions_bypass', array( $this, 'filter_bypass_permission_checks_for_machines' ) );
		add_filter( 'ep_sync_delete_permissions_bypass', array( $this, 'filter_bypass_permission_checks_for_machines' ) );
	}

	/**
	 * Filter to allow cron and WP CLI processes to index/delete documents
	 *
	 * @param  boolean $bypass The current filtered value
	 * @return boolean Boolean indicating if permission checking should be bypased or not
	 * @since  3.6.0
	 */
	public function filter_bypass_permission_checks_for_machines( $bypass ) {
		// Allow index/delete during cron
		if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			return true;
		}

		// Allow index/delete during WP CLI commands
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return true;
		}

		return $bypass;
	}

	/**
	 * When whitelisted meta is updated, queue the post for reindex
	 *
	 * @param  int|array $meta_id Meta id.
	 * @param  int       $object_id Object id.
	 * @param  string    $meta_key Meta key.
	 * @param  string    $meta_value Meta value.
	 * @since  2.0
	 */
	public function action_queue_meta_sync( $meta_id, $object_id, $meta_key, $meta_value ) {
		if ( $this->kill_sync() ) {
			return;
		}

		$indexable = Indexables::factory()->get( $this->indexable_slug );

		$indexable_post_statuses = $indexable->get_indexable_post_status();
		$post_type               = get_post_type( $object_id );

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			// Bypass saving if doing autosave
			// @codeCoverageIgnoreStart
			return;
			// @codeCoverageIgnoreEnd
		}

		$post = get_post( $object_id );

		/**
		 * Filter to allow skipping a sync triggered by meta changes
		 *
		 * @hook ep_skip_post_meta_sync
		 * @param {bool} $skip True means kill sync for post
		 * @param {WP_Post} $post The post that's attempting to be synced
		 * @param {int} $meta_id ID of the meta that triggered the sync
		 * @param {string} $meta_key The key of the meta that triggered the sync
		 * @param {string} $meta_value The value of the meta that triggered the sync
		 * @return {boolean} New value
		 */
		if ( apply_filters( 'ep_skip_post_meta_sync', false, $post, $meta_id, $meta_key, $meta_value ) ) {
			return;
		}

		$allowed_meta_to_be_indexed = $indexable->prepare_meta( $post );
		if ( ! in_array( $meta_key, array_keys( $allowed_meta_to_be_indexed ), true ) ) {
			return;
		}

		if ( in_array( $post->post_status, $indexable_post_statuses, true ) ) {
			$indexable_post_types = $indexable->get_indexable_post_types();

			if ( in_array( $post_type, $indexable_post_types, true ) ) {
				/**
				 * Filter to kill post sync
				 *
				 * @hook ep_post_sync_kill
				 * @param {bool} $skip True meanas kill sync for post
				 * @param  {int} $object_id ID of post
				 * @param  {int} $object_id ID of post
				 * @return {boolean} New value
				 */
				if ( apply_filters( 'ep_post_sync_kill', false, $object_id, $object_id ) ) {
					return;
				}

				$this->add_to_queue( $object_id );
			}
		}
	}


	/**
	 * Delete ES post when WP post is deleted
	 *
	 * @param int $post_id Post id.
	 * @since 0.1.0
	 */
	public function action_delete_post( $post_id ) {
		if ( $this->kill_sync() ) {
			return;
		}

		/**
		 * Filter whether to skip the permissions check on deleting a post
		 *
		 * @hook ep_sync_delete_permissions_bypass
		 * @param  {bool} $bypass True to bypass
		 * @param  {int} $post_id ID of post
		 * @return {boolean} New value
		 */
		if ( ! current_user_can( 'edit_post', $post_id ) && ! apply_filters( 'ep_sync_delete_permissions_bypass', false, $post_id ) ) {
			return;
		}

		$indexable = Indexables::factory()->get( $this->indexable_slug );
		$post_type = get_post_type( $post_id );

		$indexable_post_types = $indexable->get_indexable_post_types();

		if ( ! in_array( $post_type, $indexable_post_types, true ) ) {
			// If not an indexable post type, skip delete.
			return;
		}

		Indexables::factory()->get( $this->indexable_slug )->delete( $post_id, false );

		/**
		 * Make sure to reset sync queue in case an shutdown happens before a redirect
		 * when a redirect has already been triggered.
		 */
		$this->sync_queue = [];
	}

	/**
	 * Sync ES index with what happened to the post being saved
	 *
	 * @param int $post_id Post id.
	 * @since 0.1.0
	 */
	public function action_sync_on_update( $post_id ) {
		if ( $this->kill_sync() ) {
			return;
		}

		$indexable = Indexables::factory()->get( $this->indexable_slug );
		$post_type = get_post_type( $post_id );

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			// Bypass saving if doing autosave
			// @codeCoverageIgnoreStart
			return;
			// @codeCoverageIgnoreEnd
		}

		/**
		 * Filter whether to skip the permissions check on updating a post
		 *
		 * @hook ep_sync_insert_permissions_bypass
		 * @param  {bool} $bypass True to bypass
		 * @param  {int} $post_id ID of post
		 * @return {boolean} New value
		 */
		if ( ! current_user_can( 'edit_post', $post_id ) && ! apply_filters( 'ep_sync_insert_permissions_bypass', false, $post_id ) ) {
			return;
		}

		$post = get_post( $post_id );

		$indexable_post_statuses = $indexable->get_indexable_post_status();

		// Our post was published, but is no longer, so let's remove it from the Elasticsearch index.
		if ( ! in_array( $post->post_status, $indexable_post_statuses, true ) ) {
			$this->action_delete_post( $post_id );
		} else {
			$indexable_post_types = $indexable->get_indexable_post_types();

			if ( in_array( $post_type, $indexable_post_types, true ) ) {
				/**
				 * Fire before post is queued for syncing
				 *
				 * @hook ep_sync_on_transition
				 * @param  {int} $post_id ID of post
				 */
				do_action( 'ep_sync_on_transition', $post_id );

				/**
				 * Filter to kill post sync
				 *
				 * @hook ep_post_sync_kill
				 * @param {bool} $skip True means kill sync for post
				 * @param  {int} $object_id ID of post
				 * @param  {int} $object_id ID of post
				 * @return {boolean} New value
				 */
				if ( apply_filters( 'ep_post_sync_kill', false, $post_id, $post_id ) ) {
					return;
				}

				$this->add_to_queue( $post_id );
			}
		}
	}

	/**
	 * Create mapping and network alias when a new blog is created.
	 *
	 * @param WP_Site $blog New site object.
	 */
	public function action_create_blog_index( $blog ) {
		if ( ! defined( 'EP_IS_NETWORK' ) || ! EP_IS_NETWORK ) {
			// @codeCoverageIgnoreStart
			return;
			// @codeCoverageIgnoreEnd
		}

		if ( $this->kill_sync() ) {
			return;
		}

		$non_global_indexable_objects = Indexables::factory()->get_all( false );

		switch_to_blog( $blog->blog_id );

		foreach ( $non_global_indexable_objects as $indexable ) {
			$indexable->delete_index();
			$indexable->put_mapping();

			$index_name = $indexable->get_index_name( $blog->blog_id );
			$indexable->create_network_alias( [ $index_name ] );
		}

		restore_current_blog();
	}
}
