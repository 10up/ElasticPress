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
	 * @var QueryIntegration
	 * @since  3.0
	 */
	public $query_integration;

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

		if ( isset( $query->query_vars['ep_integrate'] ) && false === $query->query_vars['ep_integrate'] ) {
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
		$new_date  = date_create( $meta_value, \wp_timezone() );
		if ( $new_date ) {
			$timestamp = $new_date->getTimestamp();

			// PHP allows DateTime to build dates with the non-existing year 0000, and this causes
			// issues when integrating into stricter systems. This is by design:
			// https://bugs.php.net/bug.php?id=60288
			if ( false !== $timestamp && '0000' !== $new_date->format( 'Y' ) ) {
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

			/**
			 * There is a strange case where meta_query looks like this:
			 * array(
			 *  "something" => array(
			 *   array(
			 *      'key' => ...
			 *      ...
			 *   )
			 *  )
			 * )
			 *
			 * Somehow WordPress (WooCommerce) handles that case so we need to as well.
			 *
			 * @since  2.1
			 */
			if ( is_array( $single_meta_query ) && empty( $single_meta_query['key'] ) ) {
				reset( $single_meta_query );
				$first_key = key( $single_meta_query );

				if ( is_array( $single_meta_query[ $first_key ] ) ) {
					$single_meta_query = $single_meta_query[ $first_key ];
				}
			}

			if ( ! empty( $single_meta_query['key'] ) ) {

				$terms_obj = false;

				$compare = '=';
				if ( ! empty( $single_meta_query['compare'] ) ) {
					$compare = strtolower( $single_meta_query['compare'] );
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
			} elseif ( is_array( $single_meta_query ) && isset( $single_meta_query[0] ) && is_array( $single_meta_query[0] ) ) {
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
	 * Must implement a method that handles sending mapping to ES
	 *
	 * @return boolean
	 */
	abstract public function put_mapping();

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
	 * @return boolean
	 */
	abstract public function query_db( $args );
}
