<?php
/**
 * Test upgrade routines.
 *
 * @since 5.4.0
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress\Upgrades;
use ElasticPress\Utils;

/**
 * Upgrades test class
 */
class TestUpgrades extends BaseTestCase {
	/**
	 * Clean up after each test.
	 */
	public function tear_down() {
		$this->set_hpos_enabled( false );
		Utils\delete_option( 'ep_feature_settings' );
		Utils\delete_option( 'ep_version' );

		parent::tear_down();
	}

	/**
	 * Test that the 5.4.0 upgrade disables HPOS query integration when HPOS is enabled.
	 *
	 * @group upgrades
	 */
	public function test_upgrade_5_4_0_sets_disable_hpos_when_hpos_is_enabled() {
		$this->set_hpos_enabled( true );
		Utils\update_option(
			'ep_feature_settings',
			[
				'woocommerce' => [
					'active' => true,
				],
			]
		);

		$this->run_upgrade( 'upgrade_5_4_0', '5.3.3' );

		$settings = Utils\get_option( 'ep_feature_settings' );

		$this->assertSame( '1', $settings['woocommerce']['disable_hpos'] );
		$this->assertTrue( $settings['woocommerce']['active'] );
	}

	/**
	 * Test that the 5.4.0 upgrade does not overwrite an existing disable_hpos setting.
	 *
	 * @group upgrades
	 */
	public function test_upgrade_5_4_0_does_not_overwrite_existing_disable_hpos() {
		$this->set_hpos_enabled( true );
		Utils\update_option(
			'ep_feature_settings',
			[
				'woocommerce' => [
					'active'       => true,
					'disable_hpos' => '0',
				],
			]
		);

		$this->run_upgrade( 'upgrade_5_4_0', '5.3.3' );

		$settings = Utils\get_option( 'ep_feature_settings' );

		$this->assertSame( '0', $settings['woocommerce']['disable_hpos'] );
	}

	/**
	 * Test that the 5.4.0 upgrade does not change settings when HPOS is disabled.
	 *
	 * @group upgrades
	 */
	public function test_upgrade_5_4_0_does_not_set_disable_hpos_when_hpos_is_disabled() {
		$this->set_hpos_enabled( false );
		Utils\update_option(
			'ep_feature_settings',
			[
				'woocommerce' => [
					'active' => true,
				],
			]
		);

		$this->run_upgrade( 'upgrade_5_4_0', '5.3.3' );

		$settings = Utils\get_option( 'ep_feature_settings' );

		$this->assertArrayNotHasKey( 'disable_hpos', $settings['woocommerce'] );
	}

	/**
	 * Test that the 5.4.0 upgrade does not run on a fresh installation.
	 *
	 * @group upgrades
	 */
	public function test_upgrade_5_4_0_skips_fresh_install() {
		$this->set_hpos_enabled( true );
		Utils\update_option(
			'ep_feature_settings',
			[
				'woocommerce' => [
					'active' => true,
				],
			]
		);

		$this->run_upgrade( 'upgrade_5_4_0', false );

		$settings = Utils\get_option( 'ep_feature_settings' );

		$this->assertArrayNotHasKey( 'disable_hpos', $settings['woocommerce'] );
	}

	/**
	 * Run a given upgrade routine as if upgrading from a given previous version.
	 *
	 * @param string       $method      Name of the Upgrades method to call.
	 * @param string|false $old_version Previous plugin version, or false for a fresh install.
	 */
	protected function run_upgrade( string $method, $old_version ): void {
		false === $old_version ? Utils\delete_option( 'ep_version' ) : Utils\update_option( 'ep_version', $old_version );

		$upgrades = new Upgrades();
		$upgrades->setup();
		$upgrades->$method();
	}

	/**
	 * Enable or disable WooCommerce HPOS.
	 *
	 * @param bool $enabled Whether HPOS should be enabled.
	 */
	protected function set_hpos_enabled( bool $enabled ): void {
		if (
			! function_exists( 'wc_get_container' )
			|| ! class_exists( '\Automattic\WooCommerce\Internal\Features\FeaturesController' )
		) {
			return;
		}

		wc_get_container()
			->get( \Automattic\WooCommerce\Internal\Features\FeaturesController::class )
			->change_feature_enable( 'custom_order_tables', $enabled );
	}
}
