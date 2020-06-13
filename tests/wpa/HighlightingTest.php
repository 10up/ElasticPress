<?php
/**
 * Basic test class
 *
 * @package elasticpress
 */

/**
 * PHPUnit test class
 */
class HighlightingTest extends TestBase {

	/**
	 * Setup functionality
	 */
	public function setUp() {
		parent::setUp();
	}

	/**
	 * Test a simple sync
	 *
	 * @testdox I can successfully run an index in the single site dashboard and see the indexes in the health page.
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
