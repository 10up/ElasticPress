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
	protected $health_data;

	/**
	 * Overall stats of the cluster
	 *
	 * @var array
	 * @since  3.0
	 */
	protected $totals;

	/**
	 * Later localized data.
	 *
	 * Used for chart building purposes
	 *
	 * @var array
	 * @since 3.0
	 */
	protected $localized_data;

	/**
	 * Cluster node data.
	 *
	 * Used to determine cluster health
	 *
	 * @var int
	 * @since 3.0
	 */
	protected $nodes;

	/**
	 * Makes an api call to elasticsearch endpoint
	 *
	 * @param  string $endpoint_url Elasticsearch endpoint
	 * @param  string $additional_endpoint Any additional endpoints to append
	 * @return array|mixed|object
	 */
	protected function api_call( $endpoint_url, $additional_endpoint ) {
		$request_args = array( 'headers' => Elasticsearch::factory()->format_request_headers() );

		try {
			$query = http_build_query( [ 'format' => 'json' ], null, '&', PHP_QUERY_RFC3986 );
			$url   = trailingslashit( $endpoint_url ) . trailingslashit( $additional_endpoint ) . '?' . $query;

			$api_call = wp_remote_get( $url, $request_args );
			if ( is_wp_error( $api_call ) ) {
				$result = [
					'success' => false,
					'errors'  => $api_call->get_error_message(),
				];
			} else {
				$result = json_decode( $api_call['body'] );
			}
		} catch ( \Exception $e ) {
			$result = [
				'success' => false,
				'errors'  => $e->getMessage(),
			];
		}
		return $result;
	}

	/**
	 * Makes api calls and organizes data depending on the specified context.
	 *
	 * @param string $context Needed context to retrieve specific data
	 */
	public function retrieve_endpoint_data( $context ) {
		if ( in_array( $context, [ 'localize', 'totals', 'health', 'nodes' ], true ) ) {
			$host  = Utils\get_host();
			$stats = $this->api_call( $host, '_stats' );

			if ( 'localize' === $context ) {
				$this->localized_data['index_total']            = $stats->_all->total->indexing->index_total;
				$this->localized_data['index_time_in_millis']   = $stats->_all->total->indexing->index_time_in_millis;
				$this->localized_data['query_total']            = $stats->_all->total->search->query_total;
				$this->localized_data['query_time_in_millis']   = $stats->_all->total->search->query_time_in_millis;
				$this->localized_data['suggest_time_in_millis'] = $stats->_all->total->search->suggest_time_in_millis;
				$this->localized_data['suggest_total']          = $stats->_all->total->search->suggest_total;

				foreach ( $stats->indices as $index_name => $current_index ) {
					$this->localized_data['indices_data'][ $index_name ]['name'] = $index_name;
					$this->localized_data['indices_data'][ $index_name ]['docs'] = $stats->indices->$index_name->total->docs->count;
				}
			} elseif ( 'health' === $context ) {
				$this->health_data = [];
				$indices           = $this->api_call( $host, '_cat/indices' );

				foreach ( $indices as  $index ) {
					$this->health_data[ $index->index ]['name']   = $index->index;
					$this->health_data[ $index->index ]['health'] = $index->health;
				}
			} elseif ( 'totals' === $context ) {
				$this->totals           = [];
				$this->totals['docs']   = $stats->_all->total->docs->count;
				$this->totals['size']   = $stats->_all->total->store->size_in_bytes;
				$this->totals['memory'] = $stats->_all->primaries->segments->memory_in_bytes;
			} elseif ( 'nodes' === $context ) {
				if ( Utils\is_epio() ) {
					$node_stats  = $this->api_call( $host, '_nodes/stats/discovery' );
					$this->nodes = $node_stats->_nodes->total;
				} else {
					$node_stats  = $this->api_call( $host, '_nodes/stats' );
					$this->nodes = $node_stats->_nodes->total;
				}
			}
		}
	}

	/**
	 * Get index list and health data of an elasticsearch endpoint
	 *
	 * @return array
	 */
	public function get_health() {
		$this->retrieve_endpoint_data( 'health' );
		return $this->health_data;
	}

	/**
	 * Get number of nodes in the current cluster
	 *
	 * @return int
	 */
	public function get_nodes() {
		$this->retrieve_endpoint_data( 'nodes' );
		return $this->nodes;
	}

	/**
	 * Gets relevant total data of an elasticsearch endpoint
	 *
	 * @return array
	 */
	public function get_totals() {
		$this->retrieve_endpoint_data( 'totals' );
		return $this->totals;
	}

	/**
	 * Gets localized data
	 *
	 * @return mixed Data used in localization for chart creation.
	 */
	public function get_localized() {
		$this->retrieve_endpoint_data( 'localize' );
		return $this->localized_data;
	}

	/**
	 * Converts a number to a readable size format.
	 *
	 * @param  int $size Desired number to convert
	 * @return string Size with appended unit
	 */
	public function convert_to_readable_size( $size ) {
		$base   = log( $size ) / log( 1024 );
		$suffix = array( '', 'KB', 'MB', 'GB', 'TB' );
		$f_base = floor( $base );
		return round( pow( 1024, $base - floor( $base ) ), 1 ) . $suffix[ $f_base ];
	}

	/**
	 * Return singleton instance of class
	 *
	 * @return self
	 * @since 3.0
	 */
	public static function factory() {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new self();
		}

		return $instance;
	}
}
