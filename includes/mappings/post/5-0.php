<?php
/**
 * Elasticsearch mapping for ES 5.0
 *
 * @since  2.1.2
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
		 * Filter Elasticsearch total field limit for posts
		 *
		 * @hook ep_total_field_limit
		 * @param  {int} $number Number of fields
		 * @return {int} New number
		 */
		'index.mapping.total_fields.limit' => apply_filters( 'ep_total_field_limit', 5000 ),
		/**
		 * Filter Elasticsearch max result window for posts
		 *
		 * @hook ep_max_result_window
		 * @param  {int} $number Size of result window
		 * @return {int} New number
		 */
		'index.max_result_window'          => apply_filters( 'ep_max_result_window', 1000000 ),
		'analysis'                         => array(
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
							'type'   => 'text',
							'path'   => 'full',
							'fields' => array(
								'{name}' => array(
									'type' => 'text',
								),
								'raw'    => array(
									'type'         => 'keyword',
									'ignore_above' => 10922,
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
									'type'   => 'text',
									'fields' => array(
										'sortable' => array(
											'type'         => 'keyword',
											'ignore_above' => 10922,
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
					'template_terms' => array(
						'path_match' => 'terms.*',
						'mapping'    => array(
							'type'       => 'object',
							'path'       => 'full',
							'properties' => array(
								'name'             => array(
									'type'   => 'text',
									'fields' => array(
										'raw'      => array(
											'type' => 'keyword',
										),
										'sortable' => array(
											'type' => 'keyword',
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
									'type' => 'keyword',
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
					'type' => 'long',
				),
				'ID'                => array(
					'type' => 'long',
				),
				'post_author'       => array(
					'type'       => 'object',
					'properties' => array(
						'display_name' => array(
							'type'   => 'text',
							'fields' => array(
								'raw'      => array(
									'type' => 'keyword',
								),
								'sortable' => array(
									'type' => 'keyword',
								),
							),
						),
						'login'        => array(
							'type'   => 'text',
							'fields' => array(
								'raw'      => array(
									'type' => 'keyword',
								),
								'sortable' => array(
									'type' => 'keyword',
								),
							),
						),
						'id'           => array(
							'type' => 'long',
						),
						'raw'          => array(
							'type' => 'keyword',
						),
					),
				),
				'post_date'         => array(
					'type'   => 'date',
					'format' => 'YYYY-MM-dd HH:mm:ss',
				),
				'post_date_gmt'     => array(
					'type'   => 'date',
					'format' => 'YYYY-MM-dd HH:mm:ss',
				),
				'post_title'        => array(
					'type'   => 'text',
					'fields' => array(
						'post_title' => array(
							'type'     => 'text',
							'analyzer' => 'standard',
						),
						'raw'        => array(
							'type'         => 'keyword',
							'ignore_above' => 10922,
						),
						'sortable'   => array(
							'type'         => 'keyword',
							'ignore_above' => 10922,
						),
					),
				),
				'post_excerpt'      => array(
					'type' => 'text',
				),
				'post_content'      => array(
					'type' => 'text',
				),
				'post_status'       => array(
					'type' => 'keyword',
				),
				'post_name'         => array(
					'type'   => 'text',
					'fields' => array(
						'post_name' => array(
							'type' => 'text',
						),
						'raw'       => array(
							'type'         => 'keyword',
							'ignore_above' => 10922,
						),
					),
				),
				'post_modified'     => array(
					'type'   => 'date',
					'format' => 'YYYY-MM-dd HH:mm:ss',
				),
				'post_modified_gmt' => array(
					'type'   => 'date',
					'format' => 'YYYY-MM-dd HH:mm:ss',
				),
				'post_parent'       => array(
					'type' => 'long',
				),
				'post_type'         => array(
					'type'   => 'text',
					'fields' => array(
						'post_type' => array(
							'type' => 'text',
						),
						'raw'       => array(
							'type' => 'keyword',
						),
					),
				),
				'post_mime_type'    => array(
					'type' => 'keyword',
				),
				'permalink'         => array(
					'type' => 'keyword',
				),
				'guid'              => array(
					'type' => 'keyword',
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
