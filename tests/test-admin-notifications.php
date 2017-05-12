<?php

class EPTestAdminNotifications extends EP_Test_Base {
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

		ep_delete_index();
		ep_put_mapping();

		EP_WP_Query_Integration::factory()->setup();
		EP_Sync_Manager::factory()->setup();
		EP_Sync_Manager::factory()->sync_post_queue = array();

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
	 * - On sites secreen
	 * - No host set
	 * - Index page not shown and notice not hidden
	 * - No sync has occured
	 * - No upgrade sync is needed
	 * - Not feature auto activate sync is needed
	 * - Elasticsearch version within bounds
	 *
	 * Do: Show setup notification
	 * 
	 * @group admin-notifications
	 * @since 2.2
	 */
	public function testInitialSetupNotification() {
		delete_site_option( 'ep_host' );
		delete_site_option( 'ep_intro_shown' );
		delete_site_option( 'ep_hide_intro_shown_notice' );
		delete_site_option( 'ep_last_sync' );
		delete_site_option( 'ep_need_upgrade_sync' );
		delete_site_option( 'ep_feature_auto_activated_sync' );

		ob_start();
		$notice = EP_Dashboard::factory()->maybe_notice( true );
		ob_get_clean();

		$this->assertEquals( 'need-setup', $notice );
	}

	/**
	 * Conditions:
	 *
	 * - On sites secreen
	 * - Host set
	 * - Index page not shown and notice not hidden
	 * - No sync has occured
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
		$notice = EP_Dashboard::factory()->maybe_notice( true );
		ob_get_clean();

		$this->assertEquals( 'no-sync', $notice );
	}

	/**
	 * Conditions:
	 *
	 * - On sites screan
	 * - Host set
	 * - Index page shown
	 * - Sync has occured
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
		$notice = EP_Dashboard::factory()->maybe_notice( true );
		ob_get_clean();

		$this->assertFalse( $notice );
	}

	/**
	 * Conditions:
	 *
	 * - On sites secreen
	 * - No host set
	 * - Index page not shown and notice not hidden
	 * - No sync has occured
	 * - Upgrade sync is needed
	 * - Feature auto activate sync is needed
	 * - Elasticsearch version within bounds
	 *
	 * Do: Show setup notification
	 * 
	 * @group admin-notifications
	 * @since 2.2
	 */
	public function testNotificationPrioritySetup() {
		delete_site_option( 'ep_host' );
		delete_site_option( 'ep_intro_shown' );
		delete_site_option( 'ep_hide_intro_shown_notice' );
		delete_site_option( 'ep_last_sync' );
		update_site_option( 'ep_need_upgrade_sync', true );
		update_site_option( 'ep_feature_auto_activated_sync', true );

		ob_start();
		$notice = EP_Dashboard::factory()->maybe_notice( true );
		ob_get_clean();

		$this->assertEquals( 'need-setup', $notice );
	}

	/**
	 * Conditions:
	 *
	 * - On sites secreen
	 * - Host set
	 * - Index page shown and notice not hidden
	 * - No sync has occured
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
		$notice = EP_Dashboard::factory()->maybe_notice( true );
		ob_get_clean();

		$this->assertEquals( 'no-sync', $notice );
	}

	/**
	 * Conditions:
	 *
	 * - On sites secreen
	 * - Bad host set
	 * - Index page  shown and notice not hidden
	 * - No sync has occured
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
		delete_site_option( 'ep_last_sync' );
		delete_site_option( 'ep_need_upgrade_sync' );
		delete_site_option( 'ep_feature_auto_activated_sync' );

		ob_start();
		$notice = EP_Dashboard::factory()->maybe_notice( true );
		ob_get_clean();

		$this->assertEquals( 'bad-host', $notice );
	}

	/**
	 * Conditions:
	 *
	 * - On sites secreen
	 * - Host set
	 * - Index page shown and notice not hidden
	 * - Sync has occured
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
		$notice = EP_Dashboard::factory()->maybe_notice( true );
		ob_get_clean();

		$this->assertEquals( 'upgrade-sync', $notice );
	}

	/**
	 * Conditions:
	 *
	 * - On sites secreen
	 * - Host set
	 * - Index page shown and notice not hidden
	 * - Sync has occured
	 * - No upgrade sync is needed
	 * - Feature auto activate sync is needed
	 * - Elasticsearch version within bounds
	 *
	 * Do: Show upgrade sync notification
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
		$notice = EP_Dashboard::factory()->maybe_notice( true );
		ob_get_clean();

		$this->assertEquals( 'auto-activate-sync', $notice );
	}

	/**
	 * Conditions:
	 *
	 * - On sites secreen
	 * - Host set
	 * - Index page shown and notice not hidden
	 * - Sync has occured
	 * - No upgrade sync is needed
	 * - No feature auto activate sync is needed
	 * - Elasticsearch version above bounds
	 *
	 * Do: Show upgrade sync notification
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
		$notice = EP_Dashboard::factory()->maybe_notice( true );
		ob_get_clean();

		remove_filter( 'ep_elasticsearch_version', array( $this, '_filter_es_version_above' ) );

		$this->assertEquals( 'above-es-compat', $notice );
	}

	/**
	 * Conditions:
	 *
	 * - On sites secreen
	 * - Host set
	 * - Index page shown and notice not hidden
	 * - Sync has occured
	 * - No upgrade sync is needed
	 * - No feature auto activate sync is needed
	 * - Elasticsearch version above bounds
	 *
	 * Do: Show upgrade sync notification
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
		$notice = EP_Dashboard::factory()->maybe_notice( true );
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
