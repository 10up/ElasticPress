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
		ElasticPress\Features::factory()->activate_feature( 'synonyms' );
	}

	/**
	 * Clean up after each test. Reset our mocks
	 *
	 * @since 2.1
	 */
	public function tear_down() {
		parent::tear_down();

		$this->fired_actions = array();
	}

	/**
	 * Return a Synonyms instance
	 *
	 * @return Synonyms
	 */
	public function getFeature() {
		return new Synonyms();
	}

	/**
	 * Test class constructor
	 */
	public function testConstructor() {
		$instance = $this->getFeature();

		$this->assertSame( 'ep_synonyms_filter', $instance->filter_name );
		$this->assertIsArray( $instance->affected_indices );
		$this->assertContains( 'post', $instance->affected_indices );
	}

	/**
	 * Test the `get_synonym_post_id` method.
	 */
	public function testGetSynonymPostId() {
		$instance = $this->getFeature();

		$post_id = $instance->get_synonym_post_id();
		$this->assertGreaterThan( 0, $post_id );
	}

	/**
	 * Test the `get_synonyms_raw` method.
	 */
	public function testGetSynonymsRaw() {
		$instance = $this->getFeature();

		$synonyms = $instance->get_synonyms_raw();

		$this->assertNotEmpty( $synonyms );
	}

	/**
	 * Test the `get_synonyms` method.
	 */
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

	/**
	 * Test the `validate_synonym` method.
	 */
	public function testValidateSynonyms() {
		$instance = $this->getFeature();

		$this->assertFalse( $instance->validate_synonym( '' ) );
		$this->assertFalse( $instance->validate_synonym( '# Comments are not valid.' ) );
		$this->assertFalse( $instance->validate_synonym( '// Comments are not valid.' ) );
		$this->assertEquals( 'foo, bar', $instance->validate_synonym( ' foo, bar ' ) );
		$this->assertEquals( 'foo => bar', $instance->validate_synonym( ' foo => bar ' ) );
	}

	/**
	 * Test synonyms with spaces
	 *
	 * @since 5.0.0
	 * @group synonyms
	 * @group skip-on-single-site
	 */
	public function test_synonyms_with_spaces() {
		$instance = $this->getFeature();

		wp_insert_post(
			[
				'ID'           => $instance->get_synonym_post_id(),
				'post_content' => 'internet of things, IoT',
				'post_type'    => $instance::POST_TYPE_NAME,
			],
			true
		);
		$instance->update_synonyms();

		$post_id = $this->ep_factory->post->create( [ 'post_content' => 'IoT' ] );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$query = new \WP_Query(
			[
				's'      => 'internet of things',
				'fields' => 'ids',
			]
		);

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertSame( $post_id, $query->posts['0'] );
	}

	/**
	 * Tests synonyms are case insensitive
	 *
	 * @since 5.1.0
	 * @group synonyms
	 */
	public function test_synonyms_case_insensitive() {
		$instance = $this->getFeature();

		$this->ep_factory->post->create(
			[
				'ID'           => $instance->get_synonym_post_id(),
				'post_content' => 'hoodie, sweatshirt',
				'post_type'    => $instance::POST_TYPE_NAME,
			]
		);

		$instance->update_synonyms();

		$post_id = $this->ep_factory->post->create( [ 'post_content' => 'sweatshirt' ] );

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$query = new \WP_Query(
			[
				's'      => 'HoOdiE',
				'fields' => 'ids',
			]
		);
		$this->assertTrue( $query->elasticsearch_success );
		$this->assertSame( $post_id, $query->posts['0'] );

		$query = new \WP_Query(
			[
				's'      => 'HOODIE',
				'fields' => 'ids',
			]
		);
		$this->assertTrue( $query->elasticsearch_success );
		$this->assertSame( $post_id, $query->posts['0'] );
	}
}
