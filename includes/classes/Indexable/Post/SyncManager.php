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
	 * Delete all post meta from other posts associated with a deleted post. Useful for attachments.
	 *
	 * @var bool
	 */
	public $delete_all_meta = false;

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
		// Called just because we need to know somehow if $delete_all is set before action_queue_meta_sync() runs.
		add_filter( 'delete_post_metadata', array( $this, 'maybe_delete_meta_for_all' ), 10, 5 );
		add_action( 'deleted_post_meta', array( $this, 'action_queue_meta_sync' ), 10, 4 );
		add_action( 'set_object_terms', array( $this, 'action_set_object_terms' ), 10, 6 );
		add_action( 'edited_term', array( $this, 'action_edited_term' ), 10, 3 );
		add_action( 'deleted_term_relationships', array( $this, 'action_deleted_term_relationships' ), 10, 3 );
		add_action( 'wp_initialize_site', array( $this, 'action_create_blog_index' ) );

		add_filter( 'ep_sync_insert_permissions_bypass', array( $this, 'filter_bypass_permission_checks_for_machines' ) );
		add_filter( 'ep_sync_delete_permissions_bypass', array( $this, 'filter_bypass_permission_checks_for_machines' ) );
	}

	/**
	 * Un-setup actions and filters (for multisite).
	 *
	 * @since 4.0
	 */
	public function tear_down() {
		remove_action( 'wp_insert_post', array( $this, 'action_sync_on_update' ), 999 );
		remove_action( 'add_attachment', array( $this, 'action_sync_on_update' ), 999 );
		remove_action( 'edit_attachment', array( $this, 'action_sync_on_update' ), 999 );
		remove_action( 'delete_post', array( $this, 'action_delete_post' ) );
		remove_action( 'updated_post_meta', array( $this, 'action_queue_meta_sync' ) );
		remove_action( 'added_post_meta', array( $this, 'action_queue_meta_sync' ) );
		remove_filter( 'delete_post_metadata', array( $this, 'maybe_delete_meta_for_all' ) );
		remove_action( 'deleted_post_meta', array( $this, 'action_queue_meta_sync' ) );
		remove_action( 'wp_initialize_site', array( $this, 'action_create_blog_index' ) );
		remove_filter( 'ep_sync_insert_permissions_bypass', array( $this, 'filter_bypass_permission_checks_for_machines' ) );
		remove_filter( 'ep_sync_delete_permissions_bypass', array( $this, 'filter_bypass_permission_checks_for_machines' ) );
	}

	/**
	 * Whether to delete all meta from other posts that is associated with the deleted post.
	 *
	 * @param bool   $check       Whether to allow metadata deletion of the given type.
	 * @param int    $object_id    ID of the object metadata is for.
	 * @param string $meta_key    Metadata key.
	 * @param mixed  $meta_value  Metadata value. Must be serializable if non-scalar.
	 * @param bool   $delete_all  Whether to delete the matching metadata entries
	 *                             for all objects, ignoring the specified $object_id
	 * @return bool
	 */
	public function maybe_delete_meta_for_all( $check, $object_id, $meta_key, $meta_value, $delete_all ) {
		$this->delete_all_meta = $delete_all;
		return $check;
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

		if ( empty( $object_id ) && $this->delete_all_meta ) {
			add_filter( 'ep_is_integrated_request', '__return_true' );

			$query = new \WP_Query(
				[
					'ep_integrate' => true,
					'meta_key'     => $meta_key,
					'meta_value'   => $meta_value,
					'fields'       => 'ids',
				]
			);

			remove_filter( 'ep_is_integrated_request', '__return_true' );

			if ( $query->have_posts() && $query->elasticsearch_success ) {
				$posts_to_be_synced = array_filter(
					$query->posts,
					function( $object_id ) {
						return ! apply_filters( 'ep_post_sync_kill', false, $object_id, $object_id );
					}
				);
				if ( ! empty( $posts_to_be_synced ) ) {
					$indexable->bulk_index( $posts_to_be_synced );
				}
			}
		} else {
			$indexable_post_statuses = $indexable->get_indexable_post_status();
			$post_type               = get_post_type( $object_id );

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
		 * Make sure to remove this post from the sync queue in case an shutdown happens
		 * before a redirect when a redirect has already been triggered.
		 */
		if ( isset( $this->sync_queue[ $post_id ] ) ) {
			unset( $this->sync_queue[ $post_id ] );
		}
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
	 * When a post's terms are changed, re-index.
	 *
	 * This catches term deletions via wp_delete_term(), because that function internally loops over all attached objects
	 * and updates their terms. It will also end up firing whenever set_object_terms is called, but the queue will de-duplicate
	 * multiple instances per post. This won't happen for taxonomies that has a default term (like Uncategorized for categories),
	 * hence why we also have `action_deleted_term_relationships`.
	 *
	 * @see set_object_terms
	 * @param int    $post_id    Post ID.
	 * @param array  $terms      An array of object terms.
	 * @param array  $tt_ids     An array of term taxonomy IDs.
	 * @param string $taxonomy   Taxonomy slug.
	 * @param bool   $append     Whether to append new terms to the old terms.
	 * @param array  $old_tt_ids Old array of term taxonomy IDs.
	 * @since  4.0.0
	 */
	public function action_set_object_terms( $post_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ) {
		if ( $this->kill_sync() ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			// Bypass saving if doing autosave
			return;
		}

		/**
		 * Filter to allow skipping this action in case of custom handling
		 *
		 * @hook ep_skip_action_set_object_terms
		 * @param {bool}   $skip       True means kill sync for post
		 * @param {int}    $post_id    ID of post
		 * @param {array}  $terms      An array of object terms.
		 * @param {array}  $tt_ids     An array of term taxonomy IDs.
		 * @param {string} $taxonomy   Taxonomy slug.
		 * @param {bool}   $append     Whether to append new terms to the old terms.
		 * @param {array}  $old_tt_ids Old array of term taxonomy IDs.
		 * @return {boolean} New value
		 */
		if ( apply_filters( 'ep_skip_action_set_object_terms', false, $post_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ) ) {
			return;
		}

		if ( ! $this->should_reindex_post( $post_id, $taxonomy ) ) {
			return;
		}

		/**
		 * Fire before post is queued for syncing
		 *
		 * @since 4.0.0
		 * @hook ep_sync_on_set_object_terms
		 * @param {int}    $post_id    ID of post
		 * @param {array}  $terms      An array of object terms.
		 * @param {array}  $tt_ids     An array of term taxonomy IDs.
		 * @param {string} $taxonomy   Taxonomy slug.
		 * @param {bool}   $append     Whether to append new terms to the old terms.
		 * @param {array}  $old_tt_ids Old array of term taxonomy IDs.
		 */
		do_action( 'ep_sync_on_set_object_terms', $post_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids );

		$this->add_to_queue( $post_id );
	}

	/**
	 * When a term is updated, re-index all posts attached to that term
	 *
	 * @param  int    $term_id    Term id.
	 * @param  int    $tt_id Term Taxonomy id.
	 * @param  string $taxonomy   Taxonomy name.
	 * @since  4.0.0
	 */
	public function action_edited_term( $term_id, $tt_id, $taxonomy ) {
		global $wpdb;

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			// Bypass saving if doing autosave
			return;
		}

		// Find ID of all attached posts (query lifted from wp_delete_term())
		$object_ids = (array) $wpdb->get_col( $wpdb->prepare( "SELECT object_id FROM $wpdb->term_relationships WHERE term_taxonomy_id = %d", $tt_id ) );

		if ( ! count( $object_ids ) ) {
			return;
		}

		/**
		 * Filter to allow skipping this action in case of custom handling
		 *
		 * @hook ep_skip_action_edited_term
		 * @param {bool}   $skip       Current value of whether to skip running action_edited_term or not
		 * @param {int}    $term_id    Term id.
		 * @param {int}    $tt_id      Term Taxonomy id.
		 * @param {string} $taxonomy   Taxonomy name.
		 * @param {array}  $object_ids IDs of the objects attached to the term id.
		 * @return {bool}  New value of whether to skip running action_edited_term or not
		 */
		if ( apply_filters( 'ep_skip_action_edited_term', false, $term_id, $tt_id, $taxonomy, $object_ids ) ) {
			return;
		}

		$indexable = Indexables::factory()->get( $this->indexable_slug );

		// Add all of them to the queue
		foreach ( $object_ids as $post_id ) {
			if ( ! $this->should_reindex_post( $post_id, $taxonomy ) ) {
				continue;
			}

			/**
			 * Fire before post is queued for syncing
			 *
			 * @hook ep_sync_on_edited_term
			 * @param  {int} $post_id ID of post
			 * @param  {int} $term_id ID of the term that was edited
			 * @param  {int} $tt_id Taxonomy Term ID of the term that was edited
			 * @param  {int} $taxonomy Taxonomy of the term that was edited
			 */
			do_action( 'ep_sync_on_edited_term', $post_id, $term_id, $tt_id, $taxonomy );

			$this->add_to_queue( $post_id );
		}
	}

	/**
	 * When a term relationship is deleted, re-index all posts attached to that term
	 *
	 * @param int    $post_id  Post ID.
	 * @param array  $tt_ids   An array of term taxonomy IDs.
	 * @param string $taxonomy Taxonomy slug.
	 * @since  4.0.0
	 */
	public function action_deleted_term_relationships( $post_id, $tt_ids, $taxonomy ) {
		if ( $this->kill_sync() ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			// Bypass saving if doing autosave
			return;
		}

		/**
		 * Filter to allow skipping this action in case of custom handling
		 *
		 * @hook ep_skip_action_deleted_term_relationships
		 * @param {bool}   $skip       Current value of whether to skip running action_edited_term or not
		 * @param {int}    $post_id  Post ID.
		 * @param {array}  $tt_ids   An array of term taxonomy IDs.
		 * @param {string} $taxonomy Taxonomy slug.
		 * @return {bool}  New value of whether to skip running action_deleted_term_relationships or not
		 */
		if ( apply_filters( 'ep_skip_action_deleted_term_relationships', false, $post_id, $tt_ids, $taxonomy ) ) {
			return;
		}

		if ( ! $this->should_reindex_post( $post_id, $taxonomy ) ) {
			return;
		}

		/**
		 * Fire before post is queued for syncing
		 *
		 * @hook ep_sync_on_deleted_term_relationships
		 * @since 4.0.0
		 * @param  {int}    $post_id ID of post
		 * @param  {array}  $tt_ids   An array of term taxonomy IDs.
		 * @param  {string} $taxonomy Taxonomy of the term that was edited
		 */
		do_action( 'ep_sync_on_deleted_term_relationships', $post_id, $tt_ids, $taxonomy );

		$this->add_to_queue( $post_id );
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

	/**
	 * Check if post attributes (post status, taxonomy, and type) match what is needed to reindex or not.
	 *
	 * @param int    $post_id  The post ID.
	 * @param string $taxonomy The taxonomy slug.
	 * @return boolean
	 */
	protected function should_reindex_post( $post_id, $taxonomy ) {
		/**
		 * Filter to kill post sync
		 *
		 * @hook ep_post_sync_kill
		 * @param {bool} $skip True meanas kill sync for post
		 * @param  {int} $object_id ID of post
		 * @param  {int} $object_id ID of post
		 * @return {boolean} New value
		 */
		if ( apply_filters( 'ep_post_sync_kill', false, $post_id, $post_id ) ) {
			return false;
		}

		$post = get_post( $post_id );
		if ( ! is_object( $post ) ) {
			return false;
		}

		$indexable = Indexables::factory()->get( $this->indexable_slug );

		// Check post status
		$indexable_post_statuses = $indexable->get_indexable_post_status();
		if ( ! in_array( $post->post_status, $indexable_post_statuses, true ) ) {
			return false;
		}

		// Only re-index if the taxonomy is indexed for this post
		$indexable_taxonomies     = $indexable->get_indexable_post_taxonomies( $post );
		$indexable_taxonomy_names = wp_list_pluck( $indexable_taxonomies, 'name' );
		if ( ! in_array( $taxonomy, $indexable_taxonomy_names, true ) ) {
			return false;
		}

		// Check post type
		$indexable_post_types = $indexable->get_indexable_post_types();
		if ( ! in_array( $post->post_type, $indexable_post_types, true ) ) {
			return false;
		}

		return true;
	}
}
