<?php
/**
 * Test EP v4.0 search algorithm
 *
 * @since 4.3.0
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress\SearchAlgorithm\Version_400;

/**
 * Test EP v4.0 search algorithm class
 */
class TestVersion_400SearchAlgorithm extends \ElasticPressTest\BaseTestCase {
	/**
	 * Test get_slug
	 *
	 * @group searchAlgorithms
	 */
	public function testGetSlug() {
		$basic = new Version_400();

		$this->assertSame( '4.0', $basic->get_slug() );
	}

	/**
	 * Test default query
	 *
	 * @group searchAlgorithms
	 */
	public function testGetQuery() {
		$basic = new Version_400();
		
		$search_term   = 'search_term';
		$search_fields = [ 'post_title', 'post_content' ];

		$query = $basic->get_query( 'indexable', $search_term, $search_fields, [] );

		$model = $this->getModel( $search_term, $search_fields);

		$this->assertEquals( $model, $query );
	}

	/**
	 * Test filters
	 *
	 * @group searchAlgorithms
	 */
	public function testFilters() {
		$basic = new Version_400();

		$search_term   = 'search_term';
		$search_fields = [ 'post_title', 'post_content' ];

		$test_filter = function() {
			return 1234;
		};

		/**
		 * Test the `ep_{$indexable_slug}_match_phrase_boost` filter.
		 */
		add_filter( 'ep_indexable_match_phrase_boost', $test_filter );

		$query = $basic->get_query( 'indexable', $search_term, $search_fields, [] );
		$this->assertEquals( 1234, $query['bool']['should'][0]['multi_match']['boost'] );

		remove_filter( 'ep_indexable_match_phrase_boost', $test_filter );

		/**
		 * Test the `ep_{$indexable_slug}_match_boost` filter.
		 */
		add_filter( 'ep_indexable_match_boost', $test_filter );

		$query = $basic->get_query( 'indexable', $search_term, $search_fields, [] );
		$this->assertEquals( 1234, $query['bool']['should'][1]['multi_match']['boost'] );

		remove_filter( 'ep_indexable_match_boost', $test_filter );

		/**
		 * Test the `ep_{$indexable_slug}_match_fuzziness` filter.
		 */
		add_filter( 'ep_indexable_match_fuzziness', $test_filter );

		$query = $basic->get_query( 'indexable', $search_term, $search_fields, [] );
		$this->assertEquals( 1234, $query['bool']['should'][1]['multi_match']['fuzziness'] );

		remove_filter( 'ep_indexable_match_fuzziness', $test_filter );

		/**
		 * Test the `ep_{$indexable_slug}_match_cross_fields_boost` filter.
		 */
		add_filter( 'ep_indexable_match_cross_fields_boost', $test_filter );

		$query = $basic->get_query( 'indexable', $search_term, $search_fields, [] );
		$this->assertEquals( 1234, $query['bool']['should'][2]['multi_match']['boost'] );

		remove_filter( 'ep_indexable_match_cross_fields_boost', $test_filter );
	}

	/**
	 * Test deprecated/legacy filters
	 *
	 * @expectedDeprecated ep_match_phrase_boost
	 * @expectedDeprecated ep_match_boost
	 * @expectedDeprecated ep_match_fuzziness
	 * @expectedDeprecated ep_match_cross_fields_boost
	 * @group searchAlgorithms
	 */
	public function testLegacyFilters() {
		$basic = new Version_400();

		$search_term   = 'search_term';
		$search_fields = [ 'post_title', 'post_content' ];

		$test_filter = function() {
			return 1234;
		};

		/**
		 * Test the `ep_match_phrase_boost` filter.
		 */
		add_filter( 'ep_match_phrase_boost', $test_filter );

		$query = $basic->get_query( 'post', $search_term, $search_fields, [] );
		$this->assertEquals( 1234, $query['bool']['should'][0]['multi_match']['boost'] );

		remove_filter( 'ep_match_phrase_boost', $test_filter );

		/**
		 * Test the `ep_match_boost` filter.
		 */
		add_filter( 'ep_match_boost', $test_filter );

		$query = $basic->get_query( 'post', $search_term, $search_fields, [] );
		$this->assertEquals( 1234, $query['bool']['should'][1]['multi_match']['boost'] );

		remove_filter( 'ep_match_boost', $test_filter );

		/**
		 * Test the `ep_match_fuzziness` filter.
		 */
		add_filter( 'ep_match_fuzziness', $test_filter );

		$query = $basic->get_query( 'post', $search_term, $search_fields, [] );
		$this->assertEquals( 1234, $query['bool']['should'][1]['multi_match']['fuzziness'] );

		remove_filter( 'ep_match_fuzziness', $test_filter );

		/**
		 * Test the `ep_match_cross_fields_boost` filter.
		 */
		add_filter( 'ep_match_cross_fields_boost', $test_filter );

		$query = $basic->get_query( 'post', $search_term, $search_fields, [] );
		$this->assertEquals( 1234, $query['bool']['should'][2]['multi_match']['boost'] );

		remove_filter( 'ep_match_cross_fields_boost', $test_filter );
	}

	/**
	 * ES Query model
	 *
	 * @param string $search_term   Search term
	 * @param array  $search_fields Search fields
	 * @return array
	 */
	protected function getModel( string $search_term, array $search_fields ) : array {
		return [
			'bool' => [
				'should' => [
					[
						'multi_match' => [
							'query'  => $search_term,
							'type'   => 'phrase',
							'fields' => $search_fields,
							'boost'  => 3,
						],
					],
					[
						'multi_match' => [
							'query'     => $search_term,
							'fields'    => $search_fields,
							'operator'  => 'and',
							'boost'     => 1,
							'fuzziness' => 'auto',
						],
					],
					[
						'multi_match' => [
							'query'       => $search_term,
							'type'        => 'cross_fields',
							'fields'      => $search_fields,
							'boost'       => 1,
							'analyzer'    => 'standard',
							'tie_breaker' => 0.5,
							'operator'    => 'and',
						],
					],
				],
			],
		];
	}
}
