<?php
/**
 * Test searchterm highlighting.
 *
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress;

class TestHighlighting extends BaseTestCase {

	/**
	 * Things to test:
	 * - choice of tag for highlighting
	 */

	/**
	 *
	 */
	// public function testHighlightColorWorks() {
	// 	$I = $this->openBrowserPage();

	// 	$I->loginAs( 'wpsnapshots' );

	// 	$data = [
	// 		'title'   => 'test highlight color',
	// 		'content' => 'findme findme findme',
	// 	];

	// 	$this->publishPost( $data, $I );

	// 	$I->moveTo( '/?s=findme' );

	// 	$I->seeElement( '.ep-highlight' );
	// }


	/**
	 * test allowed tags
	 */
	public function testAllowedTags() {
		ElasticPress\Features::factory()->activate_feature( 'searchterm_highlighting' );

		// Need to call this since it's hooked to init
		ElasticPress\Features::factory()->setup_features();

		// a tag that is in the array of allowed tags
		$not_allowed = 'span';

		$tag = ElasticPress\Features::factory()->get_registered_feature( 'searchterm_highlighting' )->get_highlighting_tag( $not_allowed );

		$this->assertTrue( $tag == 'span' );
	}


	/**
	 * test not-allowed tags
	 */
	public function testNotAllowedTags() {
		ElasticPress\Features::factory()->activate_feature( 'searchterm_highlighting' );

		// Need to call this since it's hooked to init
		ElasticPress\Features::factory()->setup_features();

		// a tag that is not in the array of allowed tags
		$not_allowed = 'div';

		$tag = ElasticPress\Features::factory()->get_registered_feature( 'searchterm_highlighting' )->get_highlighting_tag( $not_allowed );

		$this->assertTrue( $tag == 'mark' );
	}


	/**
	 * test color
	 */
	public function testDefaultColor() {
		ElasticPress\Features::factory()->activate_feature( 'searchterm_highlighting' );

		// Need to call this since it's hooked to init
		ElasticPress\Features::factory()->setup_features();

		$settings = ElasticPress\Features::factory()->get_registered_feature( 'searchterm_highlighting' )->get_settings();

		$default_color = $settings['highlight_color'];
		$this->assertTrue( $default_color == '' );
	}


	/**
	 * Testing possible color setting
	 */
	public function testColorSetting() {

		$data = ElasticPress\Features::factory()->update_feature(
			'searchterm_highlighting',
			array(
				'active' 			=> 1,
				'highlight_color' 	=> '#ff0',
			)
		);

		$settings = ElasticPress\Features::factory()->get_registered_feature( 'searchterm_highlighting' )->get_settings();

		$updated_color = $settings['highlight_color'];
		$this->assertTrue( $settings['highlight_color'] == '#ff0' );
	}


	/**
	 * Testing possible color setting
	 */
	public function testTagSetting() {
		$data = ElasticPress\Features::factory()->update_feature(
			'searchterm_highlighting',
			array(
				'active' 		=> 1,
				'highlight_tag' => 'span'
			)
		);

		$settings = ElasticPress\Features::factory()->get_registered_feature( 'searchterm_highlighting' )->get_settings();

		$updated_tag = $settings['highlight_tag'];
		$this->assertTrue( $updated_tag == 'span' );
	}


	/**
	 * Testing possible color setting
	 *
	 * Fails! Need to add ep_sanitize_feature_settings filter function to check
	 */
	public function testBadTagSetting() {
		$data = ElasticPress\Features::factory()->update_feature(
			'searchterm_highlighting',
			array(
				'active' 		=> 1,
				'highlight_tag' => 'div'
			)
		);

		$settings = ElasticPress\Features::factory()->get_registered_feature( 'searchterm_highlighting' )->get_settings();

		$updated_tag = $settings['highlight_tag'];
		$this->assertTrue( $updated_tag == 'mark' );
	}


	/**
	 * Testing excerpt settings
	 */
	public function testExcerptSetting() {

		$data = ElasticPress\Features::factory()->update_feature(
			'searchterm_highlighting',
			array(
				'active' 			=> 1,
				'highlight_excerpt' => 1
			)
		);

		$settings = ElasticPress\Features::factory()->get_registered_feature( 'searchterm_highlighting' )->get_settings();

		$updated_excerpt = $settings['highlight_excerpt'];
		$this->assertTrue( $updated_excerpt == 1 );
	}
}
