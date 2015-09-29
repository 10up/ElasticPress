<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // exit if accessed directly
}

abstract class EP_Abstract_Object_Index implements EP_Object_Index {

	/** @var EP_API */
	protected $api = '';

	/** @var string */
	protected $name = '';

	/**
	 * @param string $name
	 * @param EP_API $api
	 */
	public function __construct( $name, $api = null ) {
		$this->name = $name;
		$this->api  = $api ? $api : EP_API::factory();
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * {@inheritdoc}
	 */
	public function set_name( $name ) {
		$this->name = $name;
	}

	/**
	 * {@inheritdoc}
	 */
	public function index_document( $object ) {
		/**
		 * Filter the object prior to indexing
		 *
		 * Allows for last minute indexing of object information.
		 *
		 * @since 1.7
		 *
		 * @param         array Array of post information to index.
		 */
		$object = apply_filters( "ep_pre_index_{$this->name}", $object );

		$index = untrailingslashit( ep_get_index_name() );

		$path = implode( '/', array( $index, $this->name, $this->get_object_identifier( $object ) ) );

		$request_args = array(
			'body'    => json_encode( $object ),
			'method'  => 'PUT',
			'timeout' => 15,
		);

		$request = ep_remote_request(
			$path,
			apply_filters( "ep_index_{$this->name}_request_args", $request_args, $object )
		);

		do_action( "ep_index_{$this->name}_retrieve_raw_response", $request, $object, $path );

		if ( ! is_wp_error( $request ) ) {
			$response_body = wp_remote_retrieve_body( $request );

			return json_decode( $response_body );
		}

		return false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_document( $object ) {
		$index = untrailingslashit( ep_get_index_name() );

		$path = implode( '/', array( $index, $this->name, $object ) );

		$request_args = array( 'method' => 'GET' );

		$request = ep_remote_request(
			$path,
			apply_filters( "ep_get_{$this->name}_request_args", $request_args, $object )
		);

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
	 * {@inheritdoc}
	 */
	public function delete_document( $object ) {
		$index = untrailingslashit( ep_get_index_name() );

		$path = implode( '/', array( $index, $this->name, $object ) );

		$request_args = array( 'method' => 'DELETE', 'timeout' => 15 );

		$request = ep_remote_request(
			$path,
			apply_filters( "ep_delete_{$this->name}_request_args", $request_args, $object )
		);

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
	 * {@inheritdoc}
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

		$path = $index . "/{$this->name}/_search";

		if ( 'post' === $this->name ) {
			/**
			 * Backwards compatibility: when posts were the only type, this was the filter. This filter is deprecated in
			 * favor of ep_search_post_args
			 */
			$args = apply_filters( 'ep_search_args', $args, $scope );
		}
		$request_args = array(
			'body'   => json_encode( apply_filters( "ep_search_{$this->name}_args", $args, $scope ) ),
			'method' => 'POST',
		);

		if ( 'post' === $this->name ) {
			/**
			 * Backwards compatibility: when posts were the only type, this was the filter. This filter is deprecated in
			 * favor of ep_search_post_request_args
			 */
			$request_args = apply_filters( 'ep_search_request_args', $request_args, $args, $scope );
		}
		$request = ep_remote_request(
			$path,
			apply_filters( "ep_search_{$this->name}_request_args", $request_args, $args, $scope )
		);

		if ( ! is_wp_error( $request ) ) {

			// Allow for direct response retrieval
			do_action( 'ep_retrieve_raw_response', $request, $args, $scope, $this->name );

			$response_body = wp_remote_retrieve_body( $request );

			$response = json_decode( $response_body, true );

			if ( $this->api->is_empty_search( $response ) ) {
				return array( 'found_objects' => 0, 'objects' => array() );
			}

			$hits = $response['hits']['hits'];

			// Check for and store aggregations
			if ( ! empty( $response['aggregations'] ) ) {
				do_action( 'ep_retrieve_aggregations', $response['aggregations'], $args, $scope, $this->name );
			}

			$objects = array();

			foreach ( $hits as $hit ) {
				$object            = $hit['_source'];
				$object['site_id'] = $this->api->parse_site_id( $hit['_index'] );
				$objects[]         = apply_filters( "ep_retrieve_the_{$this->name}", $object, $hit );
			}

			$results = array( 'found_objects' => $response['hits']['total'], 'objects' => $objects );
			if ( 'post' === $this->name ) {
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
				$results = apply_filters(
					'ep_search_results_array',
					$results,
					$response
				);
			}

			return apply_filters( "ep_search_{$this->name}_results_array", $results, $response );
		}

		return false;
	}

	/**
	 * Prepare terms for optional inclusion in the index
	 *
	 * @param $object
	 *
	 * @return array
	 */
	protected function prepare_terms( $object ) {
		$taxonomies          = $this->get_object_taxonomies( $object );
		$selected_taxonomies = array();

		foreach ( $taxonomies as $taxonomy ) {
			if ( $taxonomy->public ) {
				$selected_taxonomies[] = $taxonomy;
			}
		}

		if ( 'post' === $this->name ) {
			$selected_taxonomies = apply_filters( 'ep_sync_taxonomies', $selected_taxonomies, $object );
		}
		$selected_taxonomies = apply_filters( "ep_sync_{$this->name}_taxonomies", $selected_taxonomies, $object );

		if ( empty( $selected_taxonomies ) ) {
			return array();
		}

		$terms = array();

		$allow_hierarchy = apply_filters( 'ep_sync_terms_allow_hierarchy', false );

		foreach ( $selected_taxonomies as $taxonomy ) {
			$object_terms = apply_filters( "ep_sync_get_terms_{$this->name}", null, $object, $taxonomy );
			if ( is_null( $object_terms ) ) {
				$object_terms = get_the_terms( $this->get_object_identifier( $object ), $taxonomy->name );
			}

			if ( ! $object_terms || is_wp_error( $object_terms ) ) {
				continue;
			}

			$terms_dic = array();

			foreach ( $object_terms as $term ) {
				if ( ! isset( $terms_dic[ $term->term_id ] ) ) {
					$terms_dic[ $term->term_id ] = array(
						'term_id' => $term->term_id,
						'slug'    => $term->slug,
						'name'    => $term->name,
						'parent'  => $term->parent
					);
					if ( $allow_hierarchy ) {
						$terms_dic = $this->get_parent_terms( $terms_dic, $term, $taxonomy->name );
					}
				}
			}
			$terms[ $taxonomy->name ] = array_values( $terms_dic );
		}

		return $terms;
	}

	/**
	 * Get taxonomies for the current object/object type
	 *
	 * @param $object
	 *
	 * @return array
	 */
	protected function get_object_taxonomies( $object ) {
		return array();
	}

	/**
	 * Optionally prepare metadata for this object
	 *
	 * @param $object
	 *
	 * @return array
	 */
	protected function prepare_meta( $object ) {
		$meta = (array) $this->get_object_meta( $object );

		if ( empty( $meta ) ) {
			return array();
		}

		$prepared_meta = array();

		foreach ( $meta as $key => $value ) {
			if ( ! is_protected_meta( $key ) ) {
				$prepared_meta[ $key ] = maybe_unserialize( $value );
			}
		}

		return apply_filters( "ep_prepare_{$this->name}_meta", $prepared_meta, $object );
	}

	/**
	 * Get all the metadata for an object
	 *
	 * @param $object
	 *
	 * @return array
	 */
	protected function get_object_meta( $object ) {
		return array();
	}

	/**
	 * Get the primary identifier for an object
	 *
	 * This could be a slug, or an ID, or something else. It will be used as a canonical
	 * lookup for the document.
	 *
	 * @param mixed $object
	 *
	 * @return int|string
	 */
	abstract protected function get_object_identifier( $object );

	/**
	 * Recursively get all the ancestor terms of the given term
	 *
	 * @param $terms
	 * @param $term
	 * @param $tax_name
	 *
	 * @return array
	 */
	private function get_parent_terms( $terms, $term, $tax_name ) {
		$parent_term = get_term( $term->parent, $tax_name );
		if ( ! $parent_term || is_wp_error( $parent_term ) ) {
			return $terms;
		}
		if ( ! isset( $terms[ $parent_term->term_id ] ) ) {
			$terms[ $parent_term->term_id ] = array(
				'term_id' => $parent_term->term_id,
				'slug'    => $parent_term->slug,
				'name'    => $parent_term->name,
				'parent'  => $parent_term->parent
			);
		}

		return $this->get_parent_terms( $terms, $parent_term, $tax_name );
	}

}
