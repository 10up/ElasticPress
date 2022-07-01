<?php
/**
 * EP version 3.5.0 search algorithm
 *
 * @since  4.3.0
 * @package elasticpress
 */

namespace ElasticPress\SearchAlgorithm;

if ( ! defined( 'ABSPATH' ) ) {
	// @codeCoverageIgnoreStart
	exit; // Exit if accessed directly.
	// @codeCoverageIgnoreEnd
}

/**
 * EP version 3.5.0 search algorithm class.
 */
class Version_350 extends \ElasticPress\SearchAlgorithm {

	/**
	 * Return the Elasticsearch `query` clause.
	 *
	 * @param string $indexable_slug Indexable slug
	 * @param string $search_term    Search term(s)
	 * @param array  $search_fields  Search fields
	 * @param array  $query_vars     Query vars
	 * @return array ES `query`
	 */
	protected function get_raw_query( string $indexable_slug, string $search_term, array $search_fields, array $query_vars ) : array {
		$query = [
			'bool' => [
				'should' => [
					[
						'multi_match' => [
							'query'  => $search_term,
							'type'   => 'phrase',
							'fields' => $search_fields,
							'boost'  => ( 'post' === $indexable_slug ) ?
								/** These filters are documented in /includes/classes/SearchAlgorithm/Basic.php */
								apply_filters( 'ep_match_phrase_boost', 3, $search_fields, $query_vars ) :
								apply_filters( "ep_{$indexable_slug}_match_phrase_boost", 3, $search_fields, $query_vars ),
						],
					],
					[
						'multi_match' => [
							'query'  => $search_term,
							'fields' => $search_fields,
							'type'   => 'phrase',
							'slop'   => 5,
						],
					],
				],
			],
		];

		return $query;
	}
}
