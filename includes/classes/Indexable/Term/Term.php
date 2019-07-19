<?php
/**
 * Term indexable
 *
 * @since  3.1
 * @package  elasticpress
 */

namespace ElasticPress\Indexable\Term;

use ElasticPress\Indexable as Indexable;
use ElasticPress\Elasticsearch as Elasticsearch;
use \WP_Term_Query as WP_Term_Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
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
	 * Total terms
	 *
	 * @var   int
	 * @since 3.1
	 */
	public $total_terms = 0;

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

		$all_query = new WP_Term_Query( [ 'count' => true, 'fields' => 'ids' ] );

		$this->total_terms       = count( $all_query->terms );
		$this->sync_manager      = new SyncManager( $this->slug );
		$this->query_integration = new QueryIntegration();
	}

	/**
	 * Put mapping for terms
	 *
	 * @since  3.1
	 * @return boolean
	 */
	public function put_mapping() {
		$es_version = Elasticsearch::factory()->get_elasticsearch_version();

		if ( empty( $es_version ) ) {
			$es_version = apply_filters( 'ep_fallback_elasticsearch_version', '2.0' );
		}

		$mapping_file = 'initial.php';

		if ( version_compare( $es_version, '5.0', '<' ) ) {
			$mapping_file = 'pre-5-0.php';
		}

		$mapping = require apply_filters( 'ep_term_mapping_file', __DIR__ . '/../../../mappings/term/' . $mapping_file );

		$mapping = apply_filters( 'ep_term_mapping', $mapping );

		return Elasticsearch::factory()->put_mapping( $this->get_index_name(), $mapping );
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
		];

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
			'number'   => $this->get_bulk_items_per_page(),
			'offset'   => 0,
			'orderby'  => 'id',
			'order'    => 'desc',
			'taxonomy' => $this->get_indexable_taxonomies(),
		];

		if ( isset( $args['per_page'] ) ) {
			$args['number'] = $args['per_page'];
		}

		$args = apply_filters( 'ep_term_query_db_args', wp_parse_args( $args, $defaults ) );

		$query = new WP_Term_Query( $args );

		array_walk( $query->terms, array( $this, 'remap_terms' ) );

		return [
			'objects'       => $query->terms,
			'total_objects' => $this->total_terms,
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
		 * @since 3.1
		 *
		 * @param array        Array of index-able private meta keys.
		 * @param int $term_id Term ID.
		 */
		$allowed_protected_keys = apply_filters( 'ep_prepare_term_meta_allowed_protected_keys', [], $term_id );

		/**
		 * Filter non-indexed public meta
		 *
		 * Allows for specifying public meta keys that should be excluded from the ElasticPress index.
		 *
		 * @since 3.1
		 *
		 * @param array        Array of public meta keys to exclude from index.
		 * @param int $term_id Term ID.
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

			if ( true === $allow_index || apply_filters( 'ep_prepare_term_meta_whitelist_key', false, $key, $term_id ) ) {
				$prepared_meta[ $key ] = maybe_unserialize( $value );
			}
		}

		return $prepared_meta;
	}

}
