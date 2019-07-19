<?php
/**
 * Manage syncing of content between WP and Elasticsearch for Terms
 *
 * @since   3.1
 * @package elasticpress
 */

namespace ElasticPress\Indexable\Term;

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

		add_action( 'created_term', [ $this, 'action_sync_on_update' ] );
		add_action( 'edited_terms', [ $this, 'action_sync_on_update' ] );
		add_action( 'added_term_meta', [ $this, 'action_queue_meta_sync' ] );
		add_action( 'deleted_term_meta', [ $this, 'action_queue_meta_sync' ] );
		add_action( 'updated_term_meta', [ $this, 'action_queue_meta_sync' ] );
		add_action( 'delete_term', [ $this, 'action_sync_on_delete' ] );
	}

	/**
	 * Sync ES index with changes to the term being saved
	 *
	 * @param int $term_id Term ID.
	 * @since 3.1
	 */
	public function action_sync_on_update( $term_id ) {
		if ( ! current_user_can( 'edit_term', $term_id ) ) {
			return;
		}

		if ( apply_filters( 'ep_term_sync_kill', false, $term_id ) ) {
			return;
		}

		do_action( 'ep_sync_term_on_transition', $term_id );

		$this->sync_queue[ $term_id ] = true;
	}

	/**
	 * When term meta is updated/added/deleted, queue the term for reindex
	 *
	 * @param int $meta_id Meta ID.
	 * @param int $term_id Term ID.
	 * @since 3.1
	 */
	public function action_queue_meta_sync( $meta_id, $term_id ) {
		$this->sync_queue[ $term_id ] = true;
	}

	/**
	 * Delete term from ES when deleted in WP
	 *
	 * @param int $term_id Term ID.
	 * @since 3.1
	 */
	public function action_sync_on_delete( $term_id ) {
		if ( ! current_user_can( 'delete_term', $term_id ) ) {
			return;
		}

		Indexables::factory()->get( 'term' )->delete( $term_id, false );
	}

}
