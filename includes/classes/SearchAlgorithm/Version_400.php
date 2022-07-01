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
							'query'     => $search_term,
							'fields'    => $search_fields,
							'operator'  => 'and',
							'boost'     => ( 'post' === $indexable_slug ) ?
								/** These filters are documented in /includes/classes/SearchAlgorithm/Basic.php */
								apply_filters( 'ep_match_boost', 1, $search_fields, $query_vars ) :
								apply_filters( "ep_{$indexable_slug}_match_boost", 1, $search_fields, $query_vars ),
							'fuzziness' => ( 'post' === $indexable_slug ) ?
								/**
								 * Filter fuzziness for post match query
								 *
								 * This filter exists to keep backwards-compatibility. Newer implementations should use `ep_post_match_fuzziness`.
								 *
								 * @hook ep_match_fuzziness
								 * @since 4.0.0
								 * @param {string|int} $fuzziness     Fuzziness
								 * @param {array}      $search_fields Search fields
								 * @param {array}      $query_vars    Query variables
								 * @return {string} New fuzziness
								 */
								apply_filters( 'ep_match_fuzziness', 'auto', $search_fields, $query_vars ) :
								/**
								 * Filter fuzziness for match query
								 *
								 * @hook ep_{$indexable_slug}_match_fuzziness
								 * @since 4.3.0
								 * @param {string|int} $fuzziness      Fuzziness
								 * @param {array}      $search_fields  Search fields
								 * @param {array}      $query_vars     Query variables
								 * @return {string} New fuzziness
								 */
								apply_filters( "ep_{$indexable_slug}_match_fuzziness", 'auto', $search_fields, $query_vars ),
						],
					],
					[
						'multi_match' => [
							'query'       => $search_term,
							'type'        => 'cross_fields',
							'fields'      => $search_fields,
							'boost'       => ( 'post' === $indexable_slug ) ?
								/**
								 * Filter boost for post match cross_fields query
								 *
								 * This filter exists to keep backwards-compatibility. Newer implementations should use `ep_post_match_fuzziness`.
								 *
								 * @hook ep_match_cross_fields_boost
								 * @since 4.0.0
								 * @param {int}   $boost         Boost
								 * @param {array} $search_fields Search fields
								 * @param {array} $query_vars    Query variables
								 * @return  {int} New boost
								 */
								apply_filters( 'ep_match_cross_fields_boost', 1, $search_fields, $query_vars ) :
								/**
								 * Filter boost for post match cross_fields query
								 *
								 * This filter exists to keep backwards-compatibility. Newer implementations should use `ep_post_match_cross_fields_boost`.
								 *
								 * @hook ep_{$indexable_slug}_match_cross_fields_boost
								 * @since 4.3.0
								 * @param {int}   $boost         Boost
								 * @param {array} $search_fields Search fields
								 * @param {array} $query_vars    Query variables
								 * @return  {int} New boost
								 */
								apply_filters( "ep_{$indexable_slug}_match_cross_fields_boost", 1, $search_fields, $query_vars ),
							'analyzer'    => 'standard',
							'tie_breaker' => 0.5,
							'operator'    => 'and',
						],
					],
				],
			],
		];

		return $query;
	}
}
