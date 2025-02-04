<?php
/**
 * SearchAlgorithm class.
 *
 * All search algorithms extend this class.
 *
 * @since  4.3.0
 * @package elasticpress
 */

namespace ElasticPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * SearchAlgorithm abstract class
 */
abstract class SearchAlgorithm {
	/**
	 * Return the Search Algorithm slug.
	 *
	 * @return string
	 */
	abstract public function get_slug(): string;

	/**
	 * Return the Search Algorithm human readable name.
	 *
	 * @return string
	 */
	abstract public function get_name(): string;

	/**
	 * Return the Search Algorithm description.
	 *
	 * @return string
	 */
	abstract public function get_description(): string;

	/**
	 * Return the Elasticsearch `query` clause.
	 *
	 * @param string $indexable_slug Indexable slug
	 * @param string $search_term    Search term(s)
	 * @param array  $search_fields  Search fields
	 * @param array  $query_vars     Query vars
	 * @return array ES `query`
	 */
	abstract protected function get_raw_query( string $indexable_slug, string $search_term, array $search_fields, array $query_vars ): array;

	/**
	 * Wrapper for the `get_raw_query`, making sure the `ep_{$indexable_slug}_formatted_args_query` filter is applied.
	 *
	 * @param string $indexable_slug Indexable slug
	 * @param string $search_term    Search term(s)
	 * @param array  $search_fields  Search fields
	 * @param array  $query_vars     Query vars
	 * @return array ES `query`
	 */
	public function get_query( string $indexable_slug, string $search_term, array $search_fields, array $query_vars ): array {
		$query = $this->get_raw_query( $indexable_slug, $search_term, $search_fields, $query_vars );

		/**
		 * Filter formatted Elasticsearch query (only contains query part)
		 *
		 * @hook ep_{$indexable_slug}_formatted_args_query
		 * @param {array}  $query         Current query
		 * @param {array}  $query_vars    Query variables
		 * @param {string} $search_text   Search text
		 * @param {array}  $search_fields Search fields
		 * @return {array} New query
		 *
		 * @since 4.3.0
		 */
		$query = apply_filters(
			"ep_{$indexable_slug}_formatted_args_query",
			$query,
			$query_vars,
			$search_term,
			$search_fields
		);

		// Backwards-compatibility.
		if ( 'post' === $indexable_slug ) {
			/**
			 * Filter formatted Elasticsearch query for posts.
			 *
			 * This filter exists to keep backwards-compatibility. Newer implementations should use `ep_post_formatted_args_query`.
			 *
			 * @hook ep_formatted_args_query
			 * @param {array}  $query         Current query
			 * @param {array}  $query_vars    Query variables
			 * @param {string} $search_text   Search text
			 * @param {array}  $search_fields Search fields
			 * @return {array} New query
			 *
			 * @since 3.5.5 $search_text and $search_fields parameters added.
			 */
			$query = apply_filters_deprecated(
				'ep_formatted_args_query',
				[ $query, $query_vars, $search_term, $search_fields ],
				'4.3.0',
				'ep_post_formatted_args_query'
			);
		}

		return $query;
	}
}
