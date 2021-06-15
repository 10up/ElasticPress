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
		if ( defined( 'WP_IMPORTING' ) && true === WP_IMPORTING ) {
			return;
		}

		if ( ! Elasticsearch::factory()->get_elasticsearch_version() ) {
			return;
		}

		add_action( 'wp_insert_comment', [ $this, 'action_sync_on_update' ] );
		add_action( 'edit_comment', [ $this, 'action_sync_on_update' ] );
		add_action( 'transition_comment_status', [ $this, 'action_sync_on_update' ] );

		add_action( 'trashed_comment', [ $this, 'action_sync_on_delete' ] );
		add_action( 'deleted_comment', [ $this, 'action_sync_on_delete' ] );

		add_action( 'added_comment_meta', [ $this, 'action_queue_meta_sync' ], 10, 2 );
		add_action( 'deleted_comment_meta', [ $this, 'action_queue_meta_sync' ], 10, 2 );
		add_action( 'updated_comment_meta', [ $this, 'action_queue_meta_sync' ], 10, 2 );
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

		$comment = get_comment( $comment_id );

		if ( ! empty( $comment ) ) {

			$comment_status = wp_get_comment_status( $comment );
			$post_status    = get_post_status( $comment->comment_post_ID );

			$indexable_comment_statuses = Indexables::factory()->get( 'comment' )->get_indexable_comment_status();
			$indexable_post_statuses    = Indexables::factory()->get( 'post' )->get_indexable_post_status();

			if ( ! in_array( $comment_status, $indexable_comment_statuses, true ) || ! in_array( $post_status, $indexable_post_statuses, true ) ) {
				$this->action_sync_on_delete( $comment_id );
			} else {
				$comment_type = $comment->comment_type;
				$post_type    = get_post_type( $comment->comment_post_ID );

				$indexable_comment_types = Indexables::factory()->get( 'comment' )->get_indexable_comment_types();
				$indexable_post_types    = Indexables::factory()->get( 'post' )->get_indexable_post_status();

				if ( in_array( $comment_type, $indexable_comment_types, true ) && in_array( $post_type, $indexable_post_types, true ) ) {
					$this->sync_queue[ $comment_id ] = true;
				}
			}
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
		$this->sync_queue[ $comment_id ] = true;
	}

}
