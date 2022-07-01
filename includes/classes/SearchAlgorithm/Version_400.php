<?php
/**
 * EP version 4.0.0 search algorithm
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
 * EP version 4.0.0 search algorithm class.
 */
class Version_400 extends \ElasticPress\SearchAlgorithm {

	/**
	 * Return the Elasticsearch `query` clause.
	 *
	 * @param string $indexable_slug Indexable slug
	 * @param string $search_term    Search term(s)
	 * @param array  $search_fields  Search fields
	 * @param array  $query_vars     Query vars
	 * @return array ES `query`
	 */
	public function get_query( string $indexable_slug, string $search_term, array $search_fields, array $query_vars ) : array {
		$query = [
			'bool' => [
				'should' => [
					[
						'multi_match' => [
							'query'  => $search_term,
							'type'   => 'phrase',
							'fields' => $search_fields,
							/**
							 * Filter boost for post match phrase query
							 *
							 * @hook ep_match_phrase_boost
							 * @param  {int} $boost Phrase boost
							 * @param {array} $prepared_search_fields Search fields
							 * @param {array} $query_vars Query variables
							 * @return  {int} New phrase boost
							 */
							'boost'  => apply_filters( 'ep_match_phrase_boost', 3, $search_fields, $query_vars ),
						],
					],
					[
						'multi_match' => [
							'query'     => $search_term,
							'fields'    => $search_fields,
							/**
							 * Filter boost for post match query
							 *
							 * @hook ep_match_boost
							 * @param  {int} $boost Boost
							 * @param {array} $prepared_search_fields Search fields
							 * @param {array} $query_vars Query variables
							 * @return  {int} New boost
							 */
							'boost'     => apply_filters( 'ep_match_boost', 1, $search_fields, $query_vars ),
							/**
							 * Filter fuzziness for post match query
							 *
							 * @hook ep_match_fuzziness
							 * @since 4.0.0
							 * @param {string|int} $fuzziness Fuzziness
							 * @param {array} $prepared_search_fields Search fields
							 * @param {array} $query_vars Query variables
							 * @return  {string} New boost
							 */
							'fuzziness' => apply_filters( 'ep_match_fuzziness', 'auto', $search_fields, $query_vars ),
							'operator'  => 'and',
						],
					],
					[
						'multi_match' => [
							'query'       => $search_term,
							'type'        => 'cross_fields',
							'fields'      => $search_fields,
							/**
							 * Filter boost for post match query
							 *
							 * @hook ep_match_cross_fields_boost
							 * @since 4.0.0
							 * @param  {int} $boost Boost
							 * @param {array} $prepared_search_fields Search fields
							 * @param {array} $query_vars Query variables
							 * @return  {int} New boost
							 */
							'boost'       => apply_filters( 'ep_match_cross_fields_boost', 1, $search_fields, $query_vars ),
							'analyzer'    => 'standard',
							'tie_breaker' => 0.5,
							'operator'    => 'and',
						],
					],
				],
			],
		];

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

		return $query;
	}
}
