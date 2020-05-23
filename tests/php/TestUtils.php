<?php
/**
 * Test utils functionality
 *
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress;

/**
 * Dashboard test class
 */
class TestUtils extends BaseTestCase {

	/**
	 * Setup each test.
	 *
	 * @since 3.2
	 */
	public function setUp() {
		global $wpdb;
		parent::setUp();
		$wpdb->suppress_errors();

		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		grant_super_admin( $admin_id );

		wp_set_current_user( $admin_id );

		ElasticPress\Elasticsearch::factory()->delete_all_indices();
		ElasticPress\Indexables::factory()->get( 'post' )->put_mapping();

		ElasticPress\Indexables::factory()->get( 'post' )->sync_manager->sync_queue = [];

		$this->setup_test_post_type();

		$this->current_host = get_option( 'ep_host' );

		global $hook_suffix;
		$hook_suffix = 'sites.php';
		set_current_screen();
	}

	/**
	 * Clean up after each test.
	 *
	 * @since 3.2
	 */
	public function tearDown() {
		parent::tearDown();

		// Update since we are deleting to test notifications
		update_site_option( 'ep_host', $this->current_host );

		ElasticPress\Screen::factory()->set_current_screen( null );
	}

	/**
	 * Check that a site is indexable by default
	 *
	 * @since 3.2
	 * @group utils
	 */
	public function testIsSiteIndexableByDefault() {
		delete_option( 'ep_indexable' );

		$this->assertTrue( ElasticPress\Utils\is_site_indexable() );
	}

	/**
	 * Check that a spam site is NOT indexable by default
	 *
	 * @since 3.2
	 * @group utils
	 */
	public function testIsSiteIndexableByDefaultSpam() {
		delete_option( 'ep_indexable' );

		update_blog_status( get_current_blog_id(), 'spam', 1 );

		$this->assertFalse( ElasticPress\Utils\is_site_indexable() );

		update_blog_status( get_current_blog_id(), 'spam', 0 );
	}

	/**
	 * Check that a site is not indexable after being set that way in the admin
	 *
	 * @since 3.2
	 * @group utils
	 */
	public function testIsSiteIndexableDisabled() {
		update_option( 'ep_indexable', 'no' );

		$this->assertFalse( ElasticPress\Utils\is_site_indexable() );

		delete_option( 'ep_indexable' );
	}

	/**
	 * Tests the sanitize_credentials utils function.
	 *
	 * @return void
	 */
	public function testSanitizeCredentials() {

		// First test anything that is not an array.
		$creds = \ElasticPress\Utils\sanitize_credentials( false );
		$this->assertTrue( is_array( $creds ) );

		$this->assertArrayHasKey( 'username', $creds );
		$this->assertArrayHasKey( 'token', $creds );

		$this->assertSame( '', $creds['username'] );
		$this->assertSame( '', $creds['token'] );

		// Then test arrays with invalid data.
		$creds = \ElasticPress\Utils\sanitize_credentials( [] );

		$this->assertTrue( is_array( $creds ) );

		$this->assertArrayHasKey( 'username', $creds );
		$this->assertArrayHasKey( 'token', $creds );

		$this->assertSame( '', $creds['username'] );
		$this->assertSame( '', $creds['token'] );

		$creds = \ElasticPress\Utils\sanitize_credentials(
			[
				'username' => '<strong>hello</strong> world',
				'token' => 'able <script>alert("baker");</script>',
			]
		);

		$this->assertTrue( is_array( $creds ) );

		$this->assertArrayHasKey( 'username', $creds );
		$this->assertArrayHasKey( 'token', $creds );

		$this->assertSame( 'hello world', $creds['username'] );
		$this->assertSame( 'able', $creds['token'] );

		// Finally, test with valid data.
		$creds = \ElasticPress\Utils\sanitize_credentials(
			[
				'username' => 'my-user-name',
				'token' => 'my-token',
			]
		);

		$this->assertTrue( is_array( $creds ) );

		$this->assertArrayHasKey( 'username', $creds );
		$this->assertArrayHasKey( 'token', $creds );

		$this->assertSame( 'my-user-name', $creds['username'] );
		$this->assertSame( 'my-token', $creds['token'] );
	}
}
