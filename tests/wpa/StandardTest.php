<?php
/**
 * Run standard WPA tests
 *
 * @package elasticpress
 */

/**
 * PHPUnit test class
 */
class StandardTest extends TestBase {

	/**
	 * Setup functionality
	 */
	public function setUp() {
		static $initialized = false;

		parent::setUp();

		/**
		 * Index our data
		 */

		if ( ! $initialized ) {
			$initialized = true;

			$this->runCommand( 'wp elasticpress index --setup --yes' );
		}
	}

	/**
	 * @testdox I see required HTML tags on front end.
	 */
	public function testRequiredHTMLTagsOnFrontEnd() {
		parent::_testRequiredHTMLTags();
	}

	/**
	 * @testdox I can log in.
	 */
	public function testLogin() {
		parent::_testLogin();
	}

	/**
	 * @testdox I see the admin bar
	 */
	public function testAdminBarOnFront() {
		parent::_testAdminBarOnFront();
	}

	/**
	 * @testdox I can save my profile
	 */
	public function testProfileSave() {
		parent::_testProfileSave();
	}

	/**
	 * @testdox I can change the site title
	 */
	public function testChangeSiteTitle() {
		parent::_testChangeSiteTitle();
	}
}
