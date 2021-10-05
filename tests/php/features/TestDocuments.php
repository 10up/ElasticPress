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
class TestDocuments extends BaseTestCase {

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

	/**
	 * Test that search is integrating with allowed mime type
	 *
	 * @since 2.3
	 * @group documents
	 */
	public function testSearchAllowedMimeType() {
		ElasticPress\Features::factory()->activate_feature( 'search' );
		ElasticPress\Features::factory()->activate_feature( 'documents' );
		ElasticPress\Features::factory()->setup_features();

		// Need to call this since it's hooked to init
		ElasticPress\Features::factory()->get_registered_feature( 'search' )->search_setup();

		$post_ids = array();

		Functions\create_and_sync_post();
		Functions\create_and_sync_post();
		Functions\create_and_sync_post(
			array(
				'post_content'   => 'findme',
				'post_type'      => 'attachment',
				'post_mime_type' => 'application/msword',
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's'         => 'findme',
			'post_type' => array( 'post', 'attachment' ),
		);

		$query = new \WP_Query( $args );
		$this->assertTrue( $query->elasticsearch_success );

		$this->assertEquals( 1, count( $query->posts ) );
	}

	/**
	 * Test that search isn't integrating with disallowed mime type
	 *
	 * @since 2.3
	 * @group documents
	 */
	public function testSearchDisallowedMimeType() {
		ElasticPress\Features::factory()->activate_feature( 'search' );
		ElasticPress\Features::factory()->activate_feature( 'documents' );
		ElasticPress\Features::factory()->setup_features();

		// Need to call this since it's hooked to init
		ElasticPress\Features::factory()->get_registered_feature( 'search' )->search_setup();

		$post_ids = array();

		Functions\create_and_sync_post();
		Functions\create_and_sync_post(
			array(
				'post_content'   => 'image',
				'post_type'      => 'attachment',
				'post_mime_type' => 'image',
			)
		);
		Functions\create_and_sync_post(
			array(
				'post_content'   => 'findme',
				'post_type'      => 'attachment',
				'post_mime_type' => 'bad',
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's'         => 'findme',
			'post_type' => array( 'post', 'attachment' ),
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 0, count( $query->posts ) );
	}

	/**
	 * Test finding only a normal post
	 *
	 * @since 2.3
	 * @group documents
	 */
	public function testSearchNormalPost() {
		ElasticPress\Features::factory()->activate_feature( 'search' );
		ElasticPress\Features::factory()->activate_feature( 'documents' );
		ElasticPress\Features::factory()->setup_features();

		// Need to call this since it's hooked to init
		ElasticPress\Features::factory()->get_registered_feature( 'search' )->search_setup();

		$post_ids = array();

		Functions\create_and_sync_post(
			array(
				'post_content' => 'findme',
				'post_type'    => 'post',
			)
		);
		Functions\create_and_sync_post(
			array(
				'post_content'   => '',
				'post_type'      => 'attachment',
				'post_mime_type' => 'image',
			)
		);
		Functions\create_and_sync_post(
			array(
				'post_content'   => '',
				'post_type'      => 'attachment',
				'post_mime_type' => 'bad',
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args = array(
			's'         => 'findme',
			'post_type' => array( 'post', 'attachment' ),
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 1, count( $query->posts ) );
	}
}
