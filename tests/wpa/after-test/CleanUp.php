<?php
/**
 * Delete all indexes we created.
 *
 * @package elasticpress
 */

/**
 * PHPUnit test class
 */
class CleanUp extends TestBase {

	/**
	 * @testdox Delete indexes before finishing.
	 */
	public function testDeleteIndexes() {
		$cluster_indexes = json_decode( $this->runCommand( 'wp elasticpress get-cluster-indexes' )['stdout'], true );

		$I = $this->openBrowserPage();

		$I->login();

		$docker_cid = $I->getElementInnerText( '#docker-cid' );

		foreach ( (array) $cluster_indexes as $index ) {
			if ( $docker_cid && false !== strpos( $index['index'], $docker_cid) ) {
				$this->runCommand( 'wp elasticpress delete-index --index-name=' . $index['index'] );
			}
		}
		$this->assertTrue(true);
	}
}
