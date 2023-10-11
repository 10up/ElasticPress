<?php
/**
 * Test installer class.
 *
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress;

/**
 * Installer test class
 */
class TestInstaller extends BaseTestCase {

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

		$this->current_host = get_option( 'ep_host' );
	}

	/**
	 * Clean up after each test
	 *
	 * @since 3.0
	 */
	public function tear_down() {
		parent::tear_down();

		update_site_option( 'ep_host', $this->current_host );

		delete_option( 'ep_last_sync' );

		// phpcs:disable
		if ( isset( $_POST['ep_host'] ) ) {
			unset( $_POST['ep_host'] );
		}
		// phpcs:enable
	}

	/**
	 * Test calculating install status when a sync has happened
	 *
	 * @group installer
	 * @since  3.0
	 */
	public function testCalculateInstallStatusHostAndSync() {
		update_option( 'ep_last_sync', time() );

		ElasticPress\Installer::factory()->calculate_install_status();

		$install_status = ElasticPress\Installer::factory()->get_install_status();

		$this->assertEquals( true, $install_status );
	}

	/**
	 * Test calculating install status with no sync and host
	 *
	 * @group installer
	 * @since  3.0
	 */
	public function testCalculateInstallStatusNoSync() {
		ElasticPress\Installer::factory()->calculate_install_status();

		$install_status = ElasticPress\Installer::factory()->get_install_status();

		$this->assertEquals( 3, $install_status );
	}

	/**
	 * Test calculating install status with no sync and no host
	 *
	 * @group installer
	 * @since  3.0
	 */
	public function testCalculateInstallStatusNoHost() {
		add_filter( 'ep_host', '__return_false' );

		ElasticPress\Installer::factory()->calculate_install_status();

		$install_status = ElasticPress\Installer::factory()->get_install_status();

		$this->assertEquals( 2, $install_status );
	}

	/**
	 * Test calculating install status with no sync and no host but posted host
	 *
	 * @group installer
	 * @since  3.0
	 */
	public function testCalculateInstallStatusNoHostPostHost() {
		delete_option( 'ep_host' );

		$_POST['ep_host'] = 'test';

		ElasticPress\Installer::factory()->calculate_install_status();

		$install_status = ElasticPress\Installer::factory()->get_install_status();

		$this->assertEquals( 3, $install_status );
	}

}
