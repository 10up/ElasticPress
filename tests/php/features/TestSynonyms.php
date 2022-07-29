<?php
/**
 * Test synonym feature
 *
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress;
use ElasticPress\Feature\Search\Synonyms;

/**
 * Document test class
 */
class TestSynonyms extends BaseTestCase {

		/**
	 * Setup each test.
	 *
	 * @since 3.5
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
		ElasticPress\Features::factory()->activate_feature( 'synonyms' );
	}

	/**
	 * Clean up after each test. Reset our mocks
	 *
	 * @since 2.1
	 */
	public function tearDown() {
		parent::tearDown();

		// make sure no one attached to this
		remove_filter( 'ep_sync_terms_allow_hierarchy', array( $this, 'ep_allow_multiple_level_terms_sync' ), 100 );
		$this->fired_actions = array();
	}

	public function getFeature() {
		return new Synonyms();
	}

	public function testConstructor() {
		$instance = $this->getFeature();

		$this->assertSame( 'ep_synonyms_filter', $instance->filter_name );
		$this->assertIsArray( $instance->affected_indices );
		$this->assertContains( 'post', $instance->affected_indices );
	}

	public function testGetSynonymPostId() {
		$instance = $this->getFeature();

		$post_id = $instance->get_synonym_post_id();
		$this->assertGreaterThan( 0, $post_id );
	}

	public function testGetSynonymsRaw() {
		$instance = $this->getFeature();

		$synonyms = $instance->get_synonyms_raw();

		$this->assertNotEmpty( $synonyms );
	}

	public function testGetSynonyms() {
		$instance = $this->getFeature();

		$synonyms = $instance->get_synonyms();


		// For some reason, the greater-than gets encoded during the multi-site
		// tests but not the single-site tests. This updates the encoding so
		// they both match. See https://travis-ci.com/github/petenelson/ElasticPress/jobs/470254351.
		$synonyms = array_map(
			function ( $synonym ) {
				return str_replace( '>', '&gt;', $synonym );
			},
			$synonyms
		);

		$this->assertNotEmpty( $synonyms );
		$this->assertContains( 'sneakers, tennis shoes, trainers, runners', $synonyms );
		$this->assertContains( 'shoes =&gt; sneaker, sandal, boots, high heels', $synonyms );
	}

	public function testValidateSynonyms() {
		$instance = $this->getFeature();

		$this->assertFalse( $instance->validate_synonym( '' ) );
		$this->assertFalse( $instance->validate_synonym( '# Comments are not valid.' ) );
		$this->assertFalse( $instance->validate_synonym( '// Comments are not valid.' ) );
		$this->assertEquals( 'foo, bar', $instance->validate_synonym( ' foo, bar ' ) );
		$this->assertEquals( 'foo => bar', $instance->validate_synonym( ' foo => bar ' ) );
	}

	/**
	 * Test add_synonyms_to_settings against arrays with the wrong format.
	 *
	 * @since 4.3.0
	 * @dataProvider settingsWithWrongFormatProvider
	 * @return void
	 */
	public function testAddSynonymsToSettingsWithWrongFormat() {
		$settings = func_get_args(); // It will not work with empty arrays
		$instance = $this->getFeature();

		$this->assertSame( $settings, $instance->add_synonyms_to_settings( $settings ) );
	}

	/**
	 * Test setting filters in add_synonyms_to_settings
	 *
	 * @since 4.3.0
	 */
	public function testAddSynonymsToSettingsFilter() {
		$instance = $this->getFeature();

		$settings = [
			'analysis' => [
				'filter'   => [],
				'analyzer' => [
					'default' => [
						'filter' => [],
					],
				],
			],
		];

		$synonym_es_filter = [
			'type'     => 'synonym_graph',
			'lenient'  => true,
			'synonyms' => $instance->get_synonyms(),
		];

		$changed_settings = $instance->add_synonyms_to_settings( $settings );
		$this->assertArrayHasKey( 'ep_synonyms_filter', $changed_settings['analysis']['filter'] );
		$this->assertSame( $synonym_es_filter, $changed_settings['analysis']['filter']['ep_synonyms_filter'] );

		/**
		 * Test the `ep_synonyms_filter_name` filter.
		 */
		$change_filter_name = function () {
			return 'ep_custom_synonyms_filter';
		};
		add_filter( 'ep_synonyms_filter_name', $change_filter_name );
		$changed_settings = $instance->add_synonyms_to_settings( $settings );
		$this->assertArrayNotHasKey( 'ep_synonyms_filter', $changed_settings['analysis']['filter'] );
		$this->assertArrayHasKey( 'ep_custom_synonyms_filter', $changed_settings['analysis']['filter'] );
		$this->assertSame( $synonym_es_filter, $changed_settings['analysis']['filter']['ep_custom_synonyms_filter'] );
		remove_filter( 'ep_synonyms_filter_name', $change_filter_name );

		/**
		 * Test the `ep_synonyms_filter` filter.
		 */
		$change_es_filter_content = function() {
			return [ 'lenient'  => false ];
		};
		add_filter( 'ep_synonyms_filter', $change_es_filter_content );
		$changed_settings = $instance->add_synonyms_to_settings( $settings );
		$this->assertSame( [ 'lenient'  => false ], $changed_settings['analysis']['filter']['ep_synonyms_filter'] );
		remove_filter( 'ep_synonyms_filter', $change_es_filter_content );
	}
	
	/**
	 * Test setting analyzers in add_synonyms_to_settings
	 *
	 * @since 4.3.0
	 */
	public function testAddSynonymsToSettingsAnalyzer() {
		$instance = $this->getFeature();

		/**
		 * Test an array that does not have a `default_search` yet.
		 * Filters should be copied from `default` to `default_search`
		 */
		$settings = [
			'analysis' => [
				'filter'   => [],
				'analyzer' => [
					'default' => [
						'filter' => [
							'filter_a',
							'filter_b',
						],
					],
				],
			],
		];
		$changed_settings = $instance->add_synonyms_to_settings( $settings );
		$this->assertContains( 'filter_a', $changed_settings['analysis']['analyzer']['default']['filter'] );
		$this->assertContains( 'filter_b', $changed_settings['analysis']['analyzer']['default']['filter'] );
		$this->assertNotContains( 'ep_synonyms_filter', $changed_settings['analysis']['analyzer']['default']['filter'] );
		$this->assertContains( 'filter_a', $changed_settings['analysis']['analyzer']['default_search']['filter'] );
		$this->assertContains( 'filter_b', $changed_settings['analysis']['analyzer']['default_search']['filter'] );
		$this->assertContains( 'ep_synonyms_filter', $changed_settings['analysis']['analyzer']['default_search']['filter'] );

		/**
		 * Test an array that has a `default_search`.
		 * Filters should be kept as they are now.
		 */
		$settings = [
			'analysis' => [
				'filter'   => [],
				'analyzer' => [
					'default' => [
						'filter' => [
							'filter_a',
							'filter_b',
						],
					],
					'default_search' => [
						'filter' => [
							'filter_c',
						],
					],
				],
			],
		];
		$changed_settings = $instance->add_synonyms_to_settings( $settings );
		$this->assertContains( 'filter_a', $changed_settings['analysis']['analyzer']['default']['filter'] );
		$this->assertContains( 'filter_b', $changed_settings['analysis']['analyzer']['default']['filter'] );
		$this->assertNotContains( 'ep_synonyms_filter', $changed_settings['analysis']['analyzer']['default']['filter'] );
		$this->assertNotContains( 'filter_c', $changed_settings['analysis']['analyzer']['default']['filter'] );

		$this->assertContains( 'ep_synonyms_filter', $changed_settings['analysis']['analyzer']['default_search']['filter'] );
		$this->assertNotContains( 'filter_a', $changed_settings['analysis']['analyzer']['default_search']['filter'] );
		$this->assertNotContains( 'filter_b', $changed_settings['analysis']['analyzer']['default_search']['filter'] );
		$this->assertContains( 'filter_c', $changed_settings['analysis']['analyzer']['default_search']['filter'] );
	}

	/**
	 * Data Provider with some arrays that do not follow expected
	 * format for index settings.
	 *
	 * @return array
	 */
	public function settingsWithWrongFormatProvider() {
		return [
			// Simple empty array
			[],
			// Analysis array without a filter
			[
				'analysis' => [],
			],
			// It has filter but no analyzer
			[
				'analysis' => [
					'filter' => [],
				],
			],
			// It has filter and analyzer but no default nor default_search analyzer
			[
				'analysis' => [
					'filter'   => [],
					'analyzer' => [],
				],
			],
			// Default analyzer without filters
			[
				'analysis' => [
					'filter'   => [],
					'analyzer' => [
						'default' => [],
					],
				],
			],
			// default_search analyzer without filters
			[
				'analysis' => [
					'filter'   => [],
					'analyzer' => [
						'default_search' => [],
					],
				],
			],
			// default and default_search analyzers without filters. Filters in another analyzer
			[
				'analysis' => [
					'filter'   => [],
					'analyzer' => [
						'custom_analyzer' => [
							'filter' => [],
						],
						'default'        => [],
						'default_search' => [],
					],
				],
			],
		];
	}
}
