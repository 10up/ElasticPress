<?php

class EP_Sync_Status {
	private $single_site_status = array(
		'posts_processed' => 0,
		'start_time' => 0,
	);

	/**
	 * Do nothing
	 *
	 * @since 0.1.0
	 */
	public function __construct() { }


	/**
	 * Get current sync status for a specific site or the global setup. Site sync status is
	 * stored in the option under each site id. The global cross-site index status is stored
	 * in the 0th key of the option. We use get_site_option since it will default to get_option
	 * in the event where multi-site is not setup. All syncs are stored in one option.
	 *
	 * null => current site id
	 * 0 => N/A
	 * 1 >= specific site
	 *
	 * @param int $site_id
	 * @since 0.1.0
	 * @return array
	 */
	public function get_status( $site_id = null ) {
		$option = get_site_option( 'ep_status_by_site', array() );

		if ( empty( $site_id ) ) {
			$site_id = get_current_blog_id();
		}

		if ( isset( $option[$site_id] ) ) {
			return $option[$site_id];
		}

		return $this->single_site_status;
	}

	/**
	 * Update sync for a given site or the global setup ($site_id = 0). We use
	 * update_site_option since it will default to update_option when multi-site
	 * is not setup.
	 *
	 * @param array|int|string|object $status
	 * @param int $site_id
	 * @return bool
	 */
	public function update_status( $status, $site_id = null ) {
		$option = get_site_option( 'ep_status_by_site', array() );

		if ( empty( $site_id ) ) {
			$site_id = get_current_blog_id();
		}

		$option[$site_id] = $status;

		return update_site_option( 'ep_status_by_site', $option );
	}

	/**
	 * Check if a sync is in progress for a given site or globally ($site_id = 0)
	 *
	 * @param int $site_id
	 * @since 0.1.0
	 * @return bool
	 */
	public function is_sync_alive( $site_id = null ) {
		if ( empty( $site_id ) ) {
			$site_id = get_current_blog_id();
		}

		$sync_status = $this->get_status( $site_id );

		return ( ! empty( $sync_status['start_time'] ) );
	}

	/**
	 * Return how many syncs are alive cross-network
	 *
	 * @since 0.1.0
	 * @return int
	 */
	public function get_alive_sync_count() {
		$sites = wp_get_sites();
		$alive_syncs = 0;

		foreach ( $sites as $site ) {
			if ( $this->is_sync_alive( $site['blog_id'] ) ) {
				$alive_syncs++;
			}
		}

		return $alive_syncs;
	}

	/**
	 * Reset a sync for a specific site or globally
	 *
	 * @param int $site_id
	 * @since 0.1.0
	 */
	public function reset_sync( $site_id = null ) {
		if ( empty( $site_id ) ) {
			$site_id = get_current_blog_id();
		}

		$this->update_status( $this->single_site_status, $site_id );
	}

	/**
	 * Return a singleton instance of the current class
	 *
	 * @since 0.1.0
	 * @return EP_Sync_Status
	 */
	public static function factory() {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new self();
		}

		return $instance;
	}
}

EP_Sync_Status::factory();

/**
 * Accessor functions for methods in above class. See doc blocks above for function details.
 */

function ep_get_sync_status( $site_id = null ) {
	return EP_Sync_Status::factory()->get_status( $site_id );
}

function ep_update_sync_status( $status, $site_id = null ) {
	return EP_Sync_Status::factory()->update_status( $status, $site_id );
}

function ep_get_alive_sync_count() {
	return EP_Sync_Status::factory()->get_alive_sync_count();
}

function ep_reset_sync( $site_id = null ) {
	return EP_Sync_Status::factory()->reset_sync( $site_id );
}

function ep_is_sync_alive( $site_id = null ) {
	return EP_Sync_Status::factory()->is_sync_alive( $site_id );
}