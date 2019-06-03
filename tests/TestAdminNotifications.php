<?php
/**
 * Test dashboard admin notifications. Logic here is very complex.
 *
 * @group elasticpress
 */
namespace ElasticPressTest;

use ElasticPress;

class TestAdminNotifications extends BaseTestCase {
	/**
	 * Checking if HTTP request returns 404 status code.
	 *
	 * @var boolean
	 */
	public $is_404 = false;

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
	 * Clean up after each test. Reset our mocks
	 *
	 * @since 2.2
	 */
	public function tearDown() {
		parent::tearDown();

		//make sure no one attached to this
		remove_filter( 'ep_sync_terms_allow_hierarchy', array( $this, 'ep_allow_multiple_level_terms_sync' ), 100 );
		$this->fired_actions = array();

		// Update since we are deleting to test notifications
		update_site_option( 'ep_host', $this->current_host );
	}

	/**
	 * Conditions:
	 *
	 * - On intro page
	 * - No host set
	 * - Index page not shown and notice not hidden
	 * - No sync has occurred
	 * - No upgrade sync is needed
	 * - Not feature auto activate sync is needed
	 * - Elasticsearch version within bounds
	 *
	 * Do: Show no notifications on intro
	 *
	 * @group admin-notifications
	 * @since 3.0
	 */
	public function noNotificationOnIntro() {
		delete_site_option( 'ep_host' );
		delete_site_option( 'ep_intro_shown' );
		delete_site_option( 'ep_hide_intro_shown_notice' );
		delete_site_option( 'ep_last_sync' );
		delete_site_option( 'ep_need_upgrade_sync' );
		delete_site_option( 'ep_feature_auto_activated_sync' );

		$_GET['page'] = 'elasticpress-intro';

		ob_start();
		$notice = ElasticPress\Dashboard\maybe_notice( true );
		ob_get_clean();

		$this->assertEquals( false, $notice );

		unset( $_GET['page'] );
	}

	/**
	 * Conditions:
	 *
	 * - On sites screen
	 * - Host set
	 * - Index page not shown and notice not hidden
	 * - No sync has occurred
	 * - No upgrade sync is needed
	 * - Not feature auto activate sync is needed
	 * - Elasticsearch version within bounds
	 *
	 * Do: Show need first sync notification
	 *
	 * @group admin-notifications
	 * @since 2.2
	 */
	public function testFirstSyncNotification() {
		delete_site_option( 'ep_intro_shown' );
		delete_site_option( 'ep_hide_intro_shown_notice' );
		delete_site_option( 'ep_last_sync' );
		delete_site_option( 'ep_need_upgrade_sync' );
		delete_site_option( 'ep_feature_auto_activated_sync' );

		ob_start();
		$notice = ElasticPress\Dashboard\maybe_notice( true );
		ob_get_clean();

		$this->assertEquals( 'no-sync', $notice );
	}

	/**
	 * Conditions:
	 *
	 * - On sites screan
	 * - Host set
	 * - Index page shown
	 * - Sync has occurred
	 * - No upgrade sync is needed
	 * - Not feature auto activate sync is needed
	 * - Elasticsearch version within bounds
	 *
	 * Do: Show no notifications
	 *
	 * @group admin-notifications
	 * @since 2.2
	 */
	public function testNoNotifications() {
		update_site_option( 'ep_intro_shown', true );
		update_site_option( 'ep_hide_intro_shown_notice', true );
		update_site_option( 'ep_last_sync', time() );
		delete_site_option( 'ep_need_upgrade_sync' );
		delete_site_option( 'ep_feature_auto_activated_sync' );

		ob_start();
		$notice = ElasticPress\Dashboard\maybe_notice( true );
		ob_get_clean();

		$this->assertFalse( $notice );
	}

	/**
	 * Conditions:
	 *
	 * - On sites screen
	 * - Host set
	 * - Index page shown and notice not hidden
	 * - No sync has occurred
	 * - Upgrade sync is needed
	 * - Feature auto activate sync is needed
	 * - Elasticsearch version within bounds
	 *
	 * Do: Show no sync notification
	 *
	 * @group admin-notifications
	 * @since 2.2
	 */
	public function testNotificationPrioritySync() {
		update_site_option( 'ep_intro_shown', true );
		delete_site_option( 'ep_hide_intro_shown_notice' );
		delete_site_option( 'ep_last_sync' );
		update_site_option( 'ep_need_upgrade_sync', true );
		update_site_option( 'ep_feature_auto_activated_sync', true );

		ob_start();
		$notice = ElasticPress\Dashboard\maybe_notice( true );
		ob_get_clean();

		$this->assertEquals( 'no-sync', $notice );
	}

	/**
	 * Conditions:
	 *
	 * - On sites screen
	 * - Bad host set
	 * - Index page  shown and notice not hidden
	 * - Sync has occured
	 * - No upgrade sync is needed
	 * - No feature auto activate sync is needed
	 * - Elasticsearch version within bounds
	 *
	 * Do: Show bad host notification
	 *
	 * @group admin-notifications
	 * @since 2.2
	 */
	public function testBadHostNotification() {
		update_site_option( 'ep_host', 'bad' );
		update_site_option( 'ep_intro_shown', true );
		delete_site_option( 'ep_hide_intro_shown_notice' );
		update_site_option( 'ep_last_sync', time() );
		delete_site_option( 'ep_need_upgrade_sync' );
		delete_site_option( 'ep_feature_auto_activated_sync' );

		ob_start();
		$notice = ElasticPress\Dashboard\maybe_notice( true );
		$html = ob_get_clean();

		$this->assertEquals( 'bad-host', $notice );

		$this->assertContains( 'notice-error-es-response-error', $html );

		// Test an HTTP status code.
		$callback = function() {
			return 'https://httpstat.us/405';
		};

		add_filter( 'ep_pre_request_url', $callback );

		ob_start();
		$notice = ElasticPress\Dashboard\maybe_notice( true );
		$html = ob_get_clean();

		remove_filter( 'ep_pre_request_url', $callback );

		$this->assertEquals( 'bad-host', $notice );

		$this->assertContains( 'notice-error-es-response-code', $html );
		$this->assertContains( '405', $html );
	}

	/**
	 * Conditions:
	 *
	 * - On sites screen
	 * - Host set
	 * - Index page shown and notice not hidden
	 * - Sync has occurred
	 * - Upgrade sync is needed
	 * - No feature auto activate sync is needed
	 * - Elasticsearch version within bounds
	 *
	 * Do: Show upgrade sync notification
	 *
	 * @group admin-notifications
	 * @since 2.2
	 */
	public function testUpgradeSyncNotification() {
		update_site_option( 'ep_intro_shown', true );
		delete_site_option( 'ep_hide_intro_shown_notice' );
		update_site_option( 'ep_last_sync', time() );
		update_site_option( 'ep_need_upgrade_sync', true );
		delete_site_option( 'ep_feature_auto_activated_sync' );

		ob_start();
		$notice = ElasticPress\Dashboard\maybe_notice( true );
		ob_get_clean();

		$this->assertEquals( 'upgrade-sync', $notice );
	}

	/**
	 * Conditions:
	 *
	 * - In admin
	 * - Old version set to current version
	 *
	 * Do: Don't show notification
	 *
	 * @group admin-notifications
	 * @since 2.3.1
	 */
	public function testUpgradeSyncNotificationNone() {
		update_site_option( 'ep_intro_shown', true );
		delete_site_option( 'ep_hide_intro_shown_notice' );
		update_site_option( 'ep_last_sync', time() );
		update_site_option( 'ep_need_upgrade_sync', false );
		delete_site_option( 'ep_feature_auto_activated_sync' );
		update_site_option( 'ep_version', EP_VERSION );

		ElasticPress\handle_upgrades();

		ob_start();
		$notice = ElasticPress\Dashboard\maybe_notice( true );
		ob_get_clean();

		$this->assertTrue( empty( $notice ) );
	}

	/**
	 * Conditions:
	 *
	 * - In admin
	 * - Old version is below a reindex version (2.1)
	 *
	 * Do: Show upgrade sync notification
	 *
	 * @group admin-notifications
	 * @since 2.3.1
	 */
	public function testUpgradeSyncNotificationCrossedIndexVersion() {
		update_site_option( 'ep_intro_shown', true );
		delete_site_option( 'ep_hide_intro_shown_notice' );
		update_site_option( 'ep_last_sync', time() );
		update_site_option( 'ep_need_upgrade_sync', false );
		delete_site_option( 'ep_feature_auto_activated_sync' );
		update_site_option( 'ep_version', '2.1' );

		ElasticPress\handle_upgrades();

		ob_start();
		$notice = ElasticPress\Dashboard\maybe_notice( true );
		ob_get_clean();

		$this->assertEquals( 'upgrade-sync', $notice );
	}

	/**
	 * Conditions:
	 *
	 * - On sites screen
	 * - Host set
	 * - Index page shown and notice not hidden
	 * - Sync has occurred
	 * - No upgrade sync is needed
	 * - Feature auto activate sync is needed
	 * - Elasticsearch version within bounds
	 *
	 * Do: Show auto activate sync notification
	 *
	 * @group admin-notifications
	 * @since 2.2
	 */
	public function testFeatureSyncNotification() {
		update_site_option( 'ep_intro_shown', true );
		delete_site_option( 'ep_hide_intro_shown_notice' );
		update_site_option( 'ep_last_sync', time() );
		delete_site_option( 'ep_need_upgrade_sync' );
		update_site_option( 'ep_feature_auto_activated_sync', 'woocommerce' );

		ob_start();
		$notice = ElasticPress\Dashboard\maybe_notice( true );
		ob_get_clean();

		$this->assertEquals( 'auto-activate-sync', $notice );
	}

	/**
	 * Conditions:
	 *
	 * - On sites screen
	 * - Host set
	 * - Index page shown and notice not hidden
	 * - Sync has occurred
	 * - No upgrade sync is needed
	 * - No feature auto activate sync is needed
	 * - Elasticsearch version above bounds
	 *
	 * Do: Show above es compat notification
	 *
	 * @group admin-notifications
	 * @since 2.2
	 */
	public function testAboveESCompatNotification() {
		update_site_option( 'ep_intro_shown', true );
		delete_site_option( 'ep_hide_intro_shown_notice' );
		update_site_option( 'ep_last_sync', time() );
		delete_site_option( 'ep_need_upgrade_sync' );
		delete_site_option( 'ep_feature_auto_activated_sync' );

		add_filter( 'ep_elasticsearch_version', array( $this, '_filter_es_version_above' ) );

		ob_start();
		$notice = ElasticPress\Dashboard\maybe_notice( true );
		ob_get_clean();

		remove_filter( 'ep_elasticsearch_version', array( $this, '_filter_es_version_above' ) );

		$this->assertEquals( 'above-es-compat', $notice );
	}

	/**
	 * Conditions:
	 *
	 * - On sites screen
	 * - Host set
	 * - Index page shown and notice not hidden
	 * - Sync has occurred
	 * - No upgrade sync is needed
	 * - No feature auto activate sync is needed
	 * - Elasticsearch version above bounds
	 *
	 * Do: Show below es compat notification
	 *
	 * @group admin-notifications
	 * @since 2.2
	 */
	public function testBelowESCompatNotification() {
		update_site_option( 'ep_intro_shown', true );
		delete_site_option( 'ep_hide_intro_shown_notice' );
		update_site_option( 'ep_last_sync', time() );
		delete_site_option( 'ep_need_upgrade_sync' );
		delete_site_option( 'ep_feature_auto_activated_sync' );

		add_filter( 'ep_elasticsearch_version', array( $this, '_filter_es_version_below' ) );

		ob_start();
		$notice = ElasticPress\Dashboard\maybe_notice( true );
		ob_get_clean();

		remove_filter( 'ep_elasticsearch_version', array( $this, '_filter_es_version_below' ) );

		$this->assertEquals( 'below-es-compat', $notice );
	}

	/**
	 * Filter in ES version that is too high
	 *
	 * @since  2.2
	 * @return int
	 */
	public function _filter_es_version_above() {
		return ( (int) EP_ES_VERSION_MAX ) + 1;
	}

	/**
	 * Filter in ES version that is too low
	 *
	 * @since  2.2
	 * @return int
	 */
	public function _filter_es_version_below() {
		return ( (int) EP_ES_VERSION_MIN ) - 1;
	}
}
