<?php
/**
 * Manage syncing of content between WP and Elasticsearch for Comments
 *
 * @since   3.1
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
	 * @since 3.1
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
	 * @since 3.1
	 */
	public function action_sync_on_update( $comment_id ) {
		if ( ! current_user_can( 'edit_comment', $comment_id ) ) {
			return;
		}

		if ( apply_filters( 'ep_comment_sync_kill', false, $comment_id ) ) {
			return;
		}

		do_action( 'ep_sync_comment_on_transition', $comment_id );

		$this->sync_queue[ $comment_id ] = true;
	}

	/**
	 * Delete comment from ES when deleted or trashed in WP
	 *
	 * @param int $comment_id Comment ID.
	 * @since 3.1
	 */
	public function action_sync_on_delete( $comment_id ) {
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
	 * @since 3.1
	 */
	public function action_queue_meta_sync( $meta_id, $comment_id ) {
		$this->sync_queue[ $comment_id ] = true;
	}

}
