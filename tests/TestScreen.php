<?php
/**
 * Test screen class.
 *
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress;

/**
 * Screen test class
 */
class TestScreen extends BaseTestCase {
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

		global $hook_suffix;
		$hook_suffix = 'admin.php';

		set_current_screen();
	}

	/**
	 * Run after each test
	 *
	 * @since  3.0
	 */
	public function tearDown() {
		// phpcs:disable
		if ( isset( $_GET['page'] ) ) {
			unset( $_GET['page'] );
		}

		if ( isset( $_GET['install_complete'] ) ) {
			unset( $_GET['install_complete'] );
		}

		if ( isset( $_GET['do_sync'] ) ) {
			unset( $_GET['do_sync'] );
		}
		// phpcs:enable
	}

	/**
	 * Current screen should always be false when not on an EP page
	 *
	 * @since 3.0
	 */
	public function testDetermineScreenNotEP() {
		$_GET['page'] = '';

		ElasticPress\Screen::factory()->determine_screen();

		$this->assertFalse( ElasticPress\Screen::factory()->get_current_screen() );

		$_GET['page'] = 'elasticpress';

		set_current_screen( 'front' );

		ElasticPress\Screen::factory()->determine_screen();

		$this->assertFalse( ElasticPress\Screen::factory()->get_current_screen() );
	}

	/**
	 * Test install true on settings
	 *
	 * @since  3.0
	 */
	public function testDetermineScreenSettingsInstallTrue() {
		$set_install_status = function() {
			return true;
		};

		add_filter( 'ep_install_status', $set_install_status );

		$_GET['page'] = 'elasticpress-settings';

		$this->assertEquals( 'settings', ElasticPress\Screen::factory()->determine_screen() );

		remove_filter( 'ep_install_status', $set_install_status );
	}

	/**
	 * Test install status of 1 on settings
	 *
	 * @since  3.0
	 */
	public function testDetermineScreenSettingsInstall1() {
		$set_install_status = function() {
			return 1;
		};

		add_filter( 'ep_install_status', $set_install_status );

		$_GET['page'] = 'elasticpress-settings';

		$this->assertEquals( 'install', ElasticPress\Screen::factory()->determine_screen() );

		remove_filter( 'ep_install_status', $set_install_status );
	}

	/**
	 * Test install status of 2 on settings
	 *
	 * @since  3.0
	 */
	public function testDetermineScreenSettingsInstall2() {
		$set_install_status = function() {
			return 2;
		};

		add_filter( 'ep_install_status', $set_install_status );

		$_GET['page'] = 'elasticpress-settings';

		$this->assertEquals( 'settings', ElasticPress\Screen::factory()->determine_screen() );

		remove_filter( 'ep_install_status', $set_install_status );
	}

	/**
	 * Test install status true on dashboard
	 *
	 * @since  3.0
	 */
	public function testDetermineScreenDashboardInstallTrue() {
		$set_install_status = function() {
			return true;
		};

		add_filter( 'ep_install_status', $set_install_status );

		$_GET['page'] = 'elasticpress';

		$this->assertEquals( 'dashboard', ElasticPress\Screen::factory()->determine_screen() );

		remove_filter( 'ep_install_status', $set_install_status );
	}

	/**
	 * Test install status 1 on dashboard
	 *
	 * @since  3.0
	 */
	public function testDetermineScreenDashboardInstall1() {
		$set_install_status = function() {
			return 1;
		};

		add_filter( 'ep_install_status', $set_install_status );

		$_GET['page'] = 'elasticpress';

		$this->assertEquals( 'install', ElasticPress\Screen::factory()->determine_screen() );

		remove_filter( 'ep_install_status', $set_install_status );
	}

	/**
	 * Test install status 2 on dashboard
	 *
	 * @since  3.0
	 */
	public function testDetermineScreenDashboardInstall2() {
		$set_install_status = function() {
			return 1;
		};

		add_filter( 'ep_install_status', $set_install_status );

		$_GET['page'] = 'elasticpress';

		$this->assertEquals( 'install', ElasticPress\Screen::factory()->determine_screen() );

		remove_filter( 'ep_install_status', $set_install_status );
	}

	/**
	 * Test install status true with install complete on dashboard
	 *
	 * @since  3.0
	 */
	public function testDetermineScreenDashboardInstallComplete() {
		$set_install_status = function() {
			return true;
		};

		add_filter( 'ep_install_status', $set_install_status );

		$_GET['page']             = 'elasticpress';
		$_GET['install_complete'] = 1;

		$this->assertEquals( 'install', ElasticPress\Screen::factory()->determine_screen() );

		remove_filter( 'ep_install_status', $set_install_status );
	}

	/**
	 * Test install status 3 on dashboard doing a sync
	 *
	 * @since  3.0
	 */
	public function testDetermineScreenDashboardInstall3DoSync() {
		$set_install_status = function() {
			return 3;
		};

		add_filter( 'ep_install_status', $set_install_status );

		$_GET['page']    = 'elasticpress';
		$_GET['do_sync'] = 1;

		$this->assertEquals( 'dashboard', ElasticPress\Screen::factory()->determine_screen() );

		remove_filter( 'ep_install_status', $set_install_status );
	}
}
