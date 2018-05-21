<?php
/**
 * Indexable abstract class.
 *
 * An indexable is a type of "data" in WP e.g. post type, term, user, etc.
 *
 * @since  2.6
 * @package elasticpress
 */

namespace ElasticPress;

use ElasticPress\Elasticsearch as Elasticsearch;
use ElasticPress\SyncManager as SyncManager;
use ElasticPress\QueryIntegration as QueryIntegration;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * An indexable is essentially a document type that can be indexed
 * and queried against
 *
 * @since  2.6
 */
abstract class Indexable {

	/**
	 * Declaring an Indexable global means it won't have an index for each blog in
	 * the network. Instead it will just have one index. There will also be no
	 * network alias.
	 *
	 * @var boolean
	 * @since  2.6
	 */
	public $global = false;

	/**
	 * Instance of SyncManager. This should handle automated syncing of indexable
	 * objects.
	 *
	 * @var SyncManager
	 * @since  2.6
	 */
	public $sync_manager;

	/**
	 * Instance of QueryIntegration. This should handle integrating with a default
	 * WP query.
	 *
	 * @var QueryIntegration
	 * @since  2.6
	 */
	public $query_integration;

	/**
	 * Create a new Indexable
	 *
	 * @since  2.6
	 */
	/*
	public function __construct() {
		$this->sync_manager = new SyncManager( $this->slug );
		$this->query_integration = new QueryIntegration( $this->slug );
	}*/

	/**
	 * Get the name of the index. Each indexable needs a unique index name
	 *
	 * @param  int $blog_id `null` means current blog.
	 * @since  2.6
	 * @return string
	 */
	public function get_index_name( $blog_id = null ) {
		if ( $this->global ) {
			$site_url = network_site_url();

			if ( ! empty( $site_url ) ) {
				$index_name = preg_replace( '#https?://(www\.)?#i', '', $site_url );
				$index_name = preg_replace( '#[^\w]#', '', $index_name ) . '-' . $this->slug;
			} else {
				$index_name = false;
			}
		} else {
			if ( ! $blog_id ) {
				$blog_id = get_current_blog_id();
			}

			$site_url = get_site_url( $blog_id );

			if ( ! empty( $site_url ) ) {
				$index_name = preg_replace( '#https?://(www\.)?#i', '', $site_url );
				$index_name = preg_replace( '#[^\w]#', '', $index_name ) . '-' . $this->slug . '-' . $blog_id;
			} else {
				$index_name = false;
			}
		}

		if ( defined( 'EP_INDEX_PREFIX' ) && EP_INDEX_PREFIX ) {
			$index_name = EP_INDEX_PREFIX . $index_name;
		}

		return apply_filters( 'ep_index_name', $index_name, $blog_id, $this );
	}

	/**
	 * Get unique indexable network alias
	 *
	 * @since  2.6
	 * @return string
	 */
	public function get_network_alias() {
		$url  = network_site_url();
		$slug = preg_replace( '#https?://(www\.)?#i', '', $url );
		$slug = preg_replace( '#[^\w]#', '', $slug );

		$alias = $slug . '-' . $this->slug . '-global';

		if ( defined( 'EP_INDEX_PREFIX' ) && EP_INDEX_PREFIX ) {
			$alias = EP_INDEX_PREFIX . $alias;
		}

		return apply_filters( 'ep_global_alias', $alias );
	}

	/**
	 * Delete unique indexable network alias
	 *
	 * @since  2.6
	 * @return boolean
	 */
	public function delete_network_alias() {
		return Elasticsearch::factory()->delete_network_alias( $alias );
	}

	/**
	 * Create unique indexable network alias
	 *
	 * @param  array $indexes Array of indexes.
	 * @since  2.6
	 * @return boolean
	 */
	public function create_network_alias( $indexes ) {
		return Elasticsearch::factory()->create_network_alias( $indexes, $this->get_network_alias() );
	}

	/**
	 * Delete an object within the indexable
	 *
	 * @param  int     $object_id Object to delete.
	 * @param  boolean $blocking Whether to issue blocking HTTP request or not.
	 * @since  2.6
	 * @return boolean
	 */
	public function delete( $object_id, $blocking = true ) {
		return Elasticsearch::factory()->delete_document( $this->get_index_name(), $this->slug, $object_id, $blocking );
	}

	/**
	 * Get an object within the indexable
	 *
	 * @param  int $object_id Object to get.
	 * @since  2.6
	 * @return boolean|array
	 */
	public function get( $object_id ) {
		return Elasticsearch::factory()->get_document( $this->get_index_name(), $this->slug, $object_id );
	}

	/**
	 * Delete an index within the indexable
	 *
	 * @param  int $blog_id `null` means current blog.
	 * @since  2.6
	 * @return boolean
	 */
	public function delete_index( $blog_id = null ) {
		return Elasticsearch::factory()->delete_index( $this->get_index_name( $blog_id ) );
	}

	/**
	 * Index an object within the indexable. This calls prepare_document
	 *
	 * @param  int     $object_id Object to index.
	 * @param  boolean $blocking Blocking HTTP request or not.
	 * @since  2.6
	 * @return boolean
	 */
	public function index( $object_id, $blocking = false ) {
		$document = $this->prepare_document( $object_id );

		if ( false === $document ) {
			return false;
		}

		if ( apply_filters( 'ep_' . $this->slug . '_index_kill', false, $object_id ) ) {
			return false;
		}

		$document = apply_filters( 'ep_pre_index_' . $this->slug, $document );

		$return = Elasticsearch::factory()->index_document( $this->get_index_name(), $this->slug, $document, $blocking );

		do_action( 'ep_after_index_' . $this->slug, $document, $return );

		return $return;
	}

	/**
	 * Determine if indexable index exists
	 *
	 * @param  int $blog_id Blog to check index for.
	 * @since  2.6
	 * @return boolean
	 */
	public function index_exists( $blog_id = null ) {
		return Elasticsearch::factory()->index_exists( $this->get_index_name( $blog_id ) );
	}

	/**
	 * Bulk index objects. This calls prepare_document on each object
	 *
	 * @param  array $object_ids Array of object IDs.
	 * @since  2.6
	 * @return WP_Error|array
	 */
	public function bulk_index( $object_ids ) {
		$body = '';

		foreach ( $object_ids as $object_id ) {
			$body .= '{ "index": { "_id": "' . absint( $object_id ) . '" } }' . "\n";

			$document = $this->prepare_document( $object_id );

			if ( function_exists( 'wp_json_encode' ) ) {
				$body .= addcslashes( wp_json_encode( $document ), "\n" );
			} else {
				$body .= addcslashes( json_encode( $document ), "\n" );
			}

			$body .= "\n\n";
		}

		return Elasticsearch::factory()->bulk_index( $this->get_index_name(), $this->slug, $body );
	}

	/**
	 * Query Elasticsearch for documents
	 *
	 * @param  array  $formatted_args Formatted es query arguments.
	 * @param  array  $query_args WP_Query args.
	 * @param  string $index Index(es) to query. Comma separate for multiple. Defaults to current.
	 * @since  2.6
	 * @return array
	 */
	public function query_es( $formatted_args, $query_args, $index = null ) {
		if ( null === $index ) {
			$index = $this->get_index_name();
		}

		return Elasticsearch::factory()->query( $index, $this->slug, $formatted_args, $query_args );
	}

	/**
	 * Prepare meta type values to send to ES
	 *
	 * @param array $meta Array of meta.
	 * @since  2.6
	 * @return array
	 */
	public function prepare_meta_types( $meta ) {

		$prepared_meta = [];

		foreach ( $meta as $meta_key => $meta_values ) {
			if ( ! is_array( $meta_values ) ) {
				$meta_values = array( $meta_values );
			}

			$prepared_meta[ $meta_key ] = array_map( array( $this, 'prepare_meta_value_types' ), $meta_values );
		}

		return $prepared_meta;

	}

	/**
	 * Prepare meta types for meta value
	 *
	 * @param mixed $meta_value Meta value to prepare.
	 * @since  2.6
	 * @return array
	 */
	public function prepare_meta_value_types( $meta_value ) {

		$max_java_int_value = 9223372036854775807;

		$meta_types = [];

		if ( is_array( $meta_value ) || is_object( $meta_value ) ) {
			$meta_value = serialize( $meta_value );
		}

		$meta_types['value'] = $meta_value;
		$meta_types['raw']   = $meta_value;

		if ( is_numeric( $meta_value ) ) {
			$long = intval( $meta_value );

			if ( $max_java_int_value < $long ) {
				$long = $max_java_int_value;
			}

			$double = floatval( $meta_value );

			if ( ! is_finite( $double ) ) {
				$double = 0;
			}

			$meta_types['long']   = $long;
			$meta_types['double'] = $double;
		}

		$meta_types['boolean'] = filter_var( $meta_value, FILTER_VALIDATE_BOOLEAN );

		if ( is_string( $meta_value ) ) {
			$timestamp = strtotime( $meta_value );

			$date     = '1971-01-01';
			$datetime = '1971-01-01 00:00:01';
			$time     = '00:00:01';

			if ( false !== $timestamp ) {
				$date     = date_i18n( 'Y-m-d', $timestamp );
				$datetime = date_i18n( 'Y-m-d H:i:s', $timestamp );
				$time     = date_i18n( 'H:i:s', $timestamp );
			}

			$meta_types['date']     = $date;
			$meta_types['datetime'] = $datetime;
			$meta_types['time']     = $time;
		}

		return $meta_types;
	}

	/**
	 * Must implement a method that handles sending mapping to ES
	 *
	 * @return boolean
	 */
	abstract function put_mapping();

	/**
	 * Must implement a method that given an object ID, returns a formatted Elasticsearch
	 * document
	 *
	 * @param  int $object_id Object to prepare.
	 * @return array
	 */
	abstract function prepare_document( $object_id );

	/**
	 * Must implement a method that queries MySQL for objects and returns them
	 * in a standardized format. This is necessary so we can genericize the index
	 * process across indexables.
	 *
	 * @param  array $args Array to query DB against.
	 * @return boolean
	 */
	abstract function query_db( $args );
}
