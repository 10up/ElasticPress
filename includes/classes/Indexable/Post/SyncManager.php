<?php
/**
 * Manage syncing of content between WP and Elasticsearch for posts
 *
 * @since  1.0
 * @package elasticpress
 */

namespace ElasticPress\Indexable\Post;

use ElasticPress\Indexables as Indexables;
use ElasticPress\Elasticsearch as Elasticsearch;
use ElasticPress\SyncManager as SyncManagerAbstract;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Sync manager class
 */
class SyncManager extends SyncManagerAbstract {

	/**
	 * Setup actions and filters
	 *
	 * @since 0.1.2
	 */
	public function setup() {
		if ( defined( 'WP_IMPORTING' ) && true === WP_IMPORTING ) {
			return;
		}

		if ( ! Elasticsearch::factory()->get_elasticsearch_version() ) {
			return;
		}

		add_action( 'wp_insert_post', array( $this, 'action_sync_on_update' ), 999, 3 );
		add_action( 'add_attachment', array( $this, 'action_sync_on_update' ), 999, 3 );
		add_action( 'edit_attachment', array( $this, 'action_sync_on_update' ), 999, 3 );
		add_action( 'delete_post', array( $this, 'action_delete_post' ) );
		add_action( 'delete_blog', array( $this, 'action_delete_blog_from_index' ) );
		add_action( 'make_delete_blog', array( $this, 'action_delete_blog_from_index' ) );
		add_action( 'make_spam_blog', array( $this, 'action_delete_blog_from_index' ) );
		add_action( 'archive_blog', array( $this, 'action_delete_blog_from_index' ) );
		add_action( 'deactivate_blog', array( $this, 'action_delete_blog_from_index' ) );
		add_action( 'updated_post_meta', array( $this, 'action_queue_meta_sync' ), 10, 4 );
		add_action( 'added_post_meta', array( $this, 'action_queue_meta_sync' ), 10, 4 );
		add_action( 'deleted_post_meta', array( $this, 'action_queue_meta_sync' ), 10, 4 );
		add_action( 'edited_term', array( $this, 'action_edited_term' ), 10, 3 );
		add_action( 'set_object_terms', array( $this, 'action_set_object_terms' ), 10, 6 );
		add_action( 'wp_initialize_site', array( $this, 'action_create_blog_index' ) );
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
		$indexable = Indexables::factory()->get( 'post' );

		$indexable_post_statuses = $indexable->get_indexable_post_status();
		$post_type               = get_post_type( $object_id );

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			// Bypass saving if doing autosave
			return;
		}

		$post = get_post( $object_id );

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
	 * When a term is updated, re-index all posts attached to that term
	 *
	 * @param  int    $term_id Term id.
	 * @param  int    $tt_id Term Taxonomy id.
	 * @param  string $taxonomy Taxonomy name.
	 * @since  3.5
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

		$indexable = Indexables::factory()->get( 'post' );

		// Add all of them to the queue
		foreach ( $object_ids as $post_id ) {
			$post_type = get_post_type( $post_id );

			$post = get_post( $post_id );

			// Only re-index if the taxonomy is indexed for this post
			$indexable_taxonomies = $indexable->get_indexable_post_taxonomies( $post );

			$indexable_taxonomy_names = wp_list_pluck( $indexable_taxonomies, 'name' );

			if ( ! in_array( $taxonomy, $indexable_taxonomy_names, true ) ) {
				return;
			}

			$indexable_post_types = $indexable->get_indexable_post_types();

			if ( in_array( $post_type, $indexable_post_types, true ) ) {
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
					return;
				}

				$this->add_to_queue( $post_id );
			}
		}
	}

	/**
	 * When a post's terms are changed, re-index
	 *
	 * This catches term deletions via wp_delete_term(), because that function internally loops over all attached objects
	 * and updates their terms. It will also end up firing whenever set_object_terms is called, but the queue will de-duplicate
	 * multiple instances per post
	 *
	 * @see set_object_terms
	 * @param int    $object_id  Object ID.
	 * @param array  $terms      An array of object terms.
	 * @param array  $tt_ids     An array of term taxonomy IDs.
	 * @param string $taxonomy   Taxonomy slug.
	 * @param bool   $append     Whether to append new terms to the old terms.
	 * @param array  $old_tt_ids Old array of term taxonomy IDs.
	 * @since  3.5
	 */
	public function action_set_object_terms( $object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ) {
		$post_id = $object_id;

		$indexable = Indexables::factory()->get( 'post' );
		$post_type = get_post_type( $post_id );

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			// Bypass saving if doing autosave
			return;
		}

		$post = get_post( $post_id );

		// Only re-index if the taxonomy is indexed for this post
		$indexable_taxonomies = $indexable->get_indexable_post_taxonomies( $post );

		$indexable_taxonomy_names = wp_list_pluck( $indexable_taxonomies, 'name' );

		if ( ! in_array( $taxonomy, $indexable_taxonomy_names, true ) ) {
			return;
		}

		$indexable_post_types = $indexable->get_indexable_post_types();

		if ( in_array( $post_type, $indexable_post_types, true ) ) {
			/**
			 * Fire before post is queued for syncing
			 *
			 * @hook ep_sync_on_set_object_terms
			 * @param  {int} $post_id ID of post
			 */
			do_action( 'ep_sync_on_set_object_terms', $post_id );

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
				return;
			}

			$this->add_to_queue( $post_id );
		}
	}

	/**
	 * Remove blog from index when a site is deleted, archived, or deactivated
	 *
	 * @param int $blog_id WP Blog ID.
	 */
	public function action_delete_blog_from_index( $blog_id ) {
		$indexable = Indexables::factory()->get( 'post' );

		/**
		 * Filter to whether to keep index on site deletion
		 *
		 * @hook ep_keep_index
		 * @param {bool} $keep True means don't delete index
		 * @return {boolean} New value
		 */
		if ( $indexable->index_exists( $blog_id ) && ! apply_filters( 'ep_keep_index', false ) ) {
			$indexable->delete_index( $blog_id );
		}
	}

	/**
	 * Delete ES post when WP post is deleted
	 *
	 * @param int $post_id Post id.
	 * @since 0.1.0
	 */
	public function action_delete_post( $post_id ) {
		/**
		 * Filter whether to skip the permissions check on deleting a post
		 *
		 * @hook ep_post_sync_kill
		 * @param  {bool} $bypass True to bypass
		 * @param  {int} $post_id ID of post
		 * @return {boolean} New value
		 */
		if ( ! current_user_can( 'edit_post', $post_id ) && ! apply_filters( 'ep_sync_delete_permissions_bypass', false, $post_id ) ) {
			return;
		}

		$indexable = Indexables::factory()->get( 'post' );
		$post_type = get_post_type( $post_id );

		$indexable_post_types = $indexable->get_indexable_post_types();

		if ( ! in_array( $post_type, $indexable_post_types, true ) ) {
			// If not an indexable post type, skip delete.
			return;
		}

		/**
		 * Fires before post deletion
		 *
		 * @hook ep_delete_post
		 * @param  {int} $post_id ID of post
		 */
		do_action( 'ep_delete_post', $post_id );

		Indexables::factory()->get( 'post' )->delete( $post_id, false );
	}

	/**
	 * Sync ES index with what happened to the post being saved
	 *
	 * @param int $post_id Post id.
	 * @since 0.1.0
	 */
	public function action_sync_on_update( $post_id ) {
		$indexable = Indexables::factory()->get( 'post' );
		$post_type = get_post_type( $post_id );

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			// Bypass saving if doing autosave
			return;
		}

		/**
		 * Filter whether to skip the permissions check on deleting a post
		 *
		 * @hook ep_post_sync_kill
		 * @param  {bool} $bypass True to bypass
		 * @param  {int} $post_id ID of post
		 * @return {boolean} New value
		 */
		if ( ! apply_filters( 'ep_sync_insert_permissions_bypass', false, $post_id ) ) {
			if ( ! current_user_can( 'edit_post', $post_id ) && ( ! defined( 'DOING_CRON' ) || ! DOING_CRON ) ) {
				// Bypass saving if user does not have access to edit post and we're not in a cron process.
				return;
			}
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
				 * Fire before post is queued for synxing
				 *
				 * @hook ep_sync_on_transition
				 * @param  {int} $post_id ID of post
				 */
				do_action( 'ep_sync_on_transition', $post_id );

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
