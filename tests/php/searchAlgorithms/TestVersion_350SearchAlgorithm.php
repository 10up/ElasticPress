<?php
/**
 * Test EP v3.5 search algorithm
 *
 * @since 4.3.0
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress\SearchAlgorithm\Version_350;

/**
 * Test EP v3.5 search algorithm class
 */
class TestVersion_350SearchAlgorithm extends \ElasticPressTest\BaseTestCase {
	/**
	 * Test get_slug
	 *
	 * @group searchAlgorithms
	 */
	public function testGetSlug() {
		$basic = new Version_350();

		$this->assertSame( '3.5', $basic->get_slug() );
	}

	/**
	 * Test default query
	 *
	 * @group searchAlgorithms
	 */
	public function testGetQuery() {
		$basic = new Version_350();
		
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
		$basic = new Version_350();

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
	}

	/**
	 * Test deprecated/legacy filters
	 *
	 * @expectedDeprecated ep_match_phrase_boost
	 * @group searchAlgorithms
	 */
	public function testLegacyFilters() {
		$basic = new Version_350();

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
	}

	/**
	 * ES Query model
	 *
	 * @param string $search_term   Search term
	 * @param string $search_fields Search fields
	 * @return array
	 */
	protected function getModel( string $search_term, string $search_fields ) : array {
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
							'query'  => $search_term,
							'fields' => $search_fields,
							'type'   => 'phrase',
							'slop'   => 5,
						],
					],
				],
			],
		];
	}
}
