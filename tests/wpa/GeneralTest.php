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
	 * @testdox If user enables plugin for the first time, it should quick setup message
	 */
	public function testFirstTimeActivation() {
		$I = $this->openBrowserPage();

		$I->loginAs( 'wpsnapshots' );

		$this->deactivatePlugin( $I );

		$this->activatePlugin( $I, 'fake-new-activation' );

		$this->activatePlugin( $I );

		$I->seeText( 'ElasticPress is almost ready to go. You just need to sync your content.', '#message' );

		$this->deactivatePlugin( $I, 'fake-new-activation' );
	}

}
