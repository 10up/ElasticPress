<?php
/**
 * Feature Document test class
 *
 * @package elasticpress
 */

/**
 * Feature Document test class
 */
class FeatureDocumentTest extends TestBase {
	/**
	 * @testdox After activating the media feature and adding a PDF to the attachment library, that PDF shows up in front-end search
	 */
	public function testSearchPdf() {
		$I = $this->openBrowserPage();

		$I->loginAs( 'wpsnapshots' );

		$this->activateDocumentFeature( $I );

		$this->moveTo( $I, '/wp-admin/admin.php?page=elasticpress' );

		$I->executeJavaScript( 'document.querySelector( ".start-sync" ).click();' );

		$I->waitUntilElementContainsText( 'Sync complete', '.sync-status' );

		$this->uploadFile( $I, dirname( __DIR__ ) . '/test-docs/pdf-file.pdf' );

		$this->moveTo( $I, '/?s=dummy+pdf' );

		$I->seeText( 'pdf-file' );
	}

	/**
	 * @testdox After activating the media feature and adding a PPTX to the attachment library, that PPTX shows up in front-end search
	 */
	public function testSearchPttx() {
		$I = $this->openBrowserPage();

		$I->loginAs( 'wpsnapshots' );

		$this->maybeSync( $I );

		$this->activateDocumentFeature( $I );

		$this->uploadFile( $I, dirname( __DIR__ ) . '/test-docs/pptx-file.pptx' );

		$this->moveTo( $I, '/?s=dummy+slide' );

		$I->seeText( 'pptx-file' );
	}

	/**
	 * @testdox After running an index with --setup via WP-CLI and adding a PDF to the attachment library, that PDF shows up in front-end search.
	 */
	public function testSearchPdfAfterCliIndexSetup() {
		$I = $this->openBrowserPage();

		$I->loginAs( 'wpsnapshots' );

		$this->activateDocumentFeature( $I );

		$this->runCommand( 'wp elasticpress index --setup --yes' );

		$this->uploadFile( $I, dirname( __DIR__ ) . '/test-docs/pdf-file.pdf' );

		$this->moveTo( $I, '/?s=dummy+pdf' );

		$I->seeText( 'pdf-file' );
	}

	private function uploadFile( $actor, $file ) {
		$this->moveTo( $actor, '/wp-admin/media-new.php?browser-uploader' );

		$actor->attachFile( '#async-upload', $file );

		$actor->click( '#html-upload' );

		$actor->waitUntilElementVisible( 'h1.wp-heading-inline' );
	}

	private function activateDocumentFeature( $actor ) {
		$this->moveTo( $actor, '/wp-admin/admin.php?page=elasticpress' );

		$class = $actor->getElementAttribute( '.ep-feature-documents', 'class' );

		if ( strpos( $class, 'feature-active' ) === false ) {
			$actor->click( '.ep-feature-documents .settings-button' );

			$actor->click( '#feature_active_documents_enabled' );

			$actor->click( 'a.save-settings[data-feature="documents"]' );

			sleep( 2 );
		}
	}

	private function maybeSync( $actor ) {
		try {
			$actor->click( 'div[data-ep-notice="upgrade_sync"] a' );

			$actor->waitUntilElementContainsText( 'Sync complete', '.sync-status' );
		} catch ( \Exception $e ) {}
	}
}
