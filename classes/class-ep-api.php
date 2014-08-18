<?php

class EP_API {

	/**
	 * Status of Elasticsearch connection
	 *
	 * @var bool
	 */
	private $is_alive = array();

	/*
	 * Placeholder method
	 *
	 * @since 0.1.0
	 */
	public function __construct() { }

	/**
	 * Return singleton instance of class
	 *
	 * @return EP_API
	 */
	public static function factory() {
		static $instance = false;

		if ( ! $instance  ) {
			$instance = new self();
		}

		return $instance;
	}

	/**
	 * Index a post under a given site index or the global index ($site_id = 0)
	 *
	 * @param array $post
	 * @param int $site_id
	 * @return array|bool|mixed
	 */
	public function index_post( $post, $site_id = null ) {

		$index_url = ep_get_index_url( $site_id );

		$url = $index_url . '/post/' . $this->format_es_id( $post['post_id'], $post['site_id'] );

		$request = wp_remote_request( $url, array( 'body' => json_encode( $post ), 'method' => 'PUT' ) );

		if ( ! is_wp_error( $request ) ) {
			$response_body = wp_remote_retrieve_body( $request );

			return json_decode( $response_body );
		}

		return false;
	}

	/**
	 * Format a post_id/site_id combo as an Elasticsearch ID
	 *
	 * @param int $post_id
	 * @param int $site_id
	 * @param 0.1.3
	 * @return string
	 */
	public function format_es_id( $post_id, $site_id = null ) {
		if ( $site_id === null ) {
			$site_id = get_current_blog_id();
		}

		if ( ! empty( $site_id ) && $site_id > 1 ) {
			return $site_id . 'ms' . (int) $post_id;
		}

		return $post_id;
	}

	/**
	 * Search for posts under a specific site index or the global index ($site_id = 0).
	 *
	 * @param array $args
	 * @param int $site_id
	 * @since 0.1.0
	 * @return array
	 */
	public function search( $args, $site_id = null ) {
		$index_url = ep_get_index_url( $site_id );

		$url = $index_url . '/post/_search';

		do_action( 'ep_pre_search_request', $args, $site_id );

		$request = wp_remote_request( $url, array( 'body' => json_encode( $args ), 'method' => 'POST' ) );

		if ( ! is_wp_error( $request ) ) {
			$response_body = wp_remote_retrieve_body( $request );

			$response = json_decode( $response_body, true );

			if ( $this->is_empty_search( $response ) ) {
				return array( 'found_posts' => 0, 'posts' => array() );
			}

			$hits = $response['hits']['hits'];

			return array( 'found_posts' => $response['hits']['total'], 'posts' => wp_list_pluck( $hits, '_source' ) );
		}

		return array( 'found_posts' => 0, 'posts' => array() );
	}

	/**
	 * Check if a response array contains results or not
	 *
	 * @param array $response
	 * @return bool
	 */
	public function is_empty_search( $response ) {

		if ( ! is_array( $response ) ) {
			return true;
		}

		if ( isset( $response['error'] ) ) {
			return true;
		}

		if ( empty( $response['hits'] ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Delete a post from the ES server given a site ID and a host site ID which
	 * is used to determine the index to delete from.
	 *
	 * @param int $post_id
	 * @param int $site_id
	 * @param int $host_site_id
	 * @since 0.1.0
	 * @return bool
	 */
	public function delete_post( $post_id, $site_id = null, $host_site_id = null ) {
		$index_url = ep_get_index_url( $host_site_id );

		$url = $index_url . '/post/';

		if ( ! empty( $site_id ) && $site_id > 1 ) {
			$url .= (int) $site_id . 'ms' . (int) $post_id;
		} else {
			$url .= (int) $post_id;
		}

		$request = wp_remote_request( $url, array( 'method' => 'DELETE' ) );

		if ( ! is_wp_error( $request ) ) {
			$response_body = wp_remote_retrieve_body( $request );

			$response = json_decode( $response_body, true );

			if ( ! empty( $response['found'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if a post is indexed given a $site_id and a $host_site_id
	 *
	 * @param int $post_id
	 * @param int $site_id
	 * @param int $host_site_id
	 * @since 0.1.0
	 * @return bool
	 */
	public function post_indexed( $post_id, $site_id = null, $host_site_id = null ) {
		$index_url = ep_get_index_url( $host_site_id );

		$url = $index_url . '/post/';

		if ( ! empty( $site_id ) && $site_id > 1 ) {
			$url .= (int) $site_id . 'ms' . (int) $post_id;
		} else {
			$url .= (int) $post_id;
		}

		$request = wp_remote_request( $url, array( 'method' => 'GET' ) );

		if ( ! is_wp_error( $request ) ) {
			$response_body = wp_remote_retrieve_body( $request );

			$response = json_decode( $response_body, true );

			if ( ! empty( $response['found'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Ping the server to ensure the Elasticsearch server is operating and the index exists
	 *
	 * @param int $site_id
	 * @since 0.1.1
	 * @return bool
	 */
	public function is_alive( $site_id = null ) {

		// Otherwise, let's proceed with the check
		$is_alive = false;

		// Get main site options which are stored in location 0
		$index_url = ep_get_index_url( $site_id );

		$url = $index_url . '/_status';

		$request = wp_remote_request( $url );

		if ( ! is_wp_error( $request ) ) {
			if ( isset( $request['response']['code'] ) && 200 === $request['response']['code'] ) {
				$is_alive = true;
			}
		}

		// Return our status and cache it
		return $is_alive;
	}

	public function put_mapping( $site_id = null ) {
		$mapping = array(
			'settings' => array(
				'analysis' => array(
					'analyzer' => array(
						'default' => array(
							'tokenizer' => 'standard',
							'filter' => array( 'standard', 'ewp_word_delimiter', 'lowercase', 'stop', 'ewp_snowball' ),
							'language' => 'English'
						),
						'shingle_analyzer' => array(
							'type' => 'custom',
							'tokenizer' => 'standard',
							'filter' => array( 'lowercase', 'shingle_filter' )
						),
					),
					'filter' => array(
						'shingle_filter' => array(
							'type' => 'shingle',
							'min_shingle_size' => 2,
							'max_shingle_size' => 5
						),
						'ewp_word_delimiter' => array(
							'type' => 'word_delimiter',
							'preserve_original' => true
						),
						'ewp_snowball' => array(
							'type' => 'snowball',
							'language' => 'English'
						),
						'edge_ngram' => array(
							'side' => 'front',
							'max_gram' => 10,
							'min_gram' => 3,
							'type' => 'edgeNGram'
						)
					)
				)
			),
			'mappings' => array(
				'post' => array(
					"date_detection" => false,
					"dynamic_templates" => array(
						array(
							"template_meta" => array(
								"path_match" => "post_meta.*",
								"mapping" => array(
									"type" => "multi_field",
									"path" => "full",
									"fields" => array(
										"{name}" => array(
											"type" => "string",
											"index" => "analyzed"
										),
										"raw" => array(
											"type" => "string",
											"index" => "not_analyzed",
											'include_in_all' => false
										)
									)
								)
							)
						),
						array(
							"template_terms" => array(
								"path_match" => "terms.*",
								"mapping" => array(
									"type" => "object",
									"path" => "full",
									"properties" => array(
										"name" => array(
											"type" => "string"
										),
										"term_id" => array(
											"type" => "long"
										),
										"parent" => array(
											"type" => "long"
										),
										"slug" => array(
											"type" => "string",
											"index" => "not_analyzed"
										)
									)
								)
							)
						),
						array(
							"term_suggest" => array(
								"path_match" => "term_suggest_*",
								"mapping" => array(
									"type" => "completion",
									"analyzer" => "default",
								)
							)
						)
					),
					"_all" => array(
						"analyzer" => "simple"
					),
					'properties' => array(
						'post_id' => array(
							'type' => 'long',
							'index' => 'not_analyzed',
							'include_in_all' => false
						),
						'post_author' => array(
							'type' => 'object',
							'path' => 'full',
							'properties' => array(
								'display_name' => array(
									'type' => 'string'
								),
								'login' => array(
									'type' => 'string',
									'index' => 'not_analyzed'
								)
							)
						),
						'post_date' => array(
							'type' => 'date',
							'format' => 'YYYY-MM-dd HH:mm:ss',
							'include_in_all' => false
						),
						'post_date_gmt' => array(
							'type' => 'date',
							'format' => 'YYYY-MM-dd HH:mm:ss',
							'include_in_all' => false
						),
						'post_title' => array(
							'type' => 'string',
							'_boost'  => 3.0,
							'store'  => 'yes',
							'analyzer' => 'standard'
						),
						'post_excerpt' => array(
							'type' => 'string',
							'_boost'  => 2.0
						),
						'post_content' => array(
							'type' => 'string',
							'analyzer' => 'default'
						),
						'post_status' => array(
							'type' => 'string',
							'index' => 'no'
						),
						'post_name' => array(
							'type' => 'multi_field',
							'fields' => array(
								'post_name' => array(
									'type' => 'string'
								),
								'raw' => array(
									'type' => 'string',
									'index' => 'not_analyzed',
									'include_in_all' => false
								)
							)
						),
						'post_modified' => array(
							'type' => 'date',
							'format' => 'YYYY-MM-dd HH:mm:ss',
							'include_in_all' => false
						),
						'post_modified_gmt' => array(
							'type' => 'date',
							'format' => 'YYYY-MM-dd HH:mm:ss',
							'include_in_all' => false
						),
						'post_parent' => array(
							'type' => 'long',
							'index' => 'not_analyzed',
							'include_in_all' => false
						),
						'post_type' => array(
							'type' => 'multi_field',
							'fields' => array(
								'post_type' => array(
									'type' => 'string'
								),
								'raw' => array(
									'type' => 'string',
									'index' => 'not_analyzed',
									'include_in_all' => false
								)
							)
						),
						'post_mime_type' => array(
							'type' => 'string',
							'index' => 'not_analyzed',
							'include_in_all' => false
						),
						'permalink' => array(
							'type' => 'string'
						),
						'terms' => array(
							"type" => "object"
						),
						'post_meta' => array(
							'type' => 'object'
						)
					)
				)
			)
		);

		$mapping = apply_filters( 'ep_config_mapping', $mapping );

		$index_url = ep_get_index_url( $site_id );

		$url = $index_url;

		$request = wp_remote_request( $url, array( 'body' => json_encode( $mapping ), 'method' => 'PUT' ) );

		if ( ! is_wp_error( $request ) && 200 === wp_remote_retrieve_response_code( $request ) ) {
			$response_body = wp_remote_retrieve_body( $request );

			return json_decode( $response_body );
		}

		return false;
	}

	public function flush( $site_id = null ) {
		$index_url = ep_get_index_url( $site_id );

		$request = wp_remote_request( $index_url, array( 'method' => 'DELETE' ) );

		// 200 means the flush was successful
		// 404 means the index was non-existent, but we should still pass this through as we will occasionally want to flush an already flushed index
		if ( ! is_wp_error( $request ) && ( 200 === wp_remote_retrieve_response_code( $request ) || 404 === wp_remote_retrieve_response_code( $request ) ) ) {
			$response_body = wp_remote_retrieve_body( $request );

			return json_decode( $response_body );
		}

		return false;
	}

	/**
	 * Format WP query args for ES
	 *
	 * @param array $args
	 * @param boolean $cross_site
	 * @return array
	 */
	public function format_args( $args, $cross_site = false ) {
		$formatted_args = array(
			'from' => 0,
			'size' => get_option( 'posts_per_page' ),
			'sort' => array(
				array(
					'_score' => array(
						'order' => 'desc',
					),
				),
			),
		);

		$filter = array(
			'and' => array(),
		);

		$search_fields = array(
			'post_title',
			'post_excerpt',
			'post_content',
		);

		if ( ! empty( $args['search_tax'] ) ) {
			foreach ( $args['search_tax'] as $tax ) {
				$search_fields[] = 'terms.' . $tax . '.name';
			}
		}

		if ( ! empty( $args['search_meta'] ) ) {
			foreach ( $args['search_meta'] as $key ) {
				$search_fields[] = 'post_meta.' . $key;
			}
		}

		$query = array(
			'bool' => array(
				'must' => array(
					'fuzzy_like_this' => array(
						'fields' => $search_fields,
						'like_text' => '',
						'min_similarity' => 0.5,
					),
				),
			),
		);

		if ( ! $cross_site ) {
			$formatted_args['filter']['and'][1] = array(
				'term' => array(
					'site_id' => get_current_blog_id()
				)
			);
		}

		if ( isset( $args['s'] ) ) {
			$query['bool']['must']['fuzzy_like_this']['like_text'] = $args['s'];
			$formatted_args['query'] = $query;
		}

		if ( isset( $args['post_type'] ) ) {
			$post_types = (array) $args['post_type'];
			$terms_map_name = 'terms';
			if ( count( $post_types ) < 2 ) {
				$terms_map_name = 'term';
			}

			$filter['and'][] = array(
				$terms_map_name => array(
					'post_type' => $post_types,
				),
			);

			$formatted_args['filter'] = $filter;
		}

		if ( isset( $args['offset'] ) ) {
			$formatted_args['from'] = $args['offset'];
		}

		if ( isset( $args['posts_per_page'] ) ) {
			$formatted_args['size'] = $args['posts_per_page'];
		}

		if ( isset( $args['paged'] ) ) {
			$paged = ( $args['paged'] <= 1 ) ? 0 : $args['paged'] - 1;
			$formatted_args['from'] = $args['posts_per_page'] * $paged;
		}

		return $formatted_args;
	}
}

EP_API::factory();

/**
 * Accessor functions for methods in above class. See doc blocks above for function details.
 */

function ep_index_post( $post, $site_id = null ) {
	return EP_API::factory()->index_post( $post, $site_id );
}

function ep_search( $args, $site_id = null ) {
	return EP_API::factory()->search( $args, $site_id );
}

function ep_post_indexed( $post_id, $site_id = null, $host_site_id = null ) {
	return EP_API::factory()->post_indexed( $post_id, $site_id, $host_site_id );
}

function ep_delete_post( $post_id, $site_id = null, $host_site_id = null ) {
	return EP_API::factory()->delete_post( $post_id, $site_id, $host_site_id );
}

function ep_is_alive( $site_id = null ) {
	return EP_API::factory()->is_alive( $site_id );
}

function ep_put_mapping( $site_id = null ) {
	return EP_API::factory()->put_mapping( $site_id );
}

function ep_flush( $site_id = null ) {
	return EP_API::factory()->flush( $site_id );
}

function ep_format_es_id( $post_id, $site_id = null ) {
	return EP_API::factory()->format_es_id( $post_id, $site_id );
}

function ep_format_args( $args ) {
	return EP_API::factory()->format_args( $args );
}