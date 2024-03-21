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

		$this->ep_factory->post->create();
		$this->ep_factory->post->create();
		$this->ep_factory->post->create(
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

		$this->ep_factory->post->create();
		$this->ep_factory->post->create(
			array(
				'post_content'   => 'image',
				'post_type'      => 'attachment',
				'post_mime_type' => 'image',
			)
		);
		$this->ep_factory->post->create(
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

		$this->ep_factory->post->create(
			array(
				'post_content' => 'findme',
				'post_type'    => 'post',
			)
		);
		$this->ep_factory->post->create(
			array(
				'post_content'   => '',
				'post_type'      => 'attachment',
				'post_mime_type' => 'image',
			)
		);
		$this->ep_factory->post->create(
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

	/**
	 * Tests query doesn't return the post if `ep_exclude_from_search` meta is set.
	 *
	 * @since 4.7.0
	 */
	public function testExcludeFromSearchQuery() {
		ElasticPress\Features::factory()->activate_feature( 'search' );
		ElasticPress\Features::factory()->activate_feature( 'documents' );
		ElasticPress\Features::factory()->setup_features();

		$this->ep_factory->post->create_many(
			2,
			array(
				'post_content'   => 'find me in search',
				'meta_input'     => array( 'ep_exclude_from_search' => false ),
				'post_type'      => 'attachment',
				'post_mime_type' => 'application/msword',
			)
		);
		$this->ep_factory->post->create(
			array(
				'post_content'   => 'exclude from search',
				'meta_input'     => array( 'ep_exclude_from_search' => true ),
				'post_type'      => 'attachment',
				'post_mime_type' => 'application/msword',
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$args  = array(
			's' => 'search',
		);
		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 2, $query->post_count );
	}

	/**
	 * Test that search in media library is working correctly.
	 *
	 * @since 5.1.0
	 * @group documents
	 */
	public function testQueryForAttachments() {
		define( 'DOING_AJAX', true );
		ElasticPress\Features::factory()->activate_feature( 'search' );
		ElasticPress\Features::factory()->activate_feature( 'documents' );
		ElasticPress\Features::factory()->activate_feature( 'protected_content' );
		ElasticPress\Features::factory()->setup_features();

		$this->ep_factory->post->create(
			array(
				'post_content'   => 'search me',
				'post_type'      => 'attachment',
				'post_mime_type' => 'image',
			)
		);

		ElasticPress\Elasticsearch::factory()->refresh_indices();

		$_REQUEST['action'] = 'query-attachments';
		$args               = array(
			's'           => 'search me',
			'post_type'   => 'attachment',
			'post_status' => 'inherit',
		);

		$query = new \WP_Query( $args );

		$this->assertTrue( $query->elasticsearch_success );
		$this->assertEquals( 1, $query->post_count );
	}
}
