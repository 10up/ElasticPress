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
		/**
		 * Filter whether Elasticsearch ignores malformed fields or not.
		 *
		 * @hook ep_ignore_malformed
		 * @param  {bool} $ignore True for ignore
		 * @return {bool} New value
		 */
		'index.mapping.ignore_malformed'   => apply_filters( 'ep_ignore_malformed', true ),
		/**
		 * Filter Elasticsearch maximum shingle difference
		 *
		 * @hook ep_max_shingle_diff
		 * @param  {int} $number Max difference
		 * @return {int} New number
		 */
		'index.max_shingle_diff'           => apply_filters( 'ep_max_shingle_diff', 8 ),
		'analysis'                         => [
			'analyzer'   => [
				'default'          => [
					'tokenizer' => 'standard',
					'filter'    => [ 'ewp_word_delimiter', 'lowercase', 'stop', 'ewp_snowball' ],
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
					'type'              => 'word_delimiter_graph',
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
					'type'     => 'edge_ngram',
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
		'date_detection'    => false,
		'dynamic_templates' => [
			[
				'template_meta_types' => [
					'path_match' => 'meta.*',
					'mapping'    => [
						'type'       => 'object',
						'properties' => [
							'value'    => [
								'type'   => 'text',
								'fields' => [
									'sortable' => [
										'type'         => 'keyword',
										'ignore_above' => 10922,
										'normalizer'   => 'lowerasciinormalizer',
									],
									'raw'      => [
										'type'         => 'keyword',
										'ignore_above' => 10922,
									],
								],
							],
							'raw'      => [ /* Left for backwards compat */
								'type'         => 'keyword',
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
								'format' => 'yyyy-MM-dd',
							],
							'datetime' => [
								'type'   => 'date',
								'format' => 'yyyy-MM-dd HH:mm:ss',
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
				'type' => 'keyword',
			],
			'comment_post_type'      => [
				'type'   => 'text',
				'fields' => [
					'comment_post_type' => [
						'type' => 'text',
					],
					'raw'               => [
						'type' => 'keyword',
					],
				],
			],
			'comment_post_name'      => [
				'type'   => 'text',
				'fields' => [
					'comment_post_name' => [
						'type' => 'text',
					],
					'raw'               => [
						'type'         => 'keyword',
						'ignore_above' => 10922,
					],
				],
			],
			'comment_post_parent'    => [
				'type' => 'long',
			],
			'comment_author'         => [
				'type'   => 'text',
				'fields' => [
					'comment_author' => [
						'type' => 'text',
					],
					'raw'            => [
						'type'         => 'keyword',
						'ignore_above' => 10922,
					],
				],
			],
			'comment_author_email'   => [
				'type'   => 'text',
				'fields' => [
					'comment_author_email' => [
						'type' => 'text',
					],
					'raw'                  => [
						'type'         => 'keyword',
						'ignore_above' => 10922,
					],
				],
			],
			'comment_author_url'     => [
				'type'   => 'text',
				'fields' => [
					'comment_author_url' => [
						'type' => 'text',
					],
					'raw'                => [
						'type'         => 'keyword',
						'ignore_above' => 10922,
					],
				],
			],
			'comment_author_IP'      => [
				'type'   => 'text',
				'fields' => [
					'comment_author_IP' => [
						'type' => 'text',
					],
					'raw'               => [
						'type'         => 'keyword',
						'ignore_above' => 10922,
					],
				],
			],
			'comment_date'           => [
				'type'   => 'date',
				'format' => 'yyyy-MM-dd HH:mm:ss',
			],
			'comment_date_gmt'       => [
				'type'   => 'date',
				'format' => 'yyyy-MM-dd HH:mm:ss',
			],
			'comment_content'        => [
				'type'   => 'text',
				'fields' => [
					'comment_content' => [
						'type' => 'text',
					],
					'raw'             => [
						'type'         => 'keyword',
						'ignore_above' => 10922,
					],
				],
			],
			'comment_karma'          => [
				'type' => 'long',
			],
			'comment_approved'       => [
				'type'   => 'text',
				'fields' => [
					'comment_approved' => [
						'type' => 'text',
					],
					'raw'              => [
						'type'         => 'keyword',
						'ignore_above' => 10922,
					],
				],
			],
			'comment_agent'          => [
				'type'   => 'text',
				'fields' => [
					'comment_agent' => [
						'type' => 'text',
					],
					'raw'           => [
						'type'         => 'keyword',
						'ignore_above' => 10922,
					],
				],
			],
			'comment_type'           => [
				'type'   => 'text',
				'fields' => [
					'comment_type' => [
						'type' => 'text',
					],
					'raw'          => [
						'type'         => 'keyword',
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
];
