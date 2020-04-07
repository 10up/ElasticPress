<?php
/**
 * Feature Related Posts test class
 *
 * @package elasticpress
 */

/**
 * Feature Related Posts test class
 */
class FeatureRelatedPostsTest extends TestBase {
	/**
	 * @testdox If feature is activated, user should see “ElasticPress - Related Posts” widget in dashboard.
	 */
	public function testSeeRelatedPostsWidgetIfActivated() {
		$I = $this->openBrowserPage();

		$I->loginAs( 'wpsnapshots' );

		$I->moveTo( '/wp-admin/admin.php?page=elasticpress' );

		$I->click( '.ep-feature-related_posts .settings-button' );

		$I->checkOptions( '#feature_active_related_posts_disabled' );

		$I->moveTo( '/wp-admin/widgets.php' );

		$I->dontSeeText( 'ElasticPress - Related Posts' );

		$I->moveTo( '/wp-admin/admin.php?page=elasticpress' );

		$I->click( '.ep-feature-related_posts .settings-button' );

		$I->checkOptions( '#feature_active_related_posts_enabled' );

		$I->moveTo( '/wp-admin/widgets.php' );

		$I->seeText( 'ElasticPress - Related Posts' );
	}
}
