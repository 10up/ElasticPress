<?php
/**
 * Feature Autosuggest test class
 *
 * @package elasticpress
 */

/**
 * Feature Autosuggest test class
 */
class FeatureAutosuggestTest extends TestBase {
	/**
	 * @testdox If the user types a post title in the search box, a drop-down appears with that post.
	 */
	public function testSeeAutosuggestDropdown() {
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

	/**
	 * @testdox If the user types a post title in the search box, a drop-down appears with that post.
	 */
	public function testSeeTypedPostTitleInDropdown() {
		$this->runCommand( 'wp elasticpress index --setup' );

		$I = $this->openBrowserPage();

		$I->moveTo( '/' );

		$I->waitUntilElementVisible( '.search-toggle' );

		$I->click( '.search-toggle' );

		$I->waitUntilElementVisible( '#search-form-1' );

		$I->typeInField( '#search-form-1', 'Markup: HTML Tags and Formatting' );

		$I->waitUntilElementVisible( '.ep-autosuggest' );

		$I->seeElement( '.ep-autosuggest' );

		$I->seeText( 'Markup: HTML Tags and Formatting', '.ep-autosuggest' );
	}

	/**
	 * @testdox If the user types a category in the search box, a drop-down appears with a post associated with that category.
	 */
	public function testSearchForPostByCategory() {
		/**
		 * Set weighting
		 */
		$this->updateWeighting( [
			'post' => [
				'terms.category.name' => [
					'weight'  => 1,
					'enabled' => true,
				],
			],
		] );

		$this->runCommand( 'wp elasticpress index --setup' );

		$I = $this->openBrowserPage();

		$I->moveTo( '/' );

		$I->waitUntilElementVisible( '.search-toggle' );

		$I->click( '.search-toggle' );

		$I->waitUntilElementVisible( '#search-form-1' );

		$I->typeInField( '#search-form-1', 'aciform' );

		$I->waitUntilElementVisible( '.ep-autosuggest' );

		$I->seeElement( '.ep-autosuggest' );

		$I->seeText( 'Keyboard navigation', '.ep-autosuggest' );
	}

	/**
	 * @testdox If a user clicks a post in the autosuggest drop down, they are taken directly to the post.
	 */
	public function testClickSuggestionGoToPost() {
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

		$url = $I->getElementAttribute( '.autosuggest-list > li:first-child span', 'data-url' );

		$I->click( '.autosuggest-list > li:first-child span' );

		$I->waitUntilElementVisible( '.search-toggle' );

		$this->assertEquals( $I->getCurrentUrl(), $url );
	}
}
