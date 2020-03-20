<?php
/**
 * Related Posts test class
 *
 * @package elasticpress
 */

/**
 * Related Posts test class
 */
class RelatedPostsTest extends TestBase {

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
