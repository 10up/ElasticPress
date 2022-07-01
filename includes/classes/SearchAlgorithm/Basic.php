<?php
/**
 * Basic search algorithm
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
 * Basic search algorithm class.
 */
class Basic extends \ElasticPress\SearchAlgorithm {

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
								/**
								 * Filter boost for post match phrase query.
								 *
								 * This filter exists to keep backwards-compatibility. Newer implementations should use `ep_post_match_phrase_boost`.
								 *
								 * @hook ep_match_phrase_boost
								 * @param {int}   $boost         Phrase boost
								 * @param {array} $search_fields Search fields
								 * @param {array} $query_vars    Query variables
								 * @return {int} New boost amount
								 */
								apply_filters( 'ep_match_phrase_boost', 4, $search_fields, $query_vars ) :
								/**
								 * Filter match phrase boost amount
								 *
								 * @since 4.3.0
								 * @hook ep_{$indexable_slug}_match_phrase_boost
								 * @param {int}   $boost         Phrase boost
								 * @param {array} $search_fields Search fields
								 * @param {array} $query_vars    Query variables
								 * @return {int} New boost amount
								 */
								apply_filters( "ep_{$indexable_slug}_match_phrase_boost", 4, $search_fields, $query_vars ),
						],
					],
					[
						'multi_match' => [
							'query'     => $search_term,
							'fields'    => $search_fields,
							'boost'     => ( 'post' === $indexable_slug ) ?
								/**
								 * Filter boost for post match query
								 *
								 * This filter exists to keep backwards-compatibility. Newer implementations should use `ep_post_match_boost`.
								 *
								 * @hook ep_match_boost
								 * @param {int}   $boost         Boost
								 * @param {array} $search_fields Search fields
								 * @param {array} $query_vars    Query variables
								 * @return  {int} New boost
								 */
								apply_filters( 'ep_match_boost', 2, $search_fields, $query_vars ) :
								/**
								 * Filter match boost amount
								 *
								 * @since 4.3.0
								 * @hook ep_{$indexable_slug}_match_boost
								 * @param {int}   $boost         Boost
								 * @param {array} $search_fields Search fields
								 * @param {array} $query_vars    Query variables
								 * @return  {int} New boost
								 */
								apply_filters( "ep_{$indexable_slug}_match_boost", 2, $search_fields, $query_vars ),
							'fuzziness' => 0,
							'operator'  => 'and',
						],
					],
					[
						'multi_match' => [
							'fields'    => $search_fields,
							'query'     => $search_term,
							'fuzziness' => ( 'post' === $indexable_slug ) ?
								/**
								 * Filter fuzziness for post query
								 *
								 * This filter exists to keep backwards-compatibility. Newer implementations should use `ep_post_fuzziness_arg`.
								 *
								 * @hook ep_fuzziness_arg
								 * @param {int}   $fuzziness     Fuzziness
								 * @param {array} $search_fields Search fields
								 * @param {array} $query_vars    Query variables
								 * @return  {int} New fuzziness
								 */
								apply_filters( 'ep_fuzziness_arg', 1, $search_fields, $query_vars ) :
								/**
								 * Filter fuzziness amount
								 *
								 * @since 4.3.0
								 * @hook ep_{$indexable_slug}_fuzziness_arg
								 * @param {int}   $fuzziness     Fuzziness
								 * @param {array} $search_fields Search fields
								 * @param {array} $query_vars    Query variables
								 * @return  {int} New fuzziness
								 */
								apply_filters( "ep_{$indexable_slug}_fuzziness_arg", 1, $search_fields, $query_vars ),
						],
					],
				],
			],
		];

		return $query;
	}
}
