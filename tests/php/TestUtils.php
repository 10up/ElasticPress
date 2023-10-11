<?php
/**
 * Test utils functionality
 *
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress;
use ElasticPress\Utils;

/**
 * Dashboard test class
 */
class TestUtils extends BaseTestCase {

	/**
	 * Setup each test.
	 *
	 * @since 3.2
	 */
	public function set_up() {
		global $wpdb;
		parent::set_up();
		$wpdb->suppress_errors();

		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		grant_super_admin( $admin_id );

		wp_set_current_user( $admin_id );

		ElasticPress\Elasticsearch::factory()->delete_all_indices();
		ElasticPress\Indexables::factory()->get( 'post' )->put_mapping();

		ElasticPress\Indexables::factory()->get( 'post' )->sync_manager->reset_sync_queue();

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
	public function tear_down() {
		parent::tear_down();

		// Update since we are deleting to test notifications
		update_site_option( 'ep_host', $this->current_host );

		ElasticPress\Screen::factory()->set_current_screen( null );
	}

	/**
	 * Check that a site is indexable by default
	 *
	 * @since 3.2
	 * @group utils
	 * @group skip-on-single-site
	 */
	public function testIsSiteIndexableByDefault() {
		delete_site_meta( get_current_blog_id(), 'ep_indexable' );

		$this->assertTrue( ElasticPress\Utils\is_site_indexable() );
	}

	/**
	 * Check that a spam site is NOT indexable by default
	 *
	 * @since 3.2
	 * @group utils
	 * @group skip-on-single-site
	 */
	public function testIsSiteIndexableByDefaultSpam() {
		delete_site_meta( get_current_blog_id(), 'ep_indexable' );

		update_blog_status( get_current_blog_id(), 'spam', 1 );

		$this->assertFalse( ElasticPress\Utils\is_site_indexable() );

		update_blog_status( get_current_blog_id(), 'spam', 0 );
	}

	/**
	 * Check that a site is not indexable after being set that way in the admin
	 *
	 * @since 3.2
	 * @group utils
	 * @group skip-on-single-site
	 */
	public function testIsSiteIndexableDisabled() {
		update_site_meta( get_current_blog_id(), 'ep_indexable', 'no' );
		$this->assertFalse( ElasticPress\Utils\is_site_indexable() );
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
				'token'    => 'able <script>alert("baker");</script>',
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
				'token'    => 'my-token',
			]
		);

		$this->assertTrue( is_array( $creds ) );

		$this->assertArrayHasKey( 'username', $creds );
		$this->assertArrayHasKey( 'token', $creds );

		$this->assertSame( 'my-user-name', $creds['username'] );
		$this->assertSame( 'my-token', $creds['token'] );
	}

	/**
	 * Tests the is_indexing function.
	 *
	 * @return void
	 */
	public function testIsIndexing() {

		if ( is_multisite() ) {
			update_site_option( 'ep_index_meta', [ 'method' => 'test' ] );
		} else {
			update_option( 'ep_index_meta', [ 'method' => 'test' ] );
		}

		$this->assertTrue( ElasticPress\Utils\is_indexing() );

		if ( is_multisite() ) {
			delete_site_option( 'ep_index_meta' );
		} else {
			delete_option( 'ep_index_meta' );
		}

		$this->assertFalse( ElasticPress\Utils\is_indexing() );
	}

	/**
	 * Test the get_sync_url method
	 *
	 * @since 4.4.0
	 */
	public function testGetSyncUrl() {
		/**
		 * Test without the $do_sync parameter
		 */
		$sync_url = ElasticPress\Utils\get_sync_url();
		$this->assertStringNotContainsString( '&do_sync', $sync_url );
		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			$this->assertStringContainsString( 'wp-admin/network/admin.php?page=elasticpress-sync', $sync_url );
		} else {
			$this->assertStringContainsString( 'wp-admin/admin.php?page=elasticpress-sync', $sync_url );
		}

		/**
		 * Test with the $do_sync parameter
		 */
		$sync_url = ElasticPress\Utils\get_sync_url( true );
		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			$this->assertStringContainsString( 'wp-admin/network/admin.php?page=elasticpress-sync&do_sync', $sync_url );
		} else {
			$this->assertStringContainsString( 'wp-admin/admin.php?page=elasticpress-sync&do_sync', $sync_url );
		}
	}

	/**
	 * Test the `get_request_id_base` function
	 *
	 * @since 4.5.0
	 */
	public function testGetRequestIdBase() {
		/**
		 * Use the `ep_index_prefix` filter so `get_index_prefix()` can return something.
		 */
		$custom_index_prefix = function() {
			return 'custom-prefix';
		};
		add_filter( 'ep_index_prefix', $custom_index_prefix );
		$this->assertEquals( 'customprefix', Utils\get_request_id_base() ); // `-` are removed

		/**
		 * Test the `ep_request_id_base` filter
		 */
		$custom_request_id_base = function( $base ) {
			return $base . '-plus';
		};
		add_filter( 'ep_request_id_base', $custom_request_id_base );
		$this->assertEquals( 'customprefix-plus', Utils\get_request_id_base() );
	}

	/**
	 * Test the `generate_request_id` function
	 *
	 * @since 4.5.0
	 */
	public function testGenerateRequestId() {
		$this->assertMatchesRegularExpression( '/[0-9a-f]{32}/', Utils\generate_request_id() );

		/**
		 * Use the `ep_request_id_base` filter so `get_request_id_base()` can return something.
		 */
		$custom_request_id_base = function() {
			return 'indexprefix';
		};
		add_filter( 'ep_request_id_base', $custom_request_id_base );
		$this->assertMatchesRegularExpression( '/indexprefix[0-9a-f]{32}/', Utils\generate_request_id() );

		/**
		 * Test the `ep_request_id` filter
		 */
		$custom_request_id = function( $request_id ) {
			$this->assertMatchesRegularExpression( '/indexprefix[0-9a-f]{32}/', $request_id );
			return 'totally-new-request-id';
		};
		add_filter( 'ep_request_id', $custom_request_id );
		$this->assertEquals( 'totally-new-request-id', Utils\generate_request_id() );
	}

	/**
	 * Test the `get_capability` function
	 *
	 * @since 4.5.0
	 */
	public function testGetCapability() {
		$this->assertSame( 'manage_elasticpress', Utils\get_capability() );

		/**
		 * Test the `ep_capability` filter.
		 */
		$change_cap_name = function( $cap ) {
			$this->assertSame( 'manage_elasticpress', $cap );
			return 'custom_manage_ep';
		};
		add_filter( 'ep_capability', $change_cap_name );

		$this->assertSame( 'custom_manage_ep', Utils\get_capability() );
	}

	/**
	 * Test the `get_network_capability` function
	 *
	 * @since 4.5.0
	 */
	public function testGetNetworkCapability() {
		$this->assertSame( 'manage_network_elasticpress', Utils\get_network_capability() );

		/**
		 * Test the `ep_network_capability` filter.
		 */
		$change_cap_name = function( $cap ) {
			$this->assertSame( 'manage_network_elasticpress', $cap );
			return 'custom_manage_network_ep';
		};
		add_filter( 'ep_network_capability', $change_cap_name );

		$this->assertSame( 'custom_manage_network_ep', Utils\get_network_capability() );
	}

	/**
	 * Test the `get_post_map_capabilities` function
	 *
	 * @since 4.5.0
	 */
	public function testGetPostMapCapabilities() {
		$expected = [
			'edit_post'          => 'manage_elasticpress',
			'edit_posts'         => 'manage_elasticpress',
			'edit_others_posts'  => 'manage_elasticpress',
			'publish_posts'      => 'manage_elasticpress',
			'read_post'          => 'manage_elasticpress',
			'read_private_posts' => 'manage_elasticpress',
			'delete_post'        => 'manage_elasticpress',
		];

		$this->assertSame( $expected, Utils\get_post_map_capabilities() );
	}

	/**
	 * Test the `get_elasticsearch_error_reason` function
	 *
	 * @since 4.6.0
	 */
	public function testGetElasticsearchErrorReason() {
		// Strings should be returned without any change
		$this->assertSame( 'Some message', Utils\get_elasticsearch_error_reason( 'Some message' ) );

		// Objects will be returned after passing through var_export()
		$object = (object) [ 'attribute' => 'this will be an object' ];
		$return = Utils\get_elasticsearch_error_reason( $object );
		$this->assertIsString( $return );
		$this->assertStringContainsString( 'attribute', $return );
		$this->assertStringContainsString( 'this will be an object', $return );

		// `reason` in the array root
		$reason_root = [ 'reason' => 'Error reason' ];
		$this->assertSame( 'Error reason', Utils\get_elasticsearch_error_reason( $reason_root ) );

		// array with `error`
		$reason_in_single_error_array = [
			'result' => [
				'error' => [
					'root_cause' => [
						[
							'reason' => 'Error reason',
						],
					],
				],
			],
		];
		$this->assertSame( 'Error reason', Utils\get_elasticsearch_error_reason( $reason_in_single_error_array ) );

		// array with `errors`
		$reason_in_errors_array = [
			'result' => [
				'errors' => [
					'some error',
				],
				'items'  => [
					[
						'index' => [
							'error' => [
								'reason' => 'Error reason',
							],
						],
					],
				],
			],
		];
		$this->assertSame( 'Error reason', Utils\get_elasticsearch_error_reason( $reason_in_errors_array ) );

		// For something that is an array but does not have a format of an error, return an empty string
		$not_an_error = [
			'results' => [ 1, 2, 3 ],
		];
		$this->assertSame( '', Utils\get_elasticsearch_error_reason( $not_an_error ) );
	}

	/**
	 * Test the `set_transient` function
	 *
	 * @since 4.7.0
	 * @group utils
	 */
	public function test_set_transient() {
		$filter_name = is_multisite() ?
			'expiration_of_site_transient_foo' :
			'expiration_of_transient_foo';

		$check_expiration = function ( $expiration ) {
			$this->assertSame( 1, $expiration );
			return $expiration;
		};
		add_filter( $filter_name, $check_expiration );

		Utils\set_transient( 'foo', 'bar', 1 );

		$this->assertSame( 1, did_filter( $filter_name ) );
	}

	/**
	 * Test the `get_transient` function
	 *
	 * @since 4.7.0
	 * @group utils
	 */
	public function test_get_transient() {
		Utils\get_transient( 'foo' );

		$filter_name = is_multisite() ?
			'pre_site_transient_foo' :
			'pre_transient_foo';

		$this->assertSame( 1, did_filter( $filter_name ) );
	}

	/**
	 * Test the `delete_transient` function
	 *
	 * @since 4.7.0
	 * @group utils
	 */
	public function test_delete_transient() {
		Utils\delete_transient( 'foo' );

		$filter_name = is_multisite() ?
			'delete_site_transient_foo' :
			'delete_transient_foo';

		$this->assertSame( 1, did_action( $filter_name ) );
	}

	/**
	 * Test the `get_language()` method
	 *
	 * @since 4.7.0
	 * @group utils
	 */
	public function test_get_language() {
		$this->assertSame( 'site-default', Utils\get_language() );

		$set_lang_via_option = function() {
			return 'custom_via_option';
		};
		if ( is_multisite() ) {
			add_filter( 'pre_site_option_ep_language', $set_lang_via_option );
		} else {
			add_filter( 'pre_option_ep_language', $set_lang_via_option );
		}

		$this->assertSame( 'custom_via_option', Utils\get_language() );

		/**
		 * Test the `ep_default_language` filter
		 */
		$set_lang_via_filter = function( $ep_language ) {
			$this->assertSame( 'custom_via_option', $ep_language );
			return 'custom_via_filter';
		};
		add_filter( 'ep_default_language', $set_lang_via_filter );

		$this->assertSame( 'custom_via_filter', Utils\get_language() );
	}

	/**
	 * Test the `get_sites()` method on a single site
	 *
	 * @since 4.7.0
	 * @group utils
	 * @group skip-on-multi-site
	 */
	public function test_get_sites_on_single_site() {
		$this->assertSame( [], Utils\get_sites() );
	}

	/**
	 * Test the `get_sites()` method on a multisite
	 *
	 * @since 4.7.0
	 * @group utils
	 * @group skip-on-single-site
	 */
	public function test_get_sites_on_multi_site() {
		$this->factory->blog->create_object(
			[
				'domain' => 'example2.org',
				'title'  => 'Example Site 2',
			]
		);
		$this->assertCount( 2, Utils\get_sites() );

		$this->factory->blog->create_object(
			[
				'domain' => 'example3.org',
				'title'  => 'Example Site 3',
				'spam'   => 1,
			]
		);
		$this->assertCount( 3, Utils\get_sites() );

		$this->factory->blog->create_object(
			[
				'domain'  => 'example4.org',
				'title'   => 'Example Site 4',
				'deleted' => 1,
			]
		);
		$this->assertCount( 4, Utils\get_sites() );

		$this->factory->blog->create_object(
			[
				'domain'   => 'example5.org',
				'title'    => 'Example Site 5',
				'archived' => 1,
			]
		);
		$this->assertCount( 5, Utils\get_sites() );

		$blog_6 = $this->factory->blog->create_object(
			[
				'domain' => 'example6.org',
				'title'  => 'Example Site 6',
			]
		);
		update_site_meta( $blog_6, 'ep_indexable', 'no' );
		$this->assertCount( 6, Utils\get_sites() );

		// Test the `$only_indexable` parameter
		$this->assertCount( 2, Utils\get_sites( 0, true ) );

		// Test the `$limit` parameter
		$this->assertCount( 1, Utils\get_sites( 1, true ) );
	}

	/**
	 * Test the `ep_indexable_sites_args` filter in the `get_sites()` method
	 *
	 * @since 4.7.0
	 * @group utils
	 * @group skip-on-single-site
	 */
	public function test_get_sites_ep_indexable_sites_args_filter() {
		$add_args = function ( $args ) {
			$this->assertSame( 3, $args['number'] );
			return $args;
		};
		add_filter( 'ep_indexable_sites_args', $add_args );

		Utils\get_sites( 3 );
		$this->assertGreaterThanOrEqual( 1, did_filter( 'ep_indexable_sites_args' ) );
	}

	/**
	 * Test the `ep_indexable_sites` filter in the `get_sites()` method
	 *
	 * @since 4.7.0
	 * @group utils
	 * @group skip-on-single-site
	 */
	public function test_get_sites_ep_indexable_sites_filter() {
		$add_site = function ( $sites ) {
			$this->assertIsArray( $sites );
			$sites['test'] = true;
			return $sites;
		};
		add_filter( 'ep_indexable_sites', $add_site );

		$sites = Utils\get_sites();
		$this->assertGreaterThanOrEqual( 1, did_filter( 'ep_indexable_sites' ) );
		$this->assertTrue( $sites['test'] );
	}
}
