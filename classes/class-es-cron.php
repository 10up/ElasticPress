<?php

class ES_Cron {

	public function __construct() {
		add_action( 'es_sync', array( $this, 'sync' ) );
		add_action( 'init', array( $this, 'schedule_events' ) );
	}

	/**
	 * Setup cron jobs
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function schedule_events() {
		$timestamp = wp_next_scheduled( 'es_sync' );

		if ( ! $timestamp ) {
			wp_schedule_event( time(), 'elasticsearch', 'es_sync' );
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
		$schedules['elasticsearch'] = array(
			'interval' => ( 60 * 15 ),
			'display' => 'Every 30 minutes',
		);

		return $schedules;
	}

	/**
	 * Return singleton instance of class
	 *
	 * @since 0.1.0
	 * @return ES_Cron
	 */
	public static function factory() {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new self();
		}

		return $instance;
	}

	/**
	 * Send posts to Elasticsearch for indexing
	 *
	 * @since 0.1.0
	 */
	public function sync() {
		es_full_sync();
	}

}

ES_Cron::factory();