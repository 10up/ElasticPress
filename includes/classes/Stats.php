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

		$stats = $this->remote_request_helper( '_stats?format=json' );

		if ( empty( $stats ) || empty( $stats['_all'] ) || empty( $stats['_all']['total'] ) ) {
			return;
		}

		$this->localized['index_total']            = $stats['_all']['total']['indexing']['index_total'];
		$this->localized['index_time_in_millis']   = $stats['_all']['total']['indexing']['index_time_in_millis'];
		$this->localized['query_total']            = $stats['_all']['total']['search']['query_total'];
		$this->localized['query_time_in_millis']   = $stats['_all']['total']['search']['query_time_in_millis'];
		$this->localized['suggest_time_in_millis'] = $stats['_all']['total']['search']['suggest_time_in_millis'];
		$this->localized['suggest_total']          = $stats['_all']['total']['search']['suggest_total'];

		foreach ( $stats['indices'] as $index_name => $current_index ) {
			$this->localized['indices_data'][ $index_name ]['name'] = $index_name;
			$this->localized['indices_data'][ $index_name ]['docs'] = $stats['indices'][ $index_name ]['total']['docs']['count'];
		}

		$indices = $this->remote_request_helper( '_cat/indices?format=json' );

		if ( ! empty( $indices ) ) {
			foreach ( $indices as  $index ) {
				$this->health[ $index['index'] ]['name']   = $index['index'];
				$this->health[ $index['index'] ]['health'] = $index['health'];
			}
		}

		$this->totals['docs']   = $stats['_all']['total']['docs']['count'];
		$this->totals['size']   = $stats['_all']['total']['store']['size_in_bytes'];
		$this->totals['memory'] = $stats['_all']['primaries']['segments']['memory_in_bytes'];

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
