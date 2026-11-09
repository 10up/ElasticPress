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
	 *
	 * @group commands
	 */
	public function test_activate_feature() {

		$this->command->activate_feature( [ 'comments' ], [] );

		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'Feature activated', $output );
	}

	/**
	 * Test activate-feature throws warning when feature needs re-index.
	 *
	 * @group commands
	 */
	public function test_activate_feature_throw_warnings() {

		$this->command->activate_feature( [ 'comments' ], [] );

		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'This feature requires a re-index. You may want to run the index command next', $output );
	}

	/**
	 * Test activate-feature command throws error when feature is already activated.
	 *
	 * @group commands
	 */
	public function test_activate_feature_when_feature_is_already_activated() {

		$this->expectExceptionMessage( 'This feature is already active' );

		$this->command->activate_feature( [ 'facets' ], [] );
	}

	/**
	 * Test activate-feature command throws error when feature is not registered.
	 *
	 * @group commands
	 */
	public function test_activate_feature_for_invalid_feature() {

		$this->expectExceptionMessage( 'No feature with that slug is registered' );

		$this->command->activate_feature( [ 'invalid-feature' ], [] );
	}

	/**
	 * Test activate-feature command throws error when requirement is not met.
	 *
	 * @group commands
	 */
	public function test_activate_feature_when_requirement_is_not_met() {

		$this->expectExceptionMessage( 'Feature requirements are not met' );

		$this->command->activate_feature( [ 'instant-results' ], [] );
	}

	/**
	 * Test deactivate-feature command can deactivate feature.
	 *
	 * @group commands
	 */
	public function test_deactivate_feature() {

		$this->command->deactivate_feature( [ 'search' ], [] );

		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'Feature deactivated', $output );
	}


	/**
	 * Test deactivate-feature command throws error when feature is already deactivated.
	 *
	 * @group commands
	 */
	public function test_deactivate_feature_when_feature_is_already_deactivated() {

		$this->expectExceptionMessage( 'Feature is not active' );

		$this->command->deactivate_feature( [ 'instant-results' ], [] );
	}

	/**
	 * Test deactivate-feature command throws error when feature is not registered.
	 *
	 * @group commands
	 */
	public function test_deactivate_feature_for_invalid_feature() {

		$this->expectExceptionMessage( 'No feature with that slug is registered' );

		$this->command->deactivate_feature( [ 'invalid-feature' ], [] );
	}

	/**
	 * Test list-features command can list all active features.
	 *
	 * @group commands
	 */
	public function test_list_feature() {

		$this->command->list_features( [], [] );

		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'Active features:', $output );
		$this->assertStringContainsString( 'search', $output );
	}

	/**
	 * Test list-features command can list all features.
	 *
	 * @group commands
	 */
	public function test_list_feature_all() {

		$this->command->list_features( [], [ 'all' => true ] );

		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'Registered features:', $output );
		$this->assertStringContainsString( 'search', $output );
	}

	/**
	 * Test put-mapping command can put mapping for active features.
	 *
	 * @group commands
	 */
	public function test_put_mapping() {

		$this->command->put_mapping( [], [] );

		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'Adding post mapping', $output );
		$this->assertStringContainsString( 'Mapping sent', $output );
	}

	/**
	 * Test put-mapping command can put mapping for specific indexable.
	 *
	 * @group commands
	 */
	public function test_put_mapping_with_indexables_flag() {

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
	public function test_put_mapping_for_network_wide() {

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
	 *
	 * @group commands
	 */
	public function test_put_mapping_throw_error_if_mapping_failed() {

		$this->expectExceptionMessage( 'Mapping failed: This was forced to fail' );

		add_filter(
			'ep_config_mapping_request',
			function () {
				return new \WP_Error( 'test', 'This was forced to fail' );
			}
		);

		$this->command->put_mapping( [], [] );
	}

	/**
	 * Test put-mapping command throws error if mapping failed for network-wide.
	 *
	 * @group commands
	 */
	public function test_put_mapping_for_network_wide_throw_error_if_mapping_failed() {

		$this->expectExceptionMessage( 'Mapping failed: This was forced to fail' );

		add_filter(
			'ep_config_mapping_request',
			function () {
				return new \WP_Error( 'test', 'This was forced to fail' );
			}
		);

		$this->command->put_mapping( [], [ 'network-wide' => true ] );
	}

	/**
	 * Test put-mapping command can put mapping for global indexables.
	 *
	 * @group commands
	 */
	public function test_put_mapping_for_global_indexables() {
		ElasticPress\Features::factory()->activate_feature( 'global' );
		ElasticPress\Features::factory()->setup_features();

		$this->command->put_mapping( [], [ 'indexables' => 'global,post' ] );

		$output = $this->getActualOutputForAssertion();

		$this->assertStringContainsString( 'Adding global mapping', $output );
		$this->assertStringContainsString( 'Mapping sent', $output );
	}

	/**
	 * Test get-mapping command returns mapping.
	 *
	 * @group commands
	 */
	public function test_get_mapping() {

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
	 * @group commands
	 */
	public function test_get_cluster_indices() {

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
	 * @group commands
	 */
	public function test_get_indices() {

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
	public function test_recreate_network_alias() {

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
	public function test_recreate_network_alias_on_single_site() {

		$this->expectExceptionMessage( 'ElasticPress is not network activated.' );

		$this->command->recreate_network_alias( [], [] );
	}

	/**
	 * Test sync command can sync content.
	 *
	 * @group skip-on-multi-site
	 */
	public function test_sync() {

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
	public function test_sync_on_network() {

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
	 * @group commands
	 */
	public function test_sync_index_creation_without_setup_flag() {
		Indexables::factory()->get( 'post' )->delete_index();

		$this->command->sync( [], [] );

		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'Index not present. Mapping sent', $output );
	}

	/**
	 * Test sync command with setup flag.
	 *
	 * @group commands
	 */
	public function test_sync_with_setup_flag() {

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
	 * @group commands
	 */
	public function test_sync_with_setup_flag_delete_unused_indices() {
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
	 *
	 * @group commands
	 */
	public function test_sync_with_indexables_flag() {

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
	public function test_sync_with_include_flag() {

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
	public function test_sync_with_include_flag_for_network() {

		$post_id = $this->ep_factory->post->create();

		$this->command->sync( [], [ 'include' => $post_id ] );
		$output = $this->getActualOutputForAssertion();

		$this->assertStringContainsString( 'Number of posts indexed on site 1: 1', $output );
		$this->assertStringContainsString( 'Sync complete', $output );
	}

	/**
	 * Test sync command with per-page flag.
	 *
	 * @group commands
	 */
	public function test_sync_with_per_page_flag() {

		$this->ep_factory->post->create_many( 10 );

		$this->command->sync( [], [ 'per-page' => 5 ] );
		$output = $this->getActualOutputForAssertion();

		$this->assertStringContainsString( 'Processed posts 0 - 5 of 10', $output );
		$this->assertStringContainsString( 'Processed posts 5 - 10 of 10', $output );
		$this->assertStringContainsString( 'Sync complete', $output );
	}

	/**
	 * Test sync command with post-type flag.
	 *
	 * @group commands
	 */
	public function test_sync_with_post_type_flag() {

		$this->ep_factory->post->create_many( 10 );
		$this->ep_factory->post->create_many( 10, [ 'post_type' => 'page' ] );

		$this->command->sync( [], [ 'post-type' => 'page' ] );
		$output = $this->getActualOutputForAssertion();

		$this->assertStringContainsString( 'Processed posts 0 - 10 of 10', $output );
		$this->assertStringContainsString( 'Sync complete', $output );
	}

	/**
	 * Test sync command with ep-prefix flag.
	 *
	 * @group commands
	 */
	public function test_sync_with_ep_prefix_flag() {

		$this->ep_factory->post->create_many( 10 );
		$this->ep_factory->post->create_many( 10, [ 'post_type' => 'page' ] );

		$this->command->sync( [], [ 'ep-prefix' => 'test' ] );
		$output = $this->getActualOutputForAssertion();

		$this->assertStringContainsString( 'Sync complete', $output );
	}

	/**
	 * Test sync command with ep-host flag.
	 *
	 * @group commands
	 */
	public function test_sync_with_ep_host_flag() {

		$this->expectExceptionMessage( 'Could not connect to Elasticsearch' );

		$this->command->sync( [], [ 'ep-host' => 'https://incorrect.url' ] );
	}

	/**
	 * Test sync command can ask for confirmation when setup flag is set
	 *
	 * @group commands
	 */
	public function test_sync_ask_for_confirmation_when_setup_is_passed() {
		$this->expectExceptionMessage( 'Syncing with the --setup option will delete your existing index in Elasticsearch. Are you sure you want to delete your Elasticsearch index' );

		$this->command->sync( [], [ 'setup' => true ] );
	}

	/**
	 * Test sync command throws error if mapping failed.
	 *
	 * @group commands
	 */
	public function test_sync_throws_error_if_mapping_failed() {

		$this->expectExceptionMessage( 'Mapping failed: This was forced to fail' );

		// mock the mapping request to return the error
		add_filter(
			'ep_config_mapping_request',
			function () {
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
	 * @group commands
	 */
	public function test_sync_with_force_flag() {
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
	 * @group commands
	 */
	public function test_sync_ask_for_confirmation_when_force_is_passed() {
		$this->expectExceptionMessage( 'Are you sure you want to stop any other ongoing sync?' );

		$this->command->sync( [], [ 'force' => true ] );
	}

	/**
	 * Test status command returns status information.
	 *
	 * @group commands
	 */
	public function test_status() {

		$this->command->status( [], [] );

		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( '====== Status ======', $output );
		$this->assertStringContainsString( 'exampleorg-post-1', $output );
		$this->assertStringContainsString( '====== End Status ======', $output );
	}

	/**
	 * Test delete-index command ask for confirmation.
	 *
	 * @group commands
	 */
	public function test_delete_index_ask_for_confirmation() {

		$this->expectExceptionMessage( 'Are you sure you want to delete your Elasticsearch index?' );

		$this->command->delete_index( [], [] );
	}

	/**
	 * Test delete-index command can delete index.
	 *
	 * @group commands
	 */
	public function test_delete_index() {

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
	 * @group commands
	 */
	public function test_delete_index_global() {

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
	public function test_delete_index_for_network() {

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
	public function test_clear_sync() {

		$this->command->clear_sync( [], [] );

		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'Sync cleared.', $output );
	}

	/**
	 * Test get-ongoing-sync-status command returns ongoing sync status.
	 *
	 * @group commands
	 */
	public function test_get_ongoing_sync_status() {

		$this->command->get_ongoing_sync_status( [], [] );

		$output = $this->getActualOutputForAssertion();
		$this->assertJson( $output );
	}

	/**
	 * Test get-last-sync command returns last sync information.
	 *
	 * @group commands
	 */
	public function test_get_last_sync() {

		$this->command->get_last_sync( [], [] );

		$output = $this->getActualOutputForAssertion();
		$this->assertJson( $output );
	}

	/**
	 * Test get-last-cli-sync command returns last cli sync information.
	 *
	 * @group commands
	 */
	public function test_get_last_cli_sync() {

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
	 *
	 * @group commands
	 */
	public function test_stop_sync() {

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
	 * @group commands
	 */
	public function test_set_search_algorithm_version() {

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
	 * @group commands
	 */
	public function test_set_search_algorithm_version_without_version() {

		$this->expectExceptionMessage( 'This command expects a version number or the --default flag.' );

		$this->command->set_search_algorithm_version( [], [] );
	}

	/**
	 * Test get-search-algorithm-version returns the algorithm version.
	 *
	 * @group commands
	 */
	public function test_get_search_algorithm_version() {

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
	 *
	 * @group commands
	 */
	public function test_request() {

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
	 * @group commands
	 */
	public function test_sync_stop_on_error() {
		add_filter(
			'http_response',
			function ( $request ) {
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
	 *
	 * @group commands
	 */
	public function test_request_throws_error() {

		$this->expectExceptionMessage( 'Error: Request failed.' );

		// mock request
		add_filter( 'ep_intercept_remote_request', '__return_true' );
		add_filter(
			'ep_do_intercept_request',
			function () {
				return new \WP_Error( 400, 'Error: Request failed.' );
			}
		);

		$this->command->request( [ '_cat/indices' ], [ 'method' => 'POST' ] );
	}


	/**
	 * Test settings-reset command delete all settings.
	 *
	 * @group commands
	 */
	public function test_settings_reset() {

		$this->command->settings_reset( [], [ 'yes' => true ] );

		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'Settings deleted.', $output );
	}

	/**
	 * Test settings-reset command ask for confirmation.
	 *
	 * @group commands
	 */
	public function test_settings_reset_ask_for_confirmation() {

		$this->expectExceptionMessage( 'Are you sure you want to delete all ElasticPress settings?' );

		$this->command->settings_reset( [], [] );
	}

	/**
	 * Test stats command.
	 *
	 * @group commands
	 */
	public function test_stats() {

		$this->command->stats( [], [] );

		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( '====== End Stats ======', $output );
	}

	/**
	 * Test epio-set-autosuggest command.
	 *
	 * @group commands
	 */
	public function test_epio_set_autosuggest() {

		ElasticPress\Features::factory()->activate_feature( 'autosuggest' );

		$this->command->epio_set_autosuggest( [], [] );

		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'Done.', $output );
	}

	/**
	 * Test epio-set-autosuggest command throws an error if autosuggest is not enabled.
	 *
	 * @group commands
	 */
	public function test_epio_set_autosuggest_throws_error() {

		$this->expectExceptionMessage( 'Autosuggest is not enabled.' );

		$this->command->epio_set_autosuggest( [], [] );
	}

	/**
	 * Test `should_interrupt_sync` method.
	 *
	 * @group commands
	 */
	public function test_should_interrupt_sync() {

		set_transient( 'ep_wpcli_sync_interrupted', true );

		Utility::should_interrupt_sync();

		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'Sync was interrupted', $output );
		$this->assertStringContainsString( 'Indexing cleaned up.', $output );
	}

	/**
	 * Test commands throws an error when host is not set.
	 *
	 * @group commands
	 */
	public function test_throws_error_when_host_is_not_set() {

		$this->expectExceptionMessage( 'Elasticsearch host is not set.' );

		// set host to empty string
		add_filter( 'ep_host', '__return_empty_string' );

		$this->command->sync( [], [] );
	}

	/**
	 * Test get-index-settings command returns an index settings.
	 *
	 * @since 4.7.0
	 * @group commands
	 */
	public function test_get_index_settings() {
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
	 *
	 * @group commands
	 */
	public function test_throws_error_if_indexing_is_already_happening() {

		$this->expectExceptionMessage( 'An index is already occurring. Try again later.' );

		// mock indexing
		add_filter( 'ep_is_indexing', '__return_true' );

		$this->command->sync( [], [] );
	}

	/**
	 * Test the `sync` command when an exception is thrown
	 *
	 * This is not a "unit test" in the traditional sense, but it's needed so we are sure
	 * the error message is displayed correctly when an exception is thrown.
	 *
	 * @since 5.3.0
	 * @group commands
	 */
	public function test_error_message_when_exception_is_thrown() {

		$post_id_1 = $this->ep_factory->post->create();
		$this->ep_factory->post->create();

		$throw_exception = function ( $args ) use ( $post_id_1 ) {
			if ( $args['post_id'] === $post_id_1 ) {
				throw new \Exception( 'Something went wrong.' );
			}
			return $args;
		};
		add_filter( 'ep_post_sync_args_post_prepare_meta', $throw_exception );

		$this->command->sync( [], [ 'show-errors' => true ] );

		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( $post_id_1 . ' (Post): [prepare_document_error] Something went wrong.', $output );

		if ( $this->is_network_activate() ) {
			$this->assertStringContainsString( 'Number of posts index errors on site 1: 1', $output );
			$this->assertStringContainsString( 'Number of posts indexed on site 1: 1', $output );
		} else {
			$this->assertStringContainsString( 'Number of posts index errors: 1', $output );
			$this->assertStringContainsString( 'Number of posts indexed: 1', $output );
		}

		remove_filter( 'ep_post_sync_args_post_prepare_meta', $throw_exception );
	}

	/**
	 * Test the `sync` command with `--nobulk` when an exception is thrown
	 *
	 * @since 5.3.6
	 * @group commands
	 */
	public function test_nobulk_sync_reports_exception_as_error() {
		$post_id_1 = $this->ep_factory->post->create();
		$this->ep_factory->post->create();

		$throw_exception = function ( $args ) use ( $post_id_1 ) {
			if ( $args['post_id'] === $post_id_1 ) {
				throw new \Exception( 'Something went wrong.' );
			}
			return $args;
		};
		add_filter( 'ep_post_sync_args_post_prepare_meta', $throw_exception );

		$this->command->sync(
			[],
			[
				'nobulk'      => true,
				'show-errors' => true,
			]
		);

		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( $post_id_1 . ' (Post): [prepare_document_error] Something went wrong.', $output );

		if ( $this->is_network_activate() ) {
			$this->assertStringContainsString( 'Number of posts index errors on site 1: 1', $output );
			$this->assertStringContainsString( 'Number of posts indexed on site 1: 1', $output );
		} else {
			$this->assertStringContainsString( 'Number of posts index errors: 1', $output );
			$this->assertStringContainsString( 'Number of posts indexed: 1', $output );
		}

		remove_filter( 'ep_post_sync_args_post_prepare_meta', $throw_exception );
	}

	/**
	 * Test the `sync` command with `--nobulk` and `--stop-on-error` when an exception is thrown
	 *
	 * @since 5.3.6
	 * @group commands
	 */
	public function test_nobulk_sync_stops_on_exception() {
		$post_id = $this->ep_factory->post->create();

		add_filter(
			'ep_post_sync_args_post_prepare_meta',
			function () {
				throw new \Exception( 'Something went wrong.' );
			}
		);

		$this->expectExceptionMessage( $post_id . ' (Post): [prepare_document_error] Something went wrong.' );

		$this->command->sync(
			[],
			[
				'nobulk'        => true,
				'stop-on-error' => true,
			]
		);
	}

	/**
	 * Test the `sync` command with `--nobulk` when Elasticsearch returns an error for a document
	 *
	 * @since 5.3.6
	 * @group commands
	 */
	public function test_nobulk_sync_reports_elasticsearch_error() {
		$post_id = $this->ep_factory->post->create();

		add_filter(
			'http_response',
			function ( $response, $parsed_args, $url ) use ( $post_id ) {
				if ( 'POST' !== $parsed_args['method'] || ! str_ends_with( $url, '/_doc/' . $post_id ) ) {
					return $response;
				}

				$response['body'] = wp_json_encode(
					[
						'error'  => [
							'type'   => 'my dummy error type',
							'reason' => 'my dummy error reason',
						],
						'status' => 400,
					]
				);

				return $response;
			},
			10,
			3
		);

		$this->command->sync(
			[],
			[
				'nobulk'      => true,
				'show-errors' => true,
			]
		);

		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( $post_id . ' (Post): [my dummy error type] my dummy error reason', $output );
	}

	/**
	 * Test commands throws deprecated warning.
	 *
	 *  @expectedDeprecated get-indexes
	 *
	 * @group commands
	 */
	public function test_get_indexes_throws_deprecated_warning() {

		$this->command->get_indexes( [], [] );

		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'This command is deprecated. Please use get-indices instead.', $output );
	}

	/**
	 * Test commands throws deprecated warning.
	 *
	 *  @expectedDeprecated get-cluster-indexes
	 *
	 * @group commands
	 */
	public function test_get_cluster_indexes_throws_deprecated_warning() {

		$this->command->get_cluster_indexes( [], [] );

		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'This command is deprecated. Please use get-cluster-indices instead.', $output );
	}

	/**
	 * Test commands throws deprecated warning.
	 *
	 *  @expectedDeprecated index
	 *
	 * @group commands
	 */
	public function test_index_throws_deprecated_warning() {

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
	 *
	 * @group commands
	 */
	public function test_clear_index_throws_deprecated_warning() {

		$this->command->clear_index( [], [] );

		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'This command is deprecated. Please use clear-sync instead.', $output );
	}

	/**
	 * Test commands throws deprecated warning.
	 *
	 *  @expectedDeprecated get-indexing-status
	 *
	 * @group commands
	 */
	public function test_get_indexing_status_throws_deprecated_warning() {

		$this->command->get_indexing_status( [], [] );

		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'This command is deprecated. Please use get-ongoing-sync-status instead.', $output );
	}

	/**
	 * Test commands throws deprecated warning.
	 *
	 *  @expectedDeprecated get-last-cli-index
	 *
	 * @group commands
	 */
	public function test_get_last_cli_index_throws_deprecated_warning() {

		$this->command->get_last_cli_index( [], [] );

		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'This command is deprecated. Please use get-last-cli-sync instead.', $output );
	}

	/**
	 * Test commands throws deprecated warning.
	 *
	 *  @expectedDeprecated stop-indexing
	 *
	 * @group commands
	 */
	public function test_stop_indexing_throws_deprecated_warning() {

		$this->command->stop_indexing( [], [] );

		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'This command is deprecated. Please use stop-sync instead.', $output );
	}

	/**
	 * Test put-search-template command saves the search template for all sites.
	 *
	 * @group skip-on-single-site
	 * @since 5.3.3
	 */
	public function test_put_search_template_with_network_wide_flag() {
		$this->factory->blog->create_many( 2 );

		$sites          = ElasticPress\Utils\get_sites();
		$called_indices = 0;

		add_filter( 'ep_intercept_remote_request', '__return_true' );
		add_filter(
			'ep_do_intercept_request',
			function ( $response, $query ) use ( &$called_indices ) {
				if ( str_contains( $query['url'], 'api/v1/search/posts/' ) ) {
					$called_indices++;
				}

				return [
					'body'     => '{}',
					'response' => [ 'code' => 200 ],
				];
			},
			10,
			2
		);

		$this->command->put_search_template( [], [ 'network-wide' => true ] );
		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'Done.', $output );

		$this->assertEquals( count( $sites ), $called_indices );
	}

	/**
	 * Test delete-search-template command deletes the search template for all sites.
	 *
	 * @group skip-on-single-site
	 * @since 5.3.3
	 */
	public function test_delete_search_template_with_network_wide_flag() {
		$this->factory->blog->create_many( 2 );

		$sites          = ElasticPress\Utils\get_sites();
		$called_indices = 0;

		add_filter( 'ep_intercept_remote_request', '__return_true' );
		add_filter(
			'ep_do_intercept_request',
			function ( $response, $query ) use ( &$called_indices ) {
				if ( 'DELETE' === $query['args']['method'] && str_contains( $query['url'], 'api/v1/search/posts/' ) ) {
					$called_indices++;
				}

				return [
					'body'     => '{}',
					'response' => [ 'code' => 200 ],
				];
			},
			10,
			2
		);

		$this->command->delete_search_template( [], [ 'network-wide' => true ] );
		$output = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'Done', $output );

		$this->assertEquals( count( $sites ), $called_indices );
	}
}
