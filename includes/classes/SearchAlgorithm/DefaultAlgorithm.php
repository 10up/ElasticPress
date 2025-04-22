<?php
/**
 * Default search algorithm
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
 * Default search algorithm class.
 */
class DefaultAlgorithm extends \ElasticPress\SearchAlgorithm {
	/**
	 * Search algorithm slug.
	 *
	 * @return string
	 */
	public function get_slug(): string {
		return 'default';
	}

	/**
	 * Search algorithm name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return esc_html__( 'Default', 'elasticpress' );
	}

	/**
	 * Search algorithm description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return esc_html__( 'Use a fuzzy match approach which includes results that have misspellings, and also includes matches on only some of the words in the search.', 'elasticpress' );
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
							'boost'  => apply_filters( "ep_{$indexable_slug}_match_phrase_boost", 4, $search_fields, $query_vars ),
						],
					],
					[
						'multi_match' => [
							'query'     => $search_term,
							'fields'    => $search_fields,
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
							'boost'     => apply_filters( "ep_{$indexable_slug}_match_boost", 2, $search_fields, $query_vars ),
							'fuzziness' => 0,
							'operator'  => 'and',
						],
					],
					[
						'multi_match' => [
							'fields'    => $search_fields,
							'query'     => $search_term,
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
							'fuzziness' => apply_filters( "ep_{$indexable_slug}_fuzziness_arg", 1, $search_fields, $query_vars ),
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
		$query['bool']['should'][0]['multi_match']['boost'] = apply_filters_deprecated(
			'ep_match_phrase_boost',
			[ $query['bool']['should'][0]['multi_match']['boost'], $search_fields, $query_vars ],
			'4.3.0',
			'ep_post_match_phrase_boost'
		);

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
		$query['bool']['should'][1]['multi_match']['boost'] = apply_filters_deprecated(
			'ep_match_boost',
			[ $query['bool']['should'][1]['multi_match']['boost'], $search_fields, $query_vars ],
			'4.3.0',
			'ep_post_match_boost'
		);

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
		$query['bool']['should'][2]['multi_match']['fuzziness'] = apply_filters_deprecated(
			'ep_fuzziness_arg',
			[ $query['bool']['should'][2]['multi_match']['fuzziness'], $search_fields, $query_vars ],
			'4.3.0',
			'ep_post_fuzziness_arg'
		);

		return $query;
	}
}
