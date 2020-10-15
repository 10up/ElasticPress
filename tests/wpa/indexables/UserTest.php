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
	 * Search user by username.
	 *
	 * @param string                      $username Username.
	 * @param \WPAcceptance\PHPUnit\Actor $actor    Current actor.
	 */
	public function searchUser( $username = '', \WPAcceptance\PHPUnit\Actor $actor ) {
		$actor->moveTo( 'wp-admin/users.php' );

		$actor->waitUntilElementVisible( '#user-search-input' );

		$actor->typeInField( '#user-search-input', $username );

		$actor->click( '#search-submit' );

		$actor->waitUntilElementVisible( '.wp-list-table' );
	}

	/**
	 * Test a simple user sync.
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

		$this->searchUser( 'testuser', $I );

		$I->seeText( 'testuser@example.com' );

		$I->click( '#wp-admin-bar-debug-bar' );

		$I->click( '#debug-menu-link-EP_Debug_Bar_ElasticPress' );

		$I->seeText( 'Query Response Code: HTTP 200' );

		$I->waitUntilElementVisible( '.query-result-toggle' );

		$I->click( '.query-result-toggle' );

		$I->seeText( '"total": 1', '.query-results' );

		$I->seeText( '"user_email": "testuser@example.com"', '.query-results' );
	}

	/**
	 * Test a simple user sync with meta.
	 *
	 * @testdox I can successfully run a simple user sync with meta.
	 */
	public function testUserMetaSync() {
		$I = $this->openBrowserPage();

		$I->loginAs( 'wpsnapshots' );

		$this->searchUser( 'testuser', $I );

		$I->seeText( 'testuser@example.com' );

		$user_link = $I->getElement( '#the-list .column-username a' );

		$I->click( $user_link );

		$I->waitUntilElementVisible( '.form-table' );

		$I->typeInField( '#first_name', 'John' );

		$I->typeInField( '#last_name', 'Doe' );

		$I->click( '#submit' );

		$I->moveTo( 'wp-admin/admin.php?page=elasticpress' );

		$I->click( '.start-sync' );

		$I->waitUntilElementContainsText( 'Sync complete', '.sync-status' );

		$this->searchUser( 'testuser', $I );

		$I->seeText( 'testuser@example.com' );

		$I->click( '#wp-admin-bar-debug-bar' );

		$I->click( '#debug-menu-link-EP_Debug_Bar_ElasticPress' );

		$I->seeText( 'Query Response Code: HTTP 200' );

		$I->waitUntilElementVisible( '.query-result-toggle' );

		$I->click( '.query-result-toggle' );

		$I->seeText( '"total": 1', '.query-results' );

		$I->seeText( '"user_email": "testuser@example.com"', '.query-results' );

		$I->seeText( '"value": "John"', '.query-results' );

		$I->seeText( '"value": "Doe"', '.query-results' );
	}
}
