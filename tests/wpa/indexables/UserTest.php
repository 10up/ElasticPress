<?php
/**
 * User test class
 *
 * @package elasticpress
 */

/**
 * User test class
 */
class UserTest extends TestBase {

	/**
	 * Test a simple user sync
	 *
	 * @testdox I can successfully run a simple user sync.
	 */
	public function testUserSync() {
		$I = $this->openBrowserPage();

		$I->loginAs( 'wpsnapshots' );

		$data = [
			'user_login' => 'testuser',
			'user_email' => 'testuser@example.com',
		];

		$this->createUser( $data, $I );

		$I->moveTo( 'wp-admin/admin.php?page=elasticpress' );

		$I->click( '.start-sync' );

		$I->waitUntilElementContainsText( 'Sync complete', '.sync-status' );

		$I->moveTo( 'wp-admin/users.php' );

		$I->waitUntilElementVisible( '#user-search-input' );

		$I->typeInField( '#user-search-input', 'testuser' );

		$I->click( '#search-submit' );

		$I->waitUntilElementVisible( '.wp-list-table' );

		$I->seeText( 'testuser@example.com' );

		$I->click( '#wp-admin-bar-debug-bar' );

		$I->click( '#debug-menu-link-EP_Debug_Bar_ElasticPress' );

		$I->seeText( 'Query Response Code: HTTP 200' );

		$I->waitUntilElementVisible( '.query-result-toggle' );

		$I->click( '.query-result-toggle' );

		$I->seeText( '"total": 1', '.query-results' );

		$I->seeText( '"user_email": "testuser@example.com"', '.query-results' );
	}
}
