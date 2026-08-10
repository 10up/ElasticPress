<?php
/**
 * kNN search algorithm
 *
 * @since 5.4.0
 * @package elasticpress
 */

namespace ElasticPress\Feature\SemanticSearch\SearchAlgorithm;

if ( ! defined( 'ABSPATH' ) ) {
	// @codeCoverageIgnoreStart
	exit; // Exit if accessed directly.
	// @codeCoverageIgnoreEnd
}

/**
 * kNN search algorithm class.
 */
class Knn extends SearchAlgorithm {
	/**
	 * Search algorithm slug.
	 *
	 * @return string
	 */
	public function get_slug(): string {
		return 'knn';
	}

	/**
	 * Search algorithm name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return esc_html__( 'kNN', 'elasticpress' );
	}

	/**
	 * Search algorithm description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return esc_html__( 'Search using Elasticsearch kNN.', 'elasticpress' );
	}

	/**
	 * Return the whole ES query
	 *
	 * @param array     $formatted_args Formatted Elasticsearch query
	 * @param array     $args           The WP_Query variables
	 * @param \WP_Query $query          The WP_Query object
	 * @return array
	 */
	public function get_es_query( $formatted_args, $args, $query ): array {
		if ( ! $query->is_search() ) {
			return $formatted_args;
		}

		$query_embedding = $this->get_search_term_vector( $args, $query->query_vars['s'] );
		if ( empty( $query_embedding ) ) {
			return $formatted_args;
		}

		$semantic_search_feature = \ElasticPress\Features::factory()->get_registered_feature( 'semantic_search' );

		return [
			'from'        => $formatted_args['from'],
			'size'        => $formatted_args['size'],
			'post_filter' => $formatted_args['post_filter'],
			'min_score'   => $semantic_search_feature->get_min_score(),
			'knn'         => [
				'field'          => 'chunks.vector',
				'query_vector'   => $query_embedding,
				'num_candidates' => 200,
				'k'              => (int) $formatted_args['size'],
			],
		];
	}
}
