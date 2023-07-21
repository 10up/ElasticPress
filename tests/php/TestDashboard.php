<?php
/**
 * Test dashboard functions
 *
 * @since 4.7.0
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress\Dashboard;

/**
 * Dashboard test class
 */
class TestDashboard extends BaseTestCase {
	/**
	 * Setup each test
	 */
	public function set_up() {
		remove_filter( 'translations_api', __NAMESPACE__ . '\skip_translations_api' );
	}

	/**
	 * Clean up after each test
	 */
	public function tear_down() {
		tests_add_filter( 'translations_api', __NAMESPACE__ . '\skip_translations_api' );
	}

	/**
	 * Test the default behavior of the `use_language_in_setting` function
	 *
	 * @group dashboard
	 */
	public function test_use_language_in_setting() {
		$this->assertSame( 'english', Dashboard\use_language_in_setting() );

		$existing_lang = function () {
			return 'ar';
		};
		add_filter( 'ep_default_language', $existing_lang );
		$this->assertSame( 'arabic', Dashboard\use_language_in_setting() );

		$existing_lang = function () {
			return 'non-existent';
		};
		add_filter( 'ep_default_language', $existing_lang );
		$this->assertSame( 'english', Dashboard\use_language_in_setting() );
	}

	/**
	 * Test the default behavior of the `use_language_in_setting` function for the `filter_ewp_snowball` context
	 *
	 * @group dashboard
	 */
	public function test_use_language_in_setting_for_snowball() {
		$this->assertSame( 'English', Dashboard\use_language_in_setting( '', 'filter_ewp_snowball' ) );

		$existing_lang = function () {
			return 'hy';
		};
		add_filter( 'ep_default_language', $existing_lang );
		$this->assertSame( 'Armenian', Dashboard\use_language_in_setting( '', 'filter_ewp_snowball' ) );

		$existing_lang = function () {
			return 'non-existent';
		};
		add_filter( 'ep_default_language', $existing_lang );
		$this->assertSame( 'English', Dashboard\use_language_in_setting( '', 'filter_ewp_snowball' ) );
	}

	/**
	 * Test the default behavior of the `use_language_in_setting` function for the `filter_ewp_snowball` context
	 *
	 * @group dashboard
	 */
	public function test_use_language_in_setting_for_stop() {
		$this->assertSame( '_english_', Dashboard\use_language_in_setting( '', 'filter_ep_stop' ) );

		$existing_lang = function () {
			return 'ar';
		};
		add_filter( 'ep_default_language', $existing_lang );
		$this->assertSame( '_arabic_', Dashboard\use_language_in_setting( '', 'filter_ep_stop' ) );

		$existing_lang = function () {
			return 'non-existent';
		};
		add_filter( 'ep_default_language', $existing_lang );
		$this->assertSame( '_english_', Dashboard\use_language_in_setting( '', 'filter_ep_stop' ) );
	}
}
