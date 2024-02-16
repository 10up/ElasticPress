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

		parent::set_up();
	}

	/**
	 * Clean up after each test
	 */
	public function tear_down() {
		tests_add_filter( 'translations_api', __NAMESPACE__ . '\skip_translations_api' );

		parent::tear_down();
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

		/**
		 * Test similar languages
		 */
		$existing_lang = function () {
			return 'pt_BR';
		};
		add_filter( 'ep_default_language', $existing_lang );
		$this->assertSame( 'Portuguese', Dashboard\use_language_in_setting( '', 'filter_ewp_snowball' ) );
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

	/**
	 * Test the `use_language_in_setting` function when on multisite using `site-default`
	 *
	 * @group skip-on-single-site
	 * @group dashboard
	 */
	public function test_use_language_in_setting_for_multisite() {
		$site_factory = new \WP_UnitTest_Factory_For_Blog();
		$site_pt_br   = $site_factory->create(
			[
				'domain'  => 'example.org',
				'path'    => '/pt_BR',
				'options' => [
					'WPLANG' => 'pt_BR',
				],
			]
		);
		$site_he_il   = $site_factory->create(
			[
				'domain'  => 'example.org',
				'path'    => '/he_IL',
				'options' => [
					'WPLANG' => 'he_IL',
				],
			]
		);

		switch_to_blog( $site_pt_br );
		$this->assertSame( 'brazilian', Dashboard\use_language_in_setting() );

		/*
		 * Hebrew is not a language supported by Elasticsearch out-of-the-box, so it should fallback to English.
		 */
		switch_to_blog( $site_he_il );
		$this->assertSame( 'english', Dashboard\use_language_in_setting() );
	}

	/**
	 * Test the `get_available_languages` function
	 *
	 * @group dashboard
	 */
	public function test_get_available_languages() {
		$languages = Dashboard\get_available_languages();
		$this->assertSame( [ 'ar', 'ary' ], $languages['arabic'] );
		$this->assertSame( [ 'th' ], $languages['thai'] );

		$languages = Dashboard\get_available_languages( 'locales' );
		$this->assertContains( 'ar', $languages );
		$this->assertContains( 'ary', $languages );
		$this->assertContains( 'th', $languages );
	}

	/**
	 * Test the `ep_available_languages` filter
	 *
	 * @group dashboard
	 */
	public function test_get_available_languages_ep_available_languages_filter() {
		$add_language = function ( $languages ) {
			$languages['custom'] = [ 'cu_ST', 'om' ];
			return $languages;
		};
		add_filter( 'ep_available_languages', $add_language );

		$languages = Dashboard\get_available_languages();
		$this->assertSame( [ 'cu_ST', 'om' ], $languages['custom'] );
	}
}
