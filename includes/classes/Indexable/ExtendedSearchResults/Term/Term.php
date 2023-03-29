<?php
/**
 * Extended Search Results - Term indexable
 *
 * @since 5.0.0
 * @package elasticpress
 */

namespace ElasticPress\Indexable\ExtendedSearchResults\Term;

use \ElasticPress\Indexable\Post\Post as PostIndexable;

/**
 * Extended Search Results - Term indexable class
 */
class Term extends PostIndexable {
	/**
	 * Indexable slug
	 *
	 * @var string
	 */
	public $slug = 'esr_term';

	/**
	 * Flag to indicate if the indexable has support for
	 * `id_range` pagination method during a sync.
	 *
	 * @var boolean
	 */
	public $support_indexing_advanced_pagination = false;

	/**
	 * Create indexable and initialize dependencies
	 */
	public function __construct() {
		$this->labels = [
			'plural'   => esc_html__( 'ESR - Terms', 'elasticpress' ),
			'singular' => esc_html__( 'ESR - Term', 'elasticpress' ),
		];
	}

	/**
	 * Prepare a term document for indexing
	 *
	 * @param  int $term_id Term ID
	 * @return bool|array
	 */
	public function prepare_document( $term_id ) {
		$term = get_term( $term_id );

		if ( ! $term || ! is_a( $term, 'WP_Term' ) ) {
			return false;
		}

		$term_args = array(
			'ID'                    => $term->term_id,
			'post_id'               => $term->term_id,
			'post_title'            => $term->name,
			'post_excerpt'          => $term->description,
			'post_content_filtered' => apply_filters( 'the_content', $term->description ),
			'post_content'          => $term->description,
			'post_status'           => 'publish',
			'post_name'             => $term->slug,
			'post_type'             => 'ep_esr_term',
			'permalink'             => get_term_link( $term->term_id, $term->taxonomy ),
			'post_author'           => [
				'raw'          => '',
				'login'        => '',
				'display_name' => '',
				'id'           => '',
			],
		);

		/**
		 * Filter term fields pre-sync
		 *
		 * @hook ep_esr_term_sync_args
		 * @param  {array} $term_args Current term fields
		 * @param  {int} $term_id Term ID
		 * @since  5.0.0
		 * @return {array} New fields
		 */
		$term_args = apply_filters( 'ep_esr_term_sync_args', $term_args, $term_id );

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
			'number'     => $this->get_bulk_items_per_page(),
			'offset'     => 0,
			'orderby'    => 'id',
			'order'      => 'desc',
			'taxonomy'   => $this->get_indexable_taxonomies(),
			'hide_empty' => false,
		];

		if ( isset( $args['per_page'] ) ) {
			$args['number'] = $args['per_page'];
		}

		/**
		 * Filter database arguments for term query
		 *
		 * @hook ep_esr_term_query_db_args
		 * @param  {array} $args Query arguments based to WP_Term_Query
		 * @since  5.0.0
		 * @return {array} New arguments
		 */
		$args = apply_filters( 'ep_esr_term_query_db_args', wp_parse_args( $args, $defaults ) );

		$all_query_args = $args;

		unset( $all_query_args['number'] );
		unset( $all_query_args['offset'] );
		unset( $all_query_args['fields'] );

		/**
		 * This just seems so inefficient.
		 *
		 * @todo Better way to do this?
		 */

		/**
		 * Filter database arguments for term count query
		 *
		 * @hook ep_esr_term_all_query_db_args
		 * @param  {array} $args Query arguments based to WP_Term_Query
		 * @since  5.0.0
		 * @return {array} New arguments
		 */
		$all_query = new \WP_Term_Query( apply_filters( 'ep_esr_term_all_query_db_args', $all_query_args, $args ) );

		$total_objects = count( $all_query->terms );

		if ( ! empty( $args['offset'] ) ) {
			if ( (int) $args['offset'] >= $total_objects ) {
				$total_objects = 0;
			}
		}

		$query = new \WP_Term_Query( $args );

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
		 * @hook ep_esr_indexable_taxonomies
		 * @param  {array} $public_taxonomies Taxonomies
		 * @since  5.0.0
		 * @return {array} New taxonomies array
		 */
		return apply_filters( 'ep_esr_indexable_taxonomies', $public_taxonomies );
	}

	/**
	 * Rebuild our term object to match the fields we need.
	 *
	 * In particular, result of WP_Term_Query does not
	 * include an "id" field, which our index command
	 * expects.
	 *
	 * @param  object $value Term object
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
	 * Send mapping to Elasticsearch
	 *
	 * @param string $return_type Desired return type. Can be either 'bool' or 'raw'
	 * @return bool|WP_Error
	 */
	public function put_mapping( $return_type = 'bool' ) {
		return true;
	}

	/**
	 * Delete an index within the indexable
	 *
	 * @param  int $blog_id `null` means current blog.
	 * @return boolean
	 */
	public function delete_index( $blog_id = null ) {
		return false;
	}

	/**
	 * Get the name of the index. Each indexable needs a unique index name
	 *
	 * @param  int $blog_id `null` means current blog.
	 * @return string
	 */
	public function get_index_name( $blog_id = null ) {
		$post_indexable = \ElasticPress\Indexables::factory()->get( 'post' );
		return $post_indexable->get_index_name( $blog_id );
	}
}
