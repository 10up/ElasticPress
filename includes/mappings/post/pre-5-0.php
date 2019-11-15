<?php
/**
 * Elasticsearch mapping for pre 5.0 ES installs
 *
 * @since  1.3
 * @package elasticpress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

return array(
	'settings' => array(
		/**
		 * Filter number of Elasticsearch shards to use in post indices
		 *
		 * @hook ep_default_index_number_of_shards
		 * @param  {int} $shards Number of shards
		 * @return {int} New number
		 */
		'index.number_of_shards'   => apply_filters( 'ep_default_index_number_of_shards', 5 ),
		/**
		 * Filter number of Elasticsearch replicas to use in post indices
		 *
		 * @hook ep_default_index_number_of_replicas
		 * @param  {int} $replicas Number of replicas
		 * @return {int} New number
		 */
		'index.number_of_replicas' => apply_filters( 'ep_default_index_number_of_replicas', 1 ),
		'analysis'                 => array(
			'analyzer' => array(
				'default'          => array(
					'tokenizer' => 'standard',
					'filter'    => array( 'standard', 'ewp_word_delimiter', 'lowercase', 'stop', 'ewp_snowball' ),
					/**
					 * Filter Elasticsearch default language in mapping
					 *
					 * @hook ep_analyzer_language
					 * @param  {string} $lang Default language
					 * @param {string} $lang_context Language context
					 * @return {string} New language
					 */
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
			'filter'   => array(
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
					/**
					 * Filter Elasticsearch default language in mapping
					 *
					 * @hook ep_analyzer_language
					 * @param  {string} $lang Default language
					 * @param {string} $lang_context Language context
					 * @return {string} New language
					 */
					'language' => apply_filters( 'ep_analyzer_language', 'english', 'filter_ewp_snowball' ),
				),
				'edge_ngram'         => array(
					'side'     => 'front',
					'max_gram' => 10,
					'min_gram' => 3,
					'type'     => 'edgeNGram',
				),
			),
		),
	),
	'mappings' => array(
		'post' => array(
			'date_detection'    => false,
			'dynamic_templates' => array(
				array(
					'template_meta' => array(
						'path_match' => 'post_meta.*',
						'mapping'    => array(
							'type'   => 'multi_field',
							'path'   => 'full',
							'fields' => array(
								'{name}' => array(
									'type'  => 'string',
									'index' => 'analyzed',
								),
								'raw'    => array(
									'type'           => 'string',
									'index'          => 'not_analyzed',
									'include_in_all' => false,
								),
							),
						),
					),
				),
				array(
					'template_meta_types' => array(
						'path_match' => 'meta.*',
						'mapping'    => array(
							'type'       => 'object',
							'path'       => 'full',
							'properties' => array(
								'value'    => array(
									'type'   => 'string',
									'fields' => array(
										'sortable' => array(
											'type'     => 'string',
											'analyzer' => 'ewp_lowercase',
											'include_in_all' => false,
										),
										'raw'      => array(
											'type'  => 'string',
											'index' => 'not_analyzed',
											'include_in_all' => false,
										),
									),
								),
								'raw'      => array( /* Left for backwards compat. */
									'type'           => 'string',
									'index'          => 'not_analyzed',
									'include_in_all' => false,
								),
								'long'     => array(
									'type'  => 'long',
									'index' => 'not_analyzed',
								),
								'double'   => array(
									'type'  => 'double',
									'index' => 'not_analyzed',
								),
								'boolean'  => array(
									'type'  => 'boolean',
									'index' => 'not_analyzed',
								),
								'date'     => array(
									'type'   => 'date',
									'format' => 'yyyy-MM-dd',
									'index'  => 'not_analyzed',
								),
								'datetime' => array(
									'type'   => 'date',
									'format' => 'yyyy-MM-dd HH:mm:ss',
									'index'  => 'not_analyzed',
								),
								'time'     => array(
									'type'   => 'date',
									'format' => 'HH:mm:ss',
									'index'  => 'not_analyzed',
								),
							),
						),
					),
				),
				array(
					'template_terms' => array(
						'path_match' => 'terms.*',
						'mapping'    => array(
							'type'       => 'object',
							'path'       => 'full',
							'properties' => array(
								'name'             => array(
									'type'   => 'string',
									'fields' => array(
										'raw'      => array(
											'type'  => 'string',
											'index' => 'not_analyzed',
										),
										'sortable' => array(
											'type'     => 'string',
											'analyzer' => 'ewp_lowercase',
										),
									),
								),
								'term_id'          => array(
									'type' => 'long',
								),
								'term_taxonomy_id' => array(
									'type' => 'long',
								),
								'parent'           => array(
									'type' => 'long',
								),
								'slug'             => array(
									'type'  => 'string',
									'index' => 'not_analyzed',
								),
								'term_order'       => array(
									'type' => 'long',
								),
							),
						),
					),
				),
				array(
					'term_suggest' => array(
						'path_match' => 'term_suggest_*',
						'mapping'    => array(
							'type'     => 'completion',
							'analyzer' => 'default',
						),
					),
				),
			),
			'_all'              => array(
				'analyzer' => 'simple',
			),
			'properties'        => array(
				'post_id'           => array(
					'type'           => 'long',
					'index'          => 'not_analyzed',
					'include_in_all' => false,
				),
				'ID'                => array(
					'type'           => 'long',
					'index'          => 'not_analyzed',
					'include_in_all' => false,
				),
				'post_author'       => array(
					'type'       => 'object',
					'properties' => array(
						'display_name' => array(
							'type'   => 'string',
							'fields' => array(
								'raw'      => array(
									'type'  => 'string',
									'index' => 'not_analyzed',
								),
								'sortable' => array(
									'type'     => 'string',
									'analyzer' => 'ewp_lowercase',
								),
							),
						),
						'login'        => array(
							'type'   => 'string',
							'fields' => array(
								'raw'      => array(
									'type'  => 'string',
									'index' => 'not_analyzed',
								),
								'sortable' => array(
									'type'     => 'string',
									'analyzer' => 'ewp_lowercase',
								),
							),
						),
						'id'           => array(
							'type'  => 'long',
							'index' => 'not_analyzed',
						),
						'raw'          => array(
							'type'           => 'string',
							'index'          => 'not_analyzed',
							'include_in_all' => false,
						),
					),
				),
				'post_date'         => array(
					'type'           => 'date',
					'format'         => 'YYYY-MM-dd HH:mm:ss',
					'include_in_all' => false,
				),
				'post_date_gmt'     => array(
					'type'           => 'date',
					'format'         => 'YYYY-MM-dd HH:mm:ss',
					'include_in_all' => false,
				),
				'post_title'        => array(
					'type'   => 'multi_field',
					'fields' => array(
						'post_title' => array(
							'type'     => 'string',
							'analyzer' => 'standard',
							'store'    => 'yes',
						),
						'raw'        => array(
							'type'           => 'string',
							'index'          => 'not_analyzed',
							'include_in_all' => false,
						),
						'sortable'   => array(
							'type'           => 'string',
							'analyzer'       => 'ewp_lowercase',
							'include_in_all' => false,
						),
					),
				),
				'post_excerpt'      => array(
					'type' => 'string',
				),
				'post_content'      => array(
					'type'     => 'string',
					'analyzer' => 'default',
				),
				'post_status'       => array(
					'type'  => 'string',
					'index' => 'not_analyzed',
				),
				'post_name'         => array(
					'type'   => 'multi_field',
					'fields' => array(
						'post_name' => array(
							'type' => 'string',
						),
						'raw'       => array(
							'type'           => 'string',
							'index'          => 'not_analyzed',
							'include_in_all' => false,
						),
					),
				),
				'post_modified'     => array(
					'type'           => 'date',
					'format'         => 'YYYY-MM-dd HH:mm:ss',
					'include_in_all' => false,
				),
				'post_modified_gmt' => array(
					'type'           => 'date',
					'format'         => 'YYYY-MM-dd HH:mm:ss',
					'include_in_all' => false,
				),
				'post_parent'       => array(
					'type'           => 'long',
					'index'          => 'not_analyzed',
					'include_in_all' => false,
				),
				'post_type'         => array(
					'type'   => 'multi_field',
					'fields' => array(
						'post_type' => array(
							'type' => 'string',
						),
						'raw'       => array(
							'type'           => 'string',
							'index'          => 'not_analyzed',
							'include_in_all' => false,
						),
					),
				),
				'post_mime_type'    => array(
					'type'           => 'string',
					'index'          => 'not_analyzed',
					'include_in_all' => false,
				),
				'permalink'         => array(
					'type' => 'string',
				),
				'guid'              => array(
					'type'  => 'string',
					'index' => 'not_analyzed',
				),
				'terms'             => array(
					'type' => 'object',
				),
				'post_meta'         => array(
					'type' => 'object',
				),
				'meta'              => array(
					'type' => 'object',
				),
				'date_terms'        => array(
					'type'       => 'object',
					'properties' => array(
						'year'          => array( // 4 digit year (e.g. 2011).
							'type' => 'integer',
						),
						'month'         => array( // Month number (from 1 to 12) alternate name 'monthnum'.
							'type' => 'integer',
						),
						'm'             => array( // YearMonth (For e.g.: 201307).
							'type' => 'integer',
						),
						'week'          => array( // Week of the year (from 0 to 53) alternate name 'w'.
							'type' => 'integer',
						),
						'day'           => array( // Day of the month (from 1 to 31).
							'type' => 'integer',
						),
						'dayofweek'     => array( // Accepts numbers 1-7 (1 is Sunday).
							'type' => 'integer',
						),
						'dayofweek_iso' => array( // Accepts numbers 1-7 (1 is Monday).
							'type' => 'integer',
						),
						'dayofyear'     => array( // Accepts numbers 1-366.
							'type' => 'integer',
						),
						'hour'          => array( // Hour (from 0 to 23).
							'type' => 'integer',
						),
						'minute'        => array( // Minute (from 0 to 59).
							'type' => 'integer',
						),
						'second'        => array( // Second (0 to 59).
							'type' => 'integer',
						),
					),
				),
			),
		),
	),
);
