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
	}

	/**
	 * @testdox If a user searches the phrase “10up loves elasticpress”, the post with the exact phrase in post_content will show higher than the post with only “elasticpress” in the content.
	 */
	public function testExactMatchesShowHigher() {
	}

	/**
	 * @testdox If a user searches “10up loves elasticpress” with is in the post_content of two exact duplicate posts, the newer post will show up higher.
	 */
	public function testNewerPostsShowHigher() {
		$I = $this->openBrowserPage();

		$I->loginAs( 'wpsnapshots' );

		$this->publishPost( [
			'title'   => 'Duplicate post',
			'content' => '10up loves elasticpress',
		] );

		$this->publishPost( [
			'title'   => 'Duplicate post',
			'content' => '10up loves elasticpress',
		] );
	}
}
