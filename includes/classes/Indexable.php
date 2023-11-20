<?php
/**
 * Indexable abstract class.
 *
 * An indexable is a type of "data" in WP e.g. post type, term, user, etc.
 *
 * @since  3.0
 * @package elasticpress
 */

namespace ElasticPress;

use ElasticPress\Elasticsearch;
use ElasticPress\SyncManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * An indexable is essentially a document type that can be indexed
 * and queried against
 *
 * @since  3.0
 */
abstract class Indexable {

	/**
	 * Declaring an Indexable global means it won't have an index for each blog in
	 * the network. Instead it will just have one index. There will also be no
	 * network alias.
	 *
	 * @var boolean
	 * @since  3.0
	 */
	public $global = false;

	/**
	 * Instance of SyncManager. This should handle automated syncing of indexable
	 * objects.
	 *
	 * @var SyncManager
	 * @since  3.0
	 */
	public $sync_manager;

	/**
	 * Instance of QueryIntegration. This should handle integrating with a default
	 * WP query.
	 *
	 * @var object
	 * @since  3.0
	 */
	public $query_integration;

	/**
	 * Flag to indicate if the indexable has support for
	 * `id_range` pagination method during a sync.
	 *
	 * @var boolean
	 * @since 4.1.0
	 */
	public $support_indexing_advanced_pagination = false;

	/**
	 * Indexable slug
	 *
	 * @since 4.5.0
	 * @var string
	 */
	public $slug = '';

	/**
	 * Indexable labels
	 *
	 * @since 4.5.0
	 * @var array
	 */
	public $labels = [];

	/**
	 * Get number of bulk items to index per page
	 *
	 * @since  3.0
	 * @return int
	 */
	public function get_bulk_items_per_page() {
		/**
		 * Filter bulk items to sync per batch
		 *
		 * @hook ep_bulk_items_per_page
		 * @param  {int} $number Number of items per batch
		 * @param  {Indexable} $indexable Current indexable
		 * @return  {int} New number of items
		 * @since  3.0
		 */
		return apply_filters( 'ep_bulk_items_per_page', 350, $this );
	}

	/**
	 * Get the name of the index. Each indexable needs a unique index name
	 *
	 * @param  int $blog_id `null` means current blog.
	 * @since  3.0
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

		$prefix = Utils\get_index_prefix();

		if ( ! empty( $prefix ) ) {
			$index_name = $prefix . '-' . $index_name;
		}

		$index_name = strtolower( $index_name );

		/**
		 * Filter index name
		 *
		 * @hook ep_index_name
		 * @param  {string} $index_name Name of index
		 * @param  {int} $blog_id Blog ID
		 * @param  {Indexable} $indexable Current indexable
		 * @return  {string} Index name
		 * @since  3.0
		 */
		return apply_filters( 'ep_index_name', $index_name, $blog_id, $this );
	}

	/**
	 * Get unique indexable network alias
	 *
	 * @since  3.0
	 * @return string
	 */
	public function get_network_alias() {
		$url  = network_site_url();
		$slug = preg_replace( '#https?://(www\.)?#i', '', $url );
		$slug = preg_replace( '#[^\w]#', '', $slug );

		$alias = $slug . '-' . $this->slug . '-global';

		$prefix = Utils\get_index_prefix();

		if ( ! empty( $prefix ) ) {
			$alias = $prefix . '-' . $alias;
		}

		/**
		 * Filter global/network Elasticsearch alias
		 *
		 * @hook ep_global_alias
		 * @param  {string} $number Current alias
		 * @return  {string} New alias
		 */
		return apply_filters( 'ep_global_alias', $alias );
	}

	/**
	 * Delete unique indexable network alias
	 *
	 * @since  3.0
	 * @return boolean
	 */
	public function delete_network_alias() {
		return Elasticsearch::factory()->delete_network_alias( $this->get_network_alias() );
	}

	/**
	 * Create unique indexable network alias
	 *
	 * @param  array $indexes Array of indexes.
	 * @since  3.0
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
	 * @since  3.0
	 * @return boolean
	 */
	public function delete( $object_id, $blocking = true ) {
		/**
		 * Fires before object deletion
		 *
		 * @hook ep_delete_{indexable_slug}
		 * @param {int} $object_id ID of object being deleted
		 * @param {string} $indexable_slug The slug of the indexable type that is being deleted
		 */
		do_action( 'ep_delete_' . $this->slug, $object_id, $this->slug );

		return Elasticsearch::factory()->delete_document( $this->get_index_name(), $this->slug, $object_id, $blocking );
	}

	/**
	 * Get an object within the indexable
	 *
	 * @param  int $object_id Object to get.
	 * @since  3.0
	 * @return boolean|array
	 */
	public function get( $object_id ) {
		return Elasticsearch::factory()->get_document( $this->get_index_name(), $this->slug, $object_id );
	}

	/**
	 * Get objects within the indexable
	 *
	 * @param  int $object_ids Array of object ids to get.
	 * @since  3.6.0
	 * @return boolean|array
	 */
	public function multi_get( $object_ids ) {
		return Elasticsearch::factory()->get_documents( $this->get_index_name(), $this->slug, $object_ids );
	}

	/**
	 * Delete an index within the indexable
	 *
	 * @param  int $blog_id `null` means current blog.
	 * @since  3.0
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
	 * @since  3.0
	 * @return boolean
	 */
	public function index( $object_id, $blocking = false ) {
		$document = $this->prepare_document( $object_id );

		if ( false === $document ) {
			return false;
		}

		/**
		 * Conditionally kill indexing on a specific object
		 *
		 * @hook ep_{indexable_slug}_index_kill
		 * @param  {bool} $kill True to not index
		 * @param {int} $object_id Id of object to index
		 * @since  3.0
		 * @return {bool}  New kill value
		 */
		if ( apply_filters( 'ep_' . $this->slug . '_index_kill', false, $object_id ) ) {
			return false;
		}

		/**
		 * Filter document before index
		 *
		 * @hook ep_pre_index_{indexable_slug}
		 * @param  {array} $document Document to index
		 * @return {array} New document
		 * @since  3.0
		 */
		$document = apply_filters( 'ep_pre_index_' . $this->slug, $document );

		$return = Elasticsearch::factory()->index_document( $this->get_index_name(), $this->slug, $document, $blocking );

		/**
		 * Fires after document is indexed
		 *
		 * @hook ep_after_index_{indexable_slug}
		 * @param  {array} $document Document to index
		 * @param  {array|boolean} $return ES response on success, false on failure
		 * @since  3.0
		 */
		do_action( 'ep_after_index_' . $this->slug, $document, $return );

		return $return;
	}

	/**
	 * Determine if indexable index exists
	 *
	 * @param  int $blog_id Blog to check index for.
	 * @since  3.0
	 * @return boolean
	 */
	public function index_exists( $blog_id = null ) {
		return Elasticsearch::factory()->index_exists( $this->get_index_name( $blog_id ) );
	}

	/**
	 * Bulk index objects. This calls prepare_document on each object
	 *
	 * @param  array $object_ids Array of object IDs.
	 * @since  3.0
	 * @return WP_Error|array
	 */
	public function bulk_index( $object_ids ) {
		$body = '';

		foreach ( $object_ids as $object_id ) {
			$action_args = array(
				'index' => array(
					'_id' => absint( $object_id ),
				),
			);

			$document = $this->prepare_document( $object_id );

			/**
			 * Conditionally kill indexing on a specific object
			 *
			 * @hook ep_bulk_index_action_args
			 * @param  {array} $action_args Bulk action arguments
			 * @param {array} $document Document to index
			 * @since  3.0
			 * @return {array}  New action args
			 */
			$body .= wp_json_encode( apply_filters( 'ep_bulk_index_action_args', $action_args, $document ) ) . "\n";
			$body .= addcslashes( wp_json_encode( $document ), "\n" );

			$body .= "\n\n";
		}

		$result = Elasticsearch::factory()->bulk_index( $this->get_index_name(), $this->slug, $body );

		/**
		 * Perform actions after a bulk indexing is completed
		 *
		 * @hook ep_after_bulk_index
		 * @param {array} $object_ids List of object ids attempted to be indexed
		 * @param {string} $slug Current indexable slug
		 * @param {array|bool} $result Result of the Elasticsearch query. False on error.
		 */
		do_action( 'ep_after_bulk_index', $object_ids, $this->slug, $result );

		return $result;
	}

	/**
	 * Bulk index objects but with a dynamic size of queue.
	 *
	 * @since  4.0.0
	 * @param  array $object_ids Array of object IDs.
	 * @return array[WP_Error|array] The return of each request made.
	 */
	public function bulk_index_dynamically( $object_ids ) {
		$documents = [];

		foreach ( $object_ids as $object_id ) {
			$action_args = array(
				'index' => array(
					'_id' => absint( $object_id ),
				),
			);

			$document = $this->prepare_document( $object_id );

			if ( empty( $document ) ) {
				continue;
			}

			/**
			 * Conditionally kill indexing on a specific object
			 *
			 * @hook ep_bulk_index_action_args
			 * @param  {array} $action_args Bulk action arguments
			 * @param {array} $document Document to index
			 * @since  3.0
			 * @return {array}  New action args
			 */
			$document_str  = wp_json_encode( apply_filters( 'ep_bulk_index_action_args', $action_args, $document ) ) . "\n";
			$document_str .= addcslashes( wp_json_encode( $document ), "\n" );
			$document_str .= "\n\n";

			$documents[] = $document_str;
		}

		if ( empty( $documents ) ) {
			return [
				new \WP_Error( 'ep_bulk_index_no_documents', esc_html__( 'It was not possible to create a body request with the document IDs provided.', 'elasticpress' ), $object_ids ),
			];
		}

		$results = $this->send_bulk_index_request( $documents );

		/**
		 * Perform actions after a dynamic bulk indexing is completed
		 *
		 * @hook ep_after_bulk_index_dynamically
		 * @since 4.0.0
		 * @param {array}      $object_ids List of object ids attempted to be indexed
		 * @param {string}     $slug Current indexable slug
		 * @param {array|bool} $result Result of the Elasticsearch query. False on error.
		 */
		do_action( 'ep_after_bulk_index_dynamically', $object_ids, $this->slug, $results );

		return $results;
	}

	/**
	 * Bulk index documents through several requests with dynamic size.
	 *
	 * @param array $documents The documents to be sent to Elasticsearch (already formatted.)
	 * @return array[WP_Error|array]
	 */
	protected function send_bulk_index_request( $documents ) {
		static $min_buffer_size, $max_buffer_size, $current_buffer_size, $incremental_step;

		if ( ! $min_buffer_size ) {
			/**
			 * Filter the minimum buffer size for dynamic bulk index requests.
			 *
			 * @hook ep_dynamic_bulk_min_buffer_size
			 * @since 4.0.0
			 * @param {int} $min_buffer_size Min buffer size for dynamic bulk index (in bytes.)
			 * @return {int} New size.
			 */
			$min_buffer_size = apply_filters( 'ep_dynamic_bulk_min_buffer_size', MB_IN_BYTES / 2 );
		}

		if ( ! $max_buffer_size ) {
			/**
			 * Filter the max buffer size for dynamic bulk index requests.
			 *
			 * @hook ep_dynamic_bulk_max_buffer_size
			 * @since 4.0.0
			 * @param {int} $max_buffer_size Max buffer size for dynamic bulk index (in bytes.)
			 * @return {int} New size.
			 */
			$max_buffer_size = apply_filters( 'ep_dynamic_bulk_max_buffer_size', 150 * MB_IN_BYTES );
		}

		if ( ! $incremental_step ) {
			/**
			 * Filter the number of bytes the current buffer size should be incremented in case of success.
			 *
			 * @hook ep_dynamic_bulk_incremental_step
			 * @since 4.0.0
			 * @param {int} $incremental_step Number of bytes to add to the current buffer size.
			 * @return {int} New incremental step.
			 */
			$incremental_step = apply_filters( 'ep_dynamic_bulk_incremental_step', MB_IN_BYTES / 2 );
		}

		/**
		 * Perform actions before a new batch of documents is processed.
		 *
		 * @hook ep_before_send_dynamic_bulk_requests
		 * @since 4.0.0
		 * @param {array} $documents Array of documents to be sent to Elasticsearch.
		 */
		do_action( 'ep_before_send_dynamic_bulk_requests', $documents );

		if ( ! $current_buffer_size ) {
			$current_buffer_size = $min_buffer_size;
		}

		$results = [];

		$body = [];

		$requests = 0;

		/*
		 * This script will use two main arrays: $body and $documents, being $body the
		 * documents to be sent in the next request and $documents the list of docs to be indexed.
		 * The do-while loop will stop if all documents are sent or if a request fails even sending
		 * a buffer as small as possible.
		 */
		do {
			$next_document = array_shift( $documents );

			// If the next document alone takes the entire current buffer size,
			// let's add it back to the pipe and send what we have first
			if ( mb_strlen( $next_document ) > $current_buffer_size && count( $body ) > 0 ) {
				array_unshift( $documents, $next_document );
			} else {
				if ( mb_strlen( $next_document ) > $max_buffer_size ) {
					/**
					 * Perform actions when a post is bigger than the max buffer size.
					 *
					 * @hook ep_dynamic_bulk_post_too_big
					 * @since 4.0.0
					 * @param {string} $document JSON string of the post detected as too big.
					 */
					do_action( 'ep_dynamic_bulk_post_too_big', $next_document );
					$results[] = new \WP_Error( 'ep_too_big_request_skipped', 'Indexable too big. Request not sent.' );
					continue;
				}
				$body[] = $next_document;
				if ( mb_strlen( implode( '', $body ) ) < $current_buffer_size && ! empty( $documents ) ) {
					continue;
				}
				if ( mb_strlen( implode( '', $body ) ) > $max_buffer_size ) {
					// The last document added to body made it too big, so let's give it back.
					array_unshift( $documents, array_pop( $body ) );
				}
			}

			// Try the request.
			timer_start();
			$result       = Elasticsearch::factory()->bulk_index( $this->get_index_name(), $this->slug, implode( '', $body ) );
			$request_time = timer_stop();
			$requests++;

			/**
			 * Perform actions before a new batch of documents is processed.
			 *
			 * @hook ep_after_send_dynamic_bulk_request
			 * @since 4.0.0
			 * @param {WP_Error|array} $result              Result of the request.
			 * @param {array}          $body                Array of documents sent to Elasticsearch.
			 * @param {array}          $documents           Array of documents to be sent to Elasticsearch.
			 * @param {int}            $min_buffer_size     Min buffer size for dynamic bulk index (in bytes.)
			 * @param {int}            $max_buffer_size     Max buffer size for dynamic bulk index (in bytes.)
			 * @param {int}            $current_buffer_size Current buffer size for dynamic bulk index (in bytes.)
			 * @param {int}            $request_time        Total time of the request.
			 */
			do_action( 'ep_after_send_dynamic_bulk_request', $result, $body, $documents, $min_buffer_size, $max_buffer_size, $current_buffer_size, $request_time );

			// It failed, possibly adjust the buffer size and try again.
			if ( is_wp_error( $result ) ) {
				// Too many requests, wait and try again.
				if ( 429 === $result->get_error_code() ) {
					sleep( 2 );
				}

				// If the error is not a "Request too big" then we really fail this batch of documents.
				if ( 413 !== $result->get_error_code() ) {
					$results[] = $result;
					continue;
				}

				if ( count( $body ) === 1 ) {
					$max_buffer_size = min( $max_buffer_size, mb_strlen( implode( '', $body ) ) );
					$results[]       = $result;
					$body            = [];
					continue;
				}

				// As the buffer is as small as possible, return the error.
				if ( mb_strlen( implode( '', $body ) ) === $min_buffer_size ) {
					$results[] = $result;
					continue;
				}

				// We have a too big buffer. Remove one doc from the body, and set both max and current as its size.
				array_unshift( $documents, array_pop( $body ) );

				$max_buffer_size = count( $body ) ?
					max( $min_buffer_size, mb_strlen( implode( '', $body ) ) ) :
					$min_buffer_size;

				$current_buffer_size = $max_buffer_size;
				continue;
			}

			// Things worked so we can try to bump the buffer size.
			if ( $current_buffer_size < $max_buffer_size && mb_strlen( implode( '', $body ) ) > $current_buffer_size ) {
				$current_buffer_size = min( ( $current_buffer_size + $incremental_step ), $max_buffer_size );
			}

			$results[] = $result;

			$body = [];
		} while ( ! empty( $documents ) );

		/**
		 * Perform actions after a batch of documents was processed.
		 *
		 * @hook ep_after_send_dynamic_bulk_requests
		 * @since 4.0.0
		 * @param {array} $results  Array of results sent.
		 * @param {int}   $requests Number of all requests sent.
		 */
		do_action( 'ep_after_send_dynamic_bulk_requests', $results, $requests );

		return $results;
	}

	/**
	 * Query Elasticsearch for documents
	 *
	 * @param  array  $formatted_args Formatted es query arguments.
	 * @param  array  $query_args WP_Query args.
	 * @param  string $index Index(es) to query. Comma separate for multiple. Defaults to current.
	 * @param  mixed  $query_object Could be WP_Query, WP_User_Query, etc.
	 * @since  3.0
	 * @return array
	 */
	public function query_es( $formatted_args, $query_args, $index = null, $query_object = null ) {
		if ( null === $index ) {
			$index = $this->get_index_name();
		}

		return Elasticsearch::factory()->query( $index, $this->slug, $formatted_args, $query_args, $query_object );
	}

	/**
	 * Check to see if we should allow elasticpress to override this query
	 *
	 * @param \WP_Query|\WP_User_Query|\WP_Term_Query $query WP_Query or WP_User_Query or WP_Term_Query instance
	 * @return bool
	 * @since 3.0
	 */
	public function elasticpress_enabled( $query ) {
		$enabled = false;

		if ( ! empty( $query->query_vars['ep_integrate'] ) ) {
			$enabled = true;
		}

		/**
		 * Determine if ElasticPress should integrate with a query
		 *
		 * @hook ep_elasticpress_enabled
		 * @param  {bool} $enabled Whether to integrate with Elasticsearch or not
		 * @param {WP_Query} $query WP_Query to evaluate
		 * @return {bool}  Enabled value
		 */
		$enabled = apply_filters( 'ep_elasticpress_enabled', $enabled, $query );

		if ( isset( $query->query_vars['ep_integrate'] ) && ! filter_var( $query->query_vars['ep_integrate'], FILTER_VALIDATE_BOOLEAN ) ) {
			$enabled = false;
		}

		return $enabled;
	}

	/**
	 * Prepare meta type values to send to ES
	 *
	 * @param array $meta Array of meta.
	 * @since  3.0
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
	 * @since  3.0
	 * @return array
	 */
	public function prepare_meta_value_types( $meta_value ) {

		$max_java_int_value = PHP_INT_MAX;

		$meta_types = [];

		if ( is_array( $meta_value ) || is_object( $meta_value ) ) {
			$meta_value = serialize( $meta_value ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
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

		$meta_types = $this->prepare_date_meta_values( $meta_types, $meta_value );

		return $meta_types;
	}

	/**
	 * Checks if a meta_value is a valid date and prepare extra meta-data.
	 *
	 * @param array  $meta_types Array of currently prepared data
	 * @param string $meta_value Meta value to prepare.
	 *
	 * @return array
	 */
	public function prepare_date_meta_values( $meta_types, $meta_value ) {

		if ( empty( $meta_value ) || ! is_string( $meta_value ) ) {
			return $meta_types;
		}

		$meta_types['date']     = '1970-01-01';
		$meta_types['datetime'] = '1970-01-01 00:00:01';
		$meta_types['time']     = '00:00:01';

		// is this is a recognizable date format?
		$new_date = date_create( $meta_value, \wp_timezone() );
		if ( $new_date ) {
			$timestamp = $new_date->getTimestamp();

			/**
			 * Filter the maximum year limit for date conversion.
			 *
			 * Use default date if year is greater than max limit. EP has limitation that doesn't allow to have year greater than 2099.
			 *
			 * @see https://github.com/10up/ElasticPress/issues/2769
			 *
			 * @hook ep_max_year_limit
			 * @param  {int} $year Maximum year limit.
			 * @return {int} Maximum year limit.
			 * @since  4.2.1
			 */
			$max_year = apply_filters( 'ep_max_year_limit', 2099 );

			// PHP allows DateTime to build dates with the non-existing year 0000, and this causes
			// issues when integrating into stricter systems. This is by design:
			// https://bugs.php.net/bug.php?id=60288
			if ( false !== $timestamp && '0000' !== $new_date->format( 'Y' ) && $new_date->format( 'Y' ) <= $max_year ) {
				$meta_types['date']     = $new_date->format( 'Y-m-d' );
				$meta_types['datetime'] = $new_date->format( 'Y-m-d H:i:s' );
				$meta_types['time']     = $new_date->format( 'H:i:s' );
			}
		}

		return $meta_types;
	}

	/**
	 * Build Elasticsearch filter query for WP meta_query
	 *
	 * @since 2.2
	 * @param array $meta_queries Array of queries
	 * @return array
	 */
	public function build_meta_query( $meta_queries ) {
		$meta_filter = [];

		$outer_relation = 'must';
		if ( ! empty( $meta_queries['relation'] ) && 'or' === strtolower( $meta_queries['relation'] ) ) {
			$outer_relation = 'should';
		}

		$meta_query_type_mapping = [
			'numeric'  => 'long',
			'binary'   => 'raw',
			'char'     => 'raw',
			'date'     => 'date',
			'datetime' => 'datetime',
			'decimal'  => 'double',
			'signed'   => 'long',
			'time'     => 'time',
			'unsigned' => 'long',
		];

		foreach ( $meta_queries as $single_meta_query ) {
			if ( ! empty( $single_meta_query['key'] ) ) {

				$terms_obj = false;

				$compare = '=';
				if ( ! empty( $single_meta_query['compare'] ) ) {
					$compare = strtolower( $single_meta_query['compare'] );
				} elseif ( ! isset( $single_meta_query['value'] ) ) {
					$compare = 'exists';
				}

				$type = null;
				if ( ! empty( $single_meta_query['type'] ) ) {
					$type = strtolower( $single_meta_query['type'] );
				}

				// Comparisons need to look at different paths
				if ( in_array( $compare, array( 'exists', 'not exists' ), true ) ) {
					$meta_key_path = 'meta.' . $single_meta_query['key'];
				} elseif ( in_array( $compare, array( '=', '!=' ), true ) && ! $type ) {
					$meta_key_path = 'meta.' . $single_meta_query['key'] . '.raw';
				} elseif ( in_array( $compare, array( 'like', 'not like' ), true ) ) {
					$meta_key_path = 'meta.' . $single_meta_query['key'] . '.value';
				} elseif ( $type && isset( $meta_query_type_mapping[ $type ] ) ) {
					// Map specific meta field types to different Elasticsearch core types
					$meta_key_path = 'meta.' . $single_meta_query['key'] . '.' . $meta_query_type_mapping[ $type ];
				} elseif ( in_array( $compare, array( '>=', '<=', '>', '<', 'between', 'not between' ), true ) ) {
					$meta_key_path = 'meta.' . $single_meta_query['key'] . '.double';
				} else {
					$meta_key_path = 'meta.' . $single_meta_query['key'] . '.raw';
				}

				switch ( $compare ) {
					case 'not in':
					case '!=':
						if ( isset( $single_meta_query['value'] ) ) {
							$terms_obj = array(
								'bool' => array(
									'must_not' => array(
										array(
											'terms' => array(
												$meta_key_path => (array) $single_meta_query['value'],
											),
										),
									),
								),
							);
						}

						break;
					case 'exists':
						$terms_obj = array(
							'exists' => array(
								'field' => $meta_key_path,
							),
						);

						break;
					case 'not exists':
						$terms_obj = array(
							'bool' => array(
								'must_not' => array(
									array(
										'exists' => array(
											'field' => $meta_key_path,
										),
									),
								),
							),
						);

						break;
					case '>=':
						if ( isset( $single_meta_query['value'] ) ) {
							$terms_obj = array(
								'bool' => array(
									'must' => array(
										array(
											'range' => array(
												$meta_key_path => array(
													'gte' => $single_meta_query['value'],
												),
											),
										),
									),
								),
							);
						}

						break;
					case 'between':
						if ( isset( $single_meta_query['value'] ) && is_array( $single_meta_query['value'] ) && 2 === count( $single_meta_query['value'] ) ) {
							$terms_obj = array(
								'bool' => array(
									'must' => array(
										array(
											'range' => array(
												$meta_key_path => array(
													'gte' => $single_meta_query['value'][0],
												),
											),
										),
										array(
											'range' => array(
												$meta_key_path => array(
													'lte' => $single_meta_query['value'][1],
												),
											),
										),
									),
								),
							);
						}

						break;
					case 'not between':
						if ( isset( $single_meta_query['value'] ) && is_array( $single_meta_query['value'] ) && 2 === count( $single_meta_query['value'] ) ) {
							$terms_obj = array(
								'bool' => array(
									'should' => array(
										array(
											'range' => array(
												$meta_key_path => array(
													'lte' => $single_meta_query['value'][0],
												),
											),
										),
										array(
											'range' => array(
												$meta_key_path => array(
													'gte' => $single_meta_query['value'][1],
												),
											),
										),
									),
								),
							);
						}

						break;
					case '<=':
						if ( isset( $single_meta_query['value'] ) ) {
							$terms_obj = array(
								'bool' => array(
									'must' => array(
										array(
											'range' => array(
												$meta_key_path => array(
													'lte' => $single_meta_query['value'],
												),
											),
										),
									),
								),
							);
						}

						break;
					case '>':
						if ( isset( $single_meta_query['value'] ) ) {
							$terms_obj = array(
								'bool' => array(
									'must' => array(
										array(
											'range' => array(
												$meta_key_path => array(
													'gt' => $single_meta_query['value'],
												),
											),
										),
									),
								),
							);
						}

						break;
					case '<':
						if ( isset( $single_meta_query['value'] ) ) {
							$terms_obj = array(
								'bool' => array(
									'must' => array(
										array(
											'range' => array(
												$meta_key_path => array(
													'lt' => $single_meta_query['value'],
												),
											),
										),
									),
								),
							);
						}

						break;
					case 'like':
						if ( isset( $single_meta_query['value'] ) ) {
							$terms_obj = array(
								'match_phrase' => array(
									$meta_key_path => $single_meta_query['value'],
								),
							);
						}
						break;
					case 'not like':
						if ( isset( $single_meta_query['value'] ) ) {
							$terms_obj = array(
								'bool' => array(
									'must_not' => array(
										array(
											'match_phrase' => array(
												$meta_key_path => $single_meta_query['value'],
											),
										),
									),
								),
							);
						}
						break;
					case '=':
					default:
						if ( isset( $single_meta_query['value'] ) ) {
							$terms_obj = array(
								'terms' => array(
									$meta_key_path => (array) $single_meta_query['value'],
								),
							);
						}

						break;
				}

				// Add the meta query filter
				if ( false !== $terms_obj ) {
					$meta_filter[] = $terms_obj;
				}
			} elseif ( is_array( $single_meta_query ) ) {
				/**
				 * Handle multidimensional array. Something like:
				 *
				 * 'meta_query' => array(
				 *      'relation' => 'AND',
				 *      array(
				 *          'key' => 'meta_key_1',
				 *          'value' => '1',
				 *      ),
				 *      array(
				 *          'relation' => 'OR',
				 *          array(
				 *              'key' => 'meta_key_2',
				 *              'value' => '2',
				 *          ),
				 *          array(
				 *              'key' => 'meta_key_3',
				 *              'value' => '4',
				 *          ),
				 *      ),
				 *  ),
				 */
				$inner_relation = 'must';
				if ( ! empty( $single_meta_query['relation'] ) && 'or' === strtolower( $single_meta_query['relation'] ) ) {
					$inner_relation = 'should';
				}

				$meta_filter[] = array(
					'bool' => array(
						$inner_relation => $this->build_meta_query( $single_meta_query ),
					),
				);
			}
		}

		if ( ! empty( $meta_filter ) ) {
			return [
				'bool' => [
					$outer_relation => $meta_filter,
				],
			];
		} else {
			return false;
		}
	}

	/**
	 * Get the indexable mapping.
	 *
	 * @since  3.6.0
	 * @return boolean|array
	 */
	public function get_mapping() {
		return Elasticsearch::factory()->get_mapping( $this->get_index_name() );
	}

	/**
	 * Compare the mapping generated by the plugin and the mapping stored in Elasticsearch.
	 *
	 * @todo properly implement the check.
	 *
	 * @since  3.6.0
	 * @return bool|WP_Error
	 */
	public function compare_mappings() {
		if ( ! method_exists( $this, 'generate_mapping' ) ) {
			return new \WP_Error( 'ep_generate_mapping_not_implemented' );
		}

		$new_mapping    = $this->generate_mapping();
		$stored_mapping = $this->get_mapping();

		return ( (string) $new_mapping['settings']['index.number_of_shards'] === $stored_mapping[ $this->get_index_name() ]['settings']['index']['number_of_shards'] );
	}

	/**
	 * Utilitary function to check if the indexable is being fully reindexed, i.e.,
	 * the index was deleted, a new mapping was sent and content is being reindexed.
	 *
	 * @param int|null $blog_id Blog ID
	 * @return boolean
	 */
	public function is_full_reindexing( $blog_id = null ) {
		if ( $this->global ) {
			$blog_id = null;
		} elseif ( ! $blog_id ) {
			$blog_id = get_current_blog_id();
		}

		return \ElasticPress\IndexHelper::factory()->is_full_reindexing( $this->slug, $blog_id );
	}

	/**
	 * Send mapping to Elasticsearch
	 *
	 * @param string $return_type Desired return type. Can be either 'bool' or 'raw'
	 * @return bool|WP_Error
	 */
	public function put_mapping( $return_type = 'bool' ) {
		$mapping = $this->generate_mapping();

		return Elasticsearch::factory()->put_mapping( $this->get_index_name(), $mapping, $return_type );
	}

	/**
	 * Must implement a method that given an object ID, returns a formatted Elasticsearch
	 * document
	 *
	 * @param  int $object_id Object to prepare.
	 * @return array
	 */
	abstract public function prepare_document( $object_id );

	/**
	 * Must implement a method that queries MySQL for objects and returns them
	 * in a standardized format. This is necessary so we can genericize the index
	 * process across indexables.
	 *
	 * @param  array $args Array to query DB against.
	 * @return array
	 */
	abstract public function query_db( $args );

	/**
	 * Shim function for backwards-compatibility on custom Indexables.
	 *
	 * @since 4.1.0
	 * @return array
	 */
	public function generate_mapping() {
		_doing_it_wrong( __METHOD__, 'The Indexable class should not call generate_mapping() directly.', 'ElasticPress 4.0' );

		return [];
	}

	/**
	 * Get the search algorithm that should be used.
	 *
	 * @since 4.3.0
	 * @param string $search_text   Search term(s)
	 * @param array  $search_fields Search fields
	 * @param array  $query_vars    Query vars
	 * @return SearchAlgorithm Instance of search algorithm to be used
	 */
	public function get_search_algorithm( string $search_text, array $search_fields, array $query_vars ) : \ElasticPress\SearchAlgorithm {
		/**
		 * Filter the search algorithm to be used
		 *
		 * @hook ep_{$indexable_slug}_search_algorithm
		 * @since  4.3.0
		 * @param  {string} $search_algorithm Slug of the search algorithm used as fallback
		 * @param  {string} $search_term      Search term
		 * @param  {array}  $search_fields    Fields to be searched
		 * @param  {array}  $query_vars       Query variables
		 * @return {string} New search algorithm slug
		 */
		$search_algorithm = apply_filters( "ep_{$this->slug}_search_algorithm", 'basic', $search_text, $search_fields, $query_vars );

		return \ElasticPress\SearchAlgorithms::factory()->get( $search_algorithm );
	}

	/**
	 * Get all distinct meta field keys.
	 *
	 * @since 4.3.0
	 * @param null|int $blog_id (Optional) The blog ID. Sending `null` will use the current blog ID.
	 * @return array
	 * @throws \Exception An exception if meta fields are not available.
	 */
	public function get_distinct_meta_field_keys( $blog_id = null ) {
		$mapping = $this->get_mapping();

		try {
			if ( version_compare( (string) Elasticsearch::factory()->get_elasticsearch_version(), '7.0', '<' ) ) {
				$meta_fields = $mapping[ $this->get_index_name( $blog_id ) ]['mappings']['post']['properties']['meta']['properties'];
			} else {
				$meta_fields = $mapping[ $this->get_index_name( $blog_id ) ]['mappings']['properties']['meta']['properties'];
			}
			$meta_keys = array_values( array_keys( $meta_fields ) );
			sort( $meta_keys );
		} catch ( \Throwable $th ) {
			throw new \Exception( 'Meta fields not available.', 0 );
		}

		return $meta_keys;
	}

	/**
	 * Get all distinct values for a given field.
	 *
	 * @since 4.3.0
	 * @param string $field   Field full name. For example: `meta.name.raw`
	 * @param int    $count   (Optional) Max number of different distinct values to be returned
	 * @param int    $blog_id (Optional) The blog ID. Sending `null` will use the current blog ID.
	 * @return array
	 */
	public function get_all_distinct_values( $field, $count = 10000, $blog_id = null ) {
		$aggregation_name = 'distinct_values';

		$es_query = [
			'_source' => false,
			'size'    => 0,
			'aggs'    => [
				$aggregation_name => [
					'terms' => [
						/**
						 * Filter the max. number of different distinct values to be returned by Elasticsearch.
						 *
						 * @since 4.3.0
						 * @hook ep_{$indexable_slug}_all_distinct_values
						 * @param {int}    $size  The number of different values. Default: 10000
						 * @param {string} $field The meta field
						 * @return {string} The new number of different values
						 */
						'size'  => apply_filters( 'ep_' . $this->slug . '_all_distinct_values', $count, $field ),
						'field' => $field,
					],
				],
			],
		];

		$response = Elasticsearch::factory()->query( $this->get_index_name( $blog_id ), $this->slug, $es_query, [] );

		if ( ! $response || empty( $response['aggregations'] ) || empty( $response['aggregations'][ $aggregation_name ] ) || empty( $response['aggregations'][ $aggregation_name ]['buckets'] ) ) {
			return [];
		}

		$values = [];
		foreach ( $response['aggregations'][ $aggregation_name ]['buckets'] as $es_bucket ) {
			$values[] = $es_bucket['key'];
		}

		return $values;
	}

	/**
	 * Should instantiate the indexable SyncManager and QueryIntegration, the main responsibles for the WP integration.
	 *
	 * @since 4.5.0
	 */
	public function setup() {}

	/**
	 * Given a mapping, add the ngram analyzer to it
	 *
	 * @since 4.5.0
	 * @param array $mapping The mapping
	 * @return array
	 */
	public function add_ngram_analyzer( array $mapping ) : array {
		$mapping['settings']['analysis']['analyzer']['edge_ngram_analyzer'] = array(
			'type'      => 'custom',
			'tokenizer' => 'standard',
			'filter'    => array(
				'lowercase',
				'edge_ngram',
			),
		);

		return $mapping;
	}
}
