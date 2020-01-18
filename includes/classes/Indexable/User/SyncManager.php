<?php
/**
 * Manage syncing of content between WP and Elasticsearch for users
 *
 * @since  3.0
 * @package elasticpress
 */

namespace ElasticPress\Indexable\User;

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
	 * @since 3.0
	 */
	public function setup() {
		if ( defined( 'WP_IMPORTING' ) && true === WP_IMPORTING ) {
			return;
		}

		if ( ! Elasticsearch::factory()->get_elasticsearch_version() ) {
			return;
		}

		add_action( 'delete_user', [ $this, 'action_delete_user' ] );
		add_action( 'wpmu_delete_user', [ $this, 'action_delete_user' ] );
		add_action( 'profile_update', [ $this, 'action_sync_on_update' ] );
		add_action( 'user_register', [ $this, 'action_sync_on_update' ] );
		add_action( 'updated_user_meta', [ $this, 'action_queue_meta_sync' ], 10, 4 );
		add_action( 'added_user_meta', [ $this, 'action_queue_meta_sync' ], 10, 4 );
		add_action( 'deleted_user_meta', [ $this, 'action_queue_meta_sync' ], 10, 4 );

		// @todo Handle deleted meta
	}

	/**
	 * When whitelisted meta is updated/added/deleted, queue the object for reindex
	 *
	 * @param  int       $meta_id Meta id.
	 * @param  int|array $object_id Object id.
	 * @param  string    $meta_key Meta key.
	 * @param  string    $meta_value Meta value.
	 * @since  2.0
	 */
	public function action_queue_meta_sync( $meta_id, $object_id, $meta_key, $meta_value ) {
		$indexable = Indexables::factory()->get( 'user' );

		$this->add_to_queue( $object_id );
	}

	/**
	 * Delete ES user when WP user is deleted
	 *
	 * @param int $user_id User ID
	 * @since 3.0
	 */
	public function action_delete_user( $user_id ) {
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		Indexables::factory()->get( 'user' )->delete( $user_id, false );
	}

	/**
	 * Sync ES index with what happened to the user being saved
	 *
	 * @param int $user_id User id.
	 * @since 3.0
	 */
	public function action_sync_on_update( $user_id ) {
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		/**
		 * Filter whether to kill sync for a particular user
		 *
		 * @hook ep_user_sync_kill
		 * @param {bool} $kill True means dont sync
		 * @param  {int} $user_id User ID
		 * @since  3.0
		 * @return  {bool} New kill value
		 */
		if ( apply_filters( 'ep_user_sync_kill', false, $user_id ) ) {
			return;
		}

		/**
		 * Fires before adding user to sync queue
		 *
		 * @hook ep_sync_user_on_transition
		 * @param  {int} $user_id User ID
		 * @since  3.0
		 */
		do_action( 'ep_sync_user_on_transition', $user_id );

		$this->add_to_queue( $user_id );
	}
}
