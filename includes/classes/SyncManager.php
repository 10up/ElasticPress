<?php
/**
 * SyncManager common functionality
 *
 * @package  elasticpress
 * @since  3.0
 */

namespace ElasticPress;

use ElasticPress\Utils;

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
			 * This is sometimes useful when posts are generated programmatically.
			 */
			add_action( 'ep_after_add_to_queue', [ $this, 'index_sync_on_chunk_limit' ] );
		}
		/**
		 * We do all syncing on shutdown or redirect
		 */
		add_action( 'shutdown', [ $this, 'index_sync_queue' ] );
		add_filter( 'wp_redirect', [ $this, 'index_sync_queue_on_redirect' ], 10, 1 );

		/**
		 * Actions for multisite
		 */
		add_action( 'delete_blog', array( $this, 'action_delete_blog_from_index' ) );
		add_action( 'make_delete_blog', array( $this, 'action_delete_blog_from_index' ) );
		add_action( 'make_spam_blog', array( $this, 'action_delete_blog_from_index' ) );
		add_action( 'archive_blog', array( $this, 'action_delete_blog_from_index' ) );
		add_action( 'deactivate_blog', array( $this, 'action_delete_blog_from_index' ) );

		// Implemented by children.
		$this->setup();
	}

	/**
	 * Get sync queue.
	 *
	 * @since 5.0.0
	 * @param int $blog_id Blog ID to retrieve queue.
	 * @return array
	 */
	public function get_sync_queue( $blog_id = false ) {
		if ( ! $blog_id ) {
			$blog_id = get_current_blog_id();
		}

		if ( ! isset( $this->sync_queue[ $blog_id ] ) ) {
			$this->sync_queue[ $blog_id ] = [];
		}

		return $this->sync_queue[ $blog_id ];
	}

	/**
	 * Add an object to the sync queue.
	 *
	 * @since 3.1.2
	 *
	 * @param int $object_id Object ID to sync.
	 * @return boolean
	 */
	public function add_to_queue( $object_id ) {
		if ( ! is_numeric( $object_id ) ) {
			return false;
		}

		$current_blog_id = get_current_blog_id();
		if ( ! isset( $this->sync_queue[ $current_blog_id ] ) ) {
			$this->sync_queue[ $current_blog_id ] = [];
		}

		$this->sync_queue[ $current_blog_id ][ $object_id ] = true;

		/**
		 * Fires after item is added to sync queue
		 *
		 * @hook ep_after_add_to_queue
		 * @param  {int} $object_id ID of object
		 * @param  {array} $sync_queue Current sync queue
		 * @since  3.1.2
		 */
		do_action( 'ep_after_add_to_queue', $object_id, $this->get_sync_queue() );

		return true;
	}

	/**
	 * Remove an object from the sync queue.
	 *
	 * @since 3.5
	 *
	 * @param int $object_id Object ID to remove from the queue.
	 * @return boolean
	 */
	public function remove_from_queue( $object_id ) {
		if ( ! is_numeric( $object_id ) ) {
			return false;
		}

		$current_blog_id = get_current_blog_id();
		if ( ! isset( $this->sync_queue[ $current_blog_id ] ) ) {
			$this->sync_queue[ $current_blog_id ] = [];
		}

		unset( $this->sync_queue[ $current_blog_id ][ $object_id ] );

		/**
		 * Fires after item is removed from sync queue
		 *
		 * @hook ep_after_remove_from_queue
		 * @param  {int} $object_id ID of object
		 * @param  {array} $sync_queue Current sync queue
		 * @since  3.5
		 */
		do_action( 'ep_after_remove_from_queue', $object_id, $this->get_sync_queue() );

		return true;
	}

	/**
	 * Reset the sync queue.
	 *
	 * @since 5.0.0
	 * @param int $blog_id Blog ID to reset queue
	 */
	public function reset_sync_queue( $blog_id = false ) {
		if ( ! $blog_id ) {
			$blog_id = get_current_blog_id();
		}

		$this->sync_queue[ $blog_id ] = [];
	}

	/**
	 * Sync queued objects if the EP_SYNC_CHUNK_LIMIT is reached.
	 *
	 * @since 3.1.2
	 * @return boolean
	 */
	public function index_sync_on_chunk_limit() {
		if ( defined( 'EP_SYNC_CHUNK_LIMIT' ) && is_numeric( EP_SYNC_CHUNK_LIMIT ) &&
			is_array( $this->get_sync_queue() ) && count( $this->get_sync_queue() ) > EP_SYNC_CHUNK_LIMIT ) {
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

		$current_blog_id = get_current_blog_id();
		foreach ( $this->sync_queue as $blog_id => $sync_queue ) {
			if ( $current_blog_id !== $blog_id ) {
				switch_to_blog( $blog_id );
			}

			/**
			 * Allow other code to intercept the sync process
			 *
			 * @hook pre_ep_index_sync_queue
			 * @param {boolean} $bail True to skip the rest of index_sync_queue(), false to continue normally
			 * @param {SyncManager} $sync_manager SyncManager instance for the indexable
			 * @param {string} $indexable_slug Slug of the indexable being synced
			 * @since 3.5
			 */
			if ( apply_filters( 'pre_ep_index_sync_queue', false, $this, $this->indexable_slug ) ) {
				return;
			}

			/**
			 * Backwards compat for pre-3.0
			 */
			foreach ( $sync_queue as $object_id => $value ) {
				/**
				 * Fires when object in queue are synced
				 *
				 * @hook ep_sync_on_meta_update_queue
				 * @param  {int} $object_id ID of object
				 */
				do_action( 'ep_sync_on_meta_update', $object_id );
			}

			// Bulk sync them all.
			Indexables::factory()->get( $this->indexable_slug )->bulk_index_dynamically( array_keys( $this->get_sync_queue( $blog_id ) ) );

			/**
			 * Make sure to reset sync queue in case an shutdown happens before a redirect
			 * when a redirect has already been triggered.
			 */
			$this->reset_sync_queue( $blog_id );

			if ( $current_blog_id !== $blog_id ) {
				restore_current_blog();
			}
		}
	}

	/**
	 * Check if we can index content in the current blog
	 *
	 * @since 3.5
	 * @return boolean
	 */
	public function can_index_site() {
		if ( ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) && ! Utils\is_site_indexable() ) {
			$this->tear_down();
			return false;
		}

		return true;
	}

	/**
	 * Determine whether syncing an indexable should take place.
	 *
	 * Returns true or false depending on the value of the WP_IMPORTING global.
	 * Contains the 'ep_sync_indexable_kill' filter that enables overriding the default behavior.
	 *
	 * @since 3.4.2
	 * @return bool
	 */
	public function kill_sync() {

		$is_importing = defined( 'WP_IMPORTING' ) && true === WP_IMPORTING;

		/**
		 * Filter whether to bypass sync.
		 *
		 * @since 3.4.2
		 * @hook  ep_sync_indexable_kill
		 * @param {boolean} $kill True if WP_IMPORTING is defined and true, else false.
		 * @param {array} $indexable_slug Indexable slug.
		 */
		return apply_filters( 'ep_sync_indexable_kill', $is_importing, $this->indexable_slug );
	}

	/**
	 * Remove blog from index when a site is deleted, archived, or deactivated
	 *
	 * @param int $blog_id WP Blog ID.
	 */
	public function action_delete_blog_from_index( $blog_id ) {
		if ( $this->kill_sync() ) {
			return;
		}

		$indexable = Indexables::factory()->get( $this->indexable_slug );

		// Don't delete global indexes
		if ( $indexable->global ) {
			return;
		}

		/**
		 * Filter to whether to keep index on site deletion
		 *
		 * @hook ep_keep_index
		 * @since 3.0
		 * @since 3.6.2 Moved from Post\SyncManager to the main SyncManager class
		 * @since 3.6.5 Added `$blog_id` and `$indexable_slug`
		 * @param {bool}   $keep           True means don't delete index
		 * @param {int}    $blog_id        WP Blog ID
		 * @param {string} $indexable_slug Indexable slug
		 * @return {bool} New value
		 */
		if ( $indexable->index_exists( $blog_id ) && ! apply_filters( 'ep_keep_index', false, $blog_id, $this->indexable_slug ) ) {
			$indexable->delete_index( $blog_id );
		}
	}

	/**
	 * Clear the cache of the total fields limit
	 *
	 * @since 4.7.0
	 */
	public function clear_index_settings_cache() {
		$indexable = Indexables::factory()->get( $this->indexable_slug );
		$cache_key = 'ep_index_settings_' . $indexable->get_index_name();

		Utils\delete_transient( $cache_key );
	}

	/**
	 * Implementation should setup hooks/filters
	 *
	 * @since 3.0
	 */
	abstract public function setup();

	/**
	 * Implementation (for multisite) should un-setup hooks/filters if applicable.
	 *
	 * @since 4.0
	 */
	abstract public function tear_down();
}
