<?php
/**
 * Test the Settings screen
 *
 * @since 5.0.0
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress\Screen;
use ElasticPress\Screen\Settings;
use ElasticPress\Utils;

/**
 * Test the Settings class
 */
class TestSettings extends BaseTestCase {
	/**
	 * Test the `setup` method
	 *
	 * @group screen
	 * @group settings-screen
	 */
	public function test_setup() {
		$settings = new Settings();
		$settings->setup();

		$this->assertSame( 10, has_action( 'admin_enqueue_scripts', [ $settings, 'admin_enqueue_scripts' ] ) );
		$this->assertSame( 8, has_action( 'admin_init', [ $settings, 'action_admin_init' ] ) );
	}

	/**
	 * Test the `admin_enqueue_scripts` method
	 *
	 * @group screen
	 * @group settings-screen
	 */
	public function test_admin_enqueue_scripts() {
		$settings = new Settings();
		$settings->admin_enqueue_scripts();

		$this->assertFalse( wp_script_is( 'ep_settings_scripts' ) );

		Screen::factory()->set_current_screen( 'settings' );
		$settings->admin_enqueue_scripts();

		$this->assertTrue( wp_script_is( 'ep_settings_scripts' ) );
	}

	/**
	 * Test the `action_admin_init` method
	 *
	 * @group screen
	 * @group settings-screen
	 */
	public function test_action_admin_init() {
		global $_POST;

		$settings  = new Settings();
		$prev_host = Utils\get_option( 'ep_host' );

		Utils\update_option( 'ep_host', '--' );

		$_POST = [
			'ep_settings_nonce' => '',
			'ep_language'       => 'test_lang',
			'ep_host'           => $prev_host,
			'ep_bulk_setting'   => 4,
		];

		$settings->action_admin_init();

		// Should not change anything, as the nonce wasn't passed
		$this->assertSame( 'site-default', Utils\get_language() );
		$this->assertSame( 350, Utils\get_option( 'ep_bulk_setting', 350 ) );

		$_POST['ep_settings_nonce'] = wp_create_nonce( 'elasticpress_settings' );
		$settings->action_admin_init();

		// Should have the new values
		$this->assertSame( 'test_lang', Utils\get_language() );
		$this->assertSame( 4, Utils\get_option( 'ep_bulk_setting' ) );
	}

	/**
	 * Test the `action_admin_init` method when a wrong host is set
	 *
	 * @group screen
	 * @group settings-screen
	 */
	public function test_action_admin_init_wrong_host() {
		global $_POST;

		$settings  = new Settings();
		$prev_host = Utils\get_option( 'ep_host' );

		$_POST = [
			'ep_settings_nonce' => wp_create_nonce( 'elasticpress_settings' ),
			'ep_language'       => 'site-default',
			'ep_host'           => 'http://wrong.test/',
		];

		$settings->action_admin_init();

		$this->assertSame( $prev_host, Utils\get_host() );
		$this->assertSame( 10, has_action( 'admin_notices', [ $settings, 'add_validation_notice' ] ) );
		$this->assertNotContains( 'ep_host', $_POST );
	}

	/**
	 * Test that an empty token in POST preserves the stored token.
	 *
	 * The token field is always empty on page load, so a save without a new
	 * value must not wipe the existing token.
	 *
	 * @group screen
	 * @group settings-screen
	 */
	public function test_action_admin_init_empty_token_preserves_stored_token() {
		global $_POST;

		if ( defined( 'EP_CREDENTIALS' ) && EP_CREDENTIALS ) {
			$this->markTestSkipped( 'EP_CREDENTIALS constant overrides the option.' );
		}

		// Make is_epio() true so get_epio_credentials() reads the option.
		putenv( 'IS_EPIO_ENVIRONMENT=1' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv

		$settings = new Settings();

		Utils\update_option(
			'ep_credentials',
			[
				'username' => 'u',
				'token'    => 'secret',
			]
		);

		$_POST = [
			'ep_settings_nonce' => wp_create_nonce( 'elasticpress_settings' ),
			'ep_language'       => 'site-default',
			'ep_host'           => Utils\get_host(),
			'ep_credentials'    => [
				'username' => 'u',
				'token'    => '',
			],
		];

		$settings->action_admin_init();

		$this->assertSame( 'secret', Utils\get_option( 'ep_credentials' )['token'] );

		putenv( 'IS_EPIO_ENVIRONMENT' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv
	}

	/**
	 * Test that a new token value is saved.
	 *
	 * @group screen
	 * @group settings-screen
	 */
	public function test_action_admin_init_new_token_is_saved() {
		global $_POST;

		if ( defined( 'EP_CREDENTIALS' ) && EP_CREDENTIALS ) {
			$this->markTestSkipped( 'EP_CREDENTIALS constant overrides the option.' );
		}

		// Make is_epio() true so get_epio_credentials() reads the option.
		putenv( 'IS_EPIO_ENVIRONMENT=1' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv

		$settings = new Settings();

		Utils\update_option(
			'ep_credentials',
			[
				'username' => 'u',
				'token'    => 'old',
			]
		);

		$_POST = [
			'ep_settings_nonce' => wp_create_nonce( 'elasticpress_settings' ),
			'ep_language'       => 'site-default',
			'ep_host'           => Utils\get_host(),
			'ep_credentials'    => [
				'username' => 'u',
				'token'    => 'new-token',
			],
		];

		$settings->action_admin_init();

		$this->assertSame( 'new-token', Utils\get_option( 'ep_credentials' )['token'] );

		putenv( 'IS_EPIO_ENVIRONMENT' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv
	}

	/**
	 * Test that checking the "remove token" checkbox clears the stored token.
	 *
	 * @group screen
	 * @group settings-screen
	 */
	public function test_action_admin_init_remove_token_checkbox_clears_stored_token() {
		global $_POST;

		if ( defined( 'EP_CREDENTIALS' ) && EP_CREDENTIALS ) {
			$this->markTestSkipped( 'EP_CREDENTIALS constant overrides the option.' );
		}

		// Make is_epio() true so get_epio_credentials() reads the option.
		putenv( 'IS_EPIO_ENVIRONMENT=1' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv

		$settings = new Settings();

		Utils\update_option(
			'ep_credentials',
			[
				'username' => 'u',
				'token'    => 'secret',
			]
		);

		$_POST = [
			'ep_settings_nonce' => wp_create_nonce( 'elasticpress_settings' ),
			'ep_language'       => 'site-default',
			'ep_host'           => Utils\get_host(),
			'ep_credentials'    => [
				'username' => 'u',
				'token'    => '',
			],
			'ep_remove_token'   => '1',
		];

		$settings->action_admin_init();

		$this->assertSame( '', Utils\get_option( 'ep_credentials' )['token'] );
		$this->assertSame( 'u', Utils\get_option( 'ep_credentials' )['username'] );

		putenv( 'IS_EPIO_ENVIRONMENT' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv
	}

	/**
	 * Test the `add_validation_notice` method
	 *
	 * @group screen
	 * @group settings-screen
	 */
	public function test_add_validation_notice() {
		$settings = new Settings();

		ob_start();
		$settings->add_validation_notice();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<div class="notice notice-error">', $output );

		if ( Utils\is_epio() ) {
			$this->assertStringContainsString(
				'It was not possible to connect to your ElasticPress.io account.',
				$output
			);
		} else {
			$this->assertStringContainsString(
				'It was not possible to connect to your Elasticsearch server.',
				$output
			);
		}
	}
}
