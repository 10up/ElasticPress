<?php
/**
 * Elasticsearch mapping for comments
 *
 * @since   3.6.0
 * @package elasticpress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

return [
	'settings' => [
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
		 * @hook ep_total_field_limit
		 * @param  {int} $number Number of fields
		 * @return {int} New number
		 */
		'index.mapping.total_fields.limit' => apply_filters( 'ep_comment_total_field_limit', 5000 ),
		/**
		 * Filter Elasticsearch max result window for users
		 *
		 * @hook ep_user_max_result_window
		 * @param  {int} $number Size of result window
		 * @return {int} New number
		 */
		'index.max_result_window'          => apply_filters( 'ep_comment_max_result_window', 1000000 ),
		'analysis'                         => [
			'analyzer'   => [
				'default'          => [
					'tokenizer' => 'standard',
					'filter'    => [ 'standard', 'ewp_word_delimiter', 'lowercase', 'stop', 'ewp_snowball' ],
					/**
					 * Filter Elasticsearch default language in mapping
					 *
					 * @hook ep_analyzer_language
					 * @param  {string} $lang Default language
					 * @param {string} $lang_context Language context
					 * @return {string} New language
					 */
					'language'  => apply_filters( 'ep_analyzer_language', 'english', 'analyzer_default' ),
				],
				'shingle_analyzer' => [
					'type'      => 'custom',
					'tokenizer' => 'standard',
					'filter'    => [ 'lowercase', 'shingle_filter' ],
				],
				'ewp_lowercase'    => [
					'type'      => 'custom',
					'tokenizer' => 'keyword',
					'filter'    => [ 'lowercase' ],
				],
			],
			'filter'     => [
				'shingle_filter'     => [
					'type'             => 'shingle',
					'min_shingle_size' => 2,
					'max_shingle_size' => 5,
				],
				'ewp_word_delimiter' => [
					'type'              => 'word_delimiter',
					'preserve_original' => true,
				],
				'ewp_snowball'       => [
					'type'     => 'snowball',
					/**
					 * Filter Elasticsearch default language in mapping
					 *
					 * @hook ep_analyzer_language
					 * @param  {string} $lang Default language
					 * @param {string} $lang_context Language context
					 * @return {string} New language
					 */
					'language' => apply_filters( 'ep_analyzer_language', 'english', 'filter_ewp_snowball' ),
				],
				'edge_ngram'         => [
					'side'     => 'front',
					'max_gram' => 10,
					'min_gram' => 3,
					'type'     => 'edgeNGram',
				],
			],
			'normalizer' => [
				'lowerasciinormalizer' => [
					'type'   => 'custom',
					'filter' => [ 'lowercase', 'asciifolding' ],
				],
			],
		],
	],
	'mappings' => [
		'comment' => [
			'date_detection'    => false,
			'dynamic_templates' => [
				[
					'template_meta_types' => [
						'path_match' => 'meta.*',
						'mapping'    => [
							'type'       => 'object',
							'path'       => 'full',
							'properties' => [
								'value'    => [
									'type'   => 'string',
									'fields' => [
										'sortable' => [
											'type'         => 'string',
											'ignore_above' => 10922,
											'normalizer'   => 'lowerasciinormalizer',
										],
										'raw'      => [
											'type'         => 'string',
											'ignore_above' => 10922,
										],
									],
								],
								'raw'      => [ /* Left for backwards compat */
									'type'         => 'string',
									'ignore_above' => 10922,
								],
								'long'     => [
									'type' => 'long',
								],
								'double'   => [
									'type' => 'double',
								],
								'boolean'  => [
									'type' => 'boolean',
								],
								'date'     => [
									'type'   => 'date',
									'format' => 'YYYY-MM-dd',
								],
								'datetime' => [
									'type'   => 'date',
									'format' => 'YYYY-MM-dd HH:mm:ss',
								],
								'time'     => [
									'type'   => 'date',
									'format' => 'HH:mm:ss',
								],
							],
						],
					],
				],
			],
			'_all'              => [
				'analyzer' => 'simple',
			],
			'properties'        => [
				'comment_ID'             => [
					'type' => 'long',
				],
				'ID'                     => [
					'type' => 'long',
				],
				'comment_post_ID'        => [
					'type' => 'long',
				],
				'comment_post_author_ID' => [
					'type' => 'long',
				],
				'comment_post_status'    => [
					'type'  => 'string',
					'index' => 'not_analyzed',
				],
				'comment_post_type'      => [
					'type'   => 'string',
					'fields' => [
						'comment_post_type' => [
							'type' => 'string',
						],
						'raw'               => [
							'type'  => 'string',
							'index' => 'not_analyzed',
						],
					],
				],
				'comment_post_name'      => [
					'type'   => 'string',
					'fields' => [
						'comment_post_name' => [
							'type' => 'string',
						],
						'raw'               => [
							'type'         => 'string',
							'index'        => 'not_analyzed',
							'ignore_above' => 10922,
						],
					],
				],
				'comment_post_parent'    => [
					'type' => 'long',
				],
				'comment_author'         => [
					'type'   => 'string',
					'fields' => [
						'comment_author' => [
							'type' => 'string',
						],
						'raw'            => [
							'type'         => 'string',
							'index'        => 'not_analyzed',
							'ignore_above' => 10922,
						],
					],
				],
				'comment_author_email'   => [
					'type'   => 'string',
					'fields' => [
						'comment_author_email' => [
							'type' => 'string',
						],
						'raw'                  => [
							'type'         => 'string',
							'index'        => 'not_analyzed',
							'ignore_above' => 10922,
						],
					],
				],
				'comment_author_url'     => [
					'type'   => 'string',
					'fields' => [
						'comment_author_url' => [
							'type' => 'string',
						],
						'raw'                => [
							'type'         => 'string',
							'index'        => 'not_analyzed',
							'ignore_above' => 10922,
						],
					],
				],
				'comment_author_IP'      => [
					'type'   => 'string',
					'fields' => [
						'comment_author_IP' => [
							'type' => 'string',
						],
						'raw'               => [
							'type'         => 'string',
							'index'        => 'not_analyzed',
							'ignore_above' => 10922,
						],
					],
				],
				'comment_date'           => [
					'type'           => 'date',
					'format'         => 'YYYY-MM-dd HH:mm:ss',
					'include_in_all' => false,
				],
				'comment_date_gmt'       => [
					'type'           => 'date',
					'format'         => 'YYYY-MM-dd HH:mm:ss',
					'include_in_all' => false,
				],
				'comment_content'        => [
					'type'   => 'string',
					'fields' => [
						'comment_content' => [
							'type' => 'string',
						],
						'raw'             => [
							'type'         => 'string',
							'index'        => 'not_analyzed',
							'ignore_above' => 10922,
						],
					],
				],
				'comment_karma'          => [
					'type' => 'long',
				],
				'comment_approved'       => [
					'type'   => 'string',
					'fields' => [
						'comment_approved' => [
							'type' => 'string',
						],
						'raw'              => [
							'type'         => 'string',
							'index'        => 'not_analyzed',
							'ignore_above' => 10922,
						],
					],
				],
				'comment_agent'          => [
					'type'   => 'string',
					'fields' => [
						'comment_agent' => [
							'type' => 'string',
						],
						'raw'           => [
							'type'         => 'string',
							'index'        => 'not_analyzed',
							'ignore_above' => 10922,
						],
					],
				],
				'comment_type'           => [
					'type'   => 'string',
					'fields' => [
						'comment_type' => [
							'type' => 'string',
						],
						'raw'          => [
							'type'         => 'string',
							'index'        => 'not_analyzed',
							'ignore_above' => 10922,
						],
					],
				],
				'comment_parent'         => [
					'type' => 'long',
				],
				'user_id'                => [
					'type' => 'long',
				],
				'meta'                   => [
					'type' => 'object',
				],
			],
		],
	],
];
