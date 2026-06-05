<?php
/**
 * Vector Embeddings - Indexable
 *
 * As each indexable type (posts, terms, comments, users) uses different hooks, this abstract class is used to
 * keep implementations independent.
 *
 * @since 5.4.0
 * @package ElasticPress
 */

namespace ElasticPress\Feature\VectorEmbeddings;

use ElasticPress\Elasticsearch;

/**
 * Vector Embeddings Indexable abstract class
 */
abstract class Indexable {
	/**
	 * VectorEmbeddings instance
	 *
	 * @var VectorEmbeddings
	 */
	protected $feature;

	/**
	 * Class constructor
	 *
	 * @param VectorEmbeddings $feature The VectorEmbeddings feature instance
	 */
	public function __construct( VectorEmbeddings $feature ) {
		$this->feature = $feature;
	}

	/**
	 * Add a vector field to the Elasticsearch mapping.
	 *
	 * @param array $mapping      Current mapping.
	 * @param bool  $quantization Whether to use quantization for the vector field. Default false.
	 * @return array
	 */
	public function add_vector_mapping_field( array $mapping, bool $quantization = true ): array {
		$es_version = Elasticsearch::factory()->get_elasticsearch_version();

		if ( ! isset( $mapping['mappings'], $mapping['mappings']['properties'] ) ) {
			_doing_it_wrong(
				__METHOD__,
				esc_html__( 'The mapping is not valid.', 'elasticpress' ),
				'ElasticPress 5.4.0'
			);
			return $mapping;
		}

		// Don't add the field if it already exists.
		if ( isset( $mapping['mappings']['properties']['chunks'], $mapping['mappings']['properties']['ep_embeddings_control'] ) ) {
			return $mapping;
		}

		// Add the default vector field mapping.
		$mapping['mappings']['properties'] = array_merge(
			$mapping['mappings']['properties'],
			[
				'ep_embeddings_control' => [
					'properties' => [
						'is_processing' => [
							'type' => 'boolean',
						],
						'errors'        => [
							'type' => 'text',
						],
						'text_chunks'   => [
							'type' => 'text',
						],
					],
				],
				'chunks'                => [
					'type'       => 'nested',
					'properties' => [
						'vector' => [
							'type' => 'dense_vector',
							'dims' => $this->feature->get_dimensions(),
						],
					],
				],
			]
		);

		// Add extra vector fields for newer versions of Elasticsearch.
		if ( version_compare( $es_version, '8.0', '>=' ) ) {
			// The index (true or false, default true) and similarity (l2_norm, dot_product or cosine) fields
			// were added in 8.0. The similarity field must be set if index is true.
			$mapping['mappings']['properties']['chunks']['properties']['vector'] = array_merge(
				$mapping['mappings']['properties']['chunks']['properties']['vector'],
				[
					'index'      => true,
					'similarity' => 'cosine',
				]
			);

			// The element_type field was added in 8.6. This can be either float (default) or byte.
			if ( version_compare( $es_version, '8.6', '>=' ) ) {
				$mapping['mappings']['properties']['chunks']['properties']['vector']['element_type'] = 'float';
			}

			// The int8_hnsw type was added in 8.12.
			if ( $quantization && version_compare( $es_version, '8.12', '>=' ) ) {
				// This is supposed to result in better performance but slightly less accurate results.
				// See https://www.elastic.co/guide/en/elasticsearch/reference/8.13/knn-search.html#knn-search-quantized-example.
				// Can test with this on and off and compare results to see what works best.
				$mapping['mappings']['properties']['chunks']['properties']['vector']['index_options']['type'] = 'int8_hnsw';
			}
		}

		return $mapping;
	}

	/**
	 * Add the embedding data to the post vector sync args.
	 *
	 * @param array $args       The current sync args (an Elasticsearch document)
	 * @param array $embeddings The embeddings to add to the sync args
	 * @return array
	 */
	public function add_chunks_field_value( array $args, array $embeddings ): array {
		// If we still don't have embeddings, return early.
		if ( empty( $embeddings ) ) {
			return $args;
		}

		// Add the embeddings data to the sync args.
		$args['chunks'] = [];

		foreach ( $embeddings as $embedding ) {
			$args['chunks'][] = [
				'vector' => array_map( 'floatval', $embedding ),
			];
		}

		return $args;
	}

	/**
	 * Determine the way to send the text chunks to the Elasticsearch server.
	 *
	 * Depending on the size of the text chunks, it can either be sent using the index action args
	 * (the JSON object where we determine the index to be used, etc.) or as a regular field in the Elasticsearch.
	 * To avoid overhead, we prefer to send it as an index action arg, but sometimes it is just too big for it.
	 *
	 * @param array $text_chunks The text chunks
	 * @return string The method. Can be 'index_action_args' or 'es_doc_field'.
	 */
	protected function get_text_chunks_sending_method( $text_chunks ) {
		$post_chunks_size = mb_strlen( wp_json_encode( $text_chunks ), '8bit' );

		/**
		 * Filter to determine the threshold size for the text chunks to be sent as an index action arg.
		 *
		 * @hook ep_embeddings_sending_method_limit
		 * @since 5.4.0
		 *
		 * @param {int} $size The size limit in bytes. Defaults to 200kb.
		 * @return {int} The new $size value.
		 */
		$size_limit = apply_filters( 'ep_embeddings_sending_method_limit', 200 * KB_IN_BYTES );

		$method = $post_chunks_size < $size_limit ? 'index_action_args' : 'es_doc_field';

		/**
		 * Filter to determine the method to be used to send the text chunks.
		 *
		 * Unless you are implementing a custom solution, return should be either 'index_action_args' or 'es_doc_field'.
		 *
		 * @hook ep_embeddings_sending_method
		 * @since 5.4.0
		 *
		 * @param {string} $method           The method to be used.
		 * @param {int}    $text_chunks      The text chunks being analyzed.
		 * @param {int}    $post_chunks_size The determined size of the text chunks.
		 * @param {int}    $size_limit       The size limit in bytes. Defaults to 200kb.
		 * @return {string} The new $method value.
		 */
		return apply_filters( 'ep_embeddings_sending_method', $method, $text_chunks, $post_chunks_size, $size_limit );
	}
}
