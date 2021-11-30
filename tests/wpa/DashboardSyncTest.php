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

		$this->runCommand( 'wp elasticpress index --setup --yes' );

		$this->runCommand( 'wp elasticpress delete-index --yes' );

		$this->moveTo( $I, 'wp-admin/admin.php?page=elasticpress-health' );

		$I->seeText( 'We could not find any data for your Elasticsearch indices.' );

		$this->moveTo( $I, 'wp-admin/admin.php?page=elasticpress' );

		$I->executeJavaScript( 'document.querySelector( ".start-sync" ).click();' );

		$I->waitUntilElementContainsText( 'Sync complete', '.sync-status' );

		$this->moveTo( $I, 'wp-admin/admin.php?page=elasticpress-health' );

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

		$this->deactivatePlugin();

		$this->activatePlugin( null, 'elasticpress', true );

		$this->runCommand( 'wp elasticpress index --setup --yes' );

		$this->runCommand( 'wp elasticpress delete-index --network-wide --yes' );

		$this->moveTo( $I, 'wp-admin/network/sites.php' );

		$I->checkOptions( '.index-toggle' );

		$this->moveTo( $I, 'wp-admin/network/admin.php?page=elasticpress-health' );

		$I->seeText( 'We could not find any data for your Elasticsearch indices.' );

		$this->moveTo( $I, 'wp-admin/network/admin.php?page=elasticpress' );

		$I->executeJavaScript( 'document.querySelector( ".start-sync" ).click();' );

		$I->waitUntilElementContainsText( 'Sync complete', '.sync-status' );

		$this->moveTo( $I, 'wp-admin/network/admin.php?page=elasticpress-health' );

		$I->dontSeeText( 'We could not find any data for your Elasticsearch indices.' );

		$this->deactivatePlugin( null, 'elasticpress', true );

		$this->activatePlugin();
	}

	/**
	 * @testdox If user leaves page during sync, sync will stop. If user returns to sync page, user should be able to resume the sync.
	 */
	public function testResumeSync() {

		$I = $this->openBrowserPage();

		$I->loginAs( 'wpsnapshots' );

		$this->runCommand( 'wp elasticpress index --setup --yes' );

		$this->runCommand( 'wp elasticpress delete-index --yes' );

		$this->moveTo( $I, 'wp-admin/admin.php?page=elasticpress-health' );

		$I->seeText( 'We could not find any data for your Elasticsearch indices.' );

		$this->moveTo( $I, 'wp-admin/admin.php?page=elasticpress' );

		$I->executeJavaScript( 'document.querySelector( ".start-sync" ).click();' );

		$I->waitUntilElementVisible( '.pause-sync' );

		sleep( 1 );

		$this->moveTo( $I, 'wp-admin/index.php' );

		$this->moveTo( $I, 'wp-admin/admin.php?page=elasticpress' );

		$I->seeText( 'Sync paused', '.sync-status' );

		$I->executeJavaScript( 'document.querySelector( ".resume-sync" ).click();' );

		$I->waitUntilElementContainsText( 'Sync complete', '.sync-status' );

		$this->moveTo( $I, 'wp-admin/admin.php?page=elasticpress-health' );

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

		$this->moveTo( $I, 'wp-admin/admin.php?page=elasticpress' );

		$this->runCommand( 'wp elasticpress index --setup --yes' );

		$I->executeJavaScript( 'document.querySelector( ".start-sync" ).click();' );

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

		$this->runCommand( 'wp elasticpress index --setup --yes' );

		// Slowing the index process a bit.
		$old_value = $this->setPerIndexCycle( 10, $I );

		$this->moveTo( $I, 'wp-admin/admin.php?page=elasticpress' );

		$I->executeJavaScript( 'document.querySelector( ".start-sync" ).click();' );

		$I->waitUntilElementVisible( '.pause-sync' );

		sleep( 1 );

		$I->executeJavaScript( 'document.querySelector( ".pause-sync" ).click();' );

		$I->waitUntilElementVisible( '.resume-sync' );

		// Specially when requesting an external server, e.g. EP.io, we
		// have to wait a bit for the AJAX requests.
		sleep( 5 );

		$cli_result = $this->runCommand( 'wp elasticpress index' )['stdout'];

		$this->assertStringContainsString( 'An index is already occurring', $cli_result );

		$I->executeJavaScript( 'document.querySelector( ".resume-sync" ).click();' );

		$I->waitUntilElementContainsText( 'Sync complete', '.sync-status' );

		$this->setPerIndexCycle( $old_value, $I );
	}

}
