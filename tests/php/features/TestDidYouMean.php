<?php
/**
 * Test Did you mean feature.
 *
 * @since   4.6.0
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress;

/**
 * Did you mean test class.
 */
class TestDidYouMean extends BaseTestCase {

	/**
	 * Setup each test.
	 */
	public function set_up() {
		global $wpdb;
		parent::set_up();
		$wpdb->suppress_errors();

		$admin_id = $this->factory->user->create( [ 'role' => 'administrator' ] );

		wp_set_current_user( $admin_id );

		ElasticPress\Features::factory()->activate_feature( 'search' );
		ElasticPress\Features::factory()->activate_feature( 'did-you-mean' );

		ElasticPress\Features::factory()->setup_features();

		ElasticPress\Elasticsearch::factory()->delete_all_indices();
		ElasticPress\Indexables::factory()->get( 'post' )->put_mapping();

		$this->setup_test_post_type();
	}

	/**
	 * Test Feature properties.
	 */
	public function testConstruct() {
		$instance = new ElasticPress\Feature\DidYouMean\DidYouMean();

		$this->assertEquals( 'did-you-mean', $instance->slug );
		$this->assertEquals( 'Did You Mean', $instance->title );
		$this->assertTrue( $instance->requires_install_reindex );
		$this->assertTrue( $instance->available_during_installation );
		$this->assertTrue( $instance->is_visible() );
		$this->assertSame( [ 'search_behavior' => '0' ], $instance->default_settings );
	}

	/**
	 * Test Requirements status.
	 */
	public function testRequirementsStatus() {
		$instance = new ElasticPress\Feature\DidYouMean\DidYouMean();
		$status   = $instance->requirements_status();

		$this->assertEquals( 1, $status->code );
		$this->assertEquals( null, $status->message );
	}

	/**
	 * Tests that ES returns a suggestion when search term has a typo.
	 */
	public function testEsSearchSuggestion() {
		$this->ep_factory->post->create( [ 'post_content' => 'Test post' ] );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$query = new \WP_Query(
			[
				's' => 'teet',
			]
		);

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 'test', $query->suggested_terms['options'][0]['text'] );
	}

	/**
	 * Tests that ES returns a suggestion only for search queries.
	 */
	public function testEsSearchSuggestionOnlyIntegrateWithSearchQuery() {
		$this->ep_factory->post->create( [ 'post_content' => 'Test post' ] );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$query = new \WP_Query(
			[
				'ep_integrate' => true,
			]
		);

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEmpty( $query->suggested_terms );
	}

	/**
	 * Tests the get_suggestion method.
	 */
	public function testGetSearchSuggestionMethod() {
		$this->ep_factory->post->create( [ 'post_content' => 'Test post' ] );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$query = new \WP_Query(
			[
				's' => 'teet',
			]
		);

		$this->assertTrue( $query->elasticsearch_success );

		$expected = sprintf( '<span class="ep-spell-suggestion">Did you mean: <a href="%s">test</a>?</span>', get_search_link( 'test' ) );
		$this->assertEquals( $expected, ElasticPress\Features::factory()->get_registered_feature( 'did-you-mean' )->get_suggestion( $query ) );
	}

	/**
	 * Tests that get_suggestion method returns suggestion only for main query.
	 */
	public function testGetSearchSuggestionMethodReturnsSuggestionForMainQuery() {
		global $wp_the_query, $wp_query;

		$this->ep_factory->post->create( [ 'post_content' => 'Test post' ] );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args  = [
			's' => 'teet',
		];
		$query = new \WP_Query( $args );

		// mock the query as main query
		$wp_the_query = $query;
		$wp_query     = $query;

		$this->assertTrue( $query->elasticsearch_success );

		$query = $query->query( $args );

		$expected = sprintf( '<span class="ep-spell-suggestion">Did you mean: <a href="%s">test</a>?</span>', get_search_link( 'test' ) );
		$this->assertEquals( $expected, ElasticPress\Features::factory()->get_registered_feature( 'did-you-mean' )->get_suggestion() );
	}

	/**
	 * Tests that get_suggestion method returns false if other than WP_Query is passed.
	 */
	public function testGetSearchSuggestionMethodReturnsFalseIfOtherThanWpQueryIsPassed() {
		$query = new \stdClass();
		$this->assertFalse( ElasticPress\Features::factory()->get_registered_feature( 'did-you-mean' )->get_suggestion( $query ) );
	}

	/**
	 * Tests that get_suggestion method filter `ep_suggestion_html`.
	 */
	public function testGetSearchSuggestionMethodFilter() {
		$this->ep_factory->post->create( [ 'post_content' => 'Test post' ] );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$expected_result = '<span class="ep-spell-suggestion">Did you mean: test filter is working ?</span>';
		add_filter(
			'ep_suggestion_html',
			function( $html, $terms, $query ) use ( $expected_result ) {
				$this->assertEquals( 'test', $terms[0]['text'] );
				$this->assertInstanceOf( '\WP_Query', $query );
				return $expected_result;
			},
			10,
			3
		);

		$query = new \WP_Query(
			[
				's' => 'teet',
			]
		);
		$this->assertEquals( $expected_result, ElasticPress\Features::factory()->get_registered_feature( 'did-you-mean' )->get_suggestion( $query ) );
	}

	/**
	 * Test Mapping for ES version 7 and above.
	 */
	public function testMapping() {
		add_filter(
			'ep_elasticsearch_version',
			function() {
				return '7.0';
			}
		);

		$mapping = ElasticPress\Indexables::factory()->get( 'post' )->generate_mapping();

		$expected_result = [
			'shingle' =>
			[
				'type'     => 'text',
				'analyzer' => 'trigram',
			],
		];
		$this->assertSame( $expected_result, $mapping['mappings']['properties']['post_content']['fields'] );
	}

	/**
	 * Test Mapping for ES version lower than 7.
	 */
	public function testMappingForESVersionLowerThanSeven() {
		add_filter(
			'ep_elasticsearch_version',
			function() {
				return '5.2.0';
			}
		);

		$mapping = ElasticPress\Indexables::factory()->get( 'post' )->generate_mapping();

		$expected_result = [
			'shingle' =>
			[
				'type'     => 'text',
				'analyzer' => 'trigram',
			],
		];
		$this->assertSame( $expected_result, $mapping['mappings']['post']['properties']['post_content']['fields'] );
	}

	/**
	 * Test `ep_search_suggestion_analyzer` filter.
	 */
	public function testSearchAnalyzerFilter() {
		$this->ep_factory->post->create( [ 'post_content' => 'Test post' ] );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$search_analyzer = [
			'term' => [
				'field' => 'post_content',
			],
		];

		add_filter(
			'ep_search_suggestion_analyzer',
			function() use ( $search_analyzer ) {
				return $search_analyzer;
			}
		);

		add_filter(
			'ep_query_request_args',
			function( $request_args, $path, $index, $type, $query, $query_args, $query_object ) use ( $search_analyzer ) {
				$this->assertEquals( $search_analyzer, $query['suggest']['ep_suggestion'] );
				return $request_args;
			},
			10,
			7
		);

		$query = new \WP_Query(
			[
				's' => 'teet',
			]
		);

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 'Test post', $query->posts[0]->post_content );
	}

	/**
	 * Test that function returns the original search term when no results are found.
	 */
	public function testGetOriginalSearchTerm() {
		global $wp_the_query, $wp_query;

		$this->ep_factory->post->create( [ 'post_content' => 'Test post' ] );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		ElasticPress\Features::factory()->update_feature(
			'did-you-mean',
			[
				'active'          => true,
				'search_behavior' => 'redirect',
			]
		);

		parse_str( 'ep_suggestion_original_term=Original Term', $_GET );

		$query = new \WP_Query(
			[
				's' => 'teet',
			]
		);

		// mock the query as main query
		$wp_the_query = $query;
		$wp_query     = $query;

		$this->assertTrue( $query->elasticsearch_success );

		$html = ElasticPress\Features::factory()->get_registered_feature( 'did-you-mean' )->get_original_search_term();
		$this->assertStringContainsString( '<div class="ep-original-search-term-message">', $html );
		$this->assertStringContainsString( '<span class="result">Showing results for: </span><strong>teet</strong>', $html );
		$this->assertStringContainsString( '<span class="no-result">No results for: </span><strong>Original Term</strong>', $html );
	}

	/**
	 * Test `ep_suggestions` action for main query.
	 */
	public function testEPSuggestionsAction() {
		global $wp_the_query, $wp_query;

		$this->ep_factory->post->create( [ 'post_content' => 'Test post' ] );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$query = new \WP_Query(
			[
				's' => 'teet',
			]
		);

		ElasticPress\Features::factory()->update_feature(
			'did-you-mean',
			[
				'active'          => true,
				'search_behavior' => 'redirect',
			]
		);

		parse_str( 'ep_suggestion_original_term=Original Term', $_GET );

		// mock the query as main query
		$wp_the_query = $query;
		$wp_query     = $query;

		ob_start();
		do_action( 'ep_suggestions' );
		$output = ob_get_clean();

		$expected = sprintf( '<span class="ep-spell-suggestion">Did you mean: <a href="%s">test</a>?</span>', get_search_link( 'test' ) );
		$this->assertStringContainsString( $expected, $output );
		$this->assertStringContainsString( '<span class="result">Showing results for: </span><strong>teet</strong>', $output );
	}

	/**
	 * Test `ep_suggestions` action for other than main query.
	 */
	public function testEPSuggestionsActionOtherThanMainQuery() {
		$this->ep_factory->post->create( [ 'post_content' => 'Test post' ] );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$query = new \WP_Query(
			[
				's' => 'teet',
			]
		);

		ElasticPress\Features::factory()->update_feature(
			'did-you-mean',
			[
				'active'          => true,
				'search_behavior' => 'redirect',
			]
		);

		parse_str( 'ep_suggestion_original_term=Original Term', $_GET );

		ob_start();
		do_action( 'ep_suggestions', $query );
		$output = ob_get_clean();

		$expected = sprintf( '<span class="ep-spell-suggestion">Did you mean: <a href="%s">test</a>?</span>', get_search_link( 'test' ) );
		$this->assertStringContainsString( $expected, $output );
		$this->assertStringContainsString( '<span class="result">Showing results for: </span><strong>teet</strong>', $output );
	}

	/**
	 * Test maybe_sanitize_suggestion method.
	 */
	public function testSuggestionRemovedFromListIfScoreIsLowerThanThreshold() {
		$this->ep_factory->post->create( [ 'post_content' => 'V Neck Tee Shirt' ] );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		// mock the score.
		add_filter(
			'ep_es_query_results',
			function( $response ) {
				$response['suggest']['ep_suggestion'][0]['options'][0]['score'] = '3.730e-6';
				return $response;
			}
		);

		$query = new \WP_Query(
			[
				's' => 'woo-vneck-tee-blue',
			]
		);

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEmpty( $query->suggested_terms['options'] );
	}

	/**
	 * Test that `ep_suggestion_minimum_score` filter works.
	 */
	public function testEPSuggestionMinimumScoreFilter() {
		$this->ep_factory->post->create( [ 'post_content' => 'V Neck Tee Shirt' ] );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$query = new \WP_Query(
			[
				's' => 'woo-vneck-tee-blue',
			]
		);

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertNotEmpty( $query->suggested_terms['options'] );

		add_filter(
			'ep_suggestion_minimum_score',
			function() {
				return 0.1;
			}
		);

		$query = new \WP_Query(
			[
				's' => 'woo-vneck-tee-blue',
			]
		);
		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEmpty( $query->suggested_terms['options'] );
	}

	/**
	 * Test Did You Mean settings schema
	 *
	 * @since 5.0.0
	 * @group did-you-mean
	 */
	public function test_get_settings_schema() {
		$instance        = new \ElasticPress\Feature\DidYouMean\DidYouMean();
		$settings_schema = $instance->get_settings_schema();

		$settings_keys = wp_list_pluck( $settings_schema, 'key' );

		$this->assertSame(
			[ 'active', 'search_behavior' ],
			$settings_keys
		);
	}
}
