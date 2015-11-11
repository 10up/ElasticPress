<?php
 if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}
class EP_API {

	/**
	 * Placeholder method
	 *
	 * @since 0.1.0
	 */
	public function __construct() { }

	/**
	 * Return singleton instance of class
	 *
	 * @return EP_API
	 * @since 0.1.0
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
	 * @since 0.1.0
	 * @return array|bool|mixed
	 */
	public function index_post( $post ) {

		$index = trailingslashit( ep_get_index_name() );

		$path = $index . 'post/' . $post['post_id'];

		$request_args = array(
			'body'    => json_encode( $post ),
			'method'  => 'PUT',
			'timeout' => 15,
		);

		$request = ep_remote_request( $path, apply_filters( 'ep_index_post_request_args', $request_args, $post ) );

		if ( ! is_wp_error( $request ) ) {
			$response_body = wp_remote_retrieve_body( $request );

			return json_decode( $response_body );
		}

		return false;
	}

	/**
	 * Pull the site id from the index name
	 *
	 * @param string $index_name
	 * @since 0.9.0
	 * @return int
	 */
	public function parse_site_id( $index_name ) {
		return (int) preg_replace( '#^.*\-([0-9]+)$#', '$1', $index_name );
	}

	/**
	 * Refresh the current index
	 *
	 * @since 0.9.0
	 * @return bool
	 */
	public function refresh_index() {

		$request_args = array( 'method' => 'POST' );

		$request = ep_remote_request( '_refresh', apply_filters( 'ep_refresh_index_request_args', $request_args ) );

		if ( ! is_wp_error( $request ) ) {
			if ( isset( $request['response']['code'] ) && 200 === $request['response']['code'] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Search for posts under a specific site index or the global index ($site_id = 0).
	 *
	 * @param array $args
	 * @param string $scope
	 * @since 0.1.0
	 * @return array
	 */
	public function search( $args, $scope = 'current' ) {
		$index = null;

		if ( 'all' === $scope ) {
			$index = ep_get_network_alias();
		} elseif ( is_int( $scope ) ) {
			$index = ep_get_index_name( $scope );
		} elseif ( is_array( $scope ) ) {
			$index = array();

			foreach ( $scope as $site_id ) {
				$index[] = ep_get_index_name( $site_id );
			}

			$index = implode( ',', $index );
		} else {
			$index = ep_get_index_name();
		}

		$path = $index . '/post/_search';

		$request_args = array(
			'body'    => json_encode( $args ),
			'method'  => 'POST',
		);

		$request = ep_remote_request( $path, apply_filters( 'ep_search_request_args', $request_args, $args, $scope ) );

		if ( ! is_wp_error( $request ) ) {

			// Allow for direct response retrieval
			do_action( 'ep_retrieve_raw_response', $request, $args, $scope );

			$response_body = wp_remote_retrieve_body( $request );

			$response = json_decode( $response_body, true );

			if ( $this->is_empty_search( $response ) ) {
				return array( 'found_posts' => 0, 'posts' => array() );
			}

			$hits = $response['hits']['hits'];

			// Check for and store aggregations
			if ( ! empty( $response['aggregations'] ) ) {
				do_action( 'ep_retrieve_aggregations', $response['aggregations'], $args, $scope );
			}

			$posts = array();

			foreach ( $hits as $hit ) {
				$post = $hit['_source'];
				$post['site_id'] = $this->parse_site_id( $hit['_index'] );
				$posts[] = apply_filters( 'ep_retrieve_the_post', $post, $hit );
			}

			/**
			 * Filter search results.
			 *
			 * Allows more complete use of filtering request variables by allowing for filtering of results.
			 *
			 * @since 1.6.0
			 *
			 * @param array  $results  The unfiltered search results.
			 * @param object $response The response body retrieved from ElasticSearch.
			 */

			return apply_filters( 'ep_search_results_array', array( 'found_posts' => $response['hits']['total'], 'posts' => $posts ), $response );
		}

		return false;
	}

	/**
	 * Check if a response array contains results or not
	 *
	 * @param array $response
	 * @since 0.1.2
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
	 * @since 0.1.0
	 * @return bool
	 */
	public function delete_post( $post_id  ) {

		$index = trailingslashit( ep_get_index_name() );

		$path = $index . '/post/' . $post_id;

		$request_args = array( 'method' => 'DELETE', 'timeout' => 15 );

		$request = ep_remote_request( $path, apply_filters( 'ep_delete_post_request_args', $request_args, $post_id ) );

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
	 * Add appropriate request headers
	 *
	 * @since 1.4
	 * @return array
	 */
	public function format_request_headers() {
		$headers = array();

		if ( defined( 'EP_API_KEY' ) && EP_API_KEY ) {
			$headers['X-ElasticPress-API-Key'] = EP_API_KEY;
		}

		$headers = apply_filters( 'ep_format_request_headers', $headers );

		return $headers;
	}

	/**
	 * Get a post from the index
	 *
	 * @param int $post_id
	 * @since 0.9.0
	 * @return bool
	 */
	public function get_post( $post_id ) {

		$index = ep_get_index_name();

		$path = $index . '/post/' . $post_id;

		$request_args = array( 'method' => 'GET' );

		$request = ep_remote_request( $path, apply_filters( 'ep_get_post_request_args', $request_args, $post_id ) );

		if ( ! is_wp_error( $request ) ) {
			$response_body = wp_remote_retrieve_body( $request );

			$response = json_decode( $response_body, true );

			if ( ! empty( $response['exists'] ) || ! empty( $response['found'] ) ) {
				return $response['_source'];
			}
		}

		return false;
	}

	/**
	 * Delete the network index alias
	 *
	 * @since 0.9.0
	 * @return bool|array
	 */
	public function delete_network_alias() {

		$path = '*/_alias/' . ep_get_network_alias();

		$request_args = array( 'method' => 'DELETE' );

		$request = ep_remote_request( $path, apply_filters( 'ep_delete_network_alias_request_args', $request_args ) );

		if ( ! is_wp_error( $request ) && ( 200 >= wp_remote_retrieve_response_code( $request ) && 300 > wp_remote_retrieve_response_code( $request ) ) ) {
			$response_body = wp_remote_retrieve_body( $request );

			return json_decode( $response_body );
		}

		return false;
	}

	/**
	 * Create the network alias from an array of indexes
	 *
	 * @param array $indexes
	 * @since 0.9.0
	 * @return array|bool
	 */
	public function create_network_alias( $indexes ) {

		$path = '_aliases';

		$args = array(
			'actions' => array(),
		);

		foreach ( $indexes as $index ) {
			$args['actions'][] = array(
				'add' => array(
					'index' => $index,
					'alias' => ep_get_network_alias(),
				),
			);
		}

		$request_args = array(
			'body'    => json_encode( $args ),
			'method'  => 'POST',
		);

		$request = ep_remote_request( $path, apply_filters( 'ep_create_network_alias_request_args', $request_args, $args, $indexes ) );

		if ( ! is_wp_error( $request ) && ( 200 >= wp_remote_retrieve_response_code( $request ) && 300 > wp_remote_retrieve_response_code( $request ) ) ) {
			$response_body = wp_remote_retrieve_body( $request );

			return json_decode( $response_body );
		}

		return false;
	}

	/**
	 * Send mapping to ES
	 *
	 * @since 0.9.0
	 * @return array|bool|mixed
	 */
	public function put_mapping() {
		$mapping = require( apply_filters( 'ep_config_mapping_file', dirname( __FILE__ ) . '/../includes/mappings.php' ) );

		/**
		 * We are removing shard/replica defaults but need to maintain the filters
		 * for backwards compat.
		 *
		 * @since 1.4
		 */
		global $wp_filter;
		if ( ! empty( $wp_filter['ep_default_index_number_of_shards'] ) ) {
			if ( empty( $mapping['settings']['index'] ) ) {
				$mapping['settings']['index'] = array();
			}

			$mapping['settings']['index']['number_of_shards'] = (int) apply_filters( 'ep_default_index_number_of_shards', 5 ); // Default within Elasticsearch
		}

		if ( ! empty( $wp_filter['ep_default_index_number_of_replicas'] ) ) {
			if ( empty( $mapping['settings']['index'] ) ) {
				$mapping['settings']['index'] = array();
			}

			$mapping['settings']['index']['number_of_replicas'] = (int) apply_filters( 'ep_default_index_number_of_replicas', 1 );
		}

		$mapping = apply_filters( 'ep_config_mapping', $mapping );

		$index = ep_get_index_name();

		$request_args = array(
			'body'    => json_encode( $mapping ),
			'method'  => 'PUT',
		);

		$request = ep_remote_request( $index, apply_filters( 'ep_put_mapping_request_args', $request_args ) );

		$request = apply_filters( 'ep_config_mapping_request', $request, $index, $mapping );

		if ( ! is_wp_error( $request ) && 200 === wp_remote_retrieve_response_code( $request ) ) {
			$response_body = wp_remote_retrieve_body( $request );

			return json_decode( $response_body );
		}

		return false;
	}

	/**
	 * Prepare a post for syncing
	 *
	 * @param int $post_id
	 * @since 0.9.1
	 * @return bool|array
	 */
	public function prepare_post( $post_id ) {
		$post = get_post( $post_id );

		$user = get_userdata( $post->post_author );

		if ( $user instanceof WP_User ) {
			$user_data = array(
				'raw'          => $user->user_login,
				'login'        => $user->user_login,
				'display_name' => $user->display_name,
				'id'           => $user->ID,
			);
		} else {
			$user_data = array(
				'raw'          => '',
				'login'        => '',
				'display_name' => '',
				'id'           => '',
			);
		}

		$post_date = $post->post_date;
		$post_date_gmt = $post->post_date_gmt;
		$post_modified = $post->post_modified;
		$post_modified_gmt = $post->post_modified_gmt;
		$comment_count = absint( $post->comment_count );
		$comment_status = absint( $post->comment_status );
		$ping_status = absint( $post->ping_status );
		$menu_order = absint( $post->menu_order );

		if ( apply_filters( 'ep_ignore_invalid_dates', true, $post_id, $post ) ) {
			if ( ! strtotime( $post_date ) || $post_date === "0000-00-00 00:00:00" ) {
				$post_date = null;
			}

			if ( ! strtotime( $post_date_gmt ) || $post_date_gmt === "0000-00-00 00:00:00" ) {
				$post_date_gmt = null;
			}

			if ( ! strtotime( $post_modified ) || $post_modified === "0000-00-00 00:00:00" ) {
				$post_modified = null;
			}

			if ( ! strtotime( $post_modified_gmt ) || $post_modified_gmt === "0000-00-00 00:00:00" ) {
				$post_modified_gmt = null;
			}
		}

		$post_args = array(
			'post_id'           => $post_id,
			'post_author'       => $user_data,
			'post_date'         => $post_date,
			'post_date_gmt'     => $post_date_gmt,
			'post_title'        => get_the_title( $post_id ),
			'post_excerpt'      => $post->post_excerpt,
			'post_content'      => apply_filters( 'the_content', $post->post_content ),
			'post_status'       => $post->post_status,
			'post_name'         => $post->post_name,
			'post_modified'     => $post_modified,
			'post_modified_gmt' => $post_modified_gmt,
			'post_parent'       => $post->post_parent,
			'post_type'         => $post->post_type,
			'post_mime_type'    => $post->post_mime_type,
			'permalink'         => get_permalink( $post_id ),
			'terms'             => $this->prepare_terms( $post ),
			'post_meta'         => $this->prepare_meta( $post ),
			'date_terms'        => $this->prepare_date_terms( $post_date ),
			'comment_count'     => $comment_count,
			'comment_status'    => $comment_status,
			'ping_status'       => $ping_status,
			'menu_order'        => $menu_order
			//'site_id'         => get_current_blog_id(),
		);

		/**
		 * This filter is named poorly but has to stay to keep backwards compat
		 */
		$post_args = apply_filters( 'ep_post_sync_args', $post_args, $post_id );
		return $post_args;
	}

	/**
	 * Prepare date terms to send to ES.
	 *
	 * @param string $timestamp
	 *
	 * @since 0.1.4
	 * @return array
	 */
	private function prepare_date_terms( $post_date_gmt ) {
		$timestamp = strtotime( $post_date_gmt );
		$date_terms = array(
			'year' => (int) date( "Y", $timestamp),
			'month' => (int) date( "m", $timestamp),
			'week' => (int) date( "W", $timestamp),
			'dayofyear' => (int) date( "z", $timestamp),
			'day' => (int) date( "d", $timestamp),
			'dayofweek' => (int) date( "w", $timestamp),
			'dayofweek_iso' => (int) date( "N", $timestamp),
			'hour' => (int) date( "H", $timestamp),
			'minute' => (int) date( "i", $timestamp),
			'second' => (int) date( "s", $timestamp),
			'm' => (int) (date( "Y", $timestamp) . date( "m", $timestamp)), // yearmonth
		);
		return $date_terms;
	}

	/**
	 * Prepare terms to send to ES.
	 *
	 * @param object $post
	 *
	 * @since 0.1.0
	 * @return array
	 */
	private function prepare_terms( $post ) {
		$taxonomies          = get_object_taxonomies( $post->post_type, 'objects' );
		$selected_taxonomies = array();

		foreach ( $taxonomies as $taxonomy ) {
			if ( $taxonomy->public ) {
				$selected_taxonomies[] = $taxonomy;
			}
		}

		$selected_taxonomies = apply_filters( 'ep_sync_taxonomies', $selected_taxonomies, $post );

		if ( empty( $selected_taxonomies ) ) {
			return array();
		}

		$terms = array();

		$allow_hierarchy = apply_filters( 'ep_sync_terms_allow_hierarchy', false );

		foreach ( $selected_taxonomies as $taxonomy ) {
			$object_terms = get_the_terms( $post->ID, $taxonomy->name );

			if ( ! $object_terms || is_wp_error( $object_terms ) ) {
				continue;
			}

			$terms_dic = array();

			foreach ( $object_terms as $term ) {
				if( ! isset( $terms_dic[ $term->term_id ] ) ) {
					$terms_dic[ $term->term_id ] = array(
						'term_id' => $term->term_id,
						'slug'    => $term->slug,
						'name'    => $term->name,
						'parent'  => $term->parent
					);
					if( $allow_hierarchy ){
						$terms_dic = $this->get_parent_terms( $terms_dic, $term, $taxonomy->name );
					}
				}
			}
			$terms[ $taxonomy->name ] = array_values( $terms_dic );
		}

		return $terms;
	}

	/**
	 * Recursively get all the ancestor terms of the given term
	 * @param $terms
	 * @param $term
	 * @param $tax_name
	 * @return array
	 */
	private function get_parent_terms( $terms, $term, $tax_name ) {
		$parent_term = get_term( $term->parent, $tax_name );
		if( ! $parent_term || is_wp_error( $parent_term ) )
			return $terms;
		if( ! isset( $terms[ $parent_term->term_id ] ) ) {
			$terms[ $parent_term->term_id ] = array(
				'term_id' => $parent_term->term_id,
				'slug'    => $parent_term->slug,
				'name'    => $parent_term->name,
				'parent'  => $parent_term->parent
			);
		}
		return $this->get_parent_terms( $terms, $parent_term, $tax_name );
	}

	/**
	 * Prepare post meta to send to ES
	 *
	 * @param object $post
	 *
	 * @since 0.1.0
	 * @return array
	 */
	public function prepare_meta( $post ) {
		$meta = (array) get_post_meta( $post->ID );

		if ( empty( $meta ) ) {
			return array();
		}

		$prepared_meta = array();

		foreach ( $meta as $key => $value ) {
			if ( ! is_protected_meta( $key ) ) {
				$prepared_meta[$key] = maybe_unserialize( $value );
			}
		}

		return $prepared_meta;
	}

	/**
	 * Delete the current index or delete the index passed by name
	 *
	 * @param string $index_name
	 *
	 * @since 0.9.0
	 * @return array|bool
	 */
	public function delete_index( $index_name = null ) {

		$index = ( null === $index_name ) ? ep_get_index_name() : sanitize_text_field( $index_name );

		$request_args = array( 'method' => 'DELETE' );

		$request = ep_remote_request( $index, apply_filters( 'ep_delete_index_request_args', $request_args ) );

		// 200 means the delete was successful
		// 404 means the index was non-existent, but we should still pass this through as we will occasionally want to delete an already deleted index
		if ( ! is_wp_error( $request ) && ( 200 === wp_remote_retrieve_response_code( $request ) || 404 === wp_remote_retrieve_response_code( $request ) ) ) {
			$response_body = wp_remote_retrieve_body( $request );

			return json_decode( $response_body );
		}

		return false;
	}

	/**
	 * Checks if index exists by index name, returns true or false
	 *
	 * @param null $index_name
	 *
	 * @return bool
	 */
	public function index_exists( $index_name = null ) {

		$index = ( null === $index_name ) ? ep_get_index_name() : sanitize_text_field( $index_name );

		$request_args = array( 'method' => 'HEAD' );

		$request = ep_remote_request( $index, apply_filters( 'ep_index_exists_request_args', $request_args, $index_name ) );

		// 200 means the index exists
		// 404 means the index was non-existent
		if ( ! is_wp_error( $request ) && ( 200 === wp_remote_retrieve_response_code( $request ) || 404 === wp_remote_retrieve_response_code( $request ) ) ) {

			if ( 404 === wp_remote_retrieve_response_code( $request ) ) {
				return false;
			}

			if ( 200 === wp_remote_retrieve_response_code( $request ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Format WP query args for ES
	 *
	 * @param array $args
	 * @since 0.9.0
	 * @return array
	 */
	public function format_args( $args ) {
		if ( ! empty( $args['post_per_page'] ) ) {
			$posts_per_page = $args['post_per_page'];
		} else {
			$posts_per_page = get_option( 'posts_per_page' );
		}

		$formatted_args = array(
			'from' => 0,
			'size' => $posts_per_page,
		);

		/**
		 * Order and Orderby arguments
		 *
		 * Used for how Elasticsearch will sort results
		 *
		 * @since 1.1
		 */
		// Set sort order, default is 'desc'
		if ( ! empty( $args['order'] ) ) {
			$order = $this->parse_order( $args['order'] );
		} else {
			$order = 'desc';
		}

		// Set sort type
		if ( ! empty( $args['orderby'] ) ) {
			$sort = $this->parse_orderby( $args['orderby'], $order );

			if ( false !== $sort ) {
				$formatted_args['sort'] = $sort;
			}
		}

		// Either nothing was passed or the parse_orderby failed, use default sort
		if ( empty( $args['orderby'] ) || false === $sort ) {

			// Default sort is to use the score (based on relevance)
			$default_sort = array(
				array(
					'_score' => array(
						'order' => $order,
					),
				),
			);

            $default_sort = apply_filters( 'ep_set_default_sort', $default_sort, $order );

            $formatted_args['sort'] = $default_sort;
        }

		$filter = array(
			'and' => array(),
		);
		$use_filters = false;

		/**
		 * Tax Query support
		 *
		 * Support for the tax_query argument of WP_Query
		 * Currently only provides support for the 'AND' relation between taxonomies
		 *
		 * @use field = slug
		 *      terms array
		 * @since 0.9.1
		 */
		if ( ! empty( $args['tax_query'] ) ) {
			$tax_filter = array();

			foreach( $args['tax_query'] as $single_tax_query ) {
				if ( ! empty( $single_tax_query['terms'] ) && ! empty( $single_tax_query['field'] ) && 'slug' === $single_tax_query['field'] ) {
					$terms = (array) $single_tax_query['terms'];

					// Set up our terms object
					$terms_obj = array(
						'terms.' . $single_tax_query['taxonomy'] . '.slug' => $terms,
					);

					// Use the AND operator if passed
					if ( ! empty( $single_tax_query['operator'] ) && 'AND' === $single_tax_query['operator'] ) {
						$terms_obj['execution'] = 'and';
					}

					// Add the tax query filter
					$tax_filter[]['terms'] = $terms_obj;
				}
			}

			if ( ! empty( $tax_filter ) ) {
				$filter['and'][]['bool']['must'] = $tax_filter;
			}

			$use_filters = true;
		}

		/**
		 * 'category_name' arg support.
		 *
		 * @since 1.5
		 */
		if ( ! empty( $args[ 'category_name' ] ) ) {
			$terms_obj = array(
				'terms.category.slug' => array( $args[ 'category_name' ] ),
			);

			$filter['and'][]['bool']['must'] = array(
				'terms' => $terms_obj
			);

			$use_filters = true;
		}

		/**
		 * Author query support
		 *
		 * @since 1.0
		 */
		if ( ! empty( $args['author'] ) ) {
			$filter['and'][] = array(
				'term' => array(
					'post_author.id' => $args['author'],
				),
			);

			$use_filters = true;
		} elseif ( ! empty( $args['author_name'] ) ) {
			$filter['and'][] = array(
				'term' => array(
					'post_author.raw' => $args['author'],
				),
			);

			$use_filters = true;
		}

		/**
		 * Simple date params support
		 *
		 * @since 1.3
		 */
		if ( $date_filter = EP_WP_Date_Query::simple_es_date_filter( $args ) ) {
			$filter['and'][] = $date_filter;
			$use_filters = true;
		}

		/**
		 * 'date_query' arg support.
		 *
		 */
		if ( ! empty( $args['date_query'] ) ) {

			$date_query = new EP_WP_Date_Query( $args['date_query'] );

			$date_filter = $date_query->get_es_filter();

			if( array_key_exists('and', $date_filter ) ) {
				$filter['and'][] = $date_filter['and'];
				$use_filters = true;
			}

		}

		/**
		 * 'meta_query' arg support.
		 *
		 * Relation supports 'AND' and 'OR'. 'AND' is the default. For each individual query, the
		 * following 'compare' values are supported: =, !=, EXISTS, NOT EXISTS. '=' is the default.
		 * 'type' is NOT support at this time.
		 *
		 * @since 1.3
		 */
		if ( ! empty( $args['meta_query'] ) ) {
			$meta_filter = array();

			$relation = 'must';
			if ( ! empty( $args['meta_query']['relation'] ) && 'or' === strtolower( $args['meta_query']['relation'] ) ) {
				$relation = 'should';
			}

			foreach( $args['meta_query'] as $single_meta_query ) {
				if ( ! empty( $single_meta_query['key'] ) ) {

					$terms_obj = false;

					$compare = '=';
					if ( ! empty( $single_meta_query['compare'] ) ) {
						$compare = strtolower( $single_meta_query['compare'] );
					}

					switch ( $compare ) {
						case '!=':
							if ( isset( $single_meta_query['value'] ) ) {
								$terms_obj = array(
									'bool' => array(
										'must_not' => array(
											array(
												'terms' => array(
													'post_meta.' . $single_meta_query['key'] . '.raw' => (array) $single_meta_query['value'],
												),
											),
										),
									),
								);
							}

							break;
						case 'exists':
							$terms_obj = array(
								'exists' => array(
									'field' => 'post_meta.' . $single_meta_query['key'],
								),
							);

							break;
						case 'not exists':
							$terms_obj = array(
								'bool' => array(
									'must_not' => array(
										array(
											'exists' => array(
												'field' => 'post_meta.' . $single_meta_query['key'],
											),
										),
									),
								),
							);

							break;
						case '>=':
							if ( isset( $single_meta_query['value'] ) ) {
								$terms_obj = array(
									'bool' => array(
										'must' => array(
											array(
												'range' => array(
													'post_meta.' . $single_meta_query['key'] . '.raw' => array(
														"gte" => $single_meta_query['value'],
													),
												),
											),
										),
									),
								);
							}

							break;
						case '<=':
							if ( isset( $single_meta_query['value'] ) ) {
								$terms_obj = array(
									'bool' => array(
										'must' => array(
											array(
												'range' => array(
													'post_meta.' . $single_meta_query['key'] . '.raw' => array(
														"lte" => $single_meta_query['value'],
													),
												),
											),
										),
									),
								);
							}

							break;
						case '>':
							if ( isset( $single_meta_query['value'] ) ) {
								$terms_obj = array(
									'bool' => array(
										'must' => array(
											array(
												'range' => array(
													'post_meta.' . $single_meta_query['key'] . '.raw' => array(
														"gt" => $single_meta_query['value'],
													),
												),
											),
										),
									),
								);
							}

							break;
						case '<':
							if ( isset( $single_meta_query['value'] ) ) {
								$terms_obj = array(
									'bool' => array(
										'must' => array(
											array(
												'range' => array(
													'post_meta.' . $single_meta_query['key'] . '.raw' => array(
														"lt" => $single_meta_query['value'],
													),
												),
											),
										),
									),
								);
							}

							break;
						case 'like':
							if ( isset( $single_meta_query['value'] ) ) {
								$terms_obj = array(
									'query' => array(
										"match" => array(
											'post_meta.' . $single_meta_query['key'] => $single_meta_query['value'],
										)
									),
								);
							}
							break;
						case '=':
						default:
							if ( isset( $single_meta_query['value'] ) ) {
								$terms_obj = array(
									'terms' => array(
										'post_meta.' . $single_meta_query['key'] . '.raw' => (array) $single_meta_query['value'],
									),
								);
							}

							break;
					}

					// Add the meta query filter
					if ( false !== $terms_obj ) {
						$meta_filter[] = $terms_obj;
					}
				}
			}

			if ( ! empty( $meta_filter ) ) {
				$filter['and'][]['bool'][$relation] = $meta_filter;

				$use_filters = true;
			}
		}

		/**
		 * Allow for search field specification
		 *
		 * @since 1.0
		 */
		if ( ! empty( $args['search_fields'] ) ) {
			$search_field_args = $args['search_fields'];
			$search_fields = array();

			if ( ! empty( $search_field_args['taxonomies'] ) ) {
				$taxes = (array) $search_field_args['taxonomies'];

				foreach ( $taxes as $tax ) {
					$search_fields[] = 'terms.' . $tax . '.name';
				}

				unset( $search_field_args['taxonomies'] );
			}

			if ( ! empty( $search_field_args['meta'] ) ) {
				$metas = (array) $search_field_args['meta'];

				foreach ( $metas as $meta ) {
					$search_fields[] = 'post_meta.' . $meta;
				}

				unset( $search_field_args['meta'] );
			}

			if ( in_array( 'author_name', $search_field_args ) ) {
				$search_fields[] = 'post_author.login';

				unset( $search_field_args['author_name'] );
			}

			$search_fields = array_merge( $search_field_args, $search_fields );
		} else {
			$search_fields = array(
				'post_title',
				'post_excerpt',
				'post_content',
			);
		}

		$search_fields = apply_filters( 'ep_search_fields', $search_fields, $args );

		$query = array(
			'bool' => array(
				'should' => array(
					array(
						'multi_match' => array(
							'query' => '',
							'fields' => $search_fields,
							'boost' => apply_filters( 'ep_match_boost', 2 ),
							'fuzziness' => 0,
						)
					),
					array(
						'multi_match' => array(
							'fields' => $search_fields,
							'query' => '',
							'fuzziness' => 2,
							'operator' => 'or',
						),
					)
				),
			),
		);

		/**
		 * We are using ep_integrate instead of ep_match_all. ep_match_all will be
		 * supported for legacy code but may be deprecated and removed eventually.
		 *
		 * @since 1.3
		 */

		if ( ! empty( $args['s'] ) && empty( $args['ep_match_all'] ) && empty( $args['ep_integrate'] ) ) {
			$query['bool']['should'][1]['multi_match']['query'] = $args['s'];
			$query['bool']['should'][0]['multi_match']['query'] = $args['s'];
			$formatted_args['query'] = $query;
		} else if ( ! empty( $args['ep_match_all'] ) || ! empty( $args['ep_integrate'] ) ) {
			$formatted_args['query']['match_all'] = array();
		}

		/**
		 * Like WP_Query in search context, if no post_type is specified we default to "any". To
		 * be safe you should ALWAYS specify the post_type parameter UNLIKE with WP_Query.
		 *
		 * @since 1.3
		 */
		if ( ! empty( $args['post_type'] ) ) {
			// should NEVER be "any" but just in case
			if ( 'any' !== $args['post_type'] ) {
				$post_types = (array) $args['post_type'];
				$terms_map_name = 'terms';
				if ( count( $post_types ) < 2 ) {
					$terms_map_name = 'term';
					$post_types = $post_types[0];
 				}

				$filter['and'][] = array(
					$terms_map_name => array(
						'post_type.raw' => $post_types,
					),
				);

				$use_filters = true;
			}
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

		if ( $use_filters ) {
			$formatted_args['filter'] = $filter;
		}

		/**
		 * Aggregations
		 */
		if ( isset( $args['aggs'] ) && ! empty( $args['aggs']['aggs'] ) ) {
			$agg_obj = $args['aggs'];

			// Add a name to the aggregation if it was passed through
			if ( ! empty( $agg_obj['name'] ) ) {
				$agg_name = $agg_obj['name'];
			} else {
				$agg_name = 'aggregation_name';
			}

			// Add/use the filter if warranted
			if ( isset( $agg_obj['use-filter'] ) && false !== $agg_obj['use-filter'] && $use_filters ) {

				// If a filter is being used, use it on the aggregation as well to receive relevant information to the query
				$formatted_args['aggs'][ $agg_name ]['filter'] = $filter;
				$formatted_args['aggs'][ $agg_name ]['aggs'] = $agg_obj['aggs'];
			} else {
				$formatted_args['aggs'][ $agg_name ] = $args['aggs'];
			}
		}
		return apply_filters( 'ep_formatted_args', $formatted_args, $args );
	}

	/**
	 * Wrapper function for wp_get_sites - allows us to have one central place for the `ep_indexable_sites` filter
	 *
	 * @param int $limit The maximum amount of sites retrieved, Use 0 to return all sites
	 *
	 * @return mixed|void
	 */
	public function get_sites( $limit = 0 ) {
		$args = apply_filters( 'ep_indexable_sites_args', array(
			'limit' => $limit,
		) );

		return apply_filters( 'ep_indexable_sites', wp_get_sites( $args ) );
	}

	/**
	 * Decode the bulk index response
	 *
	 * @since 0.9.2
	 * @param $body
	 * @return array|object|WP_Error
	 */
	public function bulk_index_posts( $body ) {
		// create the url with index name and type so that we don't have to repeat it over and over in the request (thereby reducing the request size)
		$path = trailingslashit( ep_get_index_name() ) . 'post/_bulk';

		$request_args = array(
			'method'  => 'POST',
			'body'    => $body,
			'timeout' => 30,
		);

		$request = ep_remote_request( $path, apply_filters( 'ep_bulk_index_posts_request_args', $request_args, $body ) );

		if ( is_wp_error( $request ) ) {
			return $request;
		}

		$response = wp_remote_retrieve_response_code( $request );

		if ( 200 !== $response ) {
			return new WP_Error( $response, wp_remote_retrieve_response_message( $request ), $request );
		}

		return json_decode( wp_remote_retrieve_body( $request ), true );
	}

	/**
	 * Check to see if we should allow elasticpress to override this query
	 *
	 * @param $query
	 * @return bool
	 * @since 0.9.2
	 */
	public function elasticpress_enabled( $query ) {
		$enabled = false;

		if ( method_exists( $query, 'is_search' ) && $query->is_search() ) {
			$enabled = true;
		} elseif ( ! empty( $query->query['ep_match_all'] ) ) { // ep_match_all is supported for legacy reasons
			$enabled = true;
		} elseif ( ! empty( $query->query['ep_integrate'] ) ) {
			$enabled = true;
		}

		return apply_filters( 'ep_elasticpress_enabled', $enabled, $query );
	}

	/**
	 * Deactivate ElasticPress. Disallow EP to override the main WP_Query for search queries
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public function deactivate() {
		return delete_site_option( 'ep_is_active' );
	}

	/**
	 * Activate ElasticPress. Allow EP to override the main WP_Query for search queries
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	public function activate() {
		return update_site_option( 'ep_is_active', true );
	}

	/**
	 * Parse an 'order' query variable and cast it to ASC or DESC as necessary.
	 *
	 * @since 1.1
	 * @access protected
	 *
	 * @param string $order The 'order' query variable.
	 * @return string The sanitized 'order' query variable.
	 */
	protected function parse_order( $order ) {
		if ( ! is_string( $order ) || empty( $order ) ) {
			return 'desc';
		}

		if ( 'ASC' === strtoupper( $order ) ) {
			return 'asc';
		} else {
			return 'desc';
		}
	}

	/**
	 * If the passed orderby value is allowed, convert the alias to a
	 * properly-prefixed sort value.
	 *
	 * @since 1.1
	 * @access protected
	 *
	 * @param string $orderby Alias for the field to order by.
	 * @param string $order
	 * @return array|bool Array formatted value to used in the sort DSL. False otherwise.
	 */
	protected function parse_orderby( $orderby, $order ) {
		// Used to filter values.
		$allowed_keys = array(
			'relevance',
			'name',
			'title',
			'date',
		);

		if ( ! in_array( $orderby, $allowed_keys ) ) {
			return false;
		}

		switch ( $orderby ) {
			case 'relevance':
			default:
				$sort = array(
					array(
						'_score' => array(
							'order' => $order,
						),
					),
				);
				break;
			case 'date':
				$sort = array(
					array(
						'post_date' => array(
							'order' => $order,
						),
					),
				);
				break;
			case 'name':
			case 'title':
				$sort = array(
					array(
						'post_' . $orderby . '.raw' => array(
							'order' => $order,
						),
					),
				);
				break;
		}

		return $sort;
	}

	/**
	 * Check to see if ElasticPress is currently active (can be disabled during syncing, etc)
	 *
	 * @return mixed
	 * @since 0.9.2
	 */
	public function is_activated() {
		return get_site_option( 'ep_is_active', false, false );
	}

	/**
	 * This function checks two things - that the plugin is currently 'activated' and that it can successfully reach the
	 * server.
	 *
	 * @since 1.1.0
	 *
	 * @param string $host The host to check
	 *
	 * @return bool
	 */
	public function elasticsearch_alive( $host = null ) {

		$elasticsearch_alive = false;

		$host = null !== $host ? $host : ep_get_host(); //fallback to EP_HOST if no other host provided

		//If we get a WP_Error try again for backups and return false if we still get an error
		if ( is_wp_error( $host ) ) {
			$host = ep_get_host( true );
		}

		if ( is_wp_error( $host ) ) {
			return $elasticsearch_alive;
		}

		$request_args = array( 'headers' => $this->format_request_headers() );
		$url = $host;

		$request = wp_remote_request( $url, apply_filters( 'ep_es_alive_request_args', $request_args ) );

		if ( ! is_wp_error( $request ) ) {
			if ( isset( $request['response']['code'] ) && 200 === $request['response']['code'] ) {
				$elasticsearch_alive = true;
			}
		}

		return $elasticsearch_alive;
	}

	/**
	 * Wrapper for wp_remote_request
	 *
	 * This is a wrapper function for wp_remote_request that will switch to a backup server
	 * (if present) should the primary EP host fail.
	 *
	 * @since 1.6
	 *
	 * @param string $path Site URL to retrieve.
	 * @param array  $args Optional. Request arguments. Default empty array.
	 *
	 * @return WP_Error|array The response or WP_Error on failure.
	 */
	public function remote_request( $path, $args = array() ) {

		//The allowance of these variables makes testing easier.
		$force       = false;
		$use_backups = false;

		//Add the API Header
		$args['headers'] = $this->format_request_headers();

		if ( defined( 'EP_FORCE_HOST_REFRESH' ) && true === EP_FORCE_HOST_REFRESH ) {
			$force = true;
		}

		if ( defined( 'EP_HOST_USE_ONLY_BACKUPS' ) && true === EP_HOST_USE_ONLY_BACKUPS ) {
			$use_backups = true;
		}

		$host    = ep_get_host( $force, $use_backups );
		$request = false;

		if ( ! is_wp_error( $host ) ) { // probably only reachable in testing but just to be safe
			$request = wp_remote_request( esc_url( trailingslashit( $host ) . $path ), $args ); //try the existing host to avoid unnecessary calls
		}

		//If we have a failure we'll try it again with a backup host
		if ( false === $request || is_wp_error( $request ) || ( isset( $request['response']['code'] ) && 200 !== $request['response']['code'] ) ) {

			$host = ep_get_host( true, $use_backups );

			if ( is_wp_error( $host ) ) {
				return $host;
			}

			return wp_remote_request( esc_url( trailingslashit( $host ) . $path ), $args );

		}

		return $request;

	}

}

EP_API::factory();

/**
 * Accessor functions for methods in above class. See doc blocks above for function details.
 */

function ep_index_post( $post ) {
	return EP_API::factory()->index_post( $post );
}

function ep_search( $args, $cross_site = false ) {
	return EP_API::factory()->search( $args, $cross_site );
}

function ep_get_post( $post_id ) {
	return EP_API::factory()->get_post( $post_id );
}

function ep_delete_post( $post_id ) {
	return EP_API::factory()->delete_post( $post_id );
}

function ep_put_mapping() {
	return EP_API::factory()->put_mapping();
}

function ep_delete_index( $index_name = null ) {
	return EP_API::factory()->delete_index( $index_name );
}

function ep_format_args( $args ) {
	return EP_API::factory()->format_args( $args );
}

function ep_create_network_alias( $indexes ) {
	return EP_API::factory()->create_network_alias( $indexes );
}

function ep_delete_network_alias() {
	return EP_API::factory()->delete_network_alias();
}

function ep_refresh_index() {
	return EP_API::factory()->refresh_index();
}

function ep_prepare_post( $post_id ) {
	return EP_API::factory()->prepare_post( $post_id );
}

function ep_get_sites( $limit = 0 ) {
	return EP_API::factory()->get_sites( $limit );
}

function ep_bulk_index_posts( $body ) {
	return EP_API::factory()->bulk_index_posts( $body );
}

function ep_elasticpress_enabled( $query ) {
	return EP_API::factory()->elasticpress_enabled( $query );
}

function ep_activate() {
	return EP_API::factory()->activate();
}

function ep_deactivate() {
	return EP_API::factory()->deactivate();
}

function ep_is_activated() {
	return EP_API::factory()->is_activated();
}

function ep_elasticsearch_alive( $host = null ) {
	return EP_API::factory()->elasticsearch_alive( $host );
}

function ep_index_exists( $index_name = null ) {
	return EP_API::factory()->index_exists( $index_name );
}

function ep_format_request_headers() {
	return EP_API::factory()->format_request_headers();
}

function ep_remote_request( $path, $args ) {
	return EP_API::factory()->remote_request( $path, $args );
}
