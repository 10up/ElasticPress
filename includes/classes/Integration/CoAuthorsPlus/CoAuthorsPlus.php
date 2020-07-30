<?php
/**
 * ElasticPress Co-Author Plus integration.
 *
 * @package elasticpress
 */

namespace ElasticPress\Integration\CoAuthorsPlus;

use ElasticPress\Integration as Integration;

/**
 * ElasticPress Co-Author Plus integration.
 */
class CoAuthorsPlus extends Integration {
	/**
	 * Setup actions and filters.
	 */
	public function setup() {
		add_filter( 'ep_before_format_post_args', array( $this, 'update_query_var' ) );
		add_filter( 'ep_sync_taxonomies', array( $this, 'include_author_term' ) );
	}

	/**
	 * Determine if this integration is active.
	 * Mostly check if the required plugins are activated.
	 */
	public function is_active() {
		global $coauthors_plus;

		return is_a( $coauthors_plus, 'CoAuthors_Plus' );
	}

	/**
	 * Convert author query to tax query for guest author.
	 *
	 * @param array $args WP Query vars.
	 */
	public function update_query_var( $args ) {
		if ( empty( $args['author_name'] ) ) {
			return $args;
		}

		global $coauthors_plus;

		$guest_author = $coauthors_plus->guest_authors->get_guest_author_by( 'login', $args['author_name'] );

		if ( $guest_author ) {

			$args['tax_query'][] = array(
				'taxonomy' => 'author',
				'terms'    => array( 'cap-' . $args['author_name'] ),
				'field'    => 'slug',
			);

			$term = $coauthors_plus->get_author_term( $guest_author );

			$args['tax_query'][] = array(
				'taxonomy' => 'author',
				'terms'    => array( $term->term_id ),
				'field'    => 'term_id',
			);

			unset( $args['author_name'] );
		}

		return $args;
	}

	/**
	 * Include author taxonomy when indexing posts.
	 *
	 * @param array $taxonomies Selected taxonomies.
	 */
	public function include_author_term( $taxonomies ) {
		$taxonomies[] = get_taxonomy( 'author' );
		return $taxonomies;
	}

}
