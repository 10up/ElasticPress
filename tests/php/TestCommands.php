<?php
/**
 * Test WP-CLI commands.
 *
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress;
use ElasticPress\Command;

/**
 * Commands test class
 */
class TestCommands extends BaseTestCase {

	/**
	 * Holds Command class instance.
	 */
	protected $command;

	/**
	 * Setup each test.
	 *
	 * @since 4.4.1
	 */
	public function set_up() {
		$this->command = new Command();

		ElasticPress\Elasticsearch::factory()->delete_all_indices();
		ElasticPress\Indexables::factory()->get( 'post' )->put_mapping();
		ElasticPress\Elasticsearch::factory()->refresh_indices();

		delete_option( 'ep_active_features' );
		parent::set_up();
	}

	/**
	 * Test activate-feature command can activate feature.
	 *
	 * @since 4.4.1
	 */
	public function testActivateFeature() {

		$this->command->activate_feature( [ 'comments' ], [] );

		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'Feature activated', $output );
	}

	/**
	 * Test activate-feature throws warning when feature needs re-index.
	 *
	 * @since 4.4.1
	 */
	public function testActivateFeatureThrowWarnings() {

		$this->command->activate_feature( [ 'comments' ], [] );

		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'Feature is usable but there are warnings', $output );
		$this->assertStringContainsString( 'This feature requires a re-index. You may want to run the index command next', $output );
	}

	/**
	 * Test activate-feature command throws error when feature is already activated.
	 *
	 * @since 4.4.1
	 */
	public function testActivateFeatureWhenFeatureIsAlreadyActivated() {

		$this->expectExceptionMessage( 'This feature is already active' );

		$this->command->activate_feature( [ 'facets' ], [] );
	}

	/**
	 * Test activate-feature command throws error when feature is not registered.
	 *
	 * @since 4.4.1
	 */
	public function testActivateFeatureForInvalidFeature() {

		$this->expectExceptionMessage( 'No feature with that slug is registered' );

		$this->command->activate_feature( [ 'invalid-feature' ], [] );
	}

	/**
	 * Test activate-feature command throws error when requirement is not met.
	 *
	 * @since 4.4.1
	 */
	public function testActivateFeatureWhenRequirementIsNotMet() {

		$this->expectExceptionMessage( 'Feature requirements are not met' );

		$this->command->activate_feature( [ 'instant-results' ], [] );
	}

	/**
	 * Test deactivate-feature command can deactivate feature.
	 *
	 * @since 4.4.1
	 */
	public function testDeactivateFeature() {

		$this->command->deactivate_feature( [ 'search' ], [] );

		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'Feature deactivated', $output );
	}


	/**
	 * Test deactivate-feature command throws error when feature is already deactivated.
	 *
	 * @since 4.4.1
	 */
	public function testDeactivateFeatureWhenFeatureIsAlreadyDeactivated() {

		$this->expectExceptionMessage( 'Feature is not active' );

		$this->command->deactivate_feature( [ 'instant-results' ], [] );
	}

	/**
	 * Test deactivate-feature command throws error when feature is not registered.
	 *
	 * @since 4.4.1
	 */
	public function testDeactivateFeatureForInvalidFeature() {

		$this->expectExceptionMessage( 'No feature with that slug is registered' );

		$this->command->deactivate_feature( [ 'invalid-feature' ], [] );
	}

	/**
	 * Test list-features command can list all active features.
	 *
	 * @since 4.4.1
	 */
	public function testListFeature() {

		$this->command->list_features( [], [] );

		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'Active features:', $output );
		$this->assertStringContainsString( 'search', $output );
	}

	/**
	 * Test list-features command can list all features.
	 *
	 * @since 4.4.1
	 */
	public function testListFeatureAll() {

		$this->command->list_features( [], [ 'all' => true ] );

		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'Registered features:', $output );
		$this->assertStringContainsString( 'search', $output );
	}

	/**
	 * Test put-mapping command can put mapping for active features.
	 *
	 * @since 4.4.1
	 */
	public function testPutMapping() {

		$this->command->put_mapping( [], [] );

		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'Adding post mapping', $output );
		$this->assertStringContainsString( 'Mapping sent', $output );
	}

	/**
	 * Test get-mapping command returns mapping.
	 *
	 * @since 4.4.1
	 */
	public function testGetMapping() {

		$this->command->get_mapping( [], [] );

		$output = $this->getActualOutputForAssertion();
		$this->assertJson( $output );

		ob_clean();

		// test with index-name option
		$this->command->get_mapping( [], [ 'index-name' => 'exampleorg-post-1' ] );
		$output = $this->getActualOutputForAssertion();
		$this->assertJson( $output );

		ob_clean();

		// test with pretty option
		$this->command->get_mapping( [], [ 'pretty' => true ] );
		$output = $this->getActualOutputForAssertion();
		$this->assertJson( $output );
		$this->assertStringContainsString( "[\n", $output );

		ob_clean();

		// test with incorrect index name
		$this->command->get_mapping( [], [ 'index-name' => 'invalid-index' ] );
		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'index_not_found_exception', $output );
	}


	/**
	 * Test get-cluster-indices command returns cluster indices.
	 *
	 * @since 4.4.1
	 */
	public function testGetClusterIndices() {

		$this->command->get_cluster_indices( [], [] );

		$output = $this->getActualOutputForAssertion();
		$this->assertJson( $output );

		// clean output buffer
		ob_clean();

		$this->command->get_cluster_indices( [], [ 'pretty' => true ] );

		$output = $this->getActualOutputForAssertion();
		$this->assertJson( $output );
		$this->assertStringContainsString( "[\n", $output );
	}

	/**
	 * Test get-indices command returns indices information.
	 *
	 * @since 4.4.1
	 */
	public function testGetIndices() {

		$this->command->get_indices( [], [] );

		$output = $this->getActualOutputForAssertion();
		$this->assertEquals( "[\"exampleorg-post-1\"]\n", $output );

		// clean output buffer
		ob_clean();

		$this->command->get_indices( [], [ 'pretty' => true ] );

		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( "[\n", $output );
	}


	/**
	 * Test recreate-network-alias command can create aliases.
	 *
	 * @group skip-on-single-site
	 * @since 4.4.1
	 */
	public function testReCreateNetworkAlias() {

		$command = new Command();
		$command->recreate_network_alias( [], [] );

		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'Recreating post network alias…', $output );
		$this->assertStringContainsString( 'Done.', $output );
	}

	/**
	 * Test sync command can sync content.
	 *
	 * @group skip-on-multi-site
	 * @since 4.4.1
	 */
	public function testSync() {

		// activate comments feature
		ElasticPress\Features::factory()->activate_feature( 'comments' );
		ElasticPress\Features::factory()->setup_features();

		// create dummy comments
		$this->ep_factory->post->create_many( 10 );
		$this->ep_factory->comment->create_many( 10, [ 'comment_post_ID' => $this->ep_factory->post->create() ] );

		$this->command->sync( [], [] );

		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'Number of posts indexed: 11', $output );
		$this->assertStringContainsString( 'Number of comments indexed: 10', $output );
		$this->assertStringContainsString( 'Sync complete', $output );
		$this->assertStringContainsString( 'Total time elapsed', $output );
		$this->assertStringContainsString( 'Done!', $output );
	}

	/**
	 * Test sync command can sync content.
	 *
	 * @group skip-on-single-site
	 * @since 4.4.1
	 */
	public function testSyncOnNetwork() {

		// activate comments feature
		ElasticPress\Features::factory()->activate_feature( 'comments' );
		ElasticPress\Features::factory()->setup_features();

		// create dummy comments
		$this->ep_factory->post->create_many( 10 );
		$this->ep_factory->comment->create_many( 10, [ 'comment_post_ID' => $this->ep_factory->post->create() ] );

		$this->command->sync( [], [] );

		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'Number of posts indexed on site 1: 11', $output );
		$this->assertStringContainsString( 'Number of comments indexed on site 1: 10', $output );
		$this->assertStringContainsString( 'Sync complete', $output );
		$this->assertStringContainsString( 'Total time elapsed', $output );
		$this->assertStringContainsString( 'Done!', $output );
	}

	/**
	 * Test sync command can ask for confirmation when setup flag is set
	 *
	 * @since 4.4.1
	 */
	public function testSyncAskForConfirmationWhenSetupIsPassed() {

		$this->expectExceptionMessage( 'Indexing with setup option needs to delete Elasticsearch index first, are you sure you want to delete your Elasticsearch index?' );

		$this->command->sync( [], [ 'setup' => true ] );
	}

	/**
	 * Test status command returns status information.
	 *
	 * @since 4.4.1
	 */
	public function testStatus() {

		$this->command->status( [], [] );

		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( '====== Status ======', $output );
		$this->assertStringContainsString( 'exampleorg-post-1', $output );
		$this->assertStringContainsString( '====== End Status ======', $output );
	}

	/**
	 * Test delete-index command ask for confirmation.
	 *
	 * @since 4.4.1
	 */
	public function testDeleteIndexAskForConfirmation() {

		$this->expectExceptionMessage( 'Are you sure you want to delete your Elasticsearch index?' );

		$this->command->delete_index( [], [] );
	}

	/**
	 * Test delete-index command can delete index.
	 *
	 * @since 4.4.1
	 */
	public function testDeleteIndex() {

		// activate comments feature
		ElasticPress\Features::factory()->activate_feature( 'comments' );
		ElasticPress\Features::factory()->setup_features();

		$this->command->delete_index( [], [ 'yes' => true ] );

		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( "Deleting index for posts…\nIndex deleted", $output );
		$this->assertStringContainsString( "Deleting index for comments…\nIndex deleted", $output );

		// clean output buffer
		ob_clean();

		// test with index-name option
		$this->command->delete_index(
			[],
			[
				'yes'        => true,
				'index-name' => 'exampleorg-post-1',
			]
		);

		$output = $this->getActualOutputForAssertion();
		$this->assertEquals( 'Index deleted', $output );
	}

	/**
	 * Test delete-index command also delete global index.
	 *
	 * @since 4.4.1
	 */
	public function testDeleteIndexGlobal() {

		ElasticPress\Features::factory()->activate_feature( 'users' );
		ElasticPress\Features::factory()->setup_features();

		$this->command->delete_index( [], [ 'yes' => true ] );

		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( "Deleting index for users…\nIndex deleted", $output );
	}

	/**
	 *  Test delete-index command can delete all the indexes if network-wide flag is set.
	 *
	 * @group skip-on-single-site
	 * @since 4.4.1
	 */
	public function testDeleteIndexForNetwork() {

		$this->factory->blog->create();

		$this->command->delete_index(
			[],
			[
				'yes'          => true,
				'network-wide' => true,
			]
		);

		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'Deleting post index for site 1', $output );
		$this->assertStringContainsString( 'Deleting post index for site 2', $output );
	}

	/**
	 * Test delete-index command can delete all the indexes if network-wide flag is set.
	 *
	 * @group skip-on-single-site
	 * @since 4.4.1
	 */
	public function testClearSync() {

		$this->command->clear_sync( [], [] );

		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'Index cleared.', $output );
	}

	/**
	 * Test get-ongoing-sync-status command returns ongoing sync status.
	 *
	 * @since 4.4.1
	 */
	public function testGetOnGoingSyncStatus() {

		$this->command->get_ongoing_sync_status( [], [] );

		$output = $this->getActualOutputForAssertion();
		$this->assertJson( $output );
	}

	/**
	 * Test get-last-sync command returns last sync information.
	 *
	 * @since 4.4.1
	 */
	public function testGetLastSync() {

		$this->command->get_last_sync( [], [] );

		$output = $this->getActualOutputForAssertion();
		$this->assertJson( $output );
	}

	/**
	 * Test get-last-cli-sync command returns last cli sync information.
	 *
	 * @since 4.4.1
	 */
	public function testGetLastCliSync() {

		$this->command->get_last_cli_sync( [], [] );

		$output = $this->getActualOutputForAssertion();
		$this->assertJson( $output );
	}

	/**
	 * Test stop-sync command can stop indexing.
	 *
	 * @since 4.4.1
	 */
	public function testStopSync() {

		$this->command->stop_sync( [], [] );

		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'There is no indexing operation running.', $output );

		// mock sync option
		ElasticPress\Utils\update_option( 'ep_index_meta', [ 'indexing' => true ] );

		$this->command->stop_sync( [], [] );

		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'Stopping indexing…', $output );
		$this->assertStringContainsString( 'Done.', $output );
	}

	/**
	 * Test set-search-algorithm-version command can set search algorithm version.
	 *
	 * @since 4.4.1
	 */
	public function testSetSearchAlgorithmVersion() {

		$this->command->set_search_algorithm_version( [], [ 'version' => 1 ] );

		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'Done', $output );
		$this->assertEquals( 1, ElasticPress\Utils\get_option( 'ep_search_algorithm_version' ) );

		// clean output buffer
		ob_clean();

		// test with default flag
		$this->command->set_search_algorithm_version( [], [ 'default' => true ] );

		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'Done', $output );
		$this->assertEmpty( ElasticPress\Utils\get_option( 'ep_search_algorithm_version' ) );
	}

	/**
	 * Test set-search-algorithm-version command throws an error if no version is provided.
	 *
	 * @since 4.4.1
	 */
	public function testSetSearchAlgorithmVersionWithOutVersion() {

		$this->expectExceptionMessage( 'This command expects a version number or the --default flag.' );

		$this->command->set_search_algorithm_version( [], [] );
	}

	/**
	 * Test request command can make a request to Elasticsearch.
	 *
	 * @since 4.4.1
	 */
	public function testRequest() {

		$this->command->request( [ '_cat/indices' ], [] );

		$output = $this->getActualOutputForAssertion();
		$this->assertNotEmpty( $output );

		// clean output buffer
		ob_clean();

		// test with --pretty flag
		$this->command->request( [ '_cat/indices' ], [ 'pretty' => true ] );

		$output = $this->getActualOutputForAssertion();
		$this->assertNotEmpty( $output );

		// clean output buffer
		ob_clean();

		// test with --debug-http-request flag
		$this->command->request( [ '_cat/indices' ], [ 'debug-http-request' => true ] );

		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'URL:', $output );
		$this->assertStringContainsString( 'Request Args:', $output );
		$this->assertStringContainsString( 'Transport:', $output );
		$this->assertStringContainsString( 'Context:', $output );
		$this->assertStringContainsString( 'Response:', $output );
	}

	/**
	 * Test settings-reset command delete all settings.
	 *
	 * @since 4.4.1
	 */
	public function testSettingsReset() {

		$this->command->settings_reset( [], [ 'yes' => true ] );

		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'Settings deleted.', $output );
	}

	/**
	 * Test settings-reset command ask for confirmation.
	 *
	 * @since 4.4.1
	 */
	public function testSettingsResetAskForConfirmation() {

		$this->expectExceptionMessage( 'Are you sure you want to delete all ElasticPress settings?' );

		$this->command->settings_reset( [], [] );
	}

}
