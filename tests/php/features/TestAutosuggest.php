<?php
/**
 * Test document feature
 *
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress;

/**
 * Document test class
 */
class TestAutosuggest extends BaseTestCase {

	/**
	 * Setup each test.
	 *
	 * @since 2.3
	 */
	public function set_up() {
		global $wpdb;
		parent::set_up();
		$wpdb->suppress_errors();

		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );

		wp_set_current_user( $admin_id );

		ElasticPress\Elasticsearch::factory()->delete_all_indices();
		ElasticPress\Indexables::factory()->get( 'post' )->put_mapping();

		ElasticPress\Indexables::factory()->get( 'post' )->sync_manager->reset_sync_queue();

		$this->setup_test_post_type();

		set_current_screen( 'front' );
	}

	/**
	 * Clean up after each test. Reset our mocks
	 *
	 * @since 2.3
	 */
	public function tear_down() {
		parent::tear_down();

		global $hook_suffix;
		$hook_suffix = 'sites.php';

		set_current_screen();

		// make sure no one attached to this
		remove_filter( 'ep_sync_terms_allow_hierarchy', array( $this, 'ep_allow_multiple_level_terms_sync' ), 100 );
		$this->fired_actions = array();
	}

	/**
	 * Get the feature instance
	 */
	protected function get_feature() {
		return ElasticPress\Features::factory()->get_registered_feature( 'autosuggest' );
	}

	/**
	 * Test the class constructor
	 */
	public function testConstruct() {
		$instance = new ElasticPress\Feature\Autosuggest\Autosuggest();

		$this->assertEquals( 'autosuggest', $instance->slug );
		$this->assertEquals( 'Autosuggest', $instance->title );
	}

	/**
	 * Test the `output_feature_box_summary` method
	 */
	public function testBoxSummary() {
		ob_start();
		$this->get_feature()->output_feature_box_summary();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'As text is entered into the search field, suggested content will appear below it', $output );
	}

	/**
	 * Test the `output_feature_box_long` method
	 */
	public function testBoxLong() {
		ob_start();
		$this->get_feature()->output_feature_box_long();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Input fields of type &quot;search&quot;', $output );
	}

	/**
	 * Test the `output_feature_box_settings` method
	 */
	public function testOutputFeatureBoxSettings() {
		ob_start();
		$this->get_feature()->output_feature_box_settings();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Autosuggest Selector', $output );
		$this->assertStringContainsString( 'Google Analytics Events', $output );
	}

	/**
	 * Test the mapping change in ES 5 method
	 */
	public function testMappingES5() {
		$change_es_version = function() {
			return '5.2';
		};

		add_filter( 'ep_elasticsearch_version', $change_es_version );

		$mock = require __DIR__ . '/../../../includes/mappings/post/5-2.php';

		$mapping = $this->get_feature()->mapping( $mock );

		$this->assertArrayHasKey( 'type', $mapping['settings']['analysis']['analyzer']['edge_ngram_analyzer'] );
		$this->assertContains( 'custom', $mapping['settings']['analysis']['analyzer']['edge_ngram_analyzer'] );

		$this->assertArrayHasKey( 'type', $mapping['mappings']['post']['properties']['post_title']['fields']['suggest'] );
		$this->assertArrayHasKey( 'analyzer', $mapping['mappings']['post']['properties']['post_title']['fields']['suggest'] );
		$this->assertArrayHasKey( 'search_analyzer', $mapping['mappings']['post']['properties']['post_title']['fields']['suggest'] );
	}

	/**
	 * Test the mapping change in ES 7 method
	 */
	public function testMappingES7() {
		$change_es_version = function() {
			return '7.0';
		};

		add_filter( 'ep_elasticsearch_version', $change_es_version );

		$mock = require __DIR__ . '/../../../includes/mappings/post/7-0.php';

		$mapping = $this->get_feature()->mapping( $mock );

		$this->assertArrayHasKey( 'type', $mapping['settings']['analysis']['analyzer']['edge_ngram_analyzer'] );
		$this->assertContains( 'custom', $mapping['settings']['analysis']['analyzer']['edge_ngram_analyzer'] );

		$this->assertArrayHasKey( 'type', $mapping['mappings']['properties']['post_title']['fields']['suggest'] );
		$this->assertArrayHasKey( 'analyzer', $mapping['mappings']['properties']['post_title']['fields']['suggest'] );
		$this->assertArrayHasKey( 'search_analyzer', $mapping['mappings']['properties']['post_title']['fields']['suggest'] );
	}

	/**
	 * Test the `output_feature_box_settings` method
	 */
	public function testSetFuzziness() {
		set_current_screen( 'edit.php' );
		$this->assertequals( 2, $this->get_feature()->set_fuzziness( 2, [], [] ) );
		$this->assertequals( 2, $this->get_feature()->set_fuzziness( 2, [], [ 's' => 'test' ] ) );
		set_current_screen( 'front' );
		$this->assertequals( 'auto', $this->get_feature()->set_fuzziness( 2, [], [ 's' => 'test' ] ) );
	}

	/**
	 * Test the `filter_term_suggest` method
	 */
	public function testFilterTermSuggest() {
		$post_args = [];
		$this->assertEquals( [], $this->get_feature()->filter_term_suggest( $post_args ) );

		$post_args = [
			'terms' => [
				'category' => [
					[
						'name' => 'test-category',
					],
				],
			],
		];

		$result = $this->get_feature()->filter_term_suggest( $post_args );

		$this->assertArrayHasKey( 'term_suggest', $result );
		$this->assertContains( 'test-category', $result['term_suggest'] );
	}

	/**
	 * Test the `enqueue_scripts` method
	 */
	public function testEnqueueScripts() {
		$this->assertFalse( wp_script_is( 'elasticpress-autosuggest' ) );
		$this->get_feature()->enqueue_scripts();
		$this->assertFalse( wp_script_is( 'elasticpress-autosuggest' ) );

		$filter = function() {
			return [
				'autosuggest' => [
					'endpoint_url' => 'http://example.com',
				],
			];
		};

		add_filter( 'pre_site_option_ep_feature_settings', $filter );
		add_filter( 'pre_option_ep_feature_settings', $filter );

		$this->get_feature()->enqueue_scripts();
		$this->assertTrue( wp_script_is( 'elasticpress-autosuggest' ) );
	}

	/**
	 * Test the `generate_search_query` method
	 */
	public function testGenerateSearchQuery() {
		$query = $this->get_feature()->generate_search_query();

		$this->assertArrayHasKey( 'body', $query );
		$this->assertArrayHasKey( 'placeholder', $query );
		$this->assertContains( 'ep_autosuggest_placeholder', $query );
	}

	/**
	 * Test the filters in the `generate_search_query` method
	 */
	public function testGenerateSearchQueryFilters() {
		/**
		 * Test the `ep_autosuggest_query_placeholder` filter.
		 */
		$test_placeholder_filter = function() {
			return 'lorem-ipsum';
		};

		add_filter( 'ep_autosuggest_query_placeholder', $test_placeholder_filter );

		$query = $this->get_feature()->generate_search_query();
		$this->assertStringContainsString( 'lorem-ipsum', $query['body'] );

		/**
		 * Test the `ep_autosuggest_query_placeholder` filter.
		 */
		$test_post_type_filter = function() {
			return [ 'my-custom-post-type' ];
		};

		add_filter( 'ep_term_suggest_post_type', $test_post_type_filter );

		$query = $this->get_feature()->generate_search_query();
		$this->assertStringContainsString( 'my-custom-post-type', $query['body'] );
		/**
		 * Test the `ep_term_suggest_post_status` filter.
		 */
		$test_post_status_filter = function() {
			return [ 'trash' ];
		};

		add_filter( 'ep_term_suggest_post_status', $test_post_status_filter );

		$query = $this->get_feature()->generate_search_query();
		$this->assertStringContainsString( 'trash', $query['body'] );

		/**
		 * Test the `ep_term_suggest_post_status` filter.
		 */
		$test_args_filter = function( $args ) {
			$args['posts_per_page'] = 1234;
			return $args;
		};

		add_filter( 'ep_autosuggest_query_args', $test_args_filter );

		$query = $this->get_feature()->generate_search_query();
		$this->assertStringContainsString( '1234', $query['body'] );
	}

	/**
	 * Test the filters in the `return_empty_posts` method
	 */
	public function testReturnEmptyPosts() {
		$this->assertEmpty( $this->get_feature()->return_empty_posts() );
	}

	/**
	 * Test the filters in the `apply_autosuggest_weighting` method
	 */
	public function testApplyAutosuggestWeighting() {
		$filter = function() {
			return [ 'hello' => 'world' ];
		};

		$this->assertEquals( [], $this->get_feature()->apply_autosuggest_weighting( [] ) );

		add_filter( 'ep_weighting_configuration_for_autosuggest', $filter );

		$this->assertArrayHasKey( 'hello', $this->get_feature()->apply_autosuggest_weighting( [] ) );
		$this->assertContains( 'world', $this->get_feature()->apply_autosuggest_weighting( [] ) );
	}

	/**
	 * Test the filters in the `requirements_status` method
	 */
	public function testRequirementsStatus() {
		$status = $this->get_feature()->requirements_status();

		$this->assertEquals( 1, $status->code );
		$this->assertEquals( 2, count( $status->message ) );
	}

	/**
	 * Test Autosuggest settings schema
	 *
	 * @since 5.0.0
	 * @group autosuggest
	 */
	public function test_get_settings_schema() {
		$settings_schema = $this->get_feature()->get_settings_schema();

		$settings_keys = wp_list_pluck( $settings_schema, 'key' );

		$this->assertSame(
			[ 'active', 'autosuggest_selector', 'trigger_ga_event', 'endpoint_url' ],
			$settings_keys
		);
	}

	/**
	 * Test whether autosuggest ngram fields apply to the search query when AJAX integration and weighting is enabled.
	 *
	 * @since 5.1.o
	 * @group autosuggest
	 */
	public function test_autosuggest_ngram_fields_for_ajax_call() {
		define( 'DOING_AJAX', true );

		$this->get_feature()->setup();

		add_filter( 'ep_ajax_wp_query_integration', '__return_true' );
		add_filter( 'ep_enable_do_weighting', '__return_true' );

		$this->ep_factory->post->create(
			array(
				'post_title' => 'search me',
				'post_type'  => 'post',
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		add_filter(
			'ep_query_request_path',
			function( $path, $index, $type, $query, $query_args, $query_object ) {
				$fields = $query['query']['function_score']['query']['bool']['should'][0]['bool']['must'][0]['bool']['should'][1]['multi_match']['fields'];

				$this->assertContains( 'term_suggest^1', $fields );
				$this->assertContains( 'post_title.suggest^1', $fields );
				return $path;
			},
			10,
			6
		);

		$query = new \WP_Query(
			array(
				's'            => 'search me',
				'ep_integrate' => true,
			)
		);

		$this->assertTrue( $query->elasticsearch_success );
	}
}
