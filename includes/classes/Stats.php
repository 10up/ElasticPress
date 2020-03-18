<?php
/**
 * ElasticPress index health stats page handler
 *
 * @since  3.0
 * @package elasticpress
 */

namespace ElasticPress;

use ElasticPress\Utils as Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Stats
 *
 * @package ElasticPress
 */
class Stats {
	/**
	 * Index list with health data of current cluster
	 *
	 * @var array
	 * @since  3.0
	 */
	protected $health = [];

	/**
	 * Stats retrieved directly from the current cluster
	 *
	 * @var array
	 * @since 3.x
	 */
	protected $stats = [];

	/**
	 * Overall stats of the cluster
	 *
	 * @var array
	 * @since  3.2
	 */
	protected $totals = [
		'size'   => 0,
		'memory' => 0,
		'docs'   => 0,
	];

	/**
	 * Later localized data.
	 *
	 * Used for chart building purposes
	 *
	 * @var array
	 * @since 3.2
	 */
	protected $localized = [
		'index_total'            => 0,
		'index_time_in_millis'   => 0,
		'query_total'            => 0,
		'query_time_in_millis'   => 0,
		'suggest_time_in_millis' => 0,
		'suggest_total'          => 0,
		'indices_data'           => [],
	];

	/**
	 * Cluster node data.
	 *
	 * Used to determine cluster health
	 *
	 * @var int
	 * @since 3.2
	 */
	protected $nodes = 0;

	/**
	 * Makes an api call to elasticsearch endpoint
	 *
	 * @param  string $path Endpoint path to query
	 * @since 3.2
	 * @return array|mixed|object
	 */
	protected function remote_request_helper( $path ) {
		$request = Elasticsearch::factory()->remote_request( $path );

		if ( is_wp_error( $request ) || empty( $request ) ) {
			return false;
		}

		$body = wp_remote_retrieve_body( $request );

		return json_decode( $body, true );
	}

	/**
	 * Makes api calls and organizes data depending on the specified context.
	 *
	 * @param  boolean $force Force stats to be built even if cached
	 * @since 3.2
	 */
	public function build_stats( $force = false ) {
		static $stats_built = false;

		if ( $stats_built && ! $force ) {
			return;
		}

		$stats_built = true;

		$this->stats = $this->remote_request_helper( '_stats?format=json' );

		if ( empty( $this->stats ) || empty( $this->stats['_all'] ) || empty( $this->stats['_all']['total'] ) ) {
			return;
		}

		$this->populate_indices_stats();
		$this->populate_indices_averages();

		if ( Utils\is_epio() ) {
			$node_stats = $this->remote_request_helper( '_nodes/stats/discovery?format=json' );
		} else {
			$node_stats = $this->remote_request_helper( '_nodes/stats?format=json' );
		}

		if ( ! empty( $node_stats ) ) {
			$this->nodes = $node_stats['_nodes']['total'];
		}
	}

	/**
	 * Populate the instantiated object with the correct indices, based on context
	 *
	 * @since 3.x
	 */
	private function populate_indices_stats() {
		$network_activated = defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK;
		$sites[]           = Indexables::factory()->get( 'post' )->get_index_name( get_current_blog_id() );
		$indices           = $this->remote_request_helper( '_cat/indices?format=json' );
		$i = 1;

		if ( empty( $indices ) ) {
			return;
		}

		// if the plugin is network activated we only want the data from the indexable WP indexes, not any others
		if ( $network_activated ) {
			$sites = array();
			$indexable_sites = Utils\get_sites();
			foreach ( $indexable_sites as $site ) {
				$sites[] = Indexables::factory()->get( 'post' )->get_index_name( $site['blog_id'] );
				$sites[] = Indexables::factory()->get( 'term' )->get_index_name( $site['blog_id'] );
				$sites[] = Indexables::factory()->get( 'user' )->get_index_name( $site['blog_id'] );
			}
		}

		// Filter the general list of indices to contain only the ones we care about
		$filtered_indices = array_filter( $indices, function ( $index ) use ( $sites ) {
			return in_array( $index['index'], $sites, true );
		} );

		/**
		 * Allow sites to select which indices will be displayed in the Index Health page
		 *
		 * @param   {array} $filtered_indices Indices filtered to the site(s) being queried.
		 * @param   {array} $indices          All indices returned from Elasticsearch
		 *
		 * @return  {array} List of indices to use
		 *
		 * @since   3.x
		 * @hook    ep_index_health_stats_indices
		 */
		$filtered_indices = apply_filters( 'ep_index_health_stats_indices', $filtered_indices, $indices );

		foreach ( $filtered_indices as $index ) {
			$this->populate_index_stats( $index['index'], $index['health'], $i++ );
		}
	}

	/**
	 * Populate cluster performance data
	 *
	 * @since 3.x
	 */
	private function populate_indices_averages() {

		if ( empty( $this->stats['_all']['total'] ) ) {
			return;
		}

		// General cluster performance stats
		$this->localized['index_time_in_millis']   = $this->stats['_all']['total']['indexing']['index_time_in_millis'];
		$this->localized['query_time_in_millis']   = $this->stats['_all']['total']['search']['query_time_in_millis'];
		$this->localized['suggest_time_in_millis'] = $this->stats['_all']['total']['search']['suggest_time_in_millis'];
	}

	/**
	 * Populate index storage capacity and metrics
	 *
	 * @param string $index_name index name
	 * @param string $health     index health status
	 *
	 * @since 3.x
	 */
	private function populate_index_stats( $index_name, $health, $i ) {

		if ( empty( $this->stats['indices'][ $index_name ] ) ) {
			return;
		}

		// Index-specific data
		$this->health[ $index_name ]['name']   = $index_name;
		$this->health[ $index_name ]['health'] = $health;

		$this->localized['indices_data'][ $index_name ]['name'] = $index_name;
		$this->localized['indices_data'][ $index_name ]['docs'] = $this->stats['indices'][ $index_name ]['total']['docs']['count'];

		// General data counts
		$this->localized['index_total']   += absint( $this->stats['indices'][ $index_name ]['total']['indexing']['index_total'] );
		$this->localized['query_total']   += absint( $this->stats['indices'][ $index_name ]['total']['search']['query_total'] );
		$this->localized['suggest_total'] += absint( $this->stats['indices'][ $index_name ]['total']['search']['suggest_total'] );

		$this->totals['docs']   += absint( $this->stats['indices'][ $index_name ]['total']['docs']['count'] );
		$this->totals['size']   += absint( $this->stats['indices'][ $index_name ]['total']['store']['size_in_bytes'] );
		$this->totals['memory'] += absint( $this->stats['indices'][ $index_name ]['primaries']['segments']['memory_in_bytes'] );
	}

	/**
	 * Get index list and health data of an elasticsearch endpoint
	 *
	 * @return array
	 * @since 3.2
	 */
	public function get_health() {
		$this->build_stats();
		return $this->health;
	}

	/**
	 * Get number of nodes in the current cluster
	 *
	 * @since 3.2
	 * @return int
	 */
	public function get_nodes() {
		$this->build_stats();
		return $this->nodes;
	}

	/**
	 * Gets relevant total data of an elasticsearch endpoint
	 *
	 * @since 3.2
	 * @return array
	 */
	public function get_totals() {
		$this->build_stats();
		return $this->totals;
	}

	/**
	 * Gets localized data
	 *
	 * @since 3.2
	 * @return mixed Data used in localization for chart creation.
	 */
	public function get_localized() {
		$this->build_stats();
		return $this->localized;
	}

	/**
	 * Converts a number to a readable size format.
	 *
	 * @param  int $size Desired number to convert
	 * @since 3.2
	 * @return string Size with appended unit
	 */
	public function convert_to_readable_size( $size ) {
		if ( empty( $size ) ) {
			return 0;
		}

		$base   = log( $size ) / log( 1024 );
		$suffix = array( '', 'KB', 'MB', 'GB', 'TB' );
		$f_base = floor( $base );

		return round( pow( 1024, $base - floor( $base ) ), 1 ) . $suffix[ $f_base ];
	}

	/**
	 * Return singleton instance of class
	 *
	 * @return self
	 * @since 3.2
	 */
	public static function factory() {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new self();
		}

		return $instance;
	}
}
