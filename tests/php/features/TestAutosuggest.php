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
	public function setUp() {
		global $wpdb;
		parent::setUp();
		$wpdb->suppress_errors();

		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );

		wp_set_current_user( $admin_id );

		ElasticPress\Elasticsearch::factory()->delete_all_indices();
		ElasticPress\Indexables::factory()->get( 'post' )->put_mapping();

		ElasticPress\Indexables::factory()->get( 'post' )->sync_manager->sync_queue = [];

		$this->setup_test_post_type();

		set_current_screen( 'front' );

		delete_option( 'ep_active_features' );
	}

	/**
	 * Clean up after each test. Reset our mocks
	 *
	 * @since 2.3
	 */
	public function tearDown() {
		parent::tearDown();

		global $hook_suffix;
		$hook_suffix = 'sites.php';

		set_current_screen();

		// make sure no one attached to this
		remove_filter( 'ep_sync_terms_allow_hierarchy', array( $this, 'ep_allow_multiple_level_terms_sync' ), 100 );
		$this->fired_actions = array();
    }

	protected function get_feature() {
		return ElasticPress\Features::factory()->get_registered_feature( 'autosuggest' );
	}
    
    public function testConstruct() {
        $instance = new ElasticPress\Feature\Autosuggest\Autosuggest();

        $this->assertEquals( 'autosuggest', $instance->slug );
        $this->assertEquals( 'Autosuggest', $instance->title );
    }

    public function testBoxSummary() {
		ob_start();
		$this->get_feature()->output_feature_box_summary();
        $output = ob_get_clean();

		$this->assertContains( 'Suggest relevant content as text is entered into the search field', $output );
    }

    public function testBoxLong() {
		ob_start();
		$this->get_feature()->output_feature_box_long();
        $output = ob_get_clean();

		$this->assertContains( 'Input fields of type &quot;search&quot;', $output );
    }

    public function testOutputFeatureBoxSettings() {
		ob_start();
		$this->get_feature()->output_feature_box_settings();
		$output = ob_get_clean();

		$this->assertContains( 'Autosuggest Selector', $output );
		$this->assertContains( 'Google Analytics Events', $output );
	}

    public function testMappingES5() {
        $change_es_version = function() {
            return '5.2';
        };

        add_filter( 'ep_elasticsearch_version', $change_es_version );

        $mock = require __DIR__ . '/../../../includes/mappings/post/5-2.php';

        $mapping = $this->get_feature()->mapping($mock);

        $this->assertArrayHasKey( 'type', $mapping['settings']['analysis']['analyzer']['edge_ngram_analyzer'] );
        $this->assertContains( 'custom', $mapping['settings']['analysis']['analyzer']['edge_ngram_analyzer'] );

        $this->assertArrayHasKey( 'type', $mapping['mappings']['post']['properties']['post_title']['fields']['suggest'] );
        $this->assertArrayHasKey( 'analyzer', $mapping['mappings']['post']['properties']['post_title']['fields']['suggest'] );
        $this->assertArrayHasKey( 'search_analyzer', $mapping['mappings']['post']['properties']['post_title']['fields']['suggest'] );

        remove_filter( 'ep_elasticsearch_version', $change_es_version );
    }

    public function testMappingES7() {
        $change_es_version = function() {
            return '7.0';
        };

        add_filter( 'ep_elasticsearch_version', $change_es_version );

        $mock = require __DIR__ . '/../../../includes/mappings/post/7-0.php';

        $mapping = $this->get_feature()->mapping($mock);

        $this->assertArrayHasKey( 'type', $mapping['settings']['analysis']['analyzer']['edge_ngram_analyzer'] );
        $this->assertContains( 'custom', $mapping['settings']['analysis']['analyzer']['edge_ngram_analyzer'] );

        $this->assertArrayHasKey( 'type', $mapping['mappings']['properties']['post_title']['fields']['suggest'] );
        $this->assertArrayHasKey( 'analyzer', $mapping['mappings']['properties']['post_title']['fields']['suggest'] );
        $this->assertArrayHasKey( 'search_analyzer', $mapping['mappings']['properties']['post_title']['fields']['suggest'] );

        remove_filter( 'ep_elasticsearch_version', $change_es_version );
    }

    public function testSetFuzziness() {
        set_current_screen( 'edit.php' );
        $this->assertequals( 2, $this->get_feature()->set_fuzziness( 2, [], [] ) );
        $this->assertequals( 2, $this->get_feature()->set_fuzziness( 2, [], [ 's' => 'test' ] ) );
        set_current_screen( 'front' );
        $this->assertequals( 'auto', $this->get_feature()->set_fuzziness( 2, [], [ 's' => 'test' ] ) );
    }

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

        $this->get_feature()->enqueue_scripts();
        $this->assertTrue( wp_script_is( 'elasticpress-autosuggest' ) );
        
        remove_filter( 'pre_site_option_ep_feature_settings', $filter );
    }

    public function testGenerateSearchQuery() {
        $query = $this->get_feature()->generate_search_query();

        $this->assertArrayHasKey( 'body', $query );
        $this->assertArrayHasKey( 'placeholder', $query );
        $this->assertContains( 'ep_autosuggest_placeholder', $query );
    }

    public function testReturnEmptyPosts() {
        $this->assertEmpty( $this->get_feature()->return_empty_posts() );
    }

    public function testApplyAutosuggestWeighting() {
        $filter = function() {
            return [ 'hello' => 'world' ];
        };

        $this->assertEquals( [], $this->get_feature()->apply_autosuggest_weighting( [] ) );

        add_filter( 'ep_weighting_configuration_for_autosuggest', $filter );

        $this->assertArrayHasKey( 'hello', $this->get_feature()->apply_autosuggest_weighting( [] ) );
        $this->assertContains( 'world', $this->get_feature()->apply_autosuggest_weighting( [] ) );

        remove_filter( 'ep_weighting_configuration_for_autosuggest', $filter );
    }

    public function testRequirementsStatus() {
        $status = $this->get_feature()->requirements_status();

        $this->assertAttributeEquals( 1, 'code', $status );
        $this->assertEquals( 2, count( $status->message ) );
    }

}