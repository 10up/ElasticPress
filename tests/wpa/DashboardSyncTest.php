<?php
/**
 * Dashboard Sync test class
 *
 * @package elasticpress
 */

use WPAcceptance\PHPUnit\Actor;

/**
 * Dashboard Sync test class
 */
class DashboardSyncTest extends TestBase {
	/**
	 * @testdox If user clicks the sync button, all published posts should sync.
	 */
	public function testClickSyncButtonSinglesite() {

		$I = $this->openBrowserPage();

		$I->loginAs( 'wpsnapshots' );

		$this->runCommand( 'wp elasticpress index --setup' );

		$this->runCommand( 'wp elasticpress delete-index' );

		$I->moveTo( 'wp-admin/admin.php?page=elasticpress-health' );

		$I->seeText( 'We could not find any data for your Elasticsearch indices.' );

		$I->moveTo( 'wp-admin/admin.php?page=elasticpress' );

		$I->click( '.start-sync' );

		$I->waitUntilElementContainsText( 'Sync complete', '.sync-status' );

		$I->moveTo( 'wp-admin/admin.php?page=elasticpress-health' );

		$I->dontSeeText( 'We could not find any data for your Elasticsearch indices.' );

		foreach ( $this->indexes as $index_name ) {
			$I->seeText( $index_name );
		}
	}

	/**
	 * @testdox If users click the sync button in multisite, network activated mode, all published posts across all sites should sync.
	 */
	public function testClickSyncButtonMultisite() {
		$I = $this->openBrowserPage();

		$I->loginAs( 'wpsnapshots' );

		$this->deactivatePlugin( $I );

		$this->activatePlugin( $I, 'elasticpress', true );

		$this->runCommand( 'wp elasticpress delete-index --network-wide' );

		$I->moveTo( 'wp-admin/network/sites.php' );

		$I->checkOptions( '.index-toggle' );

		$I->moveTo( 'wp-admin/network/admin.php?page=elasticpress-health' );

		$I->seeText( 'We could not find any data for your Elasticsearch indices.' );

		$I->moveTo( 'wp-admin/network/admin.php?page=elasticpress' );

		$I->click( '.start-sync' );

		$I->waitUntilElementContainsText( 'Sync complete', '.sync-status' );

		$I->moveTo( 'wp-admin/network/admin.php?page=elasticpress-health' );

		$I->dontSeeText( 'We could not find any data for your Elasticsearch indices.' );

		$this->deactivatePlugin( $I, 'elasticpress', true );

		$this->activatePlugin( $I );
	}

	/**
	 * @testdox If user leaves page during sync, sync will stop. If user returns to sync page, user should be able to resume the sync.
	 */
	public function testResumeSync() {

		$I = $this->openBrowserPage();

		$I->loginAs( 'wpsnapshots' );

		$this->runCommand( 'wp elasticpress index --setup' );

		$this->runCommand( 'wp elasticpress delete-index' );

		$I->moveTo( 'wp-admin/admin.php?page=elasticpress-health' );

		$I->seeText( 'We could not find any data for your Elasticsearch indices.' );

		$I->moveTo( 'wp-admin/admin.php?page=elasticpress' );

		$I->click( '.start-sync' );

		$I->waitUntilElementVisible( '.pause-sync' );

		sleep( 1 );

		$I->moveTo( 'wp-admin/index.php' );

		$I->moveTo( 'wp-admin/admin.php?page=elasticpress' );

		$I->seeText( 'Sync paused', '.sync-status' );

		$I->executeJavaScript( 'document.querySelector( ".resume-sync" ).click();' );

		$I->waitUntilElementContainsText( 'Sync complete', '.sync-status' );

		$I->moveTo( 'wp-admin/admin.php?page=elasticpress-health' );

		$I->dontSeeText( 'We could not find any data for your Elasticsearch indices.' );

		foreach ( $this->indexes as $index_name ) {
			$I->seeText( $index_name );
		}
	}

	/**
	 * @testdox If user tries to activate/deactivate features during a sync, they will be prevented.
	 */
	public function testPreventFeaturesActivationDuringSync() {
		$I = $this->openBrowserPage();

		$I->loginAs( 'wpsnapshots' );

		$I->moveTo( 'wp-admin/admin.php?page=elasticpress' );

		$this->runCommand( 'wp elasticpress index --setup' );

		$I->click( '.start-sync' );

		sleep( 1 );

		$I->executeJavaScript( 'document.querySelector( ".pause-sync" ).click();' );

		$this->assertStringContainsString( 'syncing', $I->elementToString( $I->getElement( '.error-overlay' ) ) );

		$I->executeJavaScript( 'document.querySelector( ".resume-sync" ).click();' );

		$I->waitUntilElementContainsText( 'Sync complete', '.sync-status' );

		$this->assertStringNotContainsString( 'syncing', $I->elementToString( $I->getElement( '.error-overlay' ) ) );
	}

	/**
	 * @testdox If a user tries to WP-CLI sync during dashboard sync, they will be prevented (and vice-versa).
	 */
	public function testWpCliSyncDuringDashboardSync() {
		$I = $this->openBrowserPage();

		$I->loginAs( 'wpsnapshots' );

		$this->runCommand( 'wp elasticpress index --setup' );

		$I->moveTo( 'wp-admin/admin.php?page=elasticpress' );

		$I->click( '.start-sync' );

		sleep( 1 );

		$I->executeJavaScript( 'document.querySelector( ".pause-sync" ).click();' );

		sleep( 1 );

		$cli_result = $this->runCommand( 'wp elasticpress index' )['stdout'];

		$this->assertStringContainsString( 'An index is already occuring', $cli_result );

		$I->executeJavaScript( 'document.querySelector( ".resume-sync" ).click();' );

		$I->waitUntilElementContainsText( 'Sync complete', '.sync-status' );
	}

}
