<?php
/**
 * Extended Search Result Term type
 *
 * @since 5.0.0
 * @package elasticpress
 */

namespace ElasticPress\Feature\ExtendedSearchResults\Types;

use ElasticPress\Feature\ExtendedSearchResults\EsrType;
use ElasticPress\Indexables;

/**
 * Extended Search Result Term type class
 */
class Term extends EsrType {
	/**
	 * Indexable related to this search result type
	 *
	 * @var null|Indexable
	 */
	public $indexable = null;

	/**
	 * Class constructor
	 */
	public function __construct() {
		$this->indexable = new \ElasticPress\Indexable\ExtendedSearchResults\Term\Term();
		Indexables::factory()->register( $this->indexable, false );
	}

	/**
	 * Run on every page load for feature to set itself up
	 */
	public function setup() {
		Indexables::factory()->activate( $this->indexable->slug );

		add_filter( 'post_link', [ $this, 'post_link' ], 10, 3 );
		add_filter( 'post_thumbnail_id', [ $this, 'post_thumbnail_id' ], 10, 2 );
		add_filter( 'ep_searchable_post_types', [ $this, 'add_searchable_post_type' ] );
	}

	/**
	 * Change the link of the search result.
	 *
	 * @param string   $permalink Current permalink
	 * @param \WP_Post $post      The "post" (or the term being displayed in the posts list)
	 * @return string
	 */
	public function post_link( string $permalink, \WP_Post $post ) : string {
		if ( 'ep_esr_term' !== $post->post_type ) {
			return $permalink;
		}

		return (string) get_term_link( $post->ID );
	}

	/**
	 * Change the thumbnail ID of the search result
	 *
	 * @param int|false        $thumbnail_id Post thumbnail ID or false if the post does not exist.
	 * @param int|WP_Post|null $post         Post ID or WP_Post object. Default is global `$post`.
	 * @return int|false
	 */
	public function post_thumbnail_id( $thumbnail_id, $post ) {
		if ( ! is_a( $post, '\WP_Post' ) || 'ep_esr_term' !== $post->post_type ) {
			return $thumbnail_id;
		}

		return false;
	}

	/**
	 * Add the extended search result type as searchable
	 *
	 * @param array $post_types Current post types list
	 * @return array
	 */
	public function add_searchable_post_type( array $post_types ) : array {
		$post_types['ep_esr_term'] = 'ep_esr_term';

		return $post_types;
	}
}
