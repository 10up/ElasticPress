<?php
/**
 * Basic test class
 *
 * @package elasticpress
 */

/**
 * PHPUnit test class
 */
class BasicTest extends TestBase {

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
	public function testSyncComplete() {
		$I = $this->openBrowserPage();

		$I->loginAs( 'wpsnapshots' );

		$I->moveTo( 'wp-admin/admin.php?page=elasticpress' );

		$I->click ('.start-sync' );

		$I->waitUntilElementContainsText( 'Sync complete', '.sync-status' );

		$I->moveTo( 'wp-admin/admin.php?page=elasticpress-health' );

		foreach ( $this->indexes as $index_name ) {
			$I->seeText( $index_name );
		}
	}
}
