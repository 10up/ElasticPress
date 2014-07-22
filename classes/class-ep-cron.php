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
		add_action( 'init', array( $this, 'schedule_events' ) );
	}

	/**
	 * Setup cron jobs
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function schedule_events() {
		$timestamp = wp_next_scheduled( 'ep_sync' );

		// @todo remove this 1==1
		if ( ! $timestamp || 1==1 ) {
			wp_schedule_single_event( time(), 'ep_sync' );
		}
	}

	/**
	 * Add custom cron schedule
	 *
	 * @param array $schedules
	 * @since 0.1.0
	 * @return array
	 */
	public function filter_cron_schedules( $schedules ) {
		// @todo this schedules the sync to start 30 minutes from now, probably want to fix this to make this happen immediately instead. use EWP as inspiration
		$schedules['elasticsearch'] = array(
			'interval' => ( 60 * 15 ),
			'display' => __( 'Every 30 minutes' , 'elasticpress' ),
		);

		return $schedules;
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
		ep_full_sync();
	}

}

EP_Cron::factory();