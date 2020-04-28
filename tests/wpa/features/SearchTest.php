<?php
/**
 * Feature Search test class
 *
 * @package elasticpress
 */

/**
 * Feature Search test class
 */
class FeatureSearchTest extends TestBase {
	/**
	 * @testdox If user searches from default WordPress search (front-end), it should fetch results from Elasticsearch
	 */
	public function testSearchResultsFetchFromElasticsearch() {
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
	 * @testdox If a user searches the phrase “10up loves elasticpress”, the post with the exact phrase in post_content will show higher than the post with only “elasticpress” in the content.
	 */
	public function testExactMatchesShowHigher() {

		$I = $this->openBrowserPage();

		$I->loginAs( 'wpsnapshots' );

		$this->runCommand( 'wp elasticpress index --setup' );

		$this->publishPost( [
			'title'   => 'Higher',
			'content' => '10up loves elasticpress',
		], $I );

		$this->publishPost( [
			'title'   => 'Lower',
			'content' => 'elasticpress',
		], $I );

		$I->moveTo( '/?s=10up+loves+elasticpress' );

		$I->seeText( 'Higher', '#site-content article:nth-of-type(1)' );

		$I->seeText( 'Lower' );
	}

	/**
	 * @testdox If a user searches “10up loves elasticpress” with is in the post_content of two exact duplicate posts, the newer post will show up higher.
	 */
	public function testNewerDuplicatedPostsShowHigher() {

		// The story is needed to revise.
		return;

		$I = $this->openBrowserPage();

		$I->loginAs( 'wpsnapshots' );

		$this->runCommand( 'wp elasticpress index --setup' );

		$this->publishPost( [
			'title'   => 'Duplicated post',
			'content' => '10up loves elasticpress',
		], $I );

		$this->publishPost( [
			'title'   => 'Duplicated post',
			'content' => '10up loves elasticpress',
		], $I );

		$I->moveTo( '/?s=10up+loves+elasticpress' );

		$I->seeText( '10up loves elasticpress', '#site-content article:nth-of-type(1)' );
		$I->seeText( '10up loves elasticpress', '#site-content article:nth-of-type(2)' );

		$firstPostId = $this->getPostIdFromClassName( $I->getElementAttribute( '#site-content article:nth-of-type(1)', 'class' ) );
		$secondPostId = $this->getPostIdFromClassName( $I->getElementAttribute( '#site-content article:nth-of-type(2)', 'class' ) );

		$this->assertTrue( $firstPostId > $secondPostId );
	}

	/**
	 * Get Post ID from front end classes.
	 *
	 * @param string $classes Classes get from HTML.
	 */
	private function getPostIdFromClassName( $classes ) {
		if ( preg_match('/post-(\d+)/', $classes, $matches) ) {
			return $matches[1];
		}

		return false;
	}
}
