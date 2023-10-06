<?php
/**
 * Manage syncing of content between WP and Elasticsearch for posts
 *
 * @since  1.0
 * @package elasticpress
 */

namespace ElasticPress\Indexable\Post;

use ElasticPress\Elasticsearch;
use ElasticPress\Indexables;
use ElasticPress\IndexHelper;
use ElasticPress\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	// @codeCoverageIgnoreStart
	exit; // Exit if accessed directly.
	// @codeCoverageIgnoreEnd
}

/**
 * Sync manager class
 */
class SyncManager extends \ElasticPress\SyncManager {

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
		add_action( 'wp_media_attach_action', array( $this, 'action_sync_on_media_attach' ), 999, 2 );
		add_action( 'delete_post', array( $this, 'action_delete_post' ) );
		add_action( 'updated_post_meta', array( $this, 'action_queue_meta_sync' ), 10, 4 );
		add_action( 'added_post_meta', array( $this, 'action_queue_meta_sync' ), 10, 4 );
		// Called just because we need to know somehow if $delete_all is set before action_queue_meta_sync() runs.
		add_filter( 'delete_post_metadata', array( $this, 'maybe_delete_meta_for_all' ), 10, 5 );
		add_action( 'deleted_post_meta', array( $this, 'action_queue_meta_sync' ), 10, 4 );
		add_action( 'wp_initialize_site', array( $this, 'action_create_blog_index' ) );

		add_filter( 'ep_sync_insert_permissions_bypass', array( $this, 'filter_bypass_permission_checks_for_machines' ) );
		add_filter( 'ep_sync_delete_permissions_bypass', array( $this, 'filter_bypass_permission_checks_for_machines' ) );

		// Conditionally update posts associated with terms
		add_action( 'ep_admin_notices', [ $this, 'maybe_display_notice_edit_single_term' ] );
		add_action( 'ep_admin_notices', [ $this, 'maybe_display_notice_term_list_screen' ] );
		add_action( 'set_object_terms', array( $this, 'action_set_object_terms' ), 10, 6 );
		add_action( 'edited_term', array( $this, 'action_edited_term' ), 10, 3 );
		add_action( 'deleted_term_relationships', array( $this, 'action_deleted_term_relationships' ), 10, 3 );

		// Clear index settings cache
		add_action( 'ep_update_index_settings', [ $this, 'clear_index_settings_cache' ] );
		add_action( 'ep_after_put_mapping', [ $this, 'clear_index_settings_cache' ] );
		add_action( 'ep_saved_weighting_configuration', [ $this, 'clear_index_settings_cache' ] );

		// Clear distinct meta field per post type cache
		add_action( 'wp_insert_post', [ $this, 'clear_meta_keys_db_per_post_type_cache_by_post_id' ] );
		add_action( 'delete_post', [ $this, 'clear_meta_keys_db_per_post_type_cache_by_post_id' ] );
		add_action( 'updated_post_meta', [ $this, 'clear_meta_keys_db_per_post_type_cache_by_meta' ], 10, 2 );
		add_action( 'added_post_meta', [ $this, 'clear_meta_keys_db_per_post_type_cache_by_meta' ], 10, 2 );
		add_action( 'deleted_post_meta', [ $this, 'clear_meta_keys_db_per_post_type_cache_by_meta' ], 10, 2 );
		add_action( 'delete_post_metadata', [ $this, 'clear_meta_keys_db_per_post_type_cache_by_meta' ], 10, 2 );

		// Prevents password protected posts from being indexed
		add_filter( 'ep_post_sync_kill', [ $this, 'kill_sync_for_password_protected' ], 10, 2 );
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
		remove_action( 'wp_media_attach_action', array( $this, 'action_sync_on_media_attach' ), 999 );
		remove_action( 'delete_post', array( $this, 'action_delete_post' ) );
		remove_action( 'updated_post_meta', array( $this, 'action_queue_meta_sync' ) );
		remove_action( 'added_post_meta', array( $this, 'action_queue_meta_sync' ) );
		remove_filter( 'delete_post_metadata', array( $this, 'maybe_delete_meta_for_all' ) );
		remove_action( 'deleted_post_meta', array( $this, 'action_queue_meta_sync' ) );
		remove_action( 'wp_initialize_site', array( $this, 'action_create_blog_index' ) );
		remove_filter( 'ep_sync_insert_permissions_bypass', array( $this, 'filter_bypass_permission_checks_for_machines' ) );
		remove_filter( 'ep_sync_delete_permissions_bypass', array( $this, 'filter_bypass_permission_checks_for_machines' ) );
		remove_filter( 'ep_post_sync_kill', [ $this, 'kill_sync_for_password_protected' ] );

		// Clear index settings cache
		remove_action( 'ep_update_index_settings', [ $this, 'clear_index_settings_cache' ] );
		remove_action( 'ep_after_put_mapping', [ $this, 'clear_index_settings_cache' ] );
		remove_action( 'ep_saved_weighting_configuration', [ $this, 'clear_index_settings_cache' ] );
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

		/**
		 * Filter to whether skip a sync during autosave, defaults to true
		 *
		 * @hook ep_skip_autosave_sync
		 * @since 4.3.0
		 * @param {bool} $skip True means to disable sync for autosaves
		 * @param {string} $function Function applying filter
		 * @return {boolean} New value
		 */
		if ( apply_filters( 'ep_skip_autosave_sync', true, __FUNCTION__ ) ) {
			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
				// Bypass saving if doing autosave
				// @codeCoverageIgnoreStart
				return;
				// @codeCoverageIgnoreEnd
			}
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
					'post_type'    => $indexable->get_indexable_post_types(),
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

			$is_meta_allowed = $indexable->is_meta_allowed( $meta_key, $post );
			if ( ! $is_meta_allowed ) {
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
		$this->remove_from_queue( $post_id );
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

		/**
		 * Filter to whether skip a sync during autosave, defaults to true
		 *
		 * @hook ep_skip_autosave_sync
		 * @since 4.3.0
		 * @param {bool} $skip True means to disable sync for autosaves
		 * @param {string} $function Function applying filter
		 * @return {boolean} New value
		 */
		if ( apply_filters( 'ep_skip_autosave_sync', true, __FUNCTION__ ) ) {
			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
				// Bypass saving if doing autosave
				// @codeCoverageIgnoreStart
				return;
				// @codeCoverageIgnoreEnd
			}
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
	 * Depending on the number of posts associated with the term display an admin notice
	 *
	 * @since 4.4.0
	 * @param array $notices Current ElasticPress admin notices
	 * @return array
	 */
	public function maybe_display_notice_edit_single_term( $notices ) {
		global $pagenow, $tag;

		/**
		 * Make sure we're on a term-related page in the admin dashboard.
		 */
		if ( ! is_admin() || 'term.php' !== $pagenow || ! $tag instanceof \WP_Term ) {
			return $notices;
		}

		if ( IndexHelper::factory()->get_index_default_per_page() >= $tag->count ) {

			$child_tags = get_term_children( $tag->term_id, $tag->taxonomy );
			if ( empty( $child_tags ) ) {
				return $notices;
			}
			foreach ( $child_tags as $child_tag_id ) {
				$child_tag = get_term( $child_tag_id );
				if ( ! is_wp_error( $child_tag ) && IndexHelper::factory()->get_index_default_per_page() < $child_tag->count && ! isset( $notices['edited_single_parent_term'] ) ) {
					$notices['edited_single_parent_term'] = [
						'html'    => sprintf(
							/* translators: Sync Page URL */
							__( 'Due to the number of posts associated with its child terms, you will need to <a href="%s">resync</a> after editing or deleting it.', 'elasticpress' ),
							Utils\get_sync_url()
						),
						'type'    => 'warning',
						'dismiss' => true,
					];
					break;
				}
			}

			return $notices;
		}
		$notices['edited_single_term'] = [
			'html'    => sprintf(
				/* translators: Sync Page URL */
				__( 'Due to the number of posts associated with this term, you will need to <a href="%s">resync</a> after editing or deleting it.', 'elasticpress' ),
				Utils\get_sync_url()
			),
			'type'    => 'warning',
			'dismiss' => true,
		];

		return $notices;
	}

	/**
	 * Depending on the number of posts display an admin notice in the Dashboard Terms List Screen
	 *
	 * @since 4.4.0
	 * @param array $notices Current ElasticPress admin notices
	 * @return array
	 */
	public function maybe_display_notice_term_list_screen( $notices ) {
		global $pagenow, $tax;

		/**
		 * Make sure we're on a term-related page in the admin dashboard.
		 */
		if ( ! is_admin() || 'edit-tags.php' !== $pagenow || ! $tax instanceof \WP_Taxonomy ) {
			return $notices;
		}

		if ( ! $this->is_tax_max_count_bigger_than_items_per_cycle( $tax ) ) {
			return $notices;
		}

		$notices['too_many_posts_on_term'] = [
			'html'    => sprintf(
				/* translators: Sync Page URL */
				__( 'Depending on the number of posts associated with a term, you may need to <a href="%s">resync</a> after editing or deleting it.', 'elasticpress' ),
				Utils\get_sync_url()
			),
			'type'    => 'warning',
			'dismiss' => true,
		];

		return $notices;
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

		/**
		 * Filter to whether skip a sync during autosave, defaults to true
		 *
		 * @hook ep_skip_autosave_sync
		 * @since 4.3.0
		 * @param {bool} $skip True means to disable sync for autosaves
		 * @param {string} $function Function applying filter
		 * @return {boolean} New value
		 */
		if ( apply_filters( 'ep_skip_autosave_sync', true, __FUNCTION__ ) ) {
			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
				// Bypass saving if doing autosave
				// @codeCoverageIgnoreStart
				return;
				// @codeCoverageIgnoreEnd
			}
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

		if ( $this->kill_sync() ) {
			return;
		}

		/**
		 * Filter to whether skip a sync during autosave, defaults to true
		 *
		 * @hook ep_skip_autosave_sync
		 * @since 4.3.0
		 * @param {bool} $skip True means to disable sync for autosaves
		 * @param {string} $function Function applying filter
		 * @return {boolean} New value
		 */
		if ( apply_filters( 'ep_skip_autosave_sync', true, __FUNCTION__ ) ) {
			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
				// Bypass saving if doing autosave
				// @codeCoverageIgnoreStart
				return;
				// @codeCoverageIgnoreEnd
			}
		}

		// Find ID of all attached posts (query lifted from wp_delete_term())
		$object_ids = (array) $wpdb->get_col( // phpcs:disable WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( "SELECT object_id FROM {$wpdb->term_relationships} WHERE term_taxonomy_id = %d", $tt_id )
		);

		// If the current term is not attached, check if the child terms are attached to the post
		if ( empty( $object_ids ) ) {
			$child_terms = get_term_children( $term_id, $taxonomy );
			if ( ! empty( $child_terms ) ) {
				$in_id      = join( ',', array_fill( 0, count( $child_terms ), '%d' ) );
				$object_ids = (array) $wpdb->get_col( // phpcs:disable WordPress.DB.DirectDatabaseQuery
					$wpdb->prepare(
						"SELECT object_id FROM {$wpdb->term_relationships} WHERE term_taxonomy_id IN ( {$in_id} )", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
						$child_terms
					)
				);
			}
		}
		if ( ! count( $object_ids ) ) {
			return;
		}

		// If we have more items to update than the number set as Content Items per Index Cycle, skip it.
		$should_skip = count( $object_ids ) > IndexHelper::factory()->get_index_default_per_page();

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
		if ( apply_filters( 'ep_skip_action_edited_term', $should_skip, $term_id, $tt_id, $taxonomy, $object_ids ) ) {
			return;
		}

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

		/**
		 * Filter to whether skip a sync during autosave, defaults to true
		 *
		 * @hook ep_skip_autosave_sync
		 * @since 4.3.0
		 * @param {bool} $skip True means to disable sync for autosaves
		 * @param {string} $function Function applying filter
		 * @return {boolean} New value
		 */
		if ( apply_filters( 'ep_skip_autosave_sync', true, __FUNCTION__ ) ) {
			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
				// Bypass saving if doing autosave
				// @codeCoverageIgnoreStart
				return;
				// @codeCoverageIgnoreEnd
			}
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
	 * DEPRECATED. Clear the cache of the total fields limit
	 *
	 * @since 4.4.0
	 */
	public function clear_total_fields_limit_cache() {
		_deprecated_function( __METHOD__, '4.7.0', '\ElasticPress\Indexable\Post\SyncManager::clear_index_settings_cache()' );
	}

	/**
	 * Clear the cache of the total fields limit
	 *
	 * @param int $post_id The post ID
	 * @since 4.4.0
	 */
	public function clear_meta_keys_db_per_post_type_cache_by_post_id( $post_id ) {
		$post_type = get_post_type( $post_id );
		if ( $post_type ) {
			$this->clear_meta_keys_db_cache( $post_type );
		}
	}

	/**
	 * Clear the cache of the total fields limit
	 *
	 * @param int|array $meta_id Meta ID
	 * @param int       $post_id The post ID
	 * @since 4.4.0
	 */
	public function clear_meta_keys_db_per_post_type_cache_by_meta( $meta_id, $post_id ) {
		$post_type = get_post_type( $post_id );
		if ( $post_type ) {
			$this->clear_meta_keys_db_cache( $post_type );
		}
	}

	/**
	 * Clear the cache of the total fields limit
	 *
	 * @param string $post_type The post type
	 * @since 4.4.0
	 */
	protected function clear_meta_keys_db_cache( $post_type ) {
		delete_transient( 'ep_meta_field_keys' );
		delete_transient( 'ep_meta_field_keys_' . $post_type );
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

		// If we have more items to update than the number set as Content Items per Index Cycle, skip it to avoid a timeout.
		$single_ids_queued   = array_unique( array_keys( $this->get_sync_queue() ) );
		$has_too_many_queued = count( $single_ids_queued ) > IndexHelper::factory()->get_index_default_per_page();

		return ! $has_too_many_queued;
	}

	/**
	 * Given a taxonomy, check if the term with most posts is under or above the number set as Content Items per Index Cycle.
	 *
	 * The result will be cached in a transient. Its TTL will depend on the result:
	 * If it is determined we have a term with more posts, cache it for more time.
	 *
	 * @since 4.4.0
	 * @param \WP_Taxonomy $tax The taxonomy object
	 * @return boolean
	 */
	protected function is_tax_max_count_bigger_than_items_per_cycle( \WP_Taxonomy $tax ) : bool {
		$transient_name   = "ep_term_max_count_{$tax->name}";
		$cached_max_count = get_transient( $transient_name );

		if ( is_integer( $cached_max_count ) ) {
			return $cached_max_count > IndexHelper::factory()->get_index_default_per_page();
		}

		$max_count = get_terms(
			[
				'taxonomy' => $tax->name,
				'orderby'  => 'count',
				'order'    => 'DESC',
				'number'   => 1,
				'count'    => true,
			]
		);

		if ( ! is_array( $max_count ) || ! count( $max_count ) || ! $max_count[0] instanceof \WP_Term || ! is_integer( $max_count[0]->count ) ) {
			set_transient( $transient_name, 0, HOUR_IN_SECONDS );
			return false;
		}

		$is_max_count_bigger = $max_count[0]->count > IndexHelper::factory()->get_index_default_per_page();

		set_transient(
			$transient_name,
			$max_count[0]->count,
			$is_max_count_bigger ? DAY_IN_SECONDS : HOUR_IN_SECONDS
		);

		return $is_max_count_bigger;
	}

	/**
	 * Prevent a password protected post from being indexed.
	 *
	 * @since 4.6.0
	 * @param bool $skip      Whether should skip or not before checking for a password
	 * @param int  $object_id The Post ID
	 * @return bool New value of $skip
	 */
	public function kill_sync_for_password_protected( $skip, $object_id ) {
		/**
		 * Short-circuits the process of checking if a post should be indexed or not depending on its password.
		 *
		 * Returning a non-null value will effectively short-circuit the function.
		 *
		 * @since 4.6.0
		 * @hook ep_pre_kill_sync_for_password_protected
		 * @param {null} $new_skip     Whether should skip or not before checking for a password
		 * @param {bool} $current_skip Current value
		 * @param {int}  $object_id    The Post ID
		 * @return {null|bool} New value of $skip or `null` to keep default behavior.
		 */
		$skip_filter = apply_filters( 'ep_pre_kill_sync_for_password_protected', null, $skip, $object_id );
		if ( ! is_null( $skip_filter ) ) {
			return $skip_filter;
		}

		if ( $skip ) {
			return $skip;
		}

		$post = get_post( $object_id );

		return ! empty( $post->post_password );
	}

	/**
	 * Sync ES index when attached or detached action is called.
	 *
	 * @since 4.7.0
	 * @param string $action        Attach/detach action
	 * @param int    $attachment_id The attachment ID
	 */
	public function action_sync_on_media_attach( $action, $attachment_id ) {
		$indexable            = Indexables::factory()->get( $this->indexable_slug );
		$indexable_post_types = $indexable->get_indexable_post_types();

		if ( ! in_array( 'attachment', $indexable_post_types, true ) ) {
			return;
		}
		$this->action_sync_on_update( $attachment_id );
	}
}
