<?php
/**
 * Related Posts test class
 *
 * @package elasticpress
 */

/**
 * Feature Related Posts test class
 */
class FeatureRelatedPostsTest extends TestBase {

	/**
	 * Sidebar ID to be used on WP-CLI.
	 *
	 * @var string
	 */
	protected $sidebar_id = 'sidebar-1';

	/**
	 * @testdox If feature is activated, user should see “ElasticPress - Related Posts” widget in dashboard.
	 */
	public function testSeeRelatedPostsWidgetIfActivated() {
		$I = $this->openBrowserPage();

		$I->loginAs( 'wpsnapshots' );

		$I->moveTo( '/wp-admin/admin.php?page=elasticpress' );

		$I->click( '.ep-feature-related_posts .settings-button' );

		$I->click( '#feature_active_related_posts_disabled' );

		$I->click( 'a.save-settings[data-feature="related_posts"]' );

		sleep( 2 );

		// If we get "Page Crashed!" due to lack of memory, try WP-CLI.
		try {
			$I->moveTo( '/wp-admin/widgets.php' );

			$I->dontSeeText( 'ElasticPress - Related Posts' );
		} catch (\Throwable $th) {
			$cli_result = $this->runCommand( "wp widget list {$this->sidebar_id}" )['stdout'];

			$this->assertStringNotContainsString( 'ep-related-posts', $cli_result );
		}

		$I->moveTo( '/wp-admin/admin.php?page=elasticpress' );

		$I->click( '.ep-feature-related_posts .settings-button' );

		$I->click( '#feature_active_related_posts_enabled' );

		$I->click( 'a.save-settings[data-feature="related_posts"]' );

		sleep( 2 );

		// If we get "Page Crashed!" due to lack of memory, try WP-CLI.
		try {
			$I->moveTo( '/wp-admin/widgets.php' );

			$I->seeText( 'ElasticPress - Related Posts' );
		} catch (\Throwable $th) {
			$this->runCommand( "wp widget add ep-related-posts {$this->sidebar_id}" );

			$cli_result = $this->runCommand( "wp widget list {$this->sidebar_id}" )['stdout'];

			$this->assertStringContainsString( 'ep-related-posts', $cli_result );
		}
	}

	/**
	 * Test related posts widget.
	 *
	 * @testdox I can see the related posts widget.
	 */
	public function testRelatedPostsWidget() {
		$I = $this->openBrowserPage();

		$I->loginAs( 'wpsnapshots' );

		$I->moveTo( 'wp-admin/widgets.php' );

		$related_posts_widget = $I->getElement( '#widget-7_ep-related-posts-__i__ button' );

		$I->click( $related_posts_widget );

		$I->waitUntilElementVisible( '.widgets-chooser' );

		$I->click( '.widgets-chooser-add' );

		$widgets = $I->getElements( '#sidebar-1 .widget' );

		$related_posts_widget = end( $widgets );

		$widget_id = $I->getElementAttribute( $related_posts_widget, 'id' );

		$widget_id = substr( $widget_id, strpos( $widget_id, '_ep-related-posts-' ) );

		$widget_id = str_replace( '_ep-related-posts-', '', $widget_id );

		echo $widget_id;

		$I->typeInField( "#widget-ep-related-posts-$widget_id-title", 'Related Posts' );

		$I->click( "#widget-ep-related-posts-$widget_id-savewidget" );

		usleep( 1000000 );

		$posts_data = [
			[
				'title'   => 'test related posts 1',
				'content' => 'findme test 1',
			],
			[
				'title'   => 'test related posts 2',
				'content' => 'findme test 2',
			],
			[
				'title'   => 'test related posts 3',
				'content' => 'findme test 3',
			],
		];

		foreach ( $posts_data as $data ) {
			$this->publishPost( $data, $I );
		}

		$I->click( '.post-publish-panel__postpublish-buttons a.components-button' );

		$I->waitUntilElementVisible( '.widget_ep-related-posts' );

		$I->seeText( 'Related Posts' );

		$I->seeLink( 'test related posts 1' );

		$I->seeLink( 'test related posts 2' );
	}
}
