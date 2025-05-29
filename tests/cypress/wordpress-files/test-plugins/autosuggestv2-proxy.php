<?php
/**
 * This file sanitizes and sends search requests to the Elasticsearch server.
 *
 * phpcs:disable WordPress.PHP.DiscouragedPHPFunctions
 * phpcs:disable WordPress.Security.NonceVerification
 * phpcs:disable WordPress.WP.AlternativeFunctions
 * phpcs:disable WordPress.PHP.IniSet
 * phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
 * phpcs:disable WordPress.Security.NonceVerification.Recommended
 * phpcs:disable WordPress.Security.ValidatedSanitizedInput
 *
 * @package ElasticPress_Custom_Proxy
 */

if ( isset( $_SERVER['HTTP_HOST'] ) && false !== strpos( $_SERVER['HTTP_HOST'], '.test' ) ) {
	error_reporting( E_ALL ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
	ini_set( 'display_errors', 1 );
}

/**
 * Class to hold all the proxy functionality.
 */
class EP_AS_PHP_Proxy {

	/**
	 * The query to be sent to Elasticsearch.
	 *
	 * @var string|array
	 */
	protected $query;

	/**
	 * The additional filters the request may need.
	 *
	 * @var array
	 */
	protected $filters = array();

	/**
	 * The relation between filters.
	 *
	 * @var array
	 */
	protected $filter_relations = array();

	/**
	 * Global relation between filters
	 *
	 * @var string
	 */
	protected $relation = '';

	/**
	 * The request object.
	 *
	 * @var object
	 */
	protected $request;

	/**
	 * The request response.
	 *
	 * @var string
	 */
	protected $response;

	/**
	 * The URL of the posts index.
	 *
	 * @var string
	 */
	protected $post_index_url = '';

	/**
	 * Entry point of the class.
	 */
	public function proxy() {
		/**
		 * This file is built by the plugin when a reindex is done or the weighting dashboard is saved.
		 *
		 * It contains the template query, credentials, and the endpoint URL.
		 */
		require '../uploads/ep-custom-proxy-credentials.php';

		$this->query          = $query_template;
		$this->post_index_url = $post_index_url;

		$this->build_query();
		$this->make_request();
		$this->return_response();
	}

	/**
	 * Build the query to be sent, i.e., get the template and make all necessary replaces/changes.
	 */
	protected function build_query() {
		// For the next replacements, we'll need to work with an object
		$this->query = json_decode( $this->query, true );

		$this->set_search_term();
		$this->set_pagination();
		$this->set_order();

		$this->relation = ( ! empty( $_REQUEST['relation'] ) ) ? $this->sanitize_string( $_REQUEST['relation'] ) : 'or';
		$this->relation = ( 'or' === $this->relation ) ? $this->relation : 'and';

		$this->handle_post_type_filter();
		$this->handle_taxonomies_filters();
		$this->handle_price_filter();

		$this->apply_filters();

		$this->query = json_encode( $this->query );
	}

	/**
	 * Set the search term in the query.
	 */
	protected function set_search_term() {
		$search_term = $this->sanitize_string( $_REQUEST['search'] );

		// Stringify the JSON object again just to make the str_replace easier.
		if ( ! empty( $search_term ) ) {
			$query_string = json_encode( $this->query );
			$query_string = str_replace( '{{ep_placeholder}}', $search_term, $query_string );
			$this->query  = json_decode( $query_string, true );
			return;
		}

		// If there is no search term, get everything.
		$this->query['query'] = array( 'match_all' => array( 'boost' => 1 ) );
	}

	/**
	 * Set the pagination.
	 */
	protected function set_pagination() {
		// Pagination
		$per_page = $this->sanitize_number( $_REQUEST['per_page'] );
		$offset   = $this->sanitize_number( $_REQUEST['offset'] );
		if ( $per_page && $per_page > 1 ) {
			$this->query['size'] = $per_page;
		}
		if ( $offset && $offset > 1 ) {
			$this->query['from'] = $offset;
		}
	}

	/**
	 * Set the order.
	 */
	protected function set_order() {
		$orderby = $this->sanitize_string( $_REQUEST['orderby'] );
		$order   = $this->sanitize_string( $_REQUEST['order'] );

		$order = ( 'desc' === $order ) ? $order : 'asc';

		$sort_clause = array();

		switch ( $orderby ) {
			case 'date':
				$sort_clause['post_date'] = array( 'order' => $order );
				break;

			case 'price':
				$sort_clause['meta._price.double'] = array(
					'order' => $order,
					'mode'  => ( 'asc' === $order ) ? 'min' : 'max',
				);
				break;

			case 'rating':
				$sort_clause['meta._wc_average_rating.double'] = array( 'order' => $order );
				break;
		}

		if ( ! empty( $sort_clause ) ) {
			$this->query['sort'] = array( $sort_clause );
		}
	}

	/**
	 * Add post types to the filters.
	 */
	protected function handle_post_type_filter() {
		$post_types = ( ! empty( $_REQUEST['post_type'] ) ) ? explode( ',', $_REQUEST['post_type'] ) : array();
		$post_types = array_filter( array_map( array( $this, 'sanitize_string' ), $post_types ) );
		if ( empty( $post_types ) ) {
			return;
		}

		if ( 'or' === $this->relation ) {
			$this->filters['post_type'] = array(
				'terms' => array(
					'post_type.raw' => $post_types,
				),
			);
			return;
		}

		$terms = array();
		foreach ( $post_types as $post_type ) {
			$terms[] = array(
				'term' => array(
					'post_type.raw' => $post_type,
				),
			);
		}

		$this->filters['post_type'] = array(
			'bool' => array(
				'must' => $terms,
			),
		);
	}

	/**
	 * Add taxonomies to the filters.
	 */
	protected function handle_taxonomies_filters() {
		$taxonomies    = array();
		$tax_relations = ( ! empty( $_REQUEST['term_relations'] ) ) ? (array) $_REQUEST['term_relations'] : array();
		foreach ( (array) $_REQUEST as $key => $value ) {
			if ( ! preg_match( '/^tax-(\S+)$/', $key, $matches ) ) {
				continue;
			}

			if ( empty( $value ) ) {
				continue;
			}

			$taxonomy = $matches[1];

			$relation = ( ! empty( $tax_relations[ $taxonomy ] ) ) ?
				$this->sanitize_string( $tax_relations[ $taxonomy ] ) :
				$this->relation;

			$taxonomies[ $matches[1] ] = array(
				'relation' => $relation,
				'terms'    => array_map( array( $this, 'sanitize_number' ), explode( ',', $value ) ),
			);
		}

		if ( empty( $taxonomies ) ) {
			return;
		}

		foreach ( $taxonomies as $taxonomy_slug => $taxonomy ) {
			if ( 'or' === $this->relation ) {
				$this->filters[ $taxonomy_slug ] = array(
					'terms' => array(
						"terms.{$taxonomy_slug}.term_id" => $taxonomy['terms'],
					),
				);
				return;
			}

			$terms = array();
			foreach ( $taxonomy['terms'] as $term ) {
				$terms[] = array(
					'term' => array(
						"terms.{$taxonomy_slug}.term_id" => $term,
					),
				);
			}

			$this->filters[ $taxonomy_slug ] = array(
				'bool' => array(
					'must' => $terms,
				),
			);
		}
	}

	/**
	 * Add price ranges to the filters.
	 */
	protected function handle_price_filter() {
		$min_price = ( ! empty( $_REQUEST['min_price'] ) ) ? $this->sanitize_string( $_REQUEST['min_price'] ) : '';
		$max_price = ( ! empty( $_REQUEST['max_price'] ) ) ? $this->sanitize_string( $_REQUEST['max_price'] ) : '';

		if ( $min_price ) {
			$this->filters['min_price'] = array(
				'range' => array(
					'meta._price.double' => array(
						'gte' => $min_price,
					),
				),
			);
		}

		if ( $max_price ) {
			$this->filters['max_price'] = array(
				'range' => array(
					'meta._price.double' => array(
						'lte' => $max_price,
					),
				),
			);
		}
	}

	/**
	 * Add filters to the query.
	 */
	protected function apply_filters() {
		$occurrence = ( 'and' === $this->relation ) ? 'must' : 'should';

		$existing_filter = ( ! empty( $this->query['post_filter'] ) ) ? $this->query['post_filter'] : array( 'match_all' => array( 'boost' => 1 ) );

		if ( ! empty( $this->filters ) ) {
			$this->query['post_filter'] = array(
				'bool' => array(
					'must' => array(
						$existing_filter,
						array(
							'bool' => array(
								$occurrence => array_values( $this->filters ),
							),
						),
					),
				),
			);
		}

		/**
		 * If there's no aggregations in the template or if the relation isn't 'and', we are done.
		 */
		if ( empty( $this->query['aggs'] ) || 'and' !== $this->relation ) {
			return;
		}

		/**
		 * Apply filters to aggregations.
		 *
		 * Note the usage of `&agg` (passing by reference.)
		 */
		foreach ( $this->query['aggs'] as $agg_name => &$agg ) {
			$new_filters = array();

			/**
			 * Only filter an aggregation if there's sub-aggregations.
			 */
			if ( empty( $agg['aggs'] ) ) {
				continue;
			}

			/**
			 * Get any existing filter, or a placeholder.
			 */
			$existing_filter = $agg['filter'] ?? array( 'match_all' => array( 'boost' => 1 ) );

			/**
			 * Get new filters for this aggregation.
			 *
			 * Don't apply a filter to a matching aggregation if the relation is 'or'.
			 */
			foreach ( $this->filters as $filter_name => $filter ) {
				// @todo: this relation should not be the global one but the relation between aggs.
				if ( $filter_name === $agg_name && 'or' === $this->relation ) {
					continue;
				}

				$new_filters[] = $filter;
			}

			/**
			 * Add filters to the aggregation.
			 */
			if ( ! empty( $new_filters ) ) {
				$agg['filter'] = array(
					'bool' => array(
						'must' => array(
							$existing_filter,
							array(
								'bool' => array(
									$occurrence => $new_filters,
								),
							),
						),
					),
				);
			}
		}
	}

	/**
	 * Make the cURL request.
	 */
	protected function make_request() {
		$http_headers = array( 'Content-Type: application/json' );
		$endpoint     = $this->post_index_url . '/_search';

		// Create the cURL request.
		$this->request = curl_init( $endpoint );

		curl_setopt( $this->request, CURLOPT_POSTFIELDS, $this->query );

		curl_setopt_array(
			$this->request,
			array(
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_HEADER         => true,
				CURLOPT_RETURNTRANSFER => true,
				CURLINFO_HEADER_OUT    => true,
				CURLOPT_HTTPHEADER     => $http_headers,
			)
		);

		$this->response = curl_exec( $this->request );
	}

	/**
	 * Format and output the response from Elasticsearch.
	 */
	protected function return_response() {
		// Fetch all info from the request.
		$header_size      = curl_getinfo( $this->request, CURLINFO_HEADER_SIZE );
		$response_header  = substr( $this->response, 0, $header_size );
		$response_body    = substr( $this->response, $header_size );
		$response_info    = curl_getinfo( $this->request );
		$response_code    = $response_info['http_code'] ?? 500;
		$response_headers = preg_split( '/[\r\n]+/', $response_info['request_header'] ?? '' );
		if ( 0 === $response_code ) {
			$response_code = 404;
		}

		curl_close( $this->request );

		// Respond with the same headers, content and status code.

		// Split header text into an array.
		$response_headers = preg_split( '/[\r\n]+/', $response_header );
		// Pass headers to output
		foreach ( $response_headers as $header ) {
			// Pass following headers to response
			if ( preg_match( '/^(?:Content-Type|Content-Language|Content-Security|X)/i', $header ) ) {
				header( $header );
			} elseif ( strpos( $header, 'Set-Cookie' ) !== false ) {
				// Replace cookie domain and path
				$header = preg_replace( '/((?>domain)\s*=\s*)[^;\s]+/', '\1.' . $_SERVER['HTTP_HOST'], $header );
				$header = preg_replace( '/\s*;?\s*path\s*=\s*[^;\s]+/', '', $header );
				header( $header, false );
			} elseif ( 'Content-Encoding: gzip' === $header ) {
				// Decode response body if gzip encoding is used
				$response_body = gzdecode( $response_body );
			}
		}

		http_response_code( $response_code );
		exit( $response_body ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Utilitary function to sanitize string.
	 *
	 * @param string $string String to be sanitized
	 * @return string
	 */
	protected function sanitize_string( $string ) {
		return htmlspecialchars( $string );
	}

	/**
	 * Utilitary function to sanitize numbers.
	 *
	 * @param string $string Number to be sanitized
	 * @return string
	 */
	protected function sanitize_number( $string ) {
		return filter_var( $string, FILTER_SANITIZE_NUMBER_INT );
	}
}

$ep_as_php_proxy = new EP_AS_PHP_Proxy();
$ep_as_php_proxy->proxy();
