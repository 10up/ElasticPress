<?php
/**
 * Feature WooCommerce test class
 *
 * @package elasticpress
 */

/**
 * Feature WooCommerce test class
 */
class FeatureWooCommerceTest extends TestBase {
	/**
	 * @testdox If user activates WooCommerce plugin, it should auto-activate WooCommerce feature.
	 */
	public function testAutoActivateFeatureIfActivateWooCommerce() {
		$I = $this->openBrowserPage();

		$I->loginAs( 'wpsnapshots' );

		$this->activatePlugin( $I, 'woocommerce' );

		$I->moveTo( '/wp-admin/admin.php?page=elasticpress' );

		$this->assertStringContainsString( 'feature-active', $I->getElementAttribute( '.ep-feature-woocommerce', 'class' ) );
	}

	/**
	 * @testdox If user activates WooCommerce feature, it should sync posts.
	 */
	public function testSyncPostsAfterActivatesWooCommerceFeature() {
		$I = $this->openBrowserPage();

		$I->loginAs( 'wpsnapshots' );

		$I->moveTo( '/wp-admin/admin.php?page=elasticpress' );

		$I->click( '.ep-feature-woocommerce .settings-button' );

		$I->click( '#feature_active_woocommerce_disabled' );

		$I->click( 'a.save-settings[data-feature="woocommerce"]' );

		sleep( 2 );

		$I->moveTo( '/wp-admin/admin.php?page=elasticpress' );

		$I->click( '.ep-feature-woocommerce .settings-button' );

		$I->click( '#feature_active_woocommerce_enabled' );

		$I->click( 'a.save-settings[data-feature="woocommerce"]' );

		$I->waitUntilElementContainsText( 'Sync complete', '.sync-status' );

		$I->seeText( 'Sync complete', '.sync-status' );
	}

	/**
	 * @testdox If user browses orders in the dashboard when admin feature is active, it should fetch results from Elasticsearch.
	 */
	public function testFetchOrdersFromElasticsearch() {
		$I = $this->openBrowserPage();

		$I->loginAs( 'wpsnapshots' );

		$I->moveTo( '/wp-admin/admin.php?page=elasticpress' );

		$I->click( '.ep-feature-protected_content .settings-button' );

		$I->click( '#feature_active_protected_content_enabled' );

		$I->click( 'a.save-settings[data-feature="protected_content"]' );

		$I->waitUntilElementContainsText( 'Sync complete', '.sync-status' );

		$I->moveTo( '/wp-admin/edit.php?post_type=shop_order' );

		$I->click( '#wp-admin-bar-debug-bar' );

		$I->click( '#debug-menu-link-EP_Debug_Bar_ElasticPress' );

		$I->seeText( 'Query Response Code: HTTP 200' );
	}

	/**
	 * @testdox If user browses products in the dashboard when admin feature is active, it should fetch results from Elasticsearch.
	 */
	public function testFetchProductsFromElasticsearch() {
		$I = $this->openBrowserPage();

		$I->loginAs( 'wpsnapshots' );

		$I->moveTo( '/wp-admin/edit.php?post_type=product' );

		$I->click( '#wp-admin-bar-debug-bar' );

		$I->click( '#debug-menu-link-EP_Debug_Bar_ElasticPress' );

		$I->seeText( 'Query Response Code: HTTP 200' );
	}

	/**
	 * @testdox If user browses product category, all the products should be pulled from Elasticsearch.
	 */
	public function testProductCategoryServedByElasticsearch() {
		$I = $this->openBrowserPage();

		$I->loginAs( 'wpsnapshots' );

		$I->moveTo( '/product-category/uncategorized' );

		$I->click( '#wp-admin-bar-debug-bar' );

		$I->click( '#debug-menu-link-EP_Debug_Bar_ElasticPress' );

		$I->seeText( 'Query Response Code: HTTP 200' );
	}

	/**
	 * @testdox If user browses any product river, all products should be pulled from Elasticsearch.
	 */
	public function testProductFilterServedByElasticsearch() {
		$I = $this->openBrowserPage();

		$I->loginAs( 'wpsnapshots' );

		$I->moveTo( '/shop/?filter_size=small' );

		$I->click( '#wp-admin-bar-debug-bar' );

		$I->click( '#debug-menu-link-EP_Debug_Bar_ElasticPress' );

		$I->seeText( 'Query Response Code: HTTP 200' );

		$this->deactivatePlugin( $I, 'woocommerce' );
	}
}
