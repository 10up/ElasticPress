<?php
/**
 * Indexable abstract class
 *
 * @since  2.6
 * @package elasticpress
 */

namespace ElasticPress;

/**
 * An extendable type is essentially a document type that can be indexed
 * and queried against
 *
 * @since  2.6
 */
abstract class Indexable {
	public function get_index_name( $blog_id = null ) {
		if ( ! $blog_id ) {
			$blog_id = get_current_blog_id();
		}

		$site_url = get_site_url( $blog_id );

		if ( ! empty( $site_url ) ) {
			$index_name = preg_replace( '#https?://(www\.)?#i', '', $site_url );
			$index_name = preg_replace( '#[^\w]#', '', $index_name ) . '-' . $this->indexable_type . '-' . $blog_id;
		} else {
			$index_name = false;
		}

		if ( defined( 'EP_INDEX_PREFIX' ) && EP_INDEX_PREFIX ) {
			$index_name = EP_INDEX_PREFIX . $index_name;
		}

		return apply_filters( 'ep_index_name', $index_name, $blog_id );
	}

	public function get_network_alias() {
		$url = network_site_url();
		$slug = preg_replace( '#https?://(www\.)?#i', '', $url );
		$slug = preg_replace( '#[^\w]#', '', $slug );

		$alias = $slug . '-' . $this->indexable_type . '-global';

		if ( defined( 'EP_INDEX_PREFIX' ) && EP_INDEX_PREFIX ) {
			$alias = EP_INDEX_PREFIX . $alias;
		}

		return apply_filters( 'ep_global_alias', $alias );
	}

	public function delete_network_alias() {
		return EP_API::factory()->delete_network_alias( $alias );
	}

	public function create_network_alias( $indexes ) {
		return EP_API::factory()->create_network_alias( $indexes, $this->get_network_alias() );
	}

	abstract function index( $document, $blocking = false );

	abstract function get( $document_id );

	abstract function bulk_index( $body );

	abstract function delete( $document_id, $blocking = true );

	abstract function query( $args, $query_args, $scope = 'current' );

	abstract function put_mapping();

	abstract function delete_index();
}
