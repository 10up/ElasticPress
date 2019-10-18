<?php
/**
 * Elasticsearch mapping for comments
 *
 * @since   3.1
 * @package elasticpress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

return [
	'settings' => [
		'index.mapping.total_fields.limit' => apply_filters( 'ep_comment_total_field_limit', 5000 ),
		'index.max_result_window'          => apply_filters( 'ep_comment_max_result_window', 1000000 ),
		'analysis'                         => [
			'analyzer'   => [
				'default'          => [
					'tokenizer' => 'standard',
					'filter'    => [ 'standard', 'ewp_word_delimiter', 'lowercase', 'stop', 'ewp_snowball' ],
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
					'type' => 'string',
				],
				'comment_post_type'      => [
					'type' => 'string',
				],
				'comment_post_name'      => [
					'type' => 'string',
				],
				'comment_post_parent'    => [
					'type' => 'long',
				],
				'comment_author'         => [
					'type'   => 'string',
					'fields' => [
						'name' => [
							'type' => 'string',
						],
						'raw'  => [
							'type'         => 'string',
							'ignore_above' => 10922,
						],
					],
				],
				'comment_author_email'   => [
					'type'   => 'string',
					'fields' => [
						'name' => [
							'type' => 'string',
						],
						'raw'  => [
							'type'         => 'string',
							'ignore_above' => 10922,
						],
					],
				],
				'comment_author_url'     => [
					'type'   => 'string',
					'fields' => [
						'name' => [
							'type' => 'string',
						],
						'raw'  => [
							'type'         => 'string',
							'ignore_above' => 10922,
						],
					],
				],
				'comment_author_IP'      => [
					'type'   => 'string',
					'fields' => [
						'name' => [
							'type' => 'string',
						],
						'raw'  => [
							'type'         => 'string',
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
						'description' => [
							'type' => 'string',
						],
						'raw'         => [
							'type'         => 'string',
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
						'name' => [
							'type' => 'string',
						],
						'raw'  => [
							'type'         => 'string',
							'ignore_above' => 10922,
						],
					],
				],
				'comment_agent'          => [
					'type'   => 'string',
					'fields' => [
						'name' => [
							'type' => 'string',
						],
						'raw'  => [
							'type'         => 'string',
							'ignore_above' => 10922,
						],
					],
				],
				'comment_type'           => [
					'type'   => 'string',
					'fields' => [
						'name' => [
							'type' => 'string',
						],
						'raw'  => [
							'type'         => 'string',
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
