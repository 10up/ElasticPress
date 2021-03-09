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
}
