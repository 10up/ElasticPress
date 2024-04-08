<?php
/**
 * Term indexable
 *
 * @since  3.1
 * @package  elasticpress
 */

namespace ElasticPress\Indexable\Term;

use \WP_Term_Query;
use ElasticPress\Elasticsearch;
use ElasticPress\Indexable;

if ( ! defined( 'ABSPATH' ) ) {
	// @codeCoverageIgnoreStart
	exit; // Exit if accessed directly.
	// @codeCoverageIgnoreEnd
}

/**
 * Term indexable class
 */
class Term extends Indexable {

	/**
	 * Indexable slug
	 *
	 * @var   string
	 * @since 3.1
	 */
	public $slug = 'term';

	/**
	 * Create indexable and initialize dependencies
	 *
	 * @since 3.1
	 */
	public function __construct() {
		$this->labels = [
			'plural'   => esc_html__( 'Terms', 'elasticpress' ),
			'singular' => esc_html__( 'Term', 'elasticpress' ),
		];
	}

	/**
	 * Instantiate the indexable SyncManager and QueryIntegration, the main responsibles for the WP integration.
	 *
	 * @since 4.5.0
	 * @return void
	 */
	public function setup() {
		$this->sync_manager      = new SyncManager( $this->slug );
		$this->query_integration = new QueryIntegration( $this->slug );
	}

	/**
	 * Format query vars into ES query
	 *
	 * @param  array $query_vars WP_Term_Query args.
	 * @since  3.1
	 * @return array
	 */
	public function format_args( $query_vars ) {
		$query_vars = $this->sanitize_query_vars( $query_vars );

		$formatted_args = [
			'from' => $this->parse_from( $query_vars ),
			'size' => $this->parse_size( $query_vars ),
		];

		$formatted_args = $this->maybe_orderby( $formatted_args, $query_vars );

		$filters = $this->parse_filters( $query_vars );
		if ( ! empty( $filters ) ) {
			$formatted_args['post_filter'] = $filters;
		}

		$formatted_args = $this->maybe_set_search_fields( $formatted_args, $query_vars );
		$formatted_args = $this->maybe_set_fields( $formatted_args, $query_vars );

		/**
		 * Filter full Elasticsearch query for Terms indexable
		 *
		 * @hook ep_term_formatted_args
		 * @param  {array} $query Elasticsearch query
		 * @param  {array} $query_vars Query variables
		 * @since  3.4
		 * @return {array} New query
		 */
		return apply_filters( 'ep_term_formatted_args', $formatted_args, $query_vars );
	}

	/**
	 * Generate the mapping array
	 *
	 * @since  3.6.0
	 * @return array
	 */
	public function generate_mapping() {
		$es_version = Elasticsearch::factory()->get_elasticsearch_version();

		if ( empty( $es_version ) ) {
			$es_version = apply_filters( 'ep_fallback_elasticsearch_version', '2.0' );
		}
		$es_version = (string) $es_version;

		$mapping_file = '7-0.php';

		if ( version_compare( $es_version, '7.0', '<' ) ) {
			$mapping_file = 'initial.php';
		}

		/**
		 * Filter mapping file for Terms indexable
		 *
		 * @hook ep_term_mapping_file
		 * @param  {string} $file File name
		 * @since  3.4
		 * @return {string} New file name
		 */
		$mapping = require apply_filters( 'ep_term_mapping_file', __DIR__ . '/../../../mappings/term/' . $mapping_file );

		/**
		 * Filter full Elasticsearch query for Terms indexable
		 *
		 * @hook ep_term_mapping
		 * @param  {array} $mapping Elasticsearch mapping
		 * @since  3.4
		 * @return {array} New mapping
		 */
		$mapping = apply_filters( 'ep_term_mapping', $mapping );

		return $mapping;
	}

	/**
	 * Prepare a term document for indexing
	 *
	 * @param  int $term_id Term ID
	 * @since  3.1
	 * @return bool|array
	 */
	public function prepare_document( $term_id ) {
		$term = get_term( $term_id );

		if ( ! $term || ! is_a( $term, 'WP_Term' ) ) {
			return false;
		}

		$term_args = [
			'term_id'          => $term->term_id,
			'ID'               => $term->term_id,
			'name'             => $term->name,
			'slug'             => $term->slug,
			'term_group'       => $term->group,
			'term_taxonomy_id' => $term->term_taxonomy_id,
			'taxonomy'         => $term->taxonomy,
			'description'      => $term->description,
			'parent'           => $term->parent,
			'count'            => $term->count,
			'meta'             => $this->prepare_meta_types( $this->prepare_meta( $term->term_id ) ),
			'hierarchy'        => $this->prepare_term_hierarchy( $term->term_id, $term->taxonomy ),
			'object_ids'       => $this->prepare_object_ids( $term->term_id, $term->taxonomy ),
		];

		/**
		 * Filter term fields pre-sync
		 *
		 * @hook ep_term_sync_args
		 * @param  {array} $term_args Current term fields
		 * @param  {int} $term_id Term ID
		 * @since  3.4
		 * @return {array} New fields
		 */
		$term_args = apply_filters( 'ep_term_sync_args', $term_args, $term_id );

		return $term_args;
	}

	/**
	 * Query DB for terms
	 *
	 * @param  array $args Query arguments
	 * @since  3.1
	 * @return array
	 */
	public function query_db( $args ) {
		$defaults = [
			'number'                 => $this->get_bulk_items_per_page(),
			'offset'                 => 0,
			'orderby'                => 'id',
			'order'                  => 'desc',
			'taxonomy'               => $this->get_indexable_taxonomies(),
			'hide_empty'             => false,
			'hierarchical'           => false,
			'update_term_meta_cache' => false,
			'cache_results'          => false,
		];

		if ( isset( $args['per_page'] ) ) {
			$args['number'] = $args['per_page'];
		}

		/**
		 * Filter database arguments for term query
		 *
		 * @hook ep_term_query_db_args
		 * @param  {array} $args Query arguments based to WP_Term_Query
		 * @since  3.4
		 * @return {array} New arguments
		 */
		$args = apply_filters( 'ep_term_query_db_args', wp_parse_args( $args, $defaults ) );

		$all_query_args = $args;

		unset( $all_query_args['number'] );
		unset( $all_query_args['offset'] );
		unset( $all_query_args['fields'] );

		/**
		 * Filter database arguments for term count query
		 *
		 * @hook ep_term_all_query_db_args
		 * @param  {array} $args Query arguments based to `wp_count_terms()`
		 * @since  3.4
		 * @return {array} New arguments
		 */
		$total_objects = wp_count_terms( apply_filters( 'ep_term_all_query_db_args', $all_query_args, $args ) );
		$total_objects = ! is_wp_error( $total_objects ) ? (int) $total_objects : 0;

		if ( ! empty( $args['offset'] ) ) {
			if ( (int) $args['offset'] >= $total_objects ) {
				$total_objects = 0;
			}
		}

		$query = new WP_Term_Query( $args );

		if ( is_array( $query->terms ) ) {
			array_walk( $query->terms, array( $this, 'remap_terms' ) );
		}

		return [
			'objects'       => $query->terms,
			'total_objects' => $total_objects,
		];
	}

	/**
	 * Returns indexable taxonomies for the current site
	 *
	 * @since  3.1
	 * @return mixed|void
	 */
	public function get_indexable_taxonomies() {
		$taxonomies        = get_taxonomies( [], 'objects' );
		$public_taxonomies = [];

		foreach ( $taxonomies as $taxonomy ) {
			if ( $taxonomy->public || $taxonomy->publicly_queryable ) {
				$public_taxonomies[] = $taxonomy->name;
			}
		}

		/**
		 * Filter indexable taxonomies for Terms indexable
		 *
		 * @hook ep_indexable_taxonomies
		 * @param  {array} $public_taxonomies Taxonomies
		 * @since  3.4
		 * @return {array} New taxonomies array
		 */
		return apply_filters( 'ep_indexable_taxonomies', $public_taxonomies );
	}

	/**
	 * Rebuild our term object to match the fields we need.
	 *
	 * In particular, result of WP_Term_Query does not
	 * include an "id" field, which our index command
	 * expects.
	 *
	 * @param  object $value Term object
	 * @since  3.1
	 * @return void Returns by reference
	 */
	public function remap_terms( &$value ) {
		$value = (object) array(
			'ID'               => $value->term_id,
			'term_id'          => $value->term_id,
			'name'             => $value->name,
			'slug'             => $value->slug,
			'term_group'       => $value->term_group,
			'term_taxonomy_id' => $value->term_taxonomy_id,
			'taxonomy'         => $value->taxonomy,
			'description'      => $value->description,
			'parent'           => $value->parent,
			'count'            => $value->count,
		);
	}

	/**
	 * Prepare meta to send to ES
	 *
	 * @param  int $term_id Term ID
	 * @since  3.1
	 * @return array
	 */
	public function prepare_meta( $term_id ) {
		$meta = (array) get_term_meta( $term_id );

		if ( empty( $meta ) ) {
			return [];
		}

		$prepared_meta = [];

		/**
		 * Filter index-able private meta
		 *
		 * Allows for specifying private meta keys that may be indexed in the same manner as public meta keys.
		 *
		 * @since 3.4
		 * @hook ep_prepare_term_meta_allowed_protected_keys
		 * @param {array} $allowed_protected_keys Array of index-able private meta keys.
		 * @param {int} $term_id Term ID.
		 * @return {array} New meta keys
		 */
		$allowed_protected_keys = apply_filters( 'ep_prepare_term_meta_allowed_protected_keys', [], $term_id );

		/**
		 * Filter non-indexed public meta
		 *
		 * Allows for specifying public meta keys that should be excluded from the ElasticPress index.
		 *
		 * @since 3.4
		 * @hook ep_prepare_term_meta_excluded_public_keys
		 * @param {array} $public_keys  Array of public meta keys to exclude from index.
		 * @param {int} $term_id Term ID.
		 * @return {array} New keys
		 */
		$excluded_public_keys = apply_filters(
			'ep_prepare_term_meta_excluded_public_keys',
			[
				'session_tokens',
			],
			$term_id
		);

		foreach ( $meta as $key => $value ) {

			$allow_index = false;

			if ( is_protected_meta( $key ) ) {

				if ( true === $allowed_protected_keys || in_array( $key, $allowed_protected_keys, true ) ) {
					$allow_index = true;
				}
			} else {

				if ( true !== $excluded_public_keys && ! in_array( $key, $excluded_public_keys, true ) ) {
					$allow_index = true;
				}
			}

			/**
			 * Filter kill switch for any term meta
			 *
			 * @since 3.4
			 * @hook ep_prepare_term_meta_whitelist_key
			 * @param  {boolean} $index_key Whether to index key or not
			 * @param {string} $key Key name
			 * @param {int} $term_id Term ID.
			 * @return {boolean} New index value
			 */
			if ( true === $allow_index || apply_filters( 'ep_prepare_term_meta_whitelist_key', false, $key, $term_id ) ) {
				$prepared_meta[ $key ] = maybe_unserialize( $value );
			}
		}

		return $prepared_meta;
	}

	/**
	 * Prepare term hierarchy to send to ES
	 *
	 * @param  int    $term_id Term ID.
	 * @param  string $taxonomy Term taxonomy.
	 * @since  3.1
	 * @return array
	 */
	public function prepare_term_hierarchy( $term_id, $taxonomy ) {
		$hierarchy = [];
		$children  = get_term_children( $term_id, $taxonomy );
		$ancestors = get_ancestors( $term_id, $taxonomy, 'taxonomy' );

		if ( ! empty( $children ) && ! is_wp_error( $children ) ) {
			$hierarchy['children']['terms'] = $children;
			$children_count                 = 0;

			foreach ( $children as $child_term_id ) {
				$child_term      = get_term( $child_term_id );
				$children_count += (int) $child_term->count;
			}

			$hierarchy['children']['count'] = $children_count;
		} else {
			$hierarchy['children']['terms'] = 0;
			$hierarchy['children']['count'] = 0;
		}

		if ( ! empty( $ancestors ) ) {
			$hierarchy['ancestors']['terms'] = $ancestors;
		} else {
			$hierarchy['ancestors']['terms'] = 0;
		}

		return $hierarchy;
	}

	/**
	 * Prepare object IDs to send to ES
	 *
	 * @param  int    $term_id Term ID.
	 * @param  string $taxonomy Term taxonomy.
	 * @since  3.1
	 * @return array
	 */
	public function prepare_object_ids( $term_id, $taxonomy ) {
		$ids        = [];
		$object_ids = get_objects_in_term( [ $term_id ], [ $taxonomy ] );

		if ( ! empty( $object_ids ) && ! is_wp_error( $object_ids ) ) {
			$ids['value'] = array_map( 'absint', array_values( $object_ids ) );
		} else {
			$ids['value'] = 0;
		}

		return $ids;
	}

	/**
	 * Parse an 'order' query variable and cast it to ASC or DESC as necessary.
	 *
	 * @access protected
	 *
	 * @param  string $order The 'order' query variable.
	 * @since  3.1
	 * @return string The sanitized 'order' query variable.
	 */
	protected function parse_order( $order ) {
		if ( ! is_string( $order ) || empty( $order ) ) {
			return 'desc';
		}

		if ( 'ASC' === strtoupper( $order ) ) {
			return 'asc';
		} else {
			return 'desc';
		}
	}

	/**
	 * Convert the alias to a properly-prefixed sort value.
	 *
	 * @access protected
	 *
	 * @param  string $orderby Alias or path for the field to order by.
	 * @param  string $order Order direction
	 * @param  array  $args Query args
	 * @since  3.1
	 * @return array
	 */
	protected function parse_orderby( $orderby, $order, $args ) {
		$sort = [];

		if ( empty( $orderby ) ) {
			return $sort;
		}

		$from_to = [
			'slug'        => 'slug.raw',
			'id'          => 'term_id',
			'description' => 'description.sortable',
		];

		if ( in_array( $orderby, [ 'meta_value', 'meta_value_num' ], true ) ) {
			if ( empty( $args['meta_key'] ) ) {
				return $sort;
			} else {
				$from_to['meta_value']     = 'meta.' . $args['meta_key'] . '.value';
				$from_to['meta_value_num'] = 'meta.' . $args['meta_key'] . '.long';
			}
		}

		if ( 'name' === $orderby ) {
			$es_version      = Elasticsearch::factory()->get_elasticsearch_version();
			$from_to['name'] = version_compare( (string) $es_version, '7.0', '<' ) ? 'name.raw' : 'name.sortable';
		}

		$orderby = $from_to[ $orderby ] ?? $orderby;

		$sort[] = array(
			$orderby => array(
				'order' => $order,
			),
		);

		return $sort;
	}

	/**
	 * Sanitize WP_Term_Query arguments to be used to create the ES query.
	 *
	 * @since 5.1.0
	 * @param array $query_vars WP_Term_Query arguments
	 * @return array
	 */
	protected function sanitize_query_vars( $query_vars ) {
		if ( ! empty( $query_vars['get'] ) && 'all' === $query_vars['get'] ) {
			$query_vars['childless']    = false;
			$query_vars['child_of']     = 0;
			$query_vars['hide_empty']   = false;
			$query_vars['hierarchical'] = false;
			$query_vars['pad_counts']   = false;
		}

		$query_vars['taxonomy'] = ( ! empty( $query_vars['taxonomy'] ) ) ?
			(array) $query_vars['taxonomy'] :
			[];

		return $query_vars;
	}

	/**
	 * Parse the `from` clause of the ES Query.
	 *
	 * @since 5.1.0
	 * @param array $query_vars WP_Term_Query arguments
	 * @return int
	 */
	protected function parse_from( $query_vars ) {
		return ( isset( $query_vars['offset'] ) ) ? (int) $query_vars['offset'] : 0;
	}

	/**
	 * Parse the `size` clause of the ES Query.
	 *
	 * @since 5.1.0
	 * @param array $query_vars WP_Term_Query arguments
	 * @return int
	 */
	protected function parse_size( $query_vars ) {
		if ( ! empty( $query_vars['number'] ) ) {
			$number = (int) $query_vars['number'];
		} else {
			/**
			 * Set the maximum results window size.
			 *
			 * The request will return a HTTP 500 Internal Error if the size of the
			 * request is larger than the [index.max_result_window] parameter in ES.
			 * See the scroll api for a more efficient way to request large data sets.
			 *
			 * @return int The max results window size.
			 *
			 * @since 2.3.0
			 */
			$number = apply_filters( 'ep_max_results_window', 10000 );
		}

		return $number;
	}

	/**
	 * Parse the order of results in the ES query.
	 *
	 * @since 5.1.0
	 * @param array $formatted_args Formatted Elasticsearch query
	 * @param array $query_vars     WP_Term_Query arguments
	 * @return array
	 */
	protected function maybe_orderby( $formatted_args, $query_vars ) {
		// Set sort order, default is 'ASC'.
		if ( ! empty( $query_vars['order'] ) ) {
			$order = $this->parse_order( $query_vars['order'] );
		} else {
			$order = 'desc';
		}

		// Set orderby, default is 'name'.
		if ( empty( $query_vars['orderby'] ) ) {
			$query_vars['orderby'] = 'name';
		}

		// Set sort type.
		$formatted_args['sort'] = $this->parse_orderby( $query_vars['orderby'], $order, $query_vars );

		return $formatted_args;
	}

	/**
	 * Based on WP_Term_Query arguments, parses the various filters that could be applied into the ES query.
	 *
	 * @since 5.1.0
	 * @param array $query_vars WP_Term_Query arguments
	 * @return array
	 */
	protected function parse_filters( $query_vars ) {
		$filters = [
			'taxonomy'                => $this->parse_taxonomy( $query_vars ),
			'object_ids'              => $this->parse_object_ids( $query_vars ),
			'include'                 => $this->parse_include( $query_vars ),
			'exclude'                 => $this->parse_exclude( $query_vars ),
			'exclude_tree'            => $this->parse_exclude_tree( $query_vars ),
			'name'                    => $this->parse_name( $query_vars ),
			'slug'                    => $this->parse_slug( $query_vars ),
			'term_taxonomy_id'        => $this->parse_term_taxonomy_id( $query_vars ),
			'hierarchical_hide_empty' => $this->parse_hierarchical_hide_empty( $query_vars ),
			'child_of'                => $this->parse_child_of( $query_vars ),
			'parent'                  => $this->parse_parent( $query_vars ),
			'childless'               => $this->parse_childless( $query_vars ),
			'meta_query'              => $this->parse_meta_queries( $query_vars ),
		];

		$filters = array_values( array_filter( $filters ) );

		if ( ! empty( $filters ) ) {
			$filters = [
				'bool' => [
					'must' => $filters,
				],
			];
		}

		return $filters;
	}

	/**
	 * Parse the `taxonomy` WP Term Query arg and transform it into an ES query clause.
	 *
	 * @since 5.1.0
	 * @param array $query_vars WP_Term_Query arguments
	 * @return array
	 */
	protected function parse_taxonomy( $query_vars ) {
		if ( empty( $query_vars['taxonomy'] ) ) {
			return [];
		}

		if ( count( $query_vars['taxonomy'] ) < 2 ) {
			return [
				'term' => [
					'taxonomy.raw' => $query_vars['taxonomy'][0],
				],
			];
		}

		return [
			'terms' => [
				'taxonomy.raw' => $query_vars['taxonomy'],
			],
		];
	}

	/**
	 * Parse the `object_ids` WP Term Query arg and transform it into an ES query clause.
	 *
	 * @since 5.1.0
	 * @param array $query_vars WP_Term_Query arguments
	 * @return array
	 */
	protected function parse_object_ids( $query_vars ) {
		if ( empty( $query_vars['object_ids'] ) ) {
			return [];
		}

		return [
			'bool' => [
				'must' => [
					'terms' => [
						'object_ids.value' => (array) $query_vars['object_ids'],
					],
				],
			],
		];
	}

	/**
	 * Parse the `include` WP Term Query arg and transform it into an ES query clause.
	 *
	 * @since 5.1.0
	 * @param array $query_vars WP_Term_Query arguments
	 * @return array
	 */
	protected function parse_include( $query_vars ) {
		if ( empty( $query_vars['include'] ) ) {
			return [];
		}

		return [
			'bool' => [
				'must' => [
					'terms' => [
						'term_id' => array_values( (array) $query_vars['include'] ),
					],
				],
			],
		];
	}

	/**
	 * Parse the `exclude` WP Term Query arg and transform it into an ES query clause.
	 *
	 * @since 5.1.0
	 * @param array $query_vars WP_Term_Query arguments
	 * @return array
	 */
	protected function parse_exclude( $query_vars ) {
		if ( ! empty( $query_vars['include'] ) || empty( $query_vars['exclude'] ) ) {
			return [];
		}

		return [
			'bool' => [
				'must_not' => [
					'terms' => [
						'term_id' => array_values( (array) $query_vars['exclude'] ),
					],
				],
			],
		];
	}

	/**
	 * Parse the `exclude_tree` WP Term Query arg and transform it into an ES query clause.
	 *
	 * @since 5.1.0
	 * @param array $query_vars WP_Term_Query arguments
	 * @return array
	 */
	protected function parse_exclude_tree( $query_vars ) {
		if ( ! empty( $query_vars['include'] ) || empty( $query_vars['exclude_tree'] ) ) {
			return [];
		}

		return [
			'bool' => [
				'must_not' => [
					[
						'terms' => [
							'term_id' => array_values( (array) $query_vars['exclude_tree'] ),
						],
					],
					[
						'terms' => [
							'parent' => array_values( (array) $query_vars['exclude_tree'] ),
						],
					],
				],
			],
		];
	}

	/**
	 * Parse the `name` WP Term Query arg and transform it into an ES query clause.
	 *
	 * @since 5.1.0
	 * @param array $query_vars WP_Term_Query arguments
	 * @return array
	 */
	protected function parse_name( $query_vars ) {
		if ( empty( $query_vars['name'] ) ) {
			return [];
		}

		return [
			'terms' => [
				'name.raw' => (array) $query_vars['name'],
			],
		];
	}

	/**
	 * Parse the `slug` WP Term Query arg and transform it into an ES query clause.
	 *
	 * @since 5.1.0
	 * @param array $query_vars WP_Term_Query arguments
	 * @return array
	 */
	protected function parse_slug( $query_vars ) {
		if ( empty( $query_vars['slug'] ) ) {
			return [];
		}

		$query_vars['slug'] = (array) $query_vars['slug'];
		$query_vars['slug'] = array_map( 'sanitize_title', $query_vars['slug'] );

		return [
			'terms' => [
				'slug.raw' => (array) $query_vars['slug'],
			],
		];
	}

	/**
	 * Parse the `term_taxonomy_id` WP Term Query arg and transform it into an ES query clause.
	 *
	 * @since 5.1.0
	 * @param array $query_vars WP_Term_Query arguments
	 * @return array
	 */
	protected function parse_term_taxonomy_id( $query_vars ) {
		if ( empty( $query_vars['term_taxonomy_id'] ) ) {
			return [];
		}

		return [
			'bool' => [
				'must' => [
					'terms' => [
						'term_taxonomy_id' => array_values( (array) $query_vars['term_taxonomy_id'] ),
					],
				],
			],
		];
	}

	/**
	 * Parse the `hide_empty` and `hierarchical` WP Term Query args and transform them into ES query clauses.
	 *
	 * `hierarchical` needs to work in conjunction with `hide_empty`, as per WP docs:
	 * > `hierarchical`: Whether to include terms that have non-empty descendants (even if $hide_empty is set to true).
	 *
	 * In summary:
	 * - hide_empty AND hierarchical: count > 1 OR hierarchy.children > 1
	 * - hide_empty AND NOT hierarchical: count > 1 (ignore hierarchy.children)
	 * - NOT hide_empty (AND hierarchical): there is no need to limit the query
	 *
	 * @see https://developer.wordpress.org/reference/classes/WP_Term_Query/__construct/
	 * @since 5.1.0
	 * @param array $query_vars WP_Term_Query arguments
	 * @return array
	 */
	protected function parse_hierarchical_hide_empty( $query_vars ) {
		$hide_empty = isset( $query_vars['hide_empty'] ) ? $query_vars['hide_empty'] : '';
		if ( ! $hide_empty ) {
			return [];
		}

		$hierarchical = isset( $query_vars['hierarchical'] ) ? $query_vars['hierarchical'] : '';
		if ( ! $hierarchical ) {
			return [
				'range' => [
					'count' => [
						'gte' => 1,
					],
				],
			];
		}

		return [
			'bool' => [
				'should' => [
					[
						'range' => [
							'count' => [
								'gte' => 1,
							],
						],
					],
					[
						'range' => [
							'hierarchy.children.count' => [
								'gte' => 1,
							],
						],
					],
				],
			],
		];
	}

	/**
	 * Parse the `child_of` WP Term Query arg and transform it into an ES query clause.
	 *
	 * @since 5.1.0
	 * @param array $query_vars WP_Term_Query arguments
	 * @return array
	 */
	protected function parse_child_of( $query_vars ) {
		if ( empty( $query_vars['child_of'] ) || count( $query_vars['taxonomy'] ) > 1 ) {
			return [];
		}

		return [
			'bool' => [
				'must' => [
					'match_phrase' => [
						'hierarchy.ancestors.terms' => (int) $query_vars['child_of'],
					],
				],
			],
		];
	}

	/**
	 * Parse the `parent` WP Term Query arg and transform it into an ES query clause.
	 *
	 * @since 5.1.0
	 * @param array $query_vars WP_Term_Query arguments
	 * @return array
	 */
	protected function parse_parent( $query_vars ) {
		if ( ! isset( $query_vars['parent'] ) || '' === $query_vars['parent'] ) {
			return [];
		}

		return [
			'bool' => [
				'must' => [
					'term' => [
						'parent' => (int) $query_vars['parent'],
					],
				],
			],
		];
	}

	/**
	 * Parse the `childless` WP Term Query arg and transform it into an ES query clause.
	 *
	 * @since 5.1.0
	 * @param array $query_vars WP_Term_Query arguments
	 * @return array
	 */
	protected function parse_childless( $query_vars ) {
		if ( empty( $query_vars['childless'] ) ) {
			return [];
		}

		return [
			'bool' => [
				'must' => [
					'term' => [
						'hierarchy.children.terms' => 0,
					],
				],
			],
		];
	}

	/**
	 * Parse WP Term Query meta queries and transform them into ES query clauses.
	 *
	 * @since 5.1.0
	 * @param array $query_vars WP_Term_Query arguments
	 * @return array
	 */
	protected function parse_meta_queries( $query_vars ) {
		$meta_queries = [];
		/**
		 * Support `meta_key`, `meta_value`, and `meta_compare` query args
		 */
		if ( ! empty( $query_vars['meta_key'] ) ) {
			$meta_query_array = [
				'key' => $query_vars['meta_key'],
			];

			if ( isset( $query_vars['meta_value'] ) && '' !== $query_vars['meta_value'] ) {
				$meta_query_array['value'] = $query_vars['meta_value'];
			}

			if ( isset( $query_vars['meta_compare'] ) ) {
				$meta_query_array['compare'] = $query_vars['meta_compare'];
			}

			$meta_queries[] = $meta_query_array;
		}

		/**
		 * Support 'meta_query' query var.
		 */
		if ( ! empty( $query_vars['meta_query'] ) ) {
			$meta_queries = array_merge( $meta_queries, $query_vars['meta_query'] );
		}

		if ( ! empty( $meta_queries ) ) {
			$built_meta_queries = $this->build_meta_query( $meta_queries );

			if ( $built_meta_queries ) {
				return $built_meta_queries;
			}
		}

		return [];
	}

	/**
	 * If in a search context, using `name__like`, or `description__like` set search fields, otherwise query everything.
	 *
	 * @since 5.1.0
	 * @param array $formatted_args Formatted Elasticsearch query
	 * @param array $query_vars     WP_Term_Query arguments
	 * @return array
	 */
	protected function maybe_set_search_fields( $formatted_args, $query_vars ) {
		if ( empty( $query_vars['search'] ) && empty( $query_vars['name__like'] ) && empty( $query_vars['description__like'] ) ) {
			$formatted_args['query']['match_all'] = [
				'boost' => 1,
			];

			return $formatted_args;
		}

		$search        = ! empty( $query_vars['search'] ) ? $query_vars['search'] : '';
		$search_fields = [];

		if ( ! empty( $query_vars['name__like'] ) ) {
			$search          = $query_vars['name__like'];
			$search_fields[] = 'name';
		}

		if ( ! empty( $query_vars['description__like'] ) ) {
			$search          = $query_vars['description__like'];
			$search_fields[] = 'description';
		}

		/**
		 * Allow for search field specification
		 */
		if ( ! empty( $query_vars['search_fields'] ) ) {
			$search_fields = $query_vars['search_fields'];
		}

		if ( ! empty( $search_fields ) ) {
			$prepared_search_fields = [];

			if ( ! empty( $search_fields['meta'] ) ) {
				$metas = (array) $search_fields['meta'];

				foreach ( $metas as $meta ) {
					$prepared_search_fields[] = 'meta.' . $meta . '.value';
				}

				unset( $search_fields['meta'] );
			}

			$prepared_search_fields = array_merge( $search_fields, $prepared_search_fields );
		} else {
			$prepared_search_fields = [
				'name',
				'slug',
				'taxonomy',
				'description',
			];
		}

		/**
		 * Filter fields to search on Term query
		 *
		 * @hook ep_term_search_fields
		 * @param  {array} $search_fields Search fields
		 * @param  {array} $query_vars Query variables
		 * @since  3.4
		 * @return {array} New search fields
		 */
		$prepared_search_fields = apply_filters( 'ep_term_search_fields', $prepared_search_fields, $query_vars );

		$search_algorithm        = $this->get_search_algorithm( $search, $prepared_search_fields, $query_vars );
		$formatted_args['query'] = $search_algorithm->get_query( 'term', $search, $prepared_search_fields, $query_vars );

		return $formatted_args;
	}

	/**
	 * If needed set the `fields` ES query clause.
	 *
	 * @since 5.1.0
	 * @param array $formatted_args Formatted Elasticsearch query
	 * @param array $query_vars     WP_Term_Query arguments
	 * @return array
	 */
	protected function maybe_set_fields( $formatted_args, $query_vars ) {
		if ( ! isset( $query_vars['fields'] ) ) {
			return $formatted_args;
		}

		switch ( $query_vars['fields'] ) {
			case 'ids':
				$formatted_args['_source'] = [
					'includes' => [
						'term_id',
					],
				];
				break;

			case 'id=>name':
				$formatted_args['_source'] = [
					'includes' => [
						'term_id',
						'name',
					],
				];
				break;

			case 'id=>parent':
				$formatted_args['_source'] = [
					'includes' => [
						'term_id',
						'parent',
					],
				];
				break;

			case 'id=>slug':
				$formatted_args['_source'] = [
					'includes' => [
						'term_id',
						'slug',
					],
				];
				break;

			case 'names':
				$formatted_args['_source'] = [
					'includes' => [
						'name',
					],
				];
				break;
			case 'tt_ids':
				$formatted_args['_source'] = [
					'includes' => [
						'term_taxonomy_id',
					],
				];
				break;
		}

		return $formatted_args;
	}
}
