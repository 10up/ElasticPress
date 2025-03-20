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
	 * Search algorithm slug.
	 *
	 * @return string
	 */
	public function get_slug(): string {
		return '4.0';
	}

	/**
	 * Search algorithm name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return esc_html__( 'Version 4.0', 'elasticpress' );
	}

	/**
	 * Search algorithm description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return esc_html__( 'Search for all search terms in one field first, then prioritize them over search terms matched in different fields.', 'elasticpress' );
	}

	/**
	 * Return the Elasticsearch `query` clause.
	 *
	 * @param string $indexable_slug Indexable slug
	 * @param string $search_term    Search term(s)
	 * @param array  $search_fields  Search fields
	 * @param array  $query_vars     Query vars
	 * @return array ES `query`
	 */
	protected function get_raw_query( string $indexable_slug, string $search_term, array $search_fields, array $query_vars ): array {
		$query = [
			'bool' => [
				'should' => [
					[
						'multi_match' => [
							'query'  => $search_term,
							'type'   => 'phrase',
							'fields' => $search_fields,
							/** This filter is documented in /includes/classes/SearchAlgorithm/Basic.php */
							'boost'  => apply_filters( "ep_{$indexable_slug}_match_phrase_boost", 3, $search_fields, $query_vars ),
						],
					],
					[
						'multi_match' => [
							'query'     => $search_term,
							'fields'    => $search_fields,
							'operator'  => 'and',
							/** This filter is documented in /includes/classes/SearchAlgorithm/Basic.php */
							'boost'     => apply_filters( "ep_{$indexable_slug}_match_boost", 1, $search_fields, $query_vars ),
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
							'fuzziness' => apply_filters( "ep_{$indexable_slug}_match_fuzziness", 'auto', $search_fields, $query_vars ),
						],
					],
					[
						'multi_match' => [
							'query'       => $search_term,
							'type'        => 'cross_fields',
							'fields'      => $search_fields,
							/**
							 * Filter boost for match cross_fields query
							 *
							 * @hook ep_{$indexable_slug}_match_cross_fields_boost
							 * @since 4.3.0
							 * @param {int}   $boost         Boost
							 * @param {array} $search_fields Search fields
							 * @param {array} $query_vars    Query variables
							 * @return  {int} New boost
							 */
							'boost'       => apply_filters( "ep_{$indexable_slug}_match_cross_fields_boost", 1, $search_fields, $query_vars ),
							'analyzer'    => 'standard',
							'tie_breaker' => 0.5,
							'operator'    => 'and',
						],
					],
				],
			],
		];

		$query = $this->apply_legacy_filters( $query, $indexable_slug, $search_fields, $query_vars );

		return $query;
	}

	/**
	 * Apply legacy filters.
	 *
	 * @param array  $query          ES `query`
	 * @param string $indexable_slug Indexable slug
	 * @param array  $search_fields  Search term(s)
	 * @param array  $query_vars     Query vars
	 * @return array ES `query`
	 */
	protected function apply_legacy_filters( array $query, string $indexable_slug, array $search_fields, array $query_vars ): array {
		if ( 'post' !== $indexable_slug ) {
			return $query;
		}

		/** This filter is documented in /includes/classes/SearchAlgorithm/Basic.php */
		$query['bool']['should'][0]['multi_match']['boost'] = apply_filters_deprecated(
			'ep_match_phrase_boost',
			[ $query['bool']['should'][0]['multi_match']['boost'], $search_fields, $query_vars ],
			'4.3.0',
			'ep_post_match_phrase_boost'
		);

		/** This filter is documented in /includes/classes/SearchAlgorithm/Basic.php */
		$query['bool']['should'][1]['multi_match']['boost'] = apply_filters_deprecated(
			'ep_match_boost',
			[ $query['bool']['should'][1]['multi_match']['boost'], $search_fields, $query_vars ],
			'4.3.0',
			'ep_post_match_boost'
		);

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
		$query['bool']['should'][1]['multi_match']['fuzziness'] = apply_filters_deprecated(
			'ep_match_fuzziness',
			[ $query['bool']['should'][1]['multi_match']['fuzziness'], $search_fields, $query_vars ],
			'4.3.0',
			'ep_post_match_fuzziness'
		);

		/**
		 * Filter boost for post match cross_fields query
		 *
		 * This filter exists to keep backwards-compatibility. Newer implementations should use `ep_post_match_cross_fields_boost`.
		 *
		 * @hook ep_{$indexable_slug}_match_cross_fields_boost
		 * @since 4.0.0
		 * @param {int}   $boost         Boost
		 * @param {array} $search_fields Search fields
		 * @param {array} $query_vars    Query variables
		 * @return  {int} New boost
		 */
		$query['bool']['should'][2]['multi_match']['boost'] = apply_filters_deprecated(
			'ep_match_cross_fields_boost',
			[ $query['bool']['should'][2]['multi_match']['boost'], $search_fields, $query_vars ],
			'4.3.0',
			'ep_post_match_cross_fields_boost'
		);

		return $query;
	}
}
