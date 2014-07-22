<?php

class EP_Cron {

	/**
	 * Placeholder method
	 *
	 * @since 0.1.0
	 */
	public function __construct() { }

	/**
	 * Setup actions and filters
	 *
	 * @since 0.1.2
	 */
	public function setup() {
		add_action( 'ep_sync', array( $this, 'sync' ) );
	}

	/**
	 * Return singleton instance of class
	 *
	 * @since 0.1.0
	 * @return EP_Cron
	 */
	public static function factory() {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new self();
			$instance->setup();
		}

		return $instance;
	}

	/**
	 * Send posts to Elasticsearch for indexing
	 *
	 * @since 0.1.0
	 */
	public function sync() {
		wp_clear_scheduled_hook( 'ep_sync' );
		// Do index flush
		// get proper site ID
		ep_flush( null );

		// put mapping
		ep_put_mapping( null );

		ep_full_sync();

		// @todo report that syncing has completed (either successfully or unsuccessfully)

	}

}

EP_Cron::factory();