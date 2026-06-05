<?php
/**
 * Vector Embeddings Status Report
 *
 * @since 5.4.0
 * @package ElasticPress
 */

namespace ElasticPress\Feature\VectorEmbeddings;

use ElasticPress\StatusReport\Report;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Vector Embeddings feature
 */
class StatusReport extends Report {

	/**
	 * Return the report title
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'ElasticPress.io Vector Embeddings', 'elasticpress' );
	}

	/**
	 * Return the report fields
	 *
	 * @return array
	 */
	public function get_groups(): array {
		return [
			[
				'title'  => __( 'Vector Embeddings', 'elasticpress' ),
				'fields' => [
					[
						'label' => __( 'Content in the queue', 'elasticpress' ),
						'value' => $this->get_content_in_queue(),
					],
					[
						'label' => __( 'Content with errors', 'elasticpress' ),
						'value' => $this->get_content_with_errors(),
					],
				],
			],
		];
	}

	/**
	 * Return the number of content items in the queue
	 *
	 * @return string
	 */
	protected function get_content_in_queue(): string {
		$es_query = [
			'size'             => 10,
			'track_total_hits' => true,
			'_source'          => [
				'includes' => [ 'post_id' ],
			],
			'query'            => [
				'term' => [
					'ep_embeddings_control.is_processing' => true,
				],
			],
		];

		return $this->display_count_and_list( $es_query );
	}

	/**
	 * Return the number of content items with errors
	 *
	 * @return string
	 */
	protected function get_content_with_errors(): string {
		$es_query = [
			'size'             => 10,
			'track_total_hits' => true,
			'_source'          => [
				'includes' => [ 'post_id' ],
			],
			'query'            => [
				'exists' => [ 'field' => 'ep_embeddings_control.errors' ],
			],
		];

		return $this->display_count_and_list( $es_query );
	}

	/**
	 * Given an Elasticsearch query, display the total results count and a partial list of post links
	 *
	 * @param array $es_query The Elasticsearch query
	 * @return string
	 */
	protected function display_count_and_list( $es_query ): string {
		$post_indexable = \ElasticPress\Indexables::factory()->get( 'post' );
		$es_response    = $post_indexable->query_es( $es_query, [] );

		if ( ! $es_response || ! isset( $es_response['found_documents']['value'] ) ) {
			return 'N/A';
		}

		$total_count = $es_response['found_documents']['value'];
		if ( ! $total_count ) {
			return (string) $total_count;
		}

		$post_links = array_map(
			function ( $post_id ) {
				return '<a href="' . get_edit_post_link( $post_id ) . '">' . $post_id . '</a>';
			},
			wp_list_pluck( $es_response['documents'], 'post_id' )
		);

		if ( count( $post_links ) === $total_count ) {
			return wp_sprintf(
				// translators: 1: total count, 2: list of post links
				__( '%1$d (%2$l)', 'elasticpress' ),
				$total_count,
				$post_links
			);
		}

		return sprintf(
			// translators: 1: total count, 2: list of post links
			__( '%1$d (%2$s, and more)', 'elasticpress' ),
			$total_count,
			implode( ', ', $post_links )
		);
	}
}
