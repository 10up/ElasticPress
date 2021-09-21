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

		$this->moveTo( $I, '/wp-admin/admin.php?page=elasticpress' );

		$I->click( '.ep-feature-related_posts .settings-button' );

		$I->click( '#feature_active_related_posts_disabled' );

		$I->click( 'a.save-settings[data-feature="related_posts"]' );

		sleep( 2 );

		$this->openWidgetsPage( $I );

		$I->click( '.edit-widgets-header-toolbar__inserter-toggle' );

		$I->waitUntilElementVisible( '.block-editor-inserter__search-input' );

		$I->typeInField( '.block-editor-inserter__search-input', 'ElasticPress Related Posts' );

		$I->dontSeeText( 'ElasticPress - Related Posts', '.block-editor-block-types-list' ); // Legacy Widget

		$I->dontSeeText( 'Related Posts (ElasticPress)', '.block-editor-block-types-list' );

		$this->moveTo( $I, '/wp-admin/admin.php?page=elasticpress' );

		$I->click( '.ep-feature-related_posts .settings-button' );

		$I->click( '#feature_active_related_posts_enabled' );

		$I->click( 'a.save-settings[data-feature="related_posts"]' );

		sleep( 2 );

		$this->openWidgetsPage( $I );

		$I->click( '.edit-widgets-header-toolbar__inserter-toggle' );

		$I->waitUntilElementVisible( '.block-editor-inserter__search-input' );

		$I->typeInField( '.block-editor-inserter__search-input', 'ElasticPress Related Posts' );

		$I->seeText( 'ElasticPress - Related Posts', '.block-editor-block-types-list' ); // Legacy Widget

		$I->seeText( 'Related Posts (ElasticPress)', '.block-editor-block-types-list' );
	}

	/**
	 * Test related posts widget.
	 *
	 * @testdox I can see the related posts widget.
	 */
	public function testRelatedPostsWidget() {
		$this->maybeEnableFeature( 'related_posts' );

		$I = $this->openBrowserPage();

		$I->loginAs( 'wpsnapshots' );

		$this->openWidgetsPage( $I );

		$I->click( '.edit-widgets-header-toolbar__inserter-toggle' );

		$I->waitUntilElementVisible( '.block-editor-inserter__search-input' );

		$I->click( '.block-editor-inserter__panel-content [class*="ep-related-posts"]' );

		$I->waitUntilElementVisible( 'input[name^="widget-ep-related-posts"]' );

		$I->typeInField( 'input[name^="widget-ep-related-posts"]', 'Related Posts' );

		$update_button = $I->getElement( ".edit-widgets-header__actions .components-button.is-primary" );

		$I->click( $update_button );

		$I->waitUntilPageSourceContains( 'Widgets saved.' );

		sleep ( 1 );

		/**
		 * It seems sometimes WP keeps a dirty state even after a successful save.
		 * When that happens, we get stuck with a "Are you sure you want to leave...?" message.
		 *
		 * Saving it again seems to fix the issue.
		 *
		 * @todo Investigate why WordPress gets stuck in that dirty state.
		 */
		if ( $I->elementIsEnabled( $update_button ) ) {
			$I->click( $update_button );

			sleep( 2 );
		}

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
