<?php
/**
 * General test class
 *
 * @package elasticpress
 */

/**
 * PHPUnit test class
 */
class GeneralTest extends TestBase {
	/**
	 * @testdox If user enables plugin, it should add settings page in WordPress Dashboard
	 */
	public function testAdminSettingsPage() {
		$I = $this->openBrowserPage();

		$I->loginAs( 'wpsnapshots' );

		$this->activatePlugin( $I );

		$I->seeText( 'ElasticPress', '.toplevel_page_elasticpress .wp-menu-name' );
	}

	/**
	 * @testdox If user enables plugin for the first time, it should show a quick setup message.
	 */
	public function testFirstTimeActivation() {
		$I = $this->openBrowserPage();

		$I->loginAs( 'wpsnapshots' );

		$this->deactivatePlugin( $I );

		$this->activatePlugin( $I, 'fake-new-activation' );

		$this->activatePlugin( $I );

		$I->seeText( 'ElasticPress is almost ready to go.' );

		$this->deactivatePlugin( $I, 'fake-new-activation' );
	}

	/**
	 * @testdox If user setup plugin for the first time, it should ask to sync all the posts.
	 */
	public function testFirstSetup() {
		$I = $this->openBrowserPage();

		$I->loginAs( 'wpsnapshots' );

		$this->deactivatePlugin( $I );

		$this->activatePlugin( $I, 'fake-new-activation' );

		$this->activatePlugin( $I );

		$I->moveTo( '/wp-admin/admin.php?page=elasticpress' );

		$I->seeText( 'Index Your Content', '.setup-button' );

		$this->deactivatePlugin( $I, 'fake-new-activation' );
	}

	/**
	 * @testdox If user creates/updates a published post, post data should sync with Elasticsearch with post data and meta details.
	 */
}
