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
		if ( ! Elasticsearch::factory()->get_elasticsearch_version() ) {
			return;
		}

		if ( ! $this->can_index_site() ) {
			return;
		}

		add_action( 'created_term', [ $this, 'action_sync_on_update' ] );
		add_action( 'edited_terms', [ $this, 'action_sync_on_update' ] );
		add_action( 'added_term_meta', [ $this, 'action_queue_meta_sync' ], 10, 2 );
		add_action( 'deleted_term_meta', [ $this, 'action_queue_meta_sync' ], 10, 2 );
		add_action( 'updated_term_meta', [ $this, 'action_queue_meta_sync' ], 10, 2 );
		add_action( 'pre_delete_term', [ $this, 'action_queue_children_sync' ] );
		add_action( 'pre_delete_term', [ $this, 'action_sync_on_delete' ] );
		add_action( 'set_object_terms', [ $this, 'action_sync_on_object_update' ], 10, 2 );
	}

	/**
	 * Sync ES index with changes to the term being saved
	 *
	 * @param int $term_id Term ID.
	 * @since 3.1
	 */
	public function action_sync_on_update( $term_id ) {
		if ( $this->kill_sync() ) {
			return;
		}

		if ( ! current_user_can( 'edit_term', $term_id ) ) {
			return;
		}

		if ( apply_filters( 'ep_term_sync_kill', false, $term_id ) ) {
			return;
		}

		do_action( 'ep_sync_term_on_transition', $term_id );

		$this->sync_queue[ $term_id ] = true;

		// Find all terms in the hierarchy so we resync those as well
		$term      = get_term( $term_id );
		$children  = get_term_children( $term_id, $term->taxonomy );
		$ancestors = get_ancestors( $term_id, $term->taxonomy, 'taxonomy' );
		$hierarchy = array_merge( $ancestors, $children );

		foreach ( $hierarchy as $hierarchy_term_id ) {
			if ( ! current_user_can( 'edit_term', $hierarchy_term_id ) ) {
				return;
			}

			if ( apply_filters( 'ep_term_sync_kill', false, $hierarchy_term_id ) ) {
				return;
			}

			do_action( 'ep_sync_term_on_transition', $hierarchy_term_id );

			$this->sync_queue[ $hierarchy_term_id ] = true;
		}
	}

	/**
	 * When term relationships are updated, queue the terms for reindex
	 *
	 * @param int   $object_id Object ID.
	 * @param array $terms An array of term objects.
	 * @since 3.1
	 */
	public function action_sync_on_object_update( $object_id, $terms ) {
		if ( $this->kill_sync() ) {
			return;
		}

		if ( empty( $terms ) ) {
			return;
		}

		foreach ( $terms as $term ) {
			$term_info = term_exists( $term );

			if ( ! $term_info ) {
				continue;
			}

			$term = get_term( $term_info );

			if ( ! current_user_can( 'edit_term', $term->term_id ) ) {
				return;
			}

			if ( apply_filters( 'ep_term_sync_kill', false, $term->term_id ) ) {
				return;
			}

			do_action( 'ep_sync_term_on_transition', $term->term_id );

			$this->sync_queue[ $term->term_id ] = true;

			// Find all terms in the hierarchy so we resync those as well
			$children  = get_term_children( $term->term_id, $term->taxonomy );
			$ancestors = get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' );
			$hierarchy = array_merge( $ancestors, $children );

			foreach ( $hierarchy as $hierarchy_term_id ) {
				if ( ! current_user_can( 'edit_term', $hierarchy_term_id ) ) {
					return;
				}

				if ( apply_filters( 'ep_term_sync_kill', false, $hierarchy_term_id ) ) {
					return;
				}

				do_action( 'ep_sync_term_on_transition', $hierarchy_term_id );

				$this->sync_queue[ $hierarchy_term_id ] = true;
			}
		}
	}

	/**
	 * When term meta is updated/added/deleted, queue the term for reindex
	 *
	 * @param int $meta_id Meta ID.
	 * @param int $term_id Term ID.
	 * @since 3.1
	 */
	public function action_queue_meta_sync( $meta_id, $term_id ) {
		if ( $this->kill_sync() ) {
			return;
		}

		$this->sync_queue[ $term_id ] = true;
	}

	/**
	 * Delete term from ES when deleted in WP
	 *
	 * @param int $term_id Term ID.
	 * @since 3.1
	 */
	public function action_sync_on_delete( $term_id ) {
		if ( $this->kill_sync() ) {
			return;
		}

		if ( ! current_user_can( 'delete_term', $term_id ) ) {
			return;
		}

		Indexables::factory()->get( 'term' )->delete( $term_id, false );
	}

	/**
	 * Enqueue sync of children terms in hierchy when deleting parent. Children terms will be reasigned to
	 * a different parent and we want to reflect that change in ElasticSearch
	 *
	 * @param int $term_id Term ID.
	 * @since 3.6.3
	 */
	public function action_queue_children_sync( $term_id ) {
		if ( $this->kill_sync() ) {
			return;
		}

		// Find all terms in the hierarchy so we resync those as well
		$term      = get_term( $term_id );
		$children  = get_term_children( $term->term_id, $term->taxonomy );
		$ancestors = get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' );
		$hierarchy = array_merge( $ancestors, $children );

		foreach ( $hierarchy as $hierarchy_term_id ) {
			if ( apply_filters( 'ep_term_sync_kill', false, $hierarchy_term_id ) ) {
				return;
			}

			if ( ! current_user_can( 'edit_term', $term_id ) && ! apply_filters( 'ep_sync_insert_permissions_bypass', false, $term_id, 'term' ) ) {
				continue;
			}

			do_action( 'ep_sync_term_on_transition', $hierarchy_term_id );

			$this->sync_queue[ $hierarchy_term_id ] = true;
		}
	}

}
