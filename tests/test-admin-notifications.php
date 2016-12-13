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

		wp_set_current_user( $admin_id );

		ep_delete_index();
		ep_put_mapping();

		EP_WP_Query_Integration::factory()->setup();
		EP_Sync_Manager::factory()->setup();
		EP_Sync_Manager::factory()->sync_post_queue = array();

		$this->setup_test_post_type();

		$this->current_host = get_option( 'ep_host' );
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
		update_option( 'ep_host', $this->current_host );
	}

	/**
	 * Conditions:
	 *
	 * - On edit screem
	 * - No host set
	 * - Index page not shown and notice not hidden
	 * - No sync has occured
	 * - No upgrade sync is needed
	 * - Not feature auto activate sync is needed
	 *
	 * Do: Show setup notification
	 * 
	 * @group admin-notifications
	 * @since 2.2
	 */
	public function testInitialSetupNotification() {
		set_current_screen( 'edit.php' );

		delete_option( 'ep_host' );
		delete_option( 'ep_intro_shown' );
		delete_option( 'ep_hide_intro_shown_notice' );
		delete_option( 'ep_last_sync' );
		delete_option( 'ep_need_upgrade_sync' );
		delete_option( 'ep_feature_auto_activated_sync' );

		ob_start();
		$notice = EP_Dashboard::factory()->maybe_notice();
		ob_get_clean();

		$this->assertEquals( 'need-setup', $notice );
	}

	/**
	 * Conditions:
	 *
	 * - On edit screem
	 * - Host set
	 * - Index page not shown and notice not hidden
	 * - No sync has occured
	 * - No upgrade sync is needed
	 * - Not feature auto activate sync is needed
	 *
	 * Do: Show need first sync notification
	 * 
	 * @group admin-notifications
	 * @since 2.2
	 */
	public function testFirstSyncNotification() {
		set_current_screen( 'edit.php' );

		delete_option( 'ep_intro_shown' );
		delete_option( 'ep_hide_intro_shown_notice' );
		delete_option( 'ep_last_sync' );
		delete_option( 'ep_need_upgrade_sync' );
		delete_option( 'ep_feature_auto_activated_sync' );

		ob_start();
		$notice = EP_Dashboard::factory()->maybe_notice();
		ob_get_clean();

		$this->assertEquals( 'no-sync', $notice );
	}
}
