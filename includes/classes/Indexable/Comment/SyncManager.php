<?php
/**
 * Manage syncing of content between WP and Elasticsearch for Comments
 *
 * @since   3.6.0
 * @package elasticpress
 */

namespace ElasticPress\Indexable\Comment;

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
	 * @since 3.6.0
	 */
	public function setup() {
		if ( ! Elasticsearch::factory()->get_elasticsearch_version() ) {
			return;
		}

		if ( ! $this->can_index_site() ) {
			return;
		}

		add_action( 'wp_insert_comment', [ $this, 'action_sync_on_insert' ] );
		add_action( 'edit_comment', [ $this, 'action_sync_on_update' ] );
		add_action( 'transition_comment_status', [ $this, 'action_sync_on_transition_comment_status' ], 10, 3 );

		add_action( 'trashed_comment', [ $this, 'action_sync_on_delete' ] );
		add_action( 'deleted_comment', [ $this, 'action_sync_on_delete' ] );

		add_action( 'added_comment_meta', [ $this, 'action_queue_meta_sync' ], 10, 2 );
		add_action( 'deleted_comment_meta', [ $this, 'action_queue_meta_sync' ], 10, 2 );
		add_action( 'updated_comment_meta', [ $this, 'action_queue_meta_sync' ], 10, 2 );
	}

	/**
	 * Sync ES index when new comments are saved
	 *
	 * @param int $comment_id Comment ID.
	 * @since 3.6.3
	 */
	public function action_sync_on_insert( $comment_id ) {
		if ( $this->kill_sync() ) {
			return;
		}

		$this->maybe_index_comment( $comment_id );
	}

	/**
	 * Sync ES index with changes to the comment being saved
	 *
	 * @param int $comment_id Comment ID.
	 * @since 3.6.0
	 */
	public function action_sync_on_update( $comment_id ) {
		if ( $this->kill_sync() ) {
			return;
		}

		if ( ! current_user_can( 'edit_comment', $comment_id ) ) {
			return;
		}

		$this->maybe_index_comment( $comment_id );
	}

	/**
	 * Sync ES index with changes to the comment status
	 *
	 * @param int|string $new_status The new comment status.
	 * @param int|string $old_status  The old comment status.
	 * @param WP_Comment $comment Comment object.
	 * @since 3.6.3
	 */
	public function action_sync_on_transition_comment_status( $new_status, $old_status, $comment ) {
		if ( $this->kill_sync() ) {
			return;
		}

		if ( current_user_can( 'edit_comment', $comment->comment_ID ) || current_user_can( 'moderate_comments', $comment->comment_ID ) ) {
			$this->maybe_index_comment( $comment->comment_ID );
		} else {
			return;
		}
	}

	/**
	 * Delete comment from ES when deleted or trashed in WP
	 *
	 * @param int $comment_id Comment ID.
	 * @since 3.6.0
	 */
	public function action_sync_on_delete( $comment_id ) {
		if ( $this->kill_sync() ) {
			return;
		}

		if ( ! current_user_can( 'moderate_comments', $comment_id ) ) {
			return;
		}

		Indexables::factory()->get( 'comment' )->delete( $comment_id, false );
	}

	/**
	 * When comment meta is updated/added/deleted, queue the comment for reindex
	 *
	 * @param int $meta_id    Meta ID.
	 * @param int $comment_id Comment ID.
	 * @since 3.6.0
	 */
	public function action_queue_meta_sync( $meta_id, $comment_id ) {
		if ( $this->kill_sync() ) {
			return;
		}

		if ( ! current_user_can( 'edit_comment_meta', $comment_id ) ) {
			return;
		}

		$this->maybe_index_comment( $comment_id );
	}

	/**
	 * Maybe index a comment
	 *
	 * Check if comment could be indexed.
	 *
	 * @param int $comment_id Comment ID.
	 * @since 3.6.0
	 */
	protected function maybe_index_comment( $comment_id ) {
		$comment = get_comment( $comment_id );

		if ( ! empty( $comment ) ) {

			$comment_status = $comment->comment_approved;
			$post_status    = get_post_status( $comment->comment_post_ID );

			$indexable_comment_statuses = Indexables::factory()->get( 'comment' )->get_indexable_comment_status();
			$indexable_post_statuses    = Indexables::factory()->get( 'post' )->get_indexable_post_status();

			$has_allowed_comment_status = [ 'all' ] == $indexable_comment_statuses ? true : in_array( $comment_status, $indexable_comment_statuses, true );
			$has_allowed_post_status    = in_array( $post_status, $indexable_post_statuses, true );

			if ( ! $has_allowed_comment_status || ! $has_allowed_post_status ) {
				$this->action_sync_on_delete( $comment_id );
			} else {
				$comment_type = $comment->comment_type;
				$post_type    = get_post_type( $comment->comment_post_ID );

				$indexable_comment_types = Indexables::factory()->get( 'comment' )->get_indexable_comment_types();
				$indexable_post_types    = Indexables::factory()->get( 'post' )->get_indexable_post_types();

				if ( in_array( $comment_type, $indexable_comment_types, true ) && in_array( $post_type, $indexable_post_types, true ) ) {
					/**
					 * Fire before comment is queued for syncing
					 *
					 * @hook ep_sync_comment_on_transition
					 * @since 3.6.0
					 * @param  {int} $comment_id Comment ID
					 */
					do_action( 'ep_sync_comment_on_transition', $comment_id );

					/**
					 * Filter to kill comment sync
					 *
					 * @hook ep_comment_sync_kill
					 * @since 3.6.0
					 * @param {bool} $skip True means kill sync for comment
					 * @param  {int} $comment_id Comment ID
					 * @return {boolean} New value
					 */
					if ( apply_filters( 'ep_comment_sync_kill', false, $comment_id ) ) {
						return;
					}

					$this->add_to_queue( $comment_id );
				}
			}
		}
	}

}
