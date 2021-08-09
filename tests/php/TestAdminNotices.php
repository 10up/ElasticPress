<?php
/**
 * Test dashboard admin notices. Logic here is very complex.
 *
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress;

/**
 * Admin notices test class
 */
class TestAdminNotices extends BaseTestCase {

	/**
	 * Setup each test.
	 *
	 * @since 2.2
	 */
	public function setUp() {
		global $wpdb;
		parent::setUp();
		$wpdb->suppress_errors();

		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		grant_super_admin( $admin_id );

		wp_set_current_user( $admin_id );

		$this->real_es_version = ElasticPress\Elasticsearch::factory()->get_elasticsearch_version( true );

		add_filter(
			'ep_elasticsearch_version',
			function() {
				return (int) EP_ES_VERSION_MAX - 1;
			}
		);

		ElasticPress\Elasticsearch::factory()->delete_all_indices();
		ElasticPress\Indexables::factory()->get( 'post' )->put_mapping();

		ElasticPress\Indexables::factory()->get( 'post' )->sync_manager->sync_queue = [];

		$this->setup_test_post_type();

		$this->current_host = get_option( 'ep_host' );

		// always ensure mappings line up to avoid false positive notices,
		// even if the ES version changes.
		add_filter( 'ep_post_mapping_version_determined', [ $this, 'ep_post_mapping_version_determined' ] );

		global $hook_suffix;
		$hook_suffix = 'sites.php';
		set_current_screen();
	}

	/**
	 * Clean up after each test.
	 *
	 * @since 2.2
	 */
	public function tearDown() {
		parent::tearDown();

		// Update since we are deleting to test notifications
		update_site_option( 'ep_host', $this->current_host );

		ElasticPress\Screen::factory()->set_current_screen( null );
	}

	/**
	 * Conditions:
	 *
	 * - On install
	 * - No host set
	 * - No sync has occurred
	 * - No upgrade sync is needed
	 * - Not feature auto activate sync is needed
	 * - Elasticsearch version within bounds
	 * - Autosuggest not active
	 *
	 * Do: Show no notices on install
	 *
	 * @group admin-notices
	 * @since 3.0
	 */
	public function testNoNoticesOnInstall() {
		delete_site_option( 'ep_host' );
		delete_site_option( 'ep_last_sync' );
		delete_site_option( 'ep_need_upgrade_sync', true );
		delete_site_option( 'ep_feature_auto_activated_sync' );

		ElasticPress\Screen::factory()->set_current_screen( 'install' );

		ElasticPress\AdminNotices::factory()->process_notices();

		$notices = ElasticPress\AdminNotices::factory()->get_notices();

		$this->assertEquals( 0, count( $notices ) );
	}

	/**
	 * Conditions:
	 *
	 * - In admin, not on EP screen
	 * - No host set
	 * - No sync has occurred
	 * - No upgrade sync is needed
	 * - Not feature auto activate sync is needed
	 * - Elasticsearch version within bounds
	 * - Autosuggest not active
	 *
	 * Do: Show need setup notice
	 *
	 * @group admin-notices
	 * @since 3.0
	 */
	public function testNeedSetupNoticeInAdmin() {
		delete_site_option( 'ep_host' );
		delete_site_option( 'ep_last_sync' );
		delete_site_option( 'ep_need_upgrade_sync', true );
		delete_site_option( 'ep_feature_auto_activated_sync' );

		ElasticPress\Screen::factory()->set_current_screen( null );

		ElasticPress\AdminNotices::factory()->process_notices();

		$notices = ElasticPress\AdminNotices::factory()->get_notices();

		$this->assertEquals( 1, count( $notices ) );
		$this->assertTrue( ! empty( $notices['need_setup'] ) );
	}

	/**
	 * Conditions:
	 *
	 * - In admin, not on EP screen
	 * - No host set
	 * - No sync has occurred
	 * - No upgrade sync is needed
	 * - Not feature auto activate sync is needed
	 * - Elasticsearch version within bounds
	 * - Autosuggest not active
	 *
	 * Do: Show need setup notice
	 *
	 * @group admin-notices
	 * @since 3.0
	 */
	public function testNoSyncNoticeInAdmin() {
		delete_site_option( 'ep_last_sync' );
		delete_site_option( 'ep_need_upgrade_sync', true );
		delete_site_option( 'ep_feature_auto_activated_sync' );

		ElasticPress\Screen::factory()->set_current_screen( null );

		ElasticPress\AdminNotices::factory()->process_notices();

		$notices = ElasticPress\AdminNotices::factory()->get_notices();

		$this->assertEquals( 1, count( $notices ) );
		$this->assertTrue( ! empty( $notices['no_sync'] ) );
	}

	/**
	 * Conditions:
	 *
	 * - On install
	 * - No host set
	 * - No sync has occurred
	 * - No upgrade sync is needed
	 * - Not feature auto activate sync is needed
	 * - Elasticsearch version within bounds
	 * - Autosuggest not active
	 *
	 * Do: Show nothing
	 *
	 * @group admin-notices
	 * @since 3.0
	 */
	public function testNoSyncNoticeInInstall() {
		delete_site_option( 'ep_last_sync' );
		delete_site_option( 'ep_need_upgrade_sync', true );
		delete_site_option( 'ep_feature_auto_activated_sync' );

		ElasticPress\Screen::factory()->set_current_screen( 'install' );

		ElasticPress\AdminNotices::factory()->process_notices();

		$notices = ElasticPress\AdminNotices::factory()->get_notices();

		$this->assertEquals( 0, count( $notices ) );
	}

	/**
	 * Conditions:
	 *
	 * - In admin
	 * - Bad host
	 * - Sync has occurred
	 * - No upgrade sync is needed
	 * - Not feature auto activate sync is needed
	 * - Elasticsearch version within bounds
	 * - Autosuggest not active
	 *
	 * Do: Show host error notice
	 *
	 * @group admin-notices
	 * @since 3.0
	 */
	public function testHostErrorNoticeInAdmin() {
		update_site_option( 'ep_host', 'badhost' );
		update_site_option( 'ep_last_sync', time() );
		delete_site_option( 'ep_need_upgrade_sync', true );
		delete_site_option( 'ep_feature_auto_activated_sync' );

		remove_all_filters( 'ep_elasticsearch_version' );

		ElasticPress\Elasticsearch::factory()->get_elasticsearch_version( true );

		ElasticPress\Screen::factory()->set_current_screen( null );

		ElasticPress\AdminNotices::factory()->process_notices();

		$notices = ElasticPress\AdminNotices::factory()->get_notices();

		$this->assertEquals( 1, count( $notices ) );
		$this->assertTrue( ! empty( $notices['host_error'] ) );
	}

	/**
	 * Conditions:
	 *
	 * - On install
	 * - Bad host
	 * - Sync has occurred
	 * - No upgrade sync is needed
	 * - Not feature auto activate sync is needed
	 * - Elasticsearch version within bounds
	 * - Autosuggest not active
	 *
	 * Do: Show none
	 *
	 * @group admin-notices
	 * @since 3.0
	 */
	public function testHostErrorNoticeInInstall() {
		update_site_option( 'ep_host', 'badhost' );
		update_site_option( 'ep_last_sync', time() );
		delete_site_option( 'ep_need_upgrade_sync', true );
		delete_site_option( 'ep_feature_auto_activated_sync' );

		ElasticPress\Elasticsearch::factory()->get_elasticsearch_version( true );

		ElasticPress\Screen::factory()->set_current_screen( 'install' );

		ElasticPress\AdminNotices::factory()->process_notices();

		$notices = ElasticPress\AdminNotices::factory()->get_notices();

		$this->assertEquals( 0, count( $notices ) );

		update_site_option( 'ep_host', $this->current_host );
		ElasticPress\Elasticsearch::factory()->get_elasticsearch_version( true );
	}

	/**
	 * Conditions:
	 *
	 * - In admin
	 * - Host set
	 * - Sync has occurred
	 * - No upgrade sync is needed
	 * - Not feature auto activate sync is needed
	 * - Elasticsearch version above bounds
	 * - Autosuggest not active
	 *
	 * Do: Show es above compat
	 *
	 * @group admin-notices
	 * @since 3.0
	 */
	public function testEsAboveCompatNoticeInAdmin() {
		update_site_option( 'ep_last_sync', time() );
		delete_site_option( 'ep_need_upgrade_sync', true );
		delete_site_option( 'ep_feature_auto_activated_sync' );

		$es_version = function() {
			return '100';
		};

		add_filter( 'ep_elasticsearch_version', $es_version );

		ElasticPress\Screen::factory()->set_current_screen( null );

		ElasticPress\AdminNotices::factory()->process_notices();

		$notices = ElasticPress\AdminNotices::factory()->get_notices();

		remove_filter( 'ep_elasticsearch_version', $es_version );

		$this->assertEquals( 1, count( $notices ) );
		$this->assertTrue( ! empty( $notices['es_above_compat'] ) );
	}

	/**
	 * Conditions:
	 *
	 * - In admin
	 * - Host set
	 * - Sync has occurred
	 * - No upgrade sync is needed
	 * - Not feature auto activate sync is needed
	 * - Elasticsearch version above bounds
	 * - Autosuggest not active
	 *
	 * Do: Show es below compat
	 *
	 * @group admin-notices
	 * @since 3.0
	 */
	public function testEsBelowCompatNoticeInAdmin() {
		update_site_option( 'ep_last_sync', time() );
		delete_site_option( 'ep_need_upgrade_sync', true );
		delete_site_option( 'ep_feature_auto_activated_sync' );

		$es_version = function() {
			return '1';
		};

		add_filter( 'ep_elasticsearch_version', $es_version );

		ElasticPress\Screen::factory()->set_current_screen( null );

		ElasticPress\AdminNotices::factory()->process_notices();

		$notices = ElasticPress\AdminNotices::factory()->get_notices();

		remove_filter( 'ep_elasticsearch_version', $es_version );

		$this->assertEquals( 1, count( $notices ) );
		$this->assertTrue( ! empty( $notices['es_below_compat'] ) );
	}

	/**
	 * Conditions:
	 *
	 * - In admin
	 * - Host set
	 * - Sync has occurred
	 * - Upgrade sync is needed
	 * - No feature auto activate sync is needed
	 * - Elasticsearch version within bounds
	 * - Autosuggest not active
	 *
	 * Do: Show upgrade sync
	 *
	 * @group admin-notices
	 * @since 3.0
	 */
	public function testUpgradeSyncNoticeInAdmin() {
		update_site_option( 'ep_last_sync', time() );
		update_site_option( 'ep_need_upgrade_sync', true );
		delete_site_option( 'ep_feature_auto_activated_sync' );

		ElasticPress\Screen::factory()->set_current_screen( null );

		ElasticPress\AdminNotices::factory()->process_notices();

		$notices = ElasticPress\AdminNotices::factory()->get_notices();

		$this->assertEquals( 1, count( $notices ) );
		$this->assertTrue( ! empty( $notices['upgrade_sync'] ) );
	}

	/**
	 * Conditions:
	 *
	 * - In admin
	 * - Host set
	 * - Sync has occurred
	 * - No upgrade sync is needed
	 * - Feature auto activate sync is needed
	 * - Elasticsearch version within bounds
	 * - Autosuggest not active
	 *
	 * Do: Show auto activated sync
	 *
	 * @group admin-notices
	 * @since 3.0
	 */
	public function testFeatureSyncNoticeInAdmin() {
		update_site_option( 'ep_last_sync', time() );
		delete_site_option( 'ep_need_upgrade_sync' );
		update_site_option( 'ep_feature_auto_activated_sync', true );

		ElasticPress\Screen::factory()->set_current_screen( null );

		ElasticPress\AdminNotices::factory()->process_notices();

		$notices = ElasticPress\AdminNotices::factory()->get_notices();

		$this->assertEquals( 1, count( $notices ) );
		$this->assertTrue( ! empty( $notices['auto_activate_sync'] ) );
	}


	/**
	 * Conditions:
	 *
	 * - In admin
	 * - Host set
	 * - Sync has occurred
	 * - No upgrade sync is needed
	 * - Feature auto activate sync is not needed
	 * - Elasticsearch version within bounds
	 * - Autosuggest not active
	 * - Mapping version equals determined mapping version
	 *
	 * Do: Show no notice
	 *
	 * @group admin-notices
	 * @since 3.6.2
	 */
	public function testValidMappingNoticeInAdmin() {
		update_site_option( 'ep_last_sync', time() );
		delete_site_option( 'ep_need_upgrade_sync' );
		update_site_option( 'ep_feature_auto_activated_sync', false );

		// We need to do a proper sync with real version to ensure the index is in place
		// and we do not get a 404 when requesting the mapping version.
		$es_version = $this->real_es_version;
		add_filter(
			'ep_elasticsearch_version',
			function() use ( $es_version ) {
				return $es_version;
			}
		);

		ElasticPress\Elasticsearch::factory()->delete_all_indices();
		ElasticPress\Indexables::factory()->get( 'post' )->put_mapping();
		ElasticPress\Indexables::factory()->get( 'post' )->sync_manager->sync_queue = [];

		ElasticPress\Screen::factory()->set_current_screen( null );

		ElasticPress\AdminNotices::factory()->process_notices();

		$notices = ElasticPress\AdminNotices::factory()->get_notices();
		$this->assertCount( 0, $notices );
	}

	/**
	 * Conditions:
	 *
	 * - In admin
	 * - Host set
	 * - Sync has occurred
	 * - No upgrade sync is needed
	 * - Feature auto activate sync is not needed
	 * - Elasticsearch version within bounds
	 * - Autosuggest not active
	 * - ES Mapping Version <> Determined mapping
	 *
	 * Do: Show mapping notice
	 *
	 * @group admin-notices
	 * @since 3.6.2
	 */
	public function testInvalidMappingNoticeInAdmin() {
		update_site_option( 'ep_last_sync', time() );
		delete_site_option( 'ep_need_upgrade_sync' );
		update_site_option( 'ep_feature_auto_activated_sync', false );

		// We need to do a proper sync with real version to ensure the index is in place
		// and we do not get a 404 when requesting the mapping version.
		$es_version = $this->real_es_version;
		add_filter(
			'ep_elasticsearch_version',
			function() use ( $es_version ) {
				return $es_version;
			}
		);

		ElasticPress\Elasticsearch::factory()->delete_all_indices();
		ElasticPress\Indexables::factory()->get( 'post' )->put_mapping();
		ElasticPress\Indexables::factory()->get( 'post' )->sync_manager->sync_queue = [];

		$mapping = function() {
			return 'idonotmatch';
		};
		add_filter( 'ep_post_mapping_version_determined', $mapping );

		ElasticPress\Screen::factory()->set_current_screen( null );

		ElasticPress\AdminNotices::factory()->process_notices();

		$notices = ElasticPress\AdminNotices::factory()->get_notices();
		$this->assertCount( 1, $notices );
		$this->assertTrue( ! empty( $notices['maybe_wrong_mapping'] ) );
	}

	/**
	 * Utilitary function to set `ep_post_mapping_version_determined`
	 * as the wanted Mapping version.
	 *
	 * @return string
	 */
	public function ep_post_mapping_version_determined() {
		return ElasticPress\Indexables::factory()->get( 'post' )->get_mapping_name();
	}
}
