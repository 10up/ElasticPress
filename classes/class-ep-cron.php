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
		ep_do_syncs( true );
	}

}

EP_Cron::factory();