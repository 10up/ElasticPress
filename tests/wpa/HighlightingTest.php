<?php
/**
 * HighlightingTest class
 *
 * @package elasticpress
 */

/**
 * Sub-Feature Highlighting Class
 */
class HighlightingTest extends TestBase {

	/**
	 * Setup functionality
	 */
	public function setUp() {
		parent::setUp();
	}

	/**
	 * Test seeing a document being highlighted.
	 *
	 * @testdox I can successfully index a document and see it has been highlighted with the .ep-highlight class when I search for it.
	 */
	public function testHighlightingColor() {
		$I = $this->openBrowserPage();

		$I->loginAs( 'wpsnapshots' );

		$data = [
			'title'   => 'test highlight color',
			'content' => 'findme findme findme',
		];

		$this->publishPost( $data, $I );

		$I->moveTo( '/?s=findme' );

		$I->seeElement( '.ep-highlight' );
	}
}
