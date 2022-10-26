<?php
/**
 * Test default search algorithm
 *
 * @since 4.3.0
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress\SearchAlgorithm\DefaultAlgorithm;

/**
 * Test default search algorithm class
 */
class TestDefaultSearchAlgorithm extends \ElasticPressTest\BaseTestCase {
	/**
	 * Test get_slug
	 *
	 * @group searchAlgorithms
	 */
	public function testGetSlug() {
		$default = new DefaultAlgorithm();

		$this->assertSame( 'default', $default->get_slug() );
	}

	/**
	 * Test default query
	 *
	 * @group searchAlgorithms
	 */
	public function testGetQuery() {
		$default = new DefaultAlgorithm();
		
		$search_term   = 'search_term';
		$search_fields = [ 'post_title', 'post_content' ];

		$query = $default->get_query( 'indexable', $search_term, $search_fields, [] );

		$model = $this->getModel( $search_term, $search_fields);

		$this->assertEquals( $model, $query );
	}

	/**
	 * Test filters
	 *
	 * @group searchAlgorithms
	 */
	public function testFilters() {
		$default = new DefaultAlgorithm();

		$search_term   = 'search_term';
		$search_fields = [ 'post_title', 'post_content' ];

		$test_filter = function() {
			return 1234;
		};

		/**
		 * Test the `ep_{$indexable_slug}_match_phrase_boost` filter.
		 */
		add_filter( 'ep_indexable_match_phrase_boost', $test_filter );

		$query = $default->get_query( 'indexable', $search_term, $search_fields, [] );
		$this->assertEquals( 1234, $query['bool']['should'][0]['multi_match']['boost'] );

		remove_filter( 'ep_indexable_match_phrase_boost', $test_filter );

		/**
		 * Test the `ep_{$indexable_slug}_match_boost` filter.
		 */
		add_filter( 'ep_indexable_match_boost', $test_filter );

		$query = $default->get_query( 'indexable', $search_term, $search_fields, [] );
		$this->assertEquals( 1234, $query['bool']['should'][1]['multi_match']['boost'] );

		remove_filter( 'ep_indexable_match_boost', $test_filter );

		/**
		 * Test the `ep_{$indexable_slug}_fuzziness_arg` filter.
		 */
		add_filter( 'ep_indexable_fuzziness_arg', $test_filter );

		$query = $default->get_query( 'indexable', $search_term, $search_fields, [] );
		$this->assertEquals( 1234, $query['bool']['should'][2]['multi_match']['fuzziness'] );

		remove_filter( 'ep_indexable_fuzziness_arg', $test_filter );
	}

	/**
	 * Test filters with posts
	 * 
	 * As posts also apply the legacy filters, these tests assure code honors the value of the newer filters
	 *
	 * @see https://github.com/10up/ElasticPress/issues/3033 
	 * @group searchAlgorithms
	 */
	public function testPostFilters() {
		$default = new DefaultAlgorithm();

		$search_term   = 'search_term';
		$search_fields = [ 'post_title', 'post_content' ];

		$test_filter = function() {
			return 1234;
		};

		/**
		 * Test the `ep_post_match_phrase_boost` filter.
		 */
		add_filter( 'ep_post_match_phrase_boost', $test_filter );

		$query = $default->get_query( 'post', $search_term, $search_fields, [] );
		$this->assertEquals( 1234, $query['bool']['should'][0]['multi_match']['boost'] );

		remove_filter( 'ep_post_match_phrase_boost', $test_filter );

		/**
		 * Test the `ep_post_match_boost` filter.
		 */
		add_filter( 'ep_post_match_boost', $test_filter );

		$query = $default->get_query( 'post', $search_term, $search_fields, [] );
		$this->assertEquals( 1234, $query['bool']['should'][1]['multi_match']['boost'] );

		remove_filter( 'ep_post_match_boost', $test_filter );

		/**
		 * Test the `ep_post_fuzziness_arg` filter.
		 */
		add_filter( 'ep_post_fuzziness_arg', $test_filter );

		$query = $default->get_query( 'post', $search_term, $search_fields, [] );
		$this->assertEquals( 1234, $query['bool']['should'][2]['multi_match']['fuzziness'] );

		remove_filter( 'ep_post_fuzziness_arg', $test_filter );
	}

	/**
	 * Test deprecated/legacy filters
	 *
	 * @expectedDeprecated ep_match_phrase_boost
	 * @expectedDeprecated ep_match_boost
	 * @expectedDeprecated ep_fuzziness_arg
	 * @group searchAlgorithms
	 */
	public function testLegacyFilters() {
		$default = new DefaultAlgorithm();

		$search_term   = 'search_term';
		$search_fields = [ 'post_title', 'post_content' ];

		$test_filter = function() {
			return 1234;
		};

		/**
		 * Test the `ep_match_phrase_boost` filter.
		 */
		add_filter( 'ep_match_phrase_boost', $test_filter );

		$query = $default->get_query( 'post', $search_term, $search_fields, [] );
		$this->assertEquals( 1234, $query['bool']['should'][0]['multi_match']['boost'] );

		remove_filter( 'ep_match_phrase_boost', $test_filter );

		/**
		 * Test the `ep_match_boost` filter.
		 */
		add_filter( 'ep_match_boost', $test_filter );

		$query = $default->get_query( 'post', $search_term, $search_fields, [] );
		$this->assertEquals( 1234, $query['bool']['should'][1]['multi_match']['boost'] );

		remove_filter( 'ep_match_boost', $test_filter );

		/**
		 * Test the `ep_fuzziness_arg` filter.
		 */
		add_filter( 'ep_fuzziness_arg', $test_filter );

		$query = $default->get_query( 'post', $search_term, $search_fields, [] );
		$this->assertEquals( 1234, $query['bool']['should'][2]['multi_match']['fuzziness'] );

		remove_filter( 'ep_fuzziness_arg', $test_filter );
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
							'boost'  => 4,
						],
					],
					[
						'multi_match' => [
							'query'     => $search_term,
							'fields'    => $search_fields,
							'boost'     => 2,
							'fuzziness' => 0,
							'operator'  => 'and',
						],
					],
					[
						'multi_match' => [
							'fields'    => $search_fields,
							'query'     => $search_term,
							'fuzziness' => 1,
						],
					],
				],
			],
		];
	}
}
