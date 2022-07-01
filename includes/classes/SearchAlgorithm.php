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
	 * Return the Elasticsearch `query` clause.
	 *
	 * @param string $indexable_slug Indexable slug
	 * @param string $search_term    Search term(s)
	 * @param array  $search_fields  Search fields
	 * @param array  $query_vars     Query vars
	 * @return array ES `query`
	 */
	abstract public function get_query( string $indexable_slug, string $search_term, array $search_fields, array $query_vars ) : array;
}
