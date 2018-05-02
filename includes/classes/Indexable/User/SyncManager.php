<?php
/**
 * Manage syncing of content between WP and Elasticsearch for users
 *
 * @since  2.6
 * @package elasticpress
 */

namespace ElasticPress\Indexable\User;

use ElasticPress\Indexables as Indexables;
use ElasticPress\Elasticsearch as Elasticsearch;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class SyncManager {

	/**
	 * Save objects for indexing later
	 *
	 * @since  2.6
	 * @var    array
	 */
	public $sync_queue = [];

	/**
	 * Setup actions and filters
	 *
	 * @since 2.6
	 */
	public function setup() {
		add_action( 'shutdown', [ $this, 'action_index_sync_queue' ] );
		add_action( 'delete_user', [ $this, 'action_delete_user' ] );
		add_action( 'profile_update', [ $this, 'action_sync_on_update' ] );
		add_action( 'user_register', [ $this, 'action_sync_on_update' ] );
		add_action( 'updated_user_meta', array( $this, 'action_queue_meta_sync' ), 10, 4 );
		add_action( 'added_user_meta', array( $this, 'action_queue_meta_sync' ), 10, 4 );

		/**
		 * @todo Handle deleted meta
		 */
	}

	/**
	 * Sync queued objects on shutdown. We do this in case a post is updated multiple times.
	 *
	 * @since  2.6
	 */
	public function action_index_sync_queue() {
		if ( empty( $this->sync_queue ) ) {
			return;
		}

		foreach ( $this->sync_queue as $object_id => $value ) {
			do_action( 'ep_sync_users_on_meta_update', $object_id );

			Indexables::factory()->get( 'user' )->index( $object_id, false );
		}
	}

	/**
	 * When whitelisted meta is updated, queue the object for reindex
	 *
	 * @param  int $meta_id
	 * @param  int $object_id
	 * @param  string $meta_key
	 * @param  string $meta_value
	 * @since  2.0
	 */
	public function action_queue_meta_sync( $meta_id, $object_id, $meta_key, $meta_value ) {
		global $importer;

		if ( ! Elasticsearch::factory()->get_elasticsearch_version() ) {
			return;
		}

		// If we have an importer we must be doing an import - let's abort
		if ( ! empty( $importer ) ) {
			return;
		}

		$indexable = Indexables::factory()->get( 'user' );

		$prepared_document = $indexable->prepare_document( $object_id );

		// Make sure meta key that was changed is actually relevant
		if ( ! isset( $prepared_document['meta'][ $meta_key ] ) ) {
			return;
		}

		$this->sync_queue[ $object_id ] = true;
	}

	/**
	 * Delete ES user when WP user is deleted
	 *
	 * @param int $post_id
	 * @since 2.6
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
	 * @param $post_ID
	 * @since 2.6
	 */
	public function action_sync_on_update( $user_id ) {
		global $importer;

		// If we have an importer we must be doing an import - let's abort
		if ( ! empty( $importer ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		$this->sync_queue[ $object_id ] = true;
	}

	/**
	 * Return a singleton instance of the current class
	 *
	 * @since 0.1.0
	 * @return SyncManager
	 */
	public static function factory() {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new self();
			$instance->setup();
		}

		return $instance;
	}
}
