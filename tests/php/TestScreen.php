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

		global $hook_suffix;
		$hook_suffix = 'sites.php';

		set_current_screen();
	}

	/**
	 * Run after each test
	 *
	 * @group screen
	 * @since  3.0
	 */
	public function tear_down() {
		parent::tear_down();

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
	 * @group screen
	 * @since 3.0
	 */
	public function testDetermineScreenNotEP() {
		$_GET['page'] = '';

		ElasticPress\Installer::factory()->calculate_install_status();
		ElasticPress\Screen::factory()->determine_screen();

		$this->assertEquals( null, ElasticPress\Screen::factory()->get_current_screen() );

		$_GET['page'] = 'elasticpress';

		set_current_screen( 'front' );

		ElasticPress\Installer::factory()->calculate_install_status();
		ElasticPress\Screen::factory()->determine_screen();

		ElasticPress\Screen::factory()->determine_screen();

		// This will be 'install' for single site, but null for multisite.
		$this->assertSame( 'install', ElasticPress\Screen::factory()->get_current_screen() );
	}

	/**
	 * Test install true on settings
	 *
	 * @group screen
	 * @since  3.0
	 */
	public function testDetermineScreenSettingsInstallTrue() {
		$set_install_status = function() {
			return true;
		};

		add_filter( 'ep_install_status', $set_install_status );

		$_GET['page'] = 'elasticpress-settings';

		ElasticPress\Installer::factory()->calculate_install_status();
		ElasticPress\Screen::factory()->determine_screen();

		$this->assertEquals( 'settings', ElasticPress\Screen::factory()->get_current_screen() );
	}

	/**
	 * Test install status of 1 on settings
	 *
	 * @group screen
	 * @since  3.0
	 */
	public function testDetermineScreenSettingsInstall1() {
		$set_install_status = function() {
			return 1;
		};

		add_filter( 'ep_install_status', $set_install_status );

		$_GET['page'] = 'elasticpress-settings';

		ElasticPress\Installer::factory()->calculate_install_status();
		ElasticPress\Screen::factory()->determine_screen();

		$this->assertEquals( 'install', ElasticPress\Screen::factory()->get_current_screen() );
	}

	/**
	 * Test install status of 2 on settings
	 *
	 * @group screen
	 * @since  3.0
	 */
	public function testDetermineScreenSettingsInstall2() {
		$set_install_status = function() {
			return 2;
		};

		add_filter( 'ep_install_status', $set_install_status );

		$_GET['page'] = 'elasticpress-settings';

		ElasticPress\Installer::factory()->calculate_install_status();
		ElasticPress\Screen::factory()->determine_screen();

		$this->assertEquals( 'settings', ElasticPress\Screen::factory()->get_current_screen() );
	}

	/**
	 * Test install status true on dashboard
	 *
	 * @group screen
	 * @since  3.0
	 */
	public function testDetermineScreenDashboardInstallTrue() {
		$set_install_status = function() {
			return true;
		};

		add_filter( 'ep_install_status', $set_install_status );

		$_GET['page'] = 'elasticpress';

		ElasticPress\Installer::factory()->calculate_install_status();
		ElasticPress\Screen::factory()->determine_screen();

		$this->assertEquals( 'dashboard', ElasticPress\Screen::factory()->get_current_screen() );
	}

	/**
	 * Test install status 1 on dashboard
	 *
	 * @group screen
	 * @since  3.0
	 */
	public function testDetermineScreenDashboardInstall1() {
		$set_install_status = function() {
			return 1;
		};

		add_filter( 'ep_install_status', $set_install_status );

		$_GET['page'] = 'elasticpress';

		ElasticPress\Installer::factory()->calculate_install_status();
		ElasticPress\Screen::factory()->determine_screen();

		$this->assertEquals( 'install', ElasticPress\Screen::factory()->get_current_screen() );
	}

	/**
	 * Test install status 2 on dashboard
	 *
	 * @group screen
	 * @since  3.0
	 */
	public function testDetermineScreenDashboardInstall2() {
		$set_install_status = function() {
			return 1;
		};

		add_filter( 'ep_install_status', $set_install_status );

		$_GET['page'] = 'elasticpress';

		ElasticPress\Installer::factory()->calculate_install_status();
		ElasticPress\Screen::factory()->determine_screen();

		$this->assertEquals( 'install', ElasticPress\Screen::factory()->get_current_screen() );
	}

	/**
	 * Test install status true with install complete on dashboard
	 *
	 * @group screen
	 * @since  3.0
	 */
	public function testDetermineScreenDashboardInstallComplete() {
		$set_install_status = function() {
			return true;
		};

		add_filter( 'ep_install_status', $set_install_status );

		$_GET['page']             = 'elasticpress';
		$_GET['install_complete'] = 1;

		ElasticPress\Installer::factory()->calculate_install_status();
		ElasticPress\Screen::factory()->determine_screen();

		$this->assertEquals( 'install', ElasticPress\Screen::factory()->get_current_screen() );
	}

	/**
	 * Test install status 3 on dashboard doing a sync
	 *
	 * @group screen
	 * @since  3.0
	 */
	public function testDetermineScreenDashboardInstall3DoSync() {
		$set_install_status = function() {
			return 3;
		};

		add_filter( 'ep_install_status', $set_install_status );

		$_GET['page']    = 'elasticpress';
		$_GET['do_sync'] = 1;

		ElasticPress\Installer::factory()->calculate_install_status();
		ElasticPress\Screen::factory()->determine_screen();

		$this->assertEquals( 'dashboard', ElasticPress\Screen::factory()->get_current_screen() );
	}
}
