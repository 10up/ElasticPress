<?php
/**
 * SyncManager common functionality
 *
 * @package  elasticpress
 * @since  3.0
 */

namespace ElasticPress;

/**
 * Abstract sync manager class to be extended for each indexable
 */
abstract class SyncManager {

	/**
	 * Save objects for indexing later
	 *
	 * @since  3.0
	 * @var    array
	 */
	public $sync_queue = [];

	/**
	 * Indexable slug
	 *
	 * @var   string
	 * @since 3.0
	 */
	public $indexable_slug;

	/**
	 * Create new SyncManager
	 *
	 * @param string $indexable_slug Indexable slug.
	 * @since  3.0
	 */
	public function __construct( $indexable_slug ) {
		$this->indexable_slug = $indexable_slug;

		if ( defined( 'EP_SYNC_CHUNK_LIMIT' ) && is_numeric( EP_SYNC_CHUNK_LIMIT ) ) {
			/**
			 * We also sync when we exceed Chunk limit set.
			 * This is sometimes useful when posts are generated programatically.
			 */
			add_action( 'ep_after_add_to_queue', [ $this, 'index_sync_on_chunk_limit' ] );
		}
		/**
		 * We do all syncing on shutdown or redirect
		 */
		add_action( 'shutdown', [ $this, 'index_sync_queue' ] );
		add_filter( 'wp_redirect', [ $this, 'index_sync_queue_on_redirect' ], 10, 1 );

		// Implemented by children.
		$this->setup();
	}

	/**
	 * Add an object to the sync queue.
	 *
	 * @param  id $object_id object ID to sync
	 * @since  3.1.2
	 * @return boolean
	 */
	public function add_to_queue( $object_id ) {
		if ( ! is_numeric( $object_id ) ) {
			return false;
		}
		$this->sync_queue[ $object_id ] = true;

		/**
		 * Fires after item is added to sync queue
		 *
		 * @hook ep_after_add_to_queue
		 * @param  {int} $object_id ID of object
		 * @param  {array} $sync_queue Current sync queue
		 * @since  3.1.2
		 */
		do_action( 'ep_after_add_to_queue', $object_id, $this->sync_queue );

		return true;
	}

	/**
	 * Sync queued objects if the EP_SYNC_CHUNK_LIMIT is reached.
	 *
	 * @since 3.1.2
	 * @return boolean
	 */
	public function index_sync_on_chunk_limit() {
		if ( defined( 'EP_SYNC_CHUNK_LIMIT' ) && is_numeric( EP_SYNC_CHUNK_LIMIT ) &&
			is_array( $this->sync_queue ) && count( $this->sync_queue ) > EP_SYNC_CHUNK_LIMIT ) {
			$this->index_sync_queue();
		}
		return true;
	}

	/**
	 * Sync queued objects before a redirect occurs. Hackish but very important since
	 * shutdown won't be firing
	 *
	 * @param  string $location Redirect location.
	 * @since  3.0
	 * @return string
	 */
	public function index_sync_queue_on_redirect( $location ) {
		$this->index_sync_queue();

		return $location;
	}


	/**
	 * Sync objects in queue.
	 *
	 * @since  3.0
	 */
	public function index_sync_queue() {
		if ( empty( $this->sync_queue ) ) {
			return;
		}

		/**
		 * Backwards compat for pre-3.0
		 */
		foreach ( $this->sync_queue as $object_id => $value ) {
			/**
			 * Fires when object in queue are synced
			 *
			 * @hook ep_sync_on_meta_update_queue
			 * @param  {int} $object_id ID of object
			 */
			do_action( 'ep_sync_on_meta_update', $object_id );
		}

		// Bulk sync them all.
		Indexables::factory()->get( $this->indexable_slug )->bulk_index( array_keys( $this->sync_queue ) );

		/**
		 * Make sure to reset sync queue in case an shutdown happens before a redirect
		 * when a redirect has already been triggered.
		 */
		$this->sync_queue = [];
	}

	/**
	 * Implementation should setup hooks/filters
	 *
	 * @since 3.0
	 */
	abstract public function setup();
}
