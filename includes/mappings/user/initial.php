<?php
/**
 * Elasticsearch mapping for users
 *
 * @since  3.0
 * @package elasticpress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

return array(
	'settings' => array(
		/**
		 * Filter number of Elasticsearch shards to use in indices
		 *
		 * @hook ep_default_index_number_of_shards
		 * @param  {int} $shards Number of shards
		 * @return {int} New number
		 */
		'index.number_of_shards'           => apply_filters( 'ep_default_index_number_of_shards', 5 ),
		/**
		 * Filter number of Elasticsearch replicas to use in indices
		 *
		 * @hook ep_default_index_number_of_replicas
		 * @param  {int} $replicas Number of replicas
		 * @return {int} New number
		 */
		'index.number_of_replicas'         => apply_filters( 'ep_default_index_number_of_replicas', 1 ),
		/**
		 * Filter Elasticsearch total field limit for users
		 *
		 * @hook ep_user_total_field_limit
		 * @param  {int} $number Number of fields
		 * @return {int} New number
		 */
		'index.mapping.total_fields.limit' => apply_filters( 'ep_user_total_field_limit', 5000 ),
		/**
		 * Filter Elasticsearch max result window for users
		 *
		 * @hook ep_user_max_result_window
		 * @param  {int} $number Size of result window
		 * @return {int} New number
		 */
		'index.max_result_window'          => apply_filters( 'ep_user_max_result_window', 1000000 ),
		'analysis'                         => array(
			'analyzer'   => array(
				'default'          => array(
					'tokenizer' => 'standard',
					'filter'    => array( 'standard', 'ewp_word_delimiter', 'lowercase', 'ep_stop', 'ewp_snowball' ),
					/* This filter is documented in includes/mappings/post/7-0.php */
					'language'  => apply_filters( 'ep_analyzer_language', 'english', 'analyzer_default' ),
				),
				'shingle_analyzer' => array(
					'type'      => 'custom',
					'tokenizer' => 'standard',
					'filter'    => array( 'lowercase', 'shingle_filter' ),
				),
				'ewp_lowercase'    => array(
					'type'      => 'custom',
					'tokenizer' => 'keyword',
					'filter'    => array( 'lowercase' ),
				),
			),
			'filter'     => array(
				'shingle_filter'     => array(
					'type'             => 'shingle',
					'min_shingle_size' => 2,
					'max_shingle_size' => 5,
				),
				'ewp_word_delimiter' => array(
					'type'              => 'word_delimiter',
					'preserve_original' => true,
				),
				'ewp_snowball'       => array(
					'type'     => 'snowball',
					/* This filter is documented in includes/mappings/post/7-0.php */
					'language' => apply_filters( 'ep_analyzer_language', 'english', 'filter_ewp_snowball' ),
				),
				'edge_ngram'         => array(
					'side'     => 'front',
					'max_gram' => 10,
					'min_gram' => 3,
					'type'     => 'edgeNGram',
				),
				'ep_stop'            => [
					'type'        => 'stop',
					'ignore_case' => true,
					/* This filter is documented in includes/mappings/post/7-0.php */
					'stopwords'   => apply_filters( 'ep_analyzer_language', 'english', 'filter_ep_stop' ),
				],
			),
			'normalizer' => array(
				'lowerasciinormalizer' => array(
					'type'   => 'custom',
					'filter' => array( 'lowercase', 'asciifolding' ),
				),
			),
		),
	),
	'mappings' => array(
		'user' => array(
			'date_detection'    => false,
			'dynamic_templates' => array(
				array(
					'template_meta_types' => array(
						'path_match' => 'meta.*',
						'mapping'    => array(
							'type'       => 'object',
							'path'       => 'full',
							'properties' => array(
								'value'    => array(
									'type'   => 'text',
									'fields' => array(
										'sortable' => array(
											'type'         => 'keyword',
											'ignore_above' => 10922,
											'normalizer'   => 'lowerasciinormalizer',
										),
										'raw'      => array(
											'type'         => 'keyword',
											'ignore_above' => 10922,
										),
									),
								),
								'raw'      => array( /* Left for backwards compat */
									'type'         => 'keyword',
									'ignore_above' => 10922,
								),
								'long'     => array(
									'type' => 'long',
								),
								'double'   => array(
									'type' => 'double',
								),
								'boolean'  => array(
									'type' => 'boolean',
								),
								'date'     => array(
									'type'   => 'date',
									'format' => 'yyyy-MM-dd',
								),
								'datetime' => array(
									'type'   => 'date',
									'format' => 'yyyy-MM-dd HH:mm:ss',
								),
								'time'     => array(
									'type'   => 'date',
									'format' => 'HH:mm:ss',
								),
							),
						),
					),
				),
				array(
					'template_capabilities' => array(
						'path_match' => 'capabilities.*',
						'mapping'    => array(
							'type'       => 'object',
							'path'       => 'full',
							'properties' => array(
								'roles' => array(
									'type' => 'keyword',
								),
							),
						),
					),
				),
			),
			'_all'              => array(
				'analyzer' => 'simple',
			),
			'properties'        => array(
				'ID'              => array(
					'type' => 'long',
				),
				'user_registered' => array(
					'type'   => 'date',
					'format' => 'YYYY-MM-dd HH:mm:ss',
				),
				'user_nicename'   => array(
					'type'   => 'text',
					'fields' => array(
						'user_nicename' => array(
							'type' => 'text',
						),
						'raw'           => array(
							'type'         => 'keyword',
							'ignore_above' => 10922,
						),
					),
				),
				'user_login'      => array(
					'type'   => 'text',
					'fields' => array(
						'user_login' => array(
							'type' => 'text',
						),
						'raw'        => array(
							'type'         => 'keyword',
							'ignore_above' => 10922,
						),
					),
				),
				'display_name'    => array(
					'type'   => 'text',
					'fields' => array(
						'raw'      => array(
							'type'         => 'keyword',
							'ignore_above' => 10922,
						),
						'sortable' => array(
							'type'         => 'keyword',
							'ignore_above' => 10922,
							'normalizer'   => 'lowerasciinormalizer',
						),
					),
				),
				'user_email'      => array(
					'type'   => 'text',
					'fields' => array(
						'user_email' => array(
							'type' => 'text',
						),
						'raw'        => array(
							'type'         => 'keyword',
							'ignore_above' => 10922,
						),
					),
				),
				'capabilities'    => array(
					'type' => 'object',
				),
				'user_url'        => array(
					'type'   => 'text',
					'fields' => array(
						'user_url' => array(
							'type' => 'text',
						),
						'raw'      => array(
							'type'         => 'keyword',
							'ignore_above' => 10922,
						),
					),
				),
				'status'          => array(
					'type' => 'long',
				),
				'spam'            => array(
					'type' => 'long',
				),
				'deleted'         => array(
					'type' => 'long',
				),
				'meta'            => array(
					'type' => 'object',
				),
			),
		),
	),
);
