<?php
/**
 * Test synonym feature
 *
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress;
use ElasticPress\Feature\Synonyms\Synonyms;

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
		delete_option( 'elasticpress_synonyms_post_id' );
		delete_site_option( 'elasticpress_synonyms_post_id' );
	}

	public function getFeature() {
		return new Synonyms();
	}

	public function testConstructor() {
		$instance = $this->getFeature();

		$this->assertEquals( $instance->slug, 'synonyms' );
		$this->assertTrue( $instance->requires_install_reindex );
	}

	public function testOutputFeatureBoxSummary() {
		$instance = $this->getFeature();

		ob_start();
		$instance->output_feature_box_summary();

		$this->assertContains( 'Add synonyms to your searches.', ob_get_clean() );
	}

	public function testOutputFeatureBoxLong() {
		$instance = $this->getFeature();

		ob_start();
		$instance->output_feature_box_long();

		$this->assertContains( 'Create a custom synonym filter', ob_get_clean() );
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
}
