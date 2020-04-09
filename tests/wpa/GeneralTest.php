<?php
/**
 * General test class
 *
 * @package elasticpress
 */

/**
 * General test class
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
	public function testSyncUpdatedPostData() {
		$I = $this->openBrowserPage();

		$I->loginAs( 'wpsnapshots' );

		$data = [
			'title' => 'Test ElasticPress 1',
		];

		$this->publishPost( $data, $I );

		$I->moveTo( '/?s=Test+ElasticPress+1' );

		$I->seeText( 'Test ElasticPress 1', '.hentry' );
	}

	/**
	 * @testdox If user activates plugin with an Elasticsearch version before or after min/max requirements, they should get a warning in the dashboard.
	 */
	public function testUnsupportedElasticsearchVersion() {
		$I = $this->openBrowserPage();

		$I->loginAs( 'wpsnapshots' );

		if ( $this->isElasticPressIo( $I ) ) {
			return;
		}

		$this->runCommand( 'wp elasticpress index --setup' );

		$this->deactivatePlugin( $I );

		$this->activatePlugin( $I, 'unsupported-elasticsearch-version' );

		$this->activatePlugin( $I );

		$I->seeText( 'ElasticPress may or may not work properly.' );

		$this->deactivatePlugin( $I, 'unsupported-elasticsearch-version' );
	}
}
