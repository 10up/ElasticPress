<?php
/**
 * Test WP-CLI commands.
 *
 * @since 4.4.1
 * @package elasticpress
 */

namespace ElasticPressTest;

use ElasticPress;
use ElasticPress\Command;
use ElasticPress\Indexables;
use ElasticPress\Utils;
use ElasticPress\Command\Utility;

/**
 * Commands test class
 */
class TestCommands extends BaseTestCase {

	/**
	 * Holds Command class instance.
	 *
	 * @var Command
	 */
	protected $command;

	/**
	 * Setup each test.
	 */
	public function set_up() {
		$this->command = new Command();

		ElasticPress\Elasticsearch::factory()->delete_all_indices();
		ElasticPress\Indexables::factory()->deactivate_all();
		ElasticPress\Indexables::factory()->activate( 'post' );
		ElasticPress\Indexables::factory()->get( 'post' )->put_mapping();
		ElasticPress\Elasticsearch::factory()->refresh_indices();

		parent::set_up();
	}

	/**
	 * Clean up after each test.
	 */
	public function tear_down() {
		ob_clean();
		parent::tear_down();
	}

	/**
	 * Test activate-feature command can activate feature.
	 */
	public function testActivateFeature() {

		$this->command->activate_feature( [ 'comments' ], [] );

		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'Feature activated', $output );
	}

	/**
	 * Test activate-feature throws warning when feature needs re-index.
	 */
	public function testActivateFeatureThrowWarnings() {

		$this->command->activate_feature( [ 'comments' ], [] );

		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'Feature is usable but there are warnings', $output );
		$this->assertStringContainsString( 'This feature requires a re-index. You may want to run the index command next', $output );
	}

	/**
	 * Test activate-feature command throws error when feature is already activated.
	 */
	public function testActivateFeatureWhenFeatureIsAlreadyActivated() {

		$this->expectExceptionMessage( 'This feature is already active' );

		$this->command->activate_feature( [ 'facets' ], [] );
	}

	/**
	 * Test activate-feature command throws error when feature is not registered.
	 */
	public function testActivateFeatureForInvalidFeature() {

		$this->expectExceptionMessage( 'No feature with that slug is registered' );

		$this->command->activate_feature( [ 'invalid-feature' ], [] );
	}

	/**
	 * Test activate-feature command throws error when requirement is not met.
	 */
	public function testActivateFeatureWhenRequirementIsNotMet() {

		$this->expectExceptionMessage( 'Feature requirements are not met' );

		$this->command->activate_feature( [ 'instant-results' ], [] );
	}

	/**
	 * Test deactivate-feature command can deactivate feature.
	 */
	public function testDeactivateFeature() {

		$this->command->deactivate_feature( [ 'search' ], [] );

		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'Feature deactivated', $output );
	}


	/**
	 * Test deactivate-feature command throws error when feature is already deactivated.
	 */
	public function testDeactivateFeatureWhenFeatureIsAlreadyDeactivated() {

		$this->expectExceptionMessage( 'Feature is not active' );

		$this->command->deactivate_feature( [ 'instant-results' ], [] );
	}

	/**
	 * Test deactivate-feature command throws error when feature is not registered.
	 */
	public function testDeactivateFeatureForInvalidFeature() {

		$this->expectExceptionMessage( 'No feature with that slug is registered' );

		$this->command->deactivate_feature( [ 'invalid-feature' ], [] );
	}

	/**
	 * Test list-features command can list all active features.
	 */
	public function testListFeature() {

		$this->command->list_features( [], [] );

		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'Active features:', $output );
		$this->assertStringContainsString( 'search', $output );
	}

	/**
	 * Test list-features command can list all features.
	 */
	public function testListFeatureAll() {

		$this->command->list_features( [], [ 'all' => true ] );

		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'Registered features:', $output );
		$this->assertStringContainsString( 'search', $output );
	}

	/**
	 * Test put-mapping command can put mapping for active features.
	 */
	public function testPutMapping() {

		$this->command->put_mapping( [], [] );

		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'Adding post mapping', $output );
		$this->assertStringContainsString( 'Mapping sent', $output );

	}

	/**
	 * Test put-mapping command can put mapping for specific indexable.
	 */
	public function testPutMappingWithIndexablesFlag() {

		ElasticPress\Features::factory()->activate_feature( 'comments' );
		ElasticPress\Features::factory()->setup_features();

		// test it only index the posts.
		$this->command->put_mapping( [], [ 'indexables' => 'post' ] );

		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'Adding post mapping', $output );
		$this->assertStringContainsString( 'Mapping sent', $output );
	}

	/**
	 * Test put-mapping command can put mapping for network-wide.
	 *
	 * @group skip-on-single-site
	 */
	public function testPutMappingForNetworkWide() {

		ElasticPress\Features::factory()->activate_feature( 'comments' );
		ElasticPress\Features::factory()->setup_features();

		$blog_id = $this->factory->blog->create();
		update_site_meta( $blog_id, 'ep_indexable', 'no' );

		$this->factory->blog->create();

		// test with network-wide flag
		$this->command->put_mapping(
			[],
			[
				'network-wide' => true,
				'indexables'   => 'post',
			]
		);

		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'Adding post mapping for site 1', $output );
		$this->assertStringContainsString( 'Adding post mapping for site 3', $output );
		$this->assertStringNotContainsString( 'Adding post mapping for site 2', $output );
		$this->assertStringContainsString( 'Mapping sent', $output );
	}

	/**
	 * Test put-mapping command throws error if mapping failed.
	 */
	public function testPutMappingThrowErrorIfMappingFailed() {

		$this->expectExceptionMessage( 'Mapping failed: This was forced to fail' );

		add_filter(
			'ep_config_mapping_request',
			function() {
				return new \WP_Error( 'test', 'This was forced to fail' );
			}
		);

		$this->command->put_mapping( [], [] );
	}

	/**
	 * Test put-mapping command throws error if mapping failed for network-wide.
	 */
	public function testPutMappingForNetworkWideThrowErrorIfMappingFailed() {

		$this->expectExceptionMessage( 'Mapping failed: This was forced to fail' );

		add_filter(
			'ep_config_mapping_request',
			function() {
				return new \WP_Error( 'test', 'This was forced to fail' );
			}
		);

		$this->command->put_mapping( [], [ 'network-wide' => true ] );
	}

	/**
	 * Test put-mapping command can put mapping for global indexables.
	 */
	public function testPutMappingForGlobalIndexables() {
		ElasticPress\Features::factory()->activate_feature( 'global' );
		ElasticPress\Features::factory()->setup_features();

		$this->command->put_mapping( [], [ 'indexables' => 'global,post' ] );

		$output = $this->getActualOutputForAssertion();

		$this->assertStringContainsString( 'Adding global mapping', $output );
		$this->assertStringContainsString( 'Mapping sent', $output );
	}

	/**
	 * Test get-mapping command returns mapping.
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

		// clean output buffer
		ob_clean();

		/**
		 * Test the --status flag
		 *
		 * @since 4.5.0
		 */
		$this->command->get_indices( [], [ 'status' => 'all' ] );

		$output = $this->getActualOutputForAssertion();
		$this->assertEquals( "[\"exampleorg-post-1\",\"exampleorg-comment-1\",\"exampleorg-term-1\",\"exampleorg-global\"]\n", $output );
	}


	/**
	 * Test recreate-network-alias command can create aliases.
	 *
	 * @group skip-on-single-site
	 */
	public function testReCreateNetworkAlias() {

		$this->command->recreate_network_alias( [], [] );

		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'Recreating post network alias…', $output );
		$this->assertStringContainsString( 'Done.', $output );
	}

	/**
	 * Test recreate-network-alias command can create aliases.
	 *
	 * @group skip-on-multi-site
	 */
	public function testReCreateNetworkAliasOnSingleSite() {

		$this->expectExceptionMessage( 'ElasticPress is not network activated.' );

		$this->command->recreate_network_alias( [], [] );
	}

	/**
	 * Test sync command can sync content.
	 *
	 * @group skip-on-multi-site
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
	 * Test sync command can create an index even without the --setup flag
	 *
	 * @since 4.5.0
	 */
	public function testSyncIndexCreationWithoutSetupFlag() {
		Indexables::factory()->get( 'post' )->delete_index();

		$this->command->sync( [], [] );

		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'Index not present. Mapping sent', $output );
	}

	/**
	 * Test sync command with setup flag.
	 */
	public function testSyncWithSetupFlag() {

		// activate comments feature
		ElasticPress\Features::factory()->activate_feature( 'comments' );
		ElasticPress\Features::factory()->setup_features();

		// without these dummy content, the sync command gets failed because the static variable
		// https://github.com/10up/ElasticPress/blob/4.0.0/includes/classes/Indexable/Post/Post.php#L173
		// holds the old value.
		$this->ep_factory->post->create_many( 10 );
		$this->ep_factory->comment->create_many( 10, [ 'comment_post_ID' => $this->ep_factory->post->create() ] );

		$this->command->sync(
			[],
			[
				'setup' => true,
				'yes'   => true,
			]
		);

		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'Sync complete', $output );
		$this->assertStringContainsString( 'Total time elapsed', $output );
		$this->assertStringContainsString( 'Done!', $output );
	}

	/**
	 * Test the sync command with the setup flag. It should delete unused indices.
	 *
	 * @since 4.5.0
	 */
	public function testSyncWithSetupFlagDeleteUnusedIndices() {
		// activate comments and users features
		ElasticPress\Indexables::factory()->get( 'comment' )->put_mapping();
		ElasticPress\Indexables::factory()->get( 'term' )->put_mapping();

		$this->command->sync(
			[],
			[
				'setup' => true,
				'yes'   => true,
			]
		);

		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'Index exampleorg-comment-1 deleted', $output );
		$this->assertStringContainsString( 'Index exampleorg-term-1 deleted', $output );
	}

	/**
	 * Test sync command with indexables flag.
	 */
	public function testSyncWithIndexablesFlag() {

		ElasticPress\Features::factory()->activate_feature( 'comments' );
		ElasticPress\Features::factory()->setup_features();

		// without these dummy content, the sync command gets failed because the static variable
		// https://github.com/10up/ElasticPress/blob/4.0.0/includes/classes/Indexable/Post/Post.php#L173
		// holds the old value.
		$this->ep_factory->post->create_many( 10 );
		$this->ep_factory->comment->create_many( 10, [ 'comment_post_ID' => $this->ep_factory->post->create() ] );

		$this->command->sync( [], [ 'indexables' => 'post' ] );

		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'Indexing posts', $output );
		$this->assertStringNotContainsString( 'Indexing comments', $output );
		$this->assertStringContainsString( 'Sync complete', $output );
	}

	/**
	 * Test sync command with include flag.
	 *
	 * @group skip-on-multi-site
	 */
	public function testSyncWithIncludeFlag() {

		$post_id = $this->ep_factory->post->create();

		$this->command->sync( [], [ 'include' => $post_id ] );
		$output = $this->getActualOutputForAssertion();

		$this->assertStringContainsString( 'Processed posts 0 - 1 of 1', $output );
		$this->assertStringContainsString( 'Sync complete', $output );
	}

	/**
	 * Test sync command with include flag.
	 *
	 * @group skip-on-single-site
	 */
	public function testSyncWithIncludeFlagForNetwork() {

		$post_id = $this->ep_factory->post->create();

		$this->command->sync( [], [ 'include' => $post_id ] );
		$output = $this->getActualOutputForAssertion();

		$this->assertStringContainsString( 'Number of posts indexed on site 1: 1', $output );
		$this->assertStringContainsString( 'Sync complete', $output );
	}

	/**
	 * Test sync command with per-page flag.
	 */
	public function testSyncWithPerPageFlag() {

		$this->ep_factory->post->create_many( 10 );

		$this->command->sync( [], [ 'per-page' => 5 ] );
		$output = $this->getActualOutputForAssertion();

		$this->assertStringContainsString( 'Processed posts 0 - 5 of 10', $output );
		$this->assertStringContainsString( 'Processed posts 5 - 10 of 10', $output );
		$this->assertStringContainsString( 'Sync complete', $output );
	}

	/**
	 * Test sync command with post-type flag.
	 */
	public function testSyncWithPostTypeFlag() {

		$this->ep_factory->post->create_many( 10 );
		$this->ep_factory->post->create_many( 10, [ 'post_type' => 'page' ] );

		$this->command->sync( [], [ 'post-type' => 'page' ] );
		$output = $this->getActualOutputForAssertion();

		$this->assertStringContainsString( 'Processed posts 0 - 10 of 10', $output );
		$this->assertStringContainsString( 'Sync complete', $output );
	}

	/**
	 * Test sync command with ep-prefix flag.
	 */
	public function testSyncWithEPPrefixFlag() {

		$this->ep_factory->post->create_many( 10 );
		$this->ep_factory->post->create_many( 10, [ 'post_type' => 'page' ] );

		$this->command->sync( [], [ 'ep-prefix' => 'test' ] );
		$output = $this->getActualOutputForAssertion();

		$this->assertStringContainsString( 'Sync complete', $output );
	}

	/**
	 * Test sync command with ep-host flag.
	 */
	public function testSyncWithEPHostFlag() {

		$this->expectExceptionMessage( 'Could not connect to Elasticsearch' );

		$this->command->sync( [], [ 'ep-host' => 'https://incorrect.url' ] );
	}

	/**
	 * Test sync command can ask for confirmation when setup flag is set
	 */
	public function testSyncAskForConfirmationWhenSetupIsPassed() {
		$this->expectExceptionMessage( 'Syncing with the --setup option will delete your existing index in Elasticsearch. Are you sure you want to delete your Elasticsearch index' );

		$this->command->sync( [], [ 'setup' => true ] );
	}

	/**
	 * Test sync command throws error if mapping failed.
	 */
	public function testSyncThrowsErrorIfMappingFailed() {

		$this->expectExceptionMessage( 'Mapping failed: This was forced to fail' );

		// mock the mapping request to return the error
		add_filter(
			'ep_config_mapping_request',
			function() {
				return new \WP_Error( 'test', 'This was forced to fail' );
			}
		);

		$this->command->sync(
			[],
			[
				'setup' => true,
				'yes'   => true,
			]
		);
	}

	/**
	 * Test sync command with the force flag.
	 *
	 * @since 4.6.0
	 */
	public function testSyncWithForceFlag() {
		// mock indexing
		add_filter( 'ep_is_indexing', '__return_true' );

		$this->command->sync(
			[],
			[
				'force' => true,
				'yes'   => true,
			]
		);

		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'Sync cleared.', $output );
	}

	/**
	 * Test sync command can ask for confirmation when force flag is set
	 *
	 * @since 4.6.0
	 */
	public function testSyncAskForConfirmationWhenForceIsPassed() {
		$this->expectExceptionMessage( 'Are you sure you want to stop any other ongoing sync?' );

		$this->command->sync( [], [ 'force' => true ] );
	}

	/**
	 * Test status command returns status information.
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
	 */
	public function testDeleteIndexAskForConfirmation() {

		$this->expectExceptionMessage( 'Are you sure you want to delete your Elasticsearch index?' );

		$this->command->delete_index( [], [] );
	}

	/**
	 * Test delete-index command can delete index.
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
	 */
	public function testDeleteIndexGlobal() {

		ElasticPress\Features::factory()->activate_feature( 'global' );
		ElasticPress\Features::factory()->setup_features();

		$this->command->delete_index( [], [ 'yes' => true ] );

		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( "Deleting index for global…\nIndex deleted", $output );
	}

	/**
	 *  Test delete-index command can delete all the indexes if network-wide flag is set.
	 *
	 * @group skip-on-single-site
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
		$sites  = ElasticPress\Utils\get_sites();

		foreach ( $sites as $site ) {
			$this->assertStringContainsString( "Deleting post index for site {$site['blog_id']}", $output );
		}
	}

	/**
	 * Test the clear-sync command
	 *
	 * @group skip-on-single-site
	 */
	public function testClearSync() {

		$this->command->clear_sync( [], [] );

		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'Sync cleared.', $output );
	}

	/**
	 * Test get-ongoing-sync-status command returns ongoing sync status.
	 */
	public function testGetOnGoingSyncStatus() {

		$this->command->get_ongoing_sync_status( [], [] );

		$output = $this->getActualOutputForAssertion();
		$this->assertJson( $output );
	}

	/**
	 * Test get-last-sync command returns last sync information.
	 */
	public function testGetLastSync() {

		$this->command->get_last_sync( [], [] );

		$output = $this->getActualOutputForAssertion();
		$this->assertJson( $output );
	}

	/**
	 * Test get-last-cli-sync command returns last cli sync information.
	 */
	public function testGetLastCliSync() {

		$this->command->get_last_cli_sync( [], [] );

		$output = $this->getActualOutputForAssertion();
		$this->assertJson( $output );

		// test clear option deletes the option.
		Utils\update_option( 'ep_last_cli_index', 'test_value' );
		$this->command->get_last_cli_sync( [], [ 'clear' => true ] );

		$this->assertFalse( Utils\get_option( 'ep_last_cli_index' ) );
	}

	/**
	 * Test stop-sync command can stop indexing.
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
	 */
	public function testSetSearchAlgorithmVersionWithOutVersion() {

		$this->expectExceptionMessage( 'This command expects a version number or the --default flag.' );

		$this->command->set_search_algorithm_version( [], [] );
	}

	/**
	 * Test get-search-algorithm-version returns the algorithm version.
	 */
	public function testGetSearchAlgorithmVersion() {

		// set default version.
		Utils\update_option( 'ep_search_algorithm_version', '' );
		$this->command->get_search_algorithm_version( [], [] );

		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'default', $output );

		// clean output buffer
		ob_clean();

		// set version 1.
		Utils\update_option( 'ep_search_algorithm_version', '1' );
		$this->command->get_search_algorithm_version( [], [] );

		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( '1', $output );
	}

	/**
	 * Test request command can make a request to Elasticsearch.
	 */
	public function testRequest() {

		$this->command->request(
			[ '_cat/indices' ],
			[
				'body'   => 'test body',
				'method' => 'POST',
			]
		);

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
	 * Test sync command with stop-on-error flag.
	 * Expect an error message that stops the sync instead of a warning.
	 *
	 * @since 4.7.0
	 */
	public function test_sync_stop_on_error() {
		add_filter(
			'http_response',
			function( $request ) {
				$fake_request = json_decode( wp_remote_retrieve_body( $request ) );

				if ( ! empty( $fake_request->items ) ) {
					$fake_request->errors = true;

					$fake_item                       = new \stdClass();
					$fake_item->index                = new \stdClass();
					$fake_item->index->error         = new \stdClass();
					$fake_item->index->status        = 400;
					$fake_item->index->_id           = 10;
					$fake_item->index->type          = '_doc';
					$fake_item->index->_index        = 'dummy-index';
					$fake_item->index->error->reason = 'my dummy error reason';
					$fake_item->index->error->type   = 'my dummy error type';

					$fake_request->items[0] = $fake_item;

					$request['body'] = wp_json_encode( $fake_request );

					return $request;
				}

				return $request;
			}
		);
		Indexables::factory()->get( 'post' )->delete_index();

		$this->ep_factory->post->create();

		$this->expectExceptionMessage( '10 (Post): [my dummy error type] my dummy error reason' );

		$this->command->sync(
			[],
			[
				'stop-on-error' => true,
			]
		);
	}

	/**
	 * Test request command throws an error if request fails.
	 */
	public function testRequestThrowsError() {

		$this->expectExceptionMessage( 'Error: Request failed.' );

		// mock request
		add_filter( 'ep_intercept_remote_request', '__return_true' );
		add_filter(
			'ep_do_intercept_request',
			function() {
				return new \WP_Error( 400, 'Error: Request failed.' );
			}
		);

		$this->command->request( [ '_cat/indices' ], [ 'method' => 'POST' ] );
	}


	/**
	 * Test settings-reset command delete all settings.
	 */
	public function testSettingsReset() {

		$this->command->settings_reset( [], [ 'yes' => true ] );

		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'Settings deleted.', $output );
	}

	/**
	 * Test settings-reset command ask for confirmation.
	 */
	public function testSettingsResetAskForConfirmation() {

		$this->expectExceptionMessage( 'Are you sure you want to delete all ElasticPress settings?' );

		$this->command->settings_reset( [], [] );
	}

	/**
	 * Test stats command.
	 */
	public function testStats() {

		$this->command->stats( [], [] );

		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( '====== End Stats ======', $output );

	}

	/**
	 * Test epio-set-autosuggest command.
	 */
	public function testEPioSetAutosuggest() {

		ElasticPress\Features::factory()->activate_feature( 'autosuggest' );

		$this->command->epio_set_autosuggest( [], [] );

		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'Done.', $output );
	}

	/**
	 * Test epio-set-autosuggest command throws an error if autosuggest is not enabled.
	 */
	public function testEPioSetAutosuggestThrowsError() {

		$this->expectExceptionMessage( 'Autosuggest is not enabled.' );

		$this->command->epio_set_autosuggest( [], [] );
	}

	/**
	 * Test `should_interrupt_sync` method.
	 */
	public function testShouldInterruptSync() {

		set_transient( 'ep_wpcli_sync_interrupted', true );

		Utility::should_interrupt_sync();

		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'Sync was interrupted', $output );
		$this->assertStringContainsString( 'Indexing cleaned up.', $output );
	}

	/**
	 * Test commands throws an error when host is not set.
	 */
	public function testThrowsErrorWhenHostIsNotSet() {

		$this->expectExceptionMessage( 'Elasticsearch host is not set.' );

		// set host to empty string
		add_filter( 'ep_host', '__return_empty_string' );

		$this->command->sync( [], [] );
	}

	/**
	 * Test get-index-settings command returns an index settings.
	 *
	 * @since 4.7.0
	 */
	public function testGetIndexSettings() {
		$this->command->get_index_settings( [ 'exampleorg-post-1' ], [] );

		$output = $this->getActualOutputForAssertion();
		$this->assertStringStartsWith( '{', $output );
		$this->assertStringContainsString( 'index.mapping.total_fields.limit', $output );

		// clean output buffer
		ob_clean();

		// test with --pretty flag
		$this->command->get_index_settings( [ 'exampleorg-post-1' ], [ 'pretty' => true ] );

		$output = $this->getActualOutputForAssertion();
		$this->assertStringStartsWith( "{\n", $output );
	}

	/**
	 * Test the `get` command
	 *
	 * @since 4.7.0
	 * @group commands
	 */
	public function test_get() {
		$post_id = $this->ep_factory->post->create();

		$this->command->get( [ 'post', $post_id ], [] );

		$output = $this->getActualOutputForAssertion();
		$this->assertStringStartsWith( '{', $output );
		$this->assertStringContainsString( '"post_id":' . $post_id, $output );

		// clean output buffer
		ob_clean();

		// test with --pretty flag
		$this->command->get( [ 'post', $post_id ], [ 'pretty' => true ] );

		$output = $this->getActualOutputForAssertion();
		$this->assertStringStartsWith( "{\n", $output );
		$this->assertStringContainsString( '"post_id": ' . $post_id, $output );
	}

	/**
	 * Test the `get` command when an indexable does not exist
	 *
	 * @since 4.7.0
	 * @group commands
	 */
	public function test_get_wrong_indexable() {
		$this->expectException( '\Exception' );
		$this->command->get( [ 'absent', '1' ], [] );
	}

	/**
	 * Test the `get` command when a post is not found
	 *
	 * @since 4.7.0
	 * @group commands
	 */
	public function test_get_not_found() {
		$this->expectExceptionMessage( 'Not found' );
		$this->command->get( [ 'post', '99999' ], [] );
	}

	/**
	 * Test the `get` command when `--debug-http-request` is passed
	 *
	 * @since 4.7.0
	 * @group commands
	 */
	public function test_get_debug_http_request() {
		$this->expectExceptionMessage( 'Not found' );

		// test with --debug-http-request flag
		$this->command->get( [ 'post', '99999' ], [ 'debug-http-request' => true ] );

		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'URL:', $output );
		$this->assertStringContainsString( 'Request Args:', $output );
		$this->assertStringContainsString( 'Transport:', $output );
		$this->assertStringContainsString( 'Context:', $output );
		$this->assertStringContainsString( 'Response:', $output );
	}

	/**
	 * Test commands throws an error if indexing is already happening.
	 */
	public function testThrowsErrorIfIndexingIsAlreadyHappening() {

		$this->expectExceptionMessage( 'An index is already occurring. Try again later.' );

		// mock indexing
		add_filter( 'ep_is_indexing', '__return_true' );

		$this->command->sync( [], [] );
	}

	/**
	 * Test commands throws deprecated warning.
	 *
	 *  @expectedDeprecated get-indexes
	 */
	public function testGetIndexesThrowsDeprecatedWarning() {

		$this->command->get_indexes( [], [] );

		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'This command is deprecated. Please use get-indices instead.', $output );
	}

	/**
	 * Test commands throws deprecated warning.
	 *
	 *  @expectedDeprecated get-cluster-indexes
	 */
	public function testGetClusterIndexesThrowsDeprecatedWarning() {

		$this->command->get_cluster_indexes( [], [] );

		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'This command is deprecated. Please use get-cluster-indices instead.', $output );
	}

	/**
	 * Test commands throws deprecated warning.
	 *
	 *  @expectedDeprecated index
	 */
	public function testIndexThrowsDeprecatedWarning() {

		// activate comments feature
		ElasticPress\Features::factory()->activate_feature( 'comments' );
		ElasticPress\Features::factory()->setup_features();

		// without these dummy content, the sync command gets failed because the static variable
		// https://github.com/10up/ElasticPress/blob/4.0.0/includes/classes/Indexable/Post/Post.php#L173
		// holds the old value.
		$this->ep_factory->post->create_many( 10 );
		$this->ep_factory->comment->create_many( 10, [ 'comment_post_ID' => $this->ep_factory->post->create() ] );

		$this->command->index( [], [] );

		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'This command is deprecated. Please use sync instead.', $output );
	}

	/**
	 * Test commands throws deprecated warning.
	 *
	 *  @expectedDeprecated clear-index
	 */
	public function testClearIndexThrowsDeprecatedWarning() {

		$this->command->clear_index( [], [] );

		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'This command is deprecated. Please use clear-sync instead.', $output );
	}

	/**
	 * Test commands throws deprecated warning.
	 *
	 *  @expectedDeprecated get-indexing-status
	 */
	public function testGetIndexingStatusThrowsDeprecatedWarning() {

		$this->command->get_indexing_status( [], [] );

		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'This command is deprecated. Please use get-ongoing-sync-status instead.', $output );
	}

	/**
	 * Test commands throws deprecated warning.
	 *
	 *  @expectedDeprecated get-last-cli-index
	 */
	public function testGetLastCliIndexThrowsDeprecatedWarning() {

		$this->command->get_last_cli_index( [], [] );

		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'This command is deprecated. Please use get-last-cli-sync instead.', $output );
	}

	/**
	 * Test commands throws deprecated warning.
	 *
	 *  @expectedDeprecated stop-indexing
	 */
	public function testStopIndexingThrowsDeprecatedWarning() {

		$this->command->stop_indexing( [], [] );

		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'This command is deprecated. Please use stop-sync instead.', $output );
	}

}
