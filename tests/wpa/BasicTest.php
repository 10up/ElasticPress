<?php
/**
 * Basic test class
 *
 * @package elasticpress
 */

/**
 * PHPUnit test class
 */
class BasicTest extends TestBase {

	/**
	 * Setup functionality
	 */
	public function setUp() {
		parent::setUp();
	}

	/**
	 * Test a simple sync
	 *
	 * @testdox I can successfully run an index in the single site dashboard and see the indexes in the health page.
	 */
	public function testDashboardSyncComplete() {
		$I = $this->openBrowserPage();

		$I->loginAs( 'wpsnapshots' );

		$I->moveTo( 'wp-admin/admin.php?page=elasticpress' );

		$I->click( '.start-sync' );

		$I->waitUntilElementContainsText( 'Sync complete', '.sync-status' );

		$I->moveTo( 'wp-admin/admin.php?page=elasticpress-health' );

		foreach ( $this->indexes as $index_name ) {
			$I->seeText( $index_name );
		}
	}

	/**
	 * Test a simple search
	 *
	 * @testdox I can search on the front end and ES returns a proper response code.
	 */
	public function testSearch() {
		$this->runCommand( 'wp elasticpress index --setup' );

		$I = $this->openBrowserPage();

		$I->loginAs( 'wpsnapshots' );

		$I->moveTo( '/?s=test' );

		$I->click( '#wp-admin-bar-debug-bar' );

		$I->click( '#debug-menu-link-EP_Debug_Bar_ElasticPress' );

		$I->seeText( 'Query Response Code: HTTP 200' );

		// No error codes
		$I->dontSeeText( 'Query Response Code: HTTP 4' );

		$I->dontSeeText( 'Query Response Code: HTTP 5' );
	}

	/**
	 * Test weighting adjustments
	 *
	 * @testdox I dont see a post in search that only matches by title when title is set as not searchable in the weighting dashboard.
	 */
	public function testWeightingOnOff() {
		$this->runCommand( 'wp elasticpress index --setup' );

		$I = $this->openBrowserPage();

		$I->loginAs( 'wpsnapshots' );

		$data = [
			'title' => 'Test ElasticPress 1',
		];

		$this->publishPost( $data, $I );

		sleep( 2 );

		$I->moveTo( '/?s=Test+ElasticPress+1' );

		$I->seeText( 'Test ElasticPress 1', '.hentry' );

		$I->moveTo( 'wp-admin/admin.php?page=elasticpress-weighting' );

		$I->click( '#post-post_title-enabled' );

		$I->click( '#submit' );

		$I->waitUntilElementContainsText( 'Changes Saved', '.notice-success' );

		$I->moveTo( '/?s=Test+ElasticPress+1' );

		$I->dontSeeText( 'Test ElasticPress 1', '.hentry' );

		// Reset Changed Settings.
		$I->moveTo( 'wp-admin/admin.php?page=elasticpress-weighting' );

		$I->click( '#post-post_title-enabled' );

		$I->click( '#submit' );

		$I->waitUntilElementContainsText( 'Changes Saved', '.notice-success' );
	}

	/**
	 * Test content/title weighting
	 *
	 * @testdox I can increase post_title weighting and influence search results.
	 */
	public function testTitleContentWeighting() {
		$this->runCommand( 'wp elasticpress index --setup' );

		$I = $this->openBrowserPage();

		$I->loginAs( 'wpsnapshots' );

		$data = [
			'title'   => 'test weighting content',
			'content' => 'findme findme findme',
		];

		$this->publishPost( $data, $I );

		$data = [
			'title'   => 'test weighting title findme',
			'content' => 'Nothing here.',
		];

		$this->publishPost( $data, $I );

		$I->moveTo( '/?s=findme' );

		$posts = $I->getElements( '.post' );

		$first_post = $posts[0];

		$I->seeText( 'test weighting content', $first_post );

		$I->moveTo( 'wp-admin/admin.php?page=elasticpress-weighting' );

		$I->setElementProperty( 'input[name="weighting[post][post_title][weight]"]', 'value', 20 );

		$I->click( '#submit' );

		$I->waitUntilElementContainsText( 'Changes Saved', '.notice-success' );

		$I->moveTo( '/?s=findme' );

		$posts = $I->getElements( '.post' );

		$first_post = $posts[0];

		$I->seeText( 'test weighting title findme', $first_post );

		// Reset Changed Settings.
		$I->moveTo( 'wp-admin/admin.php?page=elasticpress-weighting' );

		$I->setElementProperty( 'input[name="weighting[post][post_title][weight]"]', 'value', 1 );

		$I->click( '#submit' );
	}

	/**
	 * Test autosugest
	 *
	 * @testdox When I type in a search field on the front end, I see the autosuggest dropdown.
	 */
	public function testAutosuggestDropdownShows() {
		$this->runCommand( 'wp elasticpress index --setup' );

		$I = $this->openBrowserPage();

		$I->moveTo( '/' );

		$I->waitUntilElementVisible( '.search-toggle' );

		$I->click( '.search-toggle' );

		$I->waitUntilElementVisible( '#search-form-1' );

		$I->typeInField( '#search-form-1', 'blog' );

		$I->waitUntilElementVisible( '.ep-autosuggest' );

		$I->seeElement( '.ep-autosuggest' );

		$I->seeText( 'a Blog page', '.ep-autosuggest' );
	}
}
