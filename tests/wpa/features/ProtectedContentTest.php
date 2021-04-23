<?php
/**
 * WP Acceptance tests for Protected Content
 *
 * @package elasticpress
 */

/**
 * Protected Content test class
 */
class ProtectedContentTest extends TestBase {

	/**
	 * Test if the Protected Content feature can be turned on.
	 *
	 * @testdox I can turn on the Protected Content feature and see a successful sync.
	 */
	public function testTurnProtectedContentFeatureOn() {

		$I = $this->openBrowserPage();

		$I->loginAs( 'wpsnapshots' );

		$I->moveTo( 'wp-admin/admin.php?page=elasticpress' );

		$I->click( '.ep-feature-protected_content .settings-button' );

		$I->waitUntilElementVisible( 'input[id="feature_active_protected_content_enabled"]' );

		$I->checkOptions( 'input[id="feature_active_protected_content_enabled"]' );

		$I->click( 'a.button[data-feature="protected_content"]' );

		$I->waitUntilElementVisible( '.start-sync' );

		$I->seeText( 'Sync complete', '.sync-status' );

		$I->seeText( 'Protected Content', '.ep-feature-protected_content h2' );
	}

	/**
	 * Test if the Protected Content works on the Posts List Screen
	 *
	 * @testdox I see 1 query running against ES on WordPress Dashboard -> Posts List Screen.
	 */
	public function testProtectedContentPostsList() {

		$I = $this->openBrowserPage();

		$I->loginAs( 'wpsnapshots' );

		$this->activateFeature( 'protected_content', $I );

		$I->moveTo( 'wp-admin/edit.php' );

		$I->click( '#wp-admin-bar-debug-bar' );

		$I->click( '#debug-menu-link-EP_Debug_Bar_ElasticPress' );

		$I->seeText( '1', '#debug-menu-target-EP_Debug_Bar_ElasticPress' );
	}

	/**
	 * Test if the Protected Content works on the Draft Posts List Screen
	 *
	 * @testdox I see 2 hits as in ES query results on WordPress Dashboard -> Draft Posts List Screen.
	 */
	public function testProtectedContentPostsDraftsList() {
		$this->runCommand( 'wp elasticpress index --setup' );

		$I = $this->openBrowserPage();

		$I->loginAs( 'wpsnapshots' );

		$this->activateFeature( 'protected_content', $I );

		$data = [
			'title'  => 'Test ElasticPress Draft',
			'status' => 'draft',
		];

		$this->publishPost( $data, $I );

		// Give some time to the async request that indexes the post.
		sleep( 5 );

		$I->moveTo( 'wp-admin/edit.php?post_status=draft&post_type=post' );

		$I->click( '#wp-admin-bar-debug-bar' );

		$I->click( '#debug-menu-link-EP_Debug_Bar_ElasticPress' );

		$I->waitUntilElementVisible( '.query-result-toggle' );

		$I->click( '.query-result-toggle' );

		$this->checkTotal( 2, $I );
	}
}
