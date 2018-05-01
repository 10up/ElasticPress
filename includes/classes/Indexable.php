<?php
/**
 * Indexable abstract class
 *
 * @since  2.6
 * @package elasticpress
 */

namespace ElasticPress;

use ElasticPress\Elasticsearch as Elasticsearch;

/**
 * An indexable is essentially a document type that can be indexed
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

	public function delete( $post_id, $blocking = true  ) {
		return Elasticsearch::factory()->delete_document( $this->get_index_name(), $this->indexable_type, $post_id, $blocking );
	}

	public function get( $post_id ) {
		return Elasticsearch::factory()->get_document( $this->get_index_name(), $this->indexable_type, $post_id );
	}

	public function delete_index( $blog_id = null ) {
		return Elasticsearch::factory()->delete_index( $this->get_index_name( $blog_id ) );
	}

	public function index( $document, $blocking = false ) {
		$document = apply_filters( 'ep_pre_index_' . $this->indexable_type, $document );

		$return = Elasticsearch::factory()->index_document( $this->get_index_name(), $this->indexable_type, $document, $blocking );

		do_action( 'ep_after_index_' . $this->indexable_type, $document, $return );

		return $return;
	}

	public function bulk_index( $body ) {
		return Elasticsearch::factory()->bulk_index( $this->get_index_name(), $this->indexable_type, $body );
	}

	public function query( $args, $query_args, $scope = 'current' ) {
		$index = null;

		if ( 'all' === $scope ) {
			$index = $this->get_network_alias();
		} elseif ( is_numeric( $scope ) ) {
			$index = $this->get_index_name( (int) $scope );
		} elseif ( is_array( $scope ) ) {
			$index = [];

			foreach ( $scope as $site_id ) {
				$index[] = $this->get_index_name( $site_id );
			}

			$index = implode( ',', $index );
		} else {
			$index = $this->get_index_name();
		}

		return Elasticsearch::factory()->query( $index, $this->indexable_type, $args, $query_args );
	}

	abstract function put_mapping();
}
