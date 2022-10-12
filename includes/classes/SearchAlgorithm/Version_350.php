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
	 * Search algorithm slug.
	 *
	 * @return string
	 */
	public function get_slug() : string {
		return '3.5';
	}

	/**
	 * Search algorithm name.
	 *
	 * @return string
	 */
	public function get_name() : string {
		return esc_html__( 'Version 3.5', 'elasticpress' );
	}

	/**
	 * Search algorithm description.
	 *
	 * @return string
	 */
	public function get_description() : string {
		return esc_html__( 'Search for the existence of all words in the search first, then return results based on how closely those words appear.', 'elasticpress' );
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
	protected function get_raw_query( string $indexable_slug, string $search_term, array $search_fields, array $query_vars ) : array {
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
							'query'  => $search_term,
							'fields' => $search_fields,
							'type'   => 'phrase',
							'slop'   => 5,
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
	protected function apply_legacy_filters( array $query, string $indexable_slug, array $search_fields, array $query_vars ) : array {
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

		return $query;
	}
}
