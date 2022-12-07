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
 * Commands notices test class
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
	 * Clean up after each test.
	 *
	 * @since 4.4.1
	 */
	public function tear_down() {
		parent::tear_down();
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

}
