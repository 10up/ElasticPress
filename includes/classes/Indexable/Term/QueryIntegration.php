<?php
/**
 * Integrate with WP_Term_Query
 *
 * @since   3.1
 * @package elasticpress
 */

namespace ElasticPress\Indexable\Term;

use \WP_Term_Query;
use ElasticPress\Indexables;
use ElasticPress\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Query integration class
 */
class QueryIntegration {

	/**
	 * Checks to see if we should be integrating and if so, sets up the appropriate actions and filters.
	 *
	 * @param string $indexable_slug Indexable slug. Optional.
	 *
	 * @since 3.1
	 * @since 3.6.0 Added $indexable_slug
	 */
	public function __construct( $indexable_slug = 'term' ) {
		/**
		 * Filter whether to enable query integration during indexing
		 *
		 * @since 4.5.2
		 * @hook ep_enable_query_integration_during_indexing
		 *
		 * @param {bool} $enable To allow query integration during indexing
		 * @param {string} $indexable_slug Indexable slug
		 * @return {bool} New value
		 */
		$allow_query_integration_during_indexing = apply_filters( 'ep_enable_query_integration_during_indexing', false, $indexable_slug );

		// Ensure that we are currently allowing ElasticPress to override the normal WP_Query
		// Indexable->is_full_reindexing() is not available at this point yet, so using the IndexHelper version of it.
		if ( \ElasticPress\IndexHelper::factory()->is_full_reindexing( $indexable_slug, get_current_blog_id() ) && ! $allow_query_integration_during_indexing ) {
			return;
		}

		// Add header
		add_action( 'pre_get_terms', array( $this, 'action_pre_get_terms' ), 5 );

		// Filter term query
		add_filter( 'terms_pre_query', [ $this, 'maybe_filter_query' ], 10, 2 );
	}

	/**
	 * Add EP header
	 *
	 * @param  WP_Term_Query $query Query object
	 * @since  3.1
	 * @return void
	 */
	public function action_pre_get_terms( WP_Term_Query $query ) {
		if ( ! Indexables::factory()->get( 'term' )->elasticpress_enabled( $query ) || apply_filters( 'ep_skip_term_query_integration', false, $query ) ) {
			return;
		}

		if ( ! headers_sent() ) {
			/**
			 * Manually setting a header as $wp_query isn't yet initialized
			 * when we call: add_filter('wp_headers', 'filter_wp_headers');
			 */
			header( 'X-ElasticPress-Term-Search: true' );
		}
	}

	/**
	 * If WP_Term_Query meets certain conditions, query results from ES
	 *
	 * @param  array         $results Term results.
	 * @param  WP_Term_Query $query   Current query.
	 * @since  3.1
	 * @return array
	 */
	public function maybe_filter_query( $results, WP_Term_Query $query ) {
		$indexable = Indexables::factory()->get( 'term' );

		if ( ! $indexable->elasticpress_enabled( $query ) || apply_filters( 'ep_skip_term_query_integration', false, $query ) ) {
			return $results;
		}

		if ( ! $this->is_searchable( $query ) ) {
			return $results;
		}

		$new_terms = apply_filters( 'ep_wp_query_cached_terms', null, $query );

		if ( null === $new_terms ) {
			$formatted_args = $indexable->format_args( $query->query_vars );

			$scope = 'current';

			$site__in     = [];
			$site__not_in = [];

			if ( ! empty( $query->query_vars['sites'] ) ) {
				_deprecated_argument( __FUNCTION__, '4.4.0', esc_html__( 'sites is deprecated. Use site__in instead.', 'elasticpress' ) );
			}

			if ( ! empty( $query->query_vars['site__in'] ) || ! empty( $query->query_vars['sites'] ) ) {
				$site__in = ! empty( $query->query_vars['site__in'] ) ? (array) $query->query_vars['site__in'] : (array) $query->query_vars['sites'];

				if ( in_array( 'all', $site__in, true ) ) {
					$scope = 'all';
				} elseif ( in_array( 'current', $site__in, true ) ) {
					$site__in = (array) get_current_blog_id();
				}
			}

			if ( ! empty( $query->query_vars['site__not_in'] ) ) {
				$site__not_in = (array) $query->query_vars['site__not_in'];
			}

			/**
			 * Filter search scope
			 *
			 * @since 3.1
			 *
			 * @param mixed $scope The search scope. Accepts `all` (string), a single
			 *                     site id (int or string), or an array of site ids (array).
			 */
			$scope = apply_filters( 'ep_term_search_scope', $scope );

			if ( ! defined( 'EP_IS_NETWORK' ) || ! EP_IS_NETWORK ) {
				$scope = 'current';
			}

			$index = null;

			if ( 'all' === $scope ) {
				$index = $indexable->get_network_alias();
			} elseif ( ! empty( $site__in ) ) {
				$index = [];

				foreach ( $site__in as $site_id ) {
					$index[] = $indexable->get_index_name( $site_id );
				}

				$index = implode( ',', $index );
			} elseif ( ! empty( $site__not_in ) ) {

				$sites = \get_sites(
					array(
						'fields'       => 'ids',
						'site__not_in' => $site__not_in,
					)
				);
				foreach ( $sites as $site_id ) {
					if ( ! Utils\is_site_indexable( $site_id ) ) {
						continue;
					}
					$index[] = Indexables::factory()->get( 'term' )->get_index_name( $site_id );
				}
				$index = implode( ',', $index );
			}

			$ep_query = $indexable->query_es( $formatted_args, $query->query_vars, $index );

			if ( false === $ep_query ) {
				$query->elasticsearch_success = false;
				return $results;
			}

			/**
			 * Elasticsearch 5 will return found_documents as a number,
			 * ES 7+ will return it as an object with `value`. If any of that is found,
			 * fallback to a simple count of returned documents.
			 *
			 * @since 3.6.3
			 */
			if ( is_integer( $ep_query['found_documents'] ) ) {
				$query->found_terms = $ep_query['found_documents'];
			} elseif ( is_array( $ep_query['found_documents'] ) && isset( $ep_query['found_documents']['value'] ) ) {
				$query->found_terms = $ep_query['found_documents']['value'];
			} else {
				$query->found_terms = count( $ep_query['documents'] );
			}

			$query->elasticsearch_success = true;

			// Determine how we should format the results from ES based on the fields parameter.
			$fields = $query->query_vars['fields'];

			$new_terms = [];

			switch ( $fields ) {
				case 'all_with_object_id':
					$new_terms = $this->format_hits_as_terms( $ep_query['documents'], $new_terms, $query->query_vars );
					break;

				case 'count':
					$new_terms = count( $ep_query['documents'] );
					break;

				case 'ids':
					$new_terms = $this->format_hits_as_ids( $ep_query['documents'], $new_terms );
					break;

				case 'id=>name':
					$new_terms = $this->format_hits_as_id_name( $ep_query['documents'], $new_terms );
					break;

				case 'id=>parent':
					$new_terms = $this->format_hits_as_id_parent( $ep_query['documents'], $new_terms );
					break;

				case 'id=>slug':
					$new_terms = $this->format_hits_as_id_slug( $ep_query['documents'], $new_terms );
					break;

				case 'names':
					$new_terms = $this->format_hits_as_names( $ep_query['documents'], $new_terms );
					break;

				case 'tt_ids':
					$new_terms = $this->format_hits_as_ids( $ep_query['documents'], $new_terms, 'term_taxonomy_id' );
					break;

				default:
					$new_terms = $this->format_hits_as_terms( $ep_query['documents'], $new_terms, $query->query_vars );
					break;
			}

			if ( ! empty( $query->query_vars['count'] ) ) {
				$new_terms = count( $ep_query['documents'] );
			}
		}

		return $new_terms;
	}

	/**
	 * Format the ES hits/results as term objects.
	 *
	 * @param  array $terms The terms that should be formatted.
	 * @param  array $new_terms Array of terms from cache.
	 * @param  array $query_vars Query variables.
	 * @since  3.1
	 * @return array
	 */
	protected function format_hits_as_terms( $terms, $new_terms, $query_vars ) {
		foreach ( $terms as $term_array ) {
			$term = new \stdClass();

			$term->ID      = $term_array['term_id'];
			$term->site_id = get_current_blog_id();

			if ( ! empty( $term_array['site_id'] ) ) {
				$term->site_id = $term_array['site_id'];
			}

			$term_return_args = apply_filters(
				'ep_search_term_return_args',
				array(
					'term_id',
					'name',
					'slug',
					'term_group',
					'term_taxonomy_id',
					'taxonomy',
					'description',
					'parent',
					'count',
					'meta',
				)
			);

			foreach ( $term_return_args as $key ) {
				if ( isset( $term_array[ $key ] ) ) {
					if ( 'count' === $key && ! empty( $query_vars['pad_counts'] ) ) {
						$term_array[ $key ] += $term_array['hierarchy']['children']['count'];
					}

					$term->$key = $term_array[ $key ];
				}
			}

			$term->elasticsearch = true; // Super useful for debugging.

			$term = new \WP_Term( $term ); // Necessary for WordPress actions that expect WP_Term as the object type.

			if ( $term ) {
				$new_terms[] = $term;
			}
		}

		return $new_terms;
	}

	/**
	 * Format the ES hits/results as an array of ids.
	 *
	 * @param  array  $terms The terms that should be formatted.
	 * @param  array  $new_terms Array of terms from cache.
	 * @param  string $type Type of ID to return. Default 'term_id'.
	 * @since  3.1
	 * @return array
	 */
	protected function format_hits_as_ids( $terms, $new_terms, $type = 'term_id' ) {
		foreach ( $terms as $term_array ) {
			$new_terms[] = 'term_id' === $type ? $term_array['term_id'] : $term_array['term_taxonomy_id'];
		}

		return $new_terms;
	}

	/**
	 * Format the ES hits/results as an array of term ID and term name.
	 *
	 * @param  array $terms The terms that should be formatted.
	 * @param  array $new_terms Array of terms from cache.
	 * @since  3.1
	 * @return array Returns an associative array with ids as keys, term names as values
	 */
	protected function format_hits_as_id_name( $terms, $new_terms ) {
		foreach ( $terms as $term_array ) {
			$new_terms[ $term_array['term_id'] ] = $term_array['name'];
		}

		return $new_terms;
	}

	/**
	 * Format the ES hits/results as an array of term ID and parent ID.
	 *
	 * @param  array $terms The terms that should be formatted.
	 * @param  array $new_terms Array of terms from cache.
	 * @since  3.1
	 * @return array Returns an associative array with ids as keys, parent term IDs as values
	 */
	protected function format_hits_as_id_parent( $terms, $new_terms ) {
		foreach ( $terms as $term_array ) {
			$new_terms[ $term_array['term_id'] ] = $term_array['parent'];
		}

		return $new_terms;
	}

	/**
	 * Format the ES hits/results as an array of term ID and term slug.
	 *
	 * @param  array $terms The terms that should be formatted.
	 * @param  array $new_terms Array of terms from cache.
	 * @since  3.1
	 * @return array Returns an associative array with ids as keys, term slugs as values
	 */
	protected function format_hits_as_id_slug( $terms, $new_terms ) {
		foreach ( $terms as $term_array ) {
			$new_terms[ $term_array['term_id'] ] = $term_array['slug'];
		}

		return $new_terms;
	}

	/**
	 * Format the ES hits/results as an array of term names.
	 *
	 * @param array $terms The terms that should be formatted.
	 * @param array $new_terms Array of terms from cache.
	 * @since  3.1
	 * @return array Returns an array of term names.
	 */
	protected function format_hits_as_names( $terms, $new_terms ) {
		foreach ( $terms as $term_array ) {
			$new_terms[] = $term_array['name'];
		}

		return $new_terms;
	}

	/**
	 * Determine whether ES should be used for the query if all taxonomies are indexable.
	 *
	 * @param \WP_Term_Query $query The WP_Term_Query object.
	 * @return boolean
	 */
	protected function is_searchable( $query ) {

		$taxonomies = $query->query_vars['taxonomy'];
		if ( ! $taxonomies ) {
			return true;
		}

		$indexable_taxonomies = Indexables::factory()->get( 'term' )->get_indexable_taxonomies();
		return empty( array_diff( $taxonomies, $indexable_taxonomies ) );
	}

}
