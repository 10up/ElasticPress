<?php
/**
 * WP CLI test class
 *
 * @package elasticpress
 */

/**
 * WP CLI test class
 */
class WpCliTest extends TestBase {
	/**
	 * @testdox If user runs wp elasticpress index command, it should index all the posts of the current blog.
	 */
	public function testIndexCommand() {
		$cli_result = $this->runCommand( 'wp elasticpress index' )['stdout'];

		$this->assertStringContainsString( 'Indexing posts', $cli_result );

		$this->assertStringContainsString( 'Number of posts indexed', $cli_result );

		$I = $this->openBrowserPage();

		$I->loginAs( 'wpsnapshots' );

		$I->moveTo( 'wp-admin/admin.php?page=elasticpress-health' );

		$I->dontSeeText( 'We could not find any data for your Elasticsearch indices.' );

		foreach ( $this->indexes as $index_name ) {
			$I->seeText( $index_name );
		}
	}

	/**
	 * @testdox If user specifies --network-wide argument in index command, it should index all blogs in network.
	 */
	public function testIndexCommandWithNetworkWide() {
		$I = $this->openBrowserPage();

		$I->loginAs( 'wpsnapshots' );

		$this->deactivatePlugin( $I );

		$this->activatePlugin( $I, 'elasticpress', true );

		$I->moveTo( 'wp-admin/network/sites.php' );

		$I->checkOptions( '.index-toggle' );

		$I->moveTo( 'wp-admin/network/admin.php?page=elasticpress-health' );

		$cli_result = $this->runCommand( 'wp elasticpress index --network-wide' )['stdout'];

		$this->assertStringContainsString( 'Indexing posts on site', $cli_result );

		$this->assertStringContainsString( 'Number of posts indexed on site', $cli_result );

		$I->moveTo( 'wp-admin/network/admin.php?page=elasticpress-health' );

		$I->dontSeeText( 'We could not find any data for your Elasticsearch indices.' );

		$this->deactivatePlugin( $I, 'elasticpress', true );

		$this->activatePlugin( $I );
	}

	/**
	 * @testdox If user specifies --setup argument in index command, it should clear the index in Elasticsearch, and should put the mapping again and then index all the posts.
	 */
	public function testIndexCommandWithSetup() {
		$cli_result = $this->runCommand( 'wp elasticpress index --setup' )['stdout'];

		$this->assertStringContainsString( 'Mapping sent', $cli_result );

		$this->assertStringContainsString( 'Indexing posts', $cli_result );

		$this->assertStringContainsString( 'Number of posts indexed', $cli_result );

		$I = $this->openBrowserPage();

		$I->loginAs( 'wpsnapshots' );

		$I->moveTo( 'wp-admin/admin.php?page=elasticpress-health' );

		$I->dontSeeText( 'We could not find any data for your Elasticsearch indices.' );

		foreach ( $this->indexes as $index_name ) {
			$I->seeText( $index_name );
		}
	}

	/**
	 * @testdox If user specifies --per-page parameter in index command, it should process that many posts in bulk index per round.
	 */
	public function testIndexCommandWithPerPage() {
		$cli_result = $this->runCommand( 'wp elasticpress index --per-page=20' )['stdout'];

		$this->assertStringContainsString( 'Indexing posts', $cli_result );

		$this->assertStringContainsString( 'Processed 20/', $cli_result );

		$this->assertStringContainsString( 'Processed 40/', $cli_result );

		$this->assertStringContainsString( 'Number of posts indexed', $cli_result );
	}

	/**
	 * @testdox If user specifies --nobulk parameter in index command, it should index one post at a time.
	 */
	public function testIndexCommandWithNoBulk() {
		$cli_result = $this->runCommand( 'wp elasticpress index --nobulk' )['stdout'];

		$this->assertStringContainsString( 'Indexing posts', $cli_result );

		$this->assertStringContainsString( 'Processed 1/', $cli_result );
		$this->assertStringContainsString( 'Processed 2/', $cli_result );
		$this->assertStringContainsString( 'Processed 3/', $cli_result );
		$this->assertStringContainsString( 'Processed 4/', $cli_result );

		$this->assertStringContainsString( 'Number of posts indexed', $cli_result );
	}

	/**
	 * @testdox If user specifies --offset parameter in index command, it should skip that many posts and index the remaining.
	 */
	public function testIndexCommandWithOffset() {
		$cli_result = $this->runCommand( 'wp elasticpress index --offset=10' )['stdout'];

		$this->assertStringContainsString( 'Indexing posts', $cli_result );

		preg_match( '/Processed (\d+)\/(\d+).../m', $cli_result, $matches );

		$total_posts = $matches[1];

		preg_match( '/Number of posts indexed: (\d+)/m', $cli_result, $matches );

		$indexed_posts = $matches[1];

		$this->assertEquals( 10, $total_posts - $indexed_posts );

		$this->assertStringContainsString( 'Number of posts indexed', $cli_result );
	}

	/**
	 * @testdox If user specify --post-type parameter in index command, it should index all the posts of that type.
	 */
	public function testIndexCommandWithPostType() {
		$cli_result = $this->runCommand( 'wp elasticpress index --post-type=post' )['stdout'];

		$this->assertStringContainsString( 'Indexing posts', $cli_result );

		preg_match( '/Number of posts indexed: (\d+)/m', $cli_result, $matches );

		$indexed_posts = $matches[1];

		$this->assertStringContainsString( 'Number of posts indexed', $cli_result );

		$cli_result = $this->runCommand( 'wp elasticpress index' )['stdout'];

		preg_match( '/Number of posts indexed: (\d+)/m', $cli_result, $matches );

		$total_posts = $matches[1];

		$this->assertNotEquals( $indexed_posts, $total_posts );
	}

	/**
	 * @testdox If user runs wp elasticpress delete-index command, it should delete the index of current blog.
	 */
	public function testDeleteIndexCommand() {
		$I = $this->openBrowserPage();

		$I->loginAs( 'wpsnapshots' );

		$cli_result = $this->runCommand( 'wp elasticpress delete-index' )['stdout'];

		$this->assertStringContainsString( 'Index deleted', $cli_result );

		$I->moveTo( 'wp-admin/admin.php?page=elasticpress-health' );

		$I->seeText( 'We could not find any data for your Elasticsearch indices.' );
	}

	/**
	 * @testdox If user runs wp elasticpress delete-index --network-wide command, it should delete all the index network-wide.
	 */
	public function testDeleteIndexCommandWithNetworkWide() {
		$I = $this->openBrowserPage();

		$I->loginAs( 'wpsnapshots' );

		$this->deactivatePlugin( $I );

		$this->activatePlugin( $I, 'elasticpress', true );

		$I->moveTo( 'wp-admin/network/sites.php' );

		$I->checkOptions( '.index-toggle' );

		$I->moveTo( 'wp-admin/network/admin.php?page=elasticpress-health' );

		$cli_result = $this->runCommand( 'wp elasticpress delete-index --network-wide' )['stdout'];

		$this->assertStringContainsString( 'Index deleted', $cli_result );

		$I->moveTo( 'wp-admin/network/admin.php?page=elasticpress-health' );

		$I->seeText( 'We could not find any data for your Elasticsearch indices.' );

		$this->deactivatePlugin( $I, 'elasticpress', true );

		$this->activatePlugin( $I );
	}

	/**
	 * @testdox If user runs wp elasticpress put-mapping command, it should put mapping of the current blog.
	 */
	public function testPutMappingCommand() {
		$cli_result = $this->runCommand( 'wp elasticpress put-mapping' )['stdout'];

		$this->assertStringContainsString( 'Adding post mapping', $cli_result );

		$this->assertStringContainsString( 'Mapping sent', $cli_result );
	}

	/**
	 * @testdox If user runs wp elasticpress put-mapping --network-wide command, it should put mapping network-wide.
	 */
	public function testPutMappingCommandWithNetworkWide() {
		$I = $this->openBrowserPage();

		$I->loginAs( 'wpsnapshots' );

		$this->deactivatePlugin( $I );

		$this->activatePlugin( $I, 'elasticpress', true );

		$I->moveTo( 'wp-admin/network/sites.php' );

		$I->checkOptions( '.index-toggle' );

		$this->runCommand( 'wp elasticpress delete-index --network-wide' );

		$cli_result = $this->runCommand( 'wp elasticpress put-mapping --network-wide' )['stdout'];

		$this->assertStringContainsString( 'Adding post mapping for site', $cli_result );

		$this->assertStringContainsString( 'Mapping sent', $cli_result );

		$this->deactivatePlugin( $I, 'elasticpress', true );

		$this->activatePlugin( $I );
	}

	/**
	 * @testdox If user runs wp elasticpress recreate-network-alias command, it should recreate the alias index which points to every index in the network.
	 */
	public function testRecreateNetworkAliasCommand() {
		return;
	}

	/**
	 * @testdox If use runs wp elasticpress activate-feature and specify feature, it should activate that feature in the current blog and if user specifies --network-wide, it should activate the feature network-wide.
	 */
	public function testActivateFeatureCommand() {
		$cli_result = $this->runCommand( 'wp elasticpress activate-feature search' )['stdout'];

		$this->assertStringContainsString( 'This feature is already active', $cli_result );

		$this->runCommand( 'wp elasticpress deactivate-feature search' );

		$cli_result = $this->runCommand( 'wp elasticpress activate-feature search' )['stdout'];

		$this->assertStringContainsString( 'Feature activated', $cli_result );
	}

	/**
	 * @testdox If user runs wp elasticpress deactivate-feature and specify feature, it should deactivate that feature in the current blog and if user specifies --network-wide, it should deactivate the feature network-wide.
	 */
	public function testDeactivateFeatureCommand() {
		$cli_result = $this->runCommand( 'wp elasticpress deactivate-feature search' )['stdout'];

		$this->assertStringContainsString( 'Feature deactivated', $cli_result );

		$cli_result = $this->runCommand( 'wp elasticpress deactivate-feature search' )['stdout'];

		$this->assertStringContainsString( 'Feature is not active', $cli_result );

		$this->runCommand( 'wp elasticpress activate-feature search' );
	}

	/**
	 * @testdox If user runs wp elasticpress list-features command, it should list all the active features. If user specifies --all parameter, it should show all the registered features and if user specifies --network-wide parameter, it should check the same network-wide.
	 */
	public function testListFeaturesCommand() {
		$cli_result = $this->runCommand( 'wp elasticpress list-features' )['stdout'];

		$this->assertStringContainsString( 'Active features', $cli_result );

		$cli_result = $this->runCommand( 'wp elasticpress list-features --all' )['stdout'];

		$this->assertStringContainsString( 'Registered features', $cli_result );
	}

	/**
	 * @testdox If user runs wp elasticpress stats command, it should return the number of documents indexed and index size.
	*/
	public function testStatsCommand() {
		$this->runCommand( 'wp elasticpress delete-index' );

		$cli_result = $this->runCommand( 'wp elasticpress stats' )['stdout'];

		$this->assertStringContainsString( 'is not currently indexed', $cli_result );

		$this->runCommand( 'wp elasticpress index --setup' );

		$cli_result = $this->runCommand( 'wp elasticpress stats' )['stdout'];

		$this->assertStringContainsString( 'Documents', $cli_result );

		$this->assertStringContainsString( 'Index Size', $cli_result );
	}
}
