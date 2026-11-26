<?php
/**
 * Test InderHelper class.
 *
 * @since 4.4.0
 * @package elasticpress
 */

namespace ElasticPressTest;

/**
 * InderHelper test class
 */
class TestIndexHelper extends BaseTestCase {

	/**
	 * Test that error data keeps a stack trace when messages share an error key.
	 *
	 * @since 5.3.6
	 * @group index-helper
	 */
	public function test_build_message_errors_data_keeps_stack_trace_for_shared_key() {
		// Both messages map to the "no such index [???]" key.
		$messages = [
			'1 (Post): [index_not_found_exception] no such index [first]',
			'2 (Post): [prepare_document_error] no such index [second] in wp-content/plugins/example.php:10',
		];

		$stack_traces = [
			$messages[1] => '#0 wp-content/plugins/example.php(10): example()',
		];

		$method = new \ReflectionMethod( \ElasticPress\IndexHelper::class, 'build_message_errors_data' );
		$method->setAccessible( true );
		$errors = $method->invoke( \ElasticPress\IndexHelper::factory(), $messages, $stack_traces );

		$this->assertCount( 1, $errors );
		$this->assertSame( 2, $errors['no such index [???]']['count'] );
		$this->assertSame( $stack_traces[ $messages[1] ], $errors['no such index [???]']['stack_trace'] );
	}

	/**
	 * Test if the ep_sync_args filter is applied in the full_index method
	 *
	 * @since 4.5.0
	 * @group index-helper
	 */
	public function test_full_index_ep_sync_args_filter() {
		$index_helper = \ElasticPress\IndexHelper::factory();

		$args = [
			'method'        => 'custom',
			'output_method' => function () {},
		];

		$change_args = function ( $filter_args, $index_meta ) use ( $args ) {
			$this->assertFalse( $index_meta );
			$this->assertSame( $filter_args, $args );
			return $filter_args;
		};
		add_filter( 'ep_sync_args', $change_args, 10, 2 );

		$index_helper->full_index( $args );

		$this->assertGreaterThanOrEqual( 1, did_filter( 'ep_sync_args' ) );
	}

	/**
	 * Test that a full sync ends when a batch comes back empty before the offset reaches the total,
	 * instead of starting over from the first object.
	 *
	 * @since 5.3.6
	 * @group index-helper
	 */
	public function test_full_index_ends_on_empty_batch() {
		global $wpdb;

		\ElasticPress\Elasticsearch::factory()->delete_all_indices();
		\ElasticPress\Indexables::factory()->get( 'post' )->put_mapping();

		$this->ep_factory->post->create_many( 4 );

		$created = false;

		// A post created while the sync runs raises the total, but its ID is above the ones still to be synced.
		$create_post = function () use ( &$created, $wpdb ) {
			if ( $created ) {
				return;
			}

			$created = true;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->insert(
				$wpdb->posts,
				[
					'post_title'            => 'Created mid-sync',
					'post_status'           => 'publish',
					'post_type'             => 'post',
					'post_content'          => '',
					'post_excerpt'          => '',
					'to_ping'               => '',
					'pinged'                => '',
					'post_content_filtered' => '',
				]
			);
		};
		add_action( 'ep_pre_index_batch', $create_post );

		\ElasticPress\IndexHelper::factory()->full_index(
			[
				'method'        => 'cli',
				'indexables'    => [ 'post' ],
				'per_page'      => 2,
				'output_method' => '__return_null',
			]
		);

		$last_sync = \ElasticPress\Utils\get_option( 'ep_last_cli_index' );

		$this->assertSame( 4, $last_sync['synced'] );
	}

	/**
	 * Test get_index_default_per_page
	 *
	 * @group index-helper
	 */
	public function test_get_index_default_per_page() {
		$index_helper = \ElasticPress\IndexHelper::factory();

		/**
		 * Test the default value.
		 */
		$this->assertEquals( 350, $index_helper->get_index_default_per_page() );

		/**
		 * Test changing the option value (most likely how users will have this changed.)
		 */
		$change_via_option_filter = function () {
			return 10;
		};
		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			add_filter( 'pre_site_option_ep_bulk_setting', $change_via_option_filter );
		} else {
			add_filter( 'pre_option_ep_bulk_setting', $change_via_option_filter );
		}
		$this->assertEquals( 10, $index_helper->get_index_default_per_page() );

		/**
		 * Test the `ep_index_default_per_page` filter.
		 */
		$change_via_ep_filter = function () {
			return 15;
		};
		add_filter( 'ep_index_default_per_page', $change_via_ep_filter );
		$this->assertEquals( 15, $index_helper->get_index_default_per_page() );
	}

	/**
	 * Run an inactive task without changing the caller's site context.
	 *
	 * @param array $task Queued inactive task.
	 * @param array $indices Index names captured when the run started.
	 * @return array Output messages.
	 */
	private function process_inactive_task( array $task, array $indices ): array {
		$helper   = new \ElasticPress\IndexHelper();
		$messages = [];
		$meta     = new \ReflectionProperty( $helper, 'index_meta' );
		$meta->setAccessible( true );
		$meta->setValue(
			$helper,
			[
				'current_sync_item' => $task,
				'starting_indices'  => $indices,
			]
		);
		$args = new \ReflectionProperty( $helper, 'args' );
		$args->setAccessible( true );
		$args->setValue(
			$helper,
			[
				'output_method' => static function ( $message ) use ( &$messages ) {
					$messages[] = $message['message'];
				},
			]
		);
		$method = new \ReflectionMethod( $helper, 'process_not_active_indexable_sync_item' );
		$method->setAccessible( true );
		$method->invoke( $helper );
		$this->assertNull( $meta->getValue( $helper )['current_sync_item'] );
		\ElasticPress\Utils\delete_option( 'ep_index_meta' );

		return $messages;
	}

	/**
	 * A queued subsite's inactive index is deleted even when Main is current.
	 *
	 * @group index-helper
	 * @group inactive-cleanup
	 */
	public function test_inactive_cleanup_uses_queued_site_for_deletion_and_log() {
		$site_id   = self::factory()->blog->create();
		$current   = get_current_blog_id();
		$indexable = \ElasticPress\Indexables::factory()->get( 'post' );
		$main_name = $indexable->get_index_name();
		$site_name = $indexable->get_index_name( $site_id );
		$indexable->delete_index();
		$indexable->delete_index( $site_id );
		$this->assertTrue( $indexable->put_mapping() );
		$this->assertTrue( \ElasticPress\Elasticsearch::factory()->put_mapping( $site_name, $indexable->generate_mapping() ) );

		$messages = $this->process_inactive_task(
			[
				'indexable'   => 'post',
				'blog_id'     => $site_id,
				'put_mapping' => true,
			],
			[ $main_name, $site_name ]
		);

		$this->assertFalse( $indexable->index_exists( $site_id ), 'The queued site index must be deleted.' );
		$this->assertTrue( $indexable->index_exists(), 'The caller site index must remain.' );
		$this->assertSame( [ "Index {$site_name} deleted" ], $messages );
		$this->assertSame( $current, get_current_blog_id() );
	}

	/**
	 * Non-fresh and missing-target tasks never delete the current site's index.
	 *
	 * @group index-helper
	 * @group inactive-cleanup
	 */
	public function test_inactive_cleanup_preserves_no_op_guards() {
		$site_id   = self::factory()->blog->create();
		$indexable = \ElasticPress\Indexables::factory()->get( 'post' );
		$main_name = $indexable->get_index_name();
		$site_name = $indexable->get_index_name( $site_id );
		$indexable->delete_index();
		$indexable->delete_index( $site_id );
		$this->assertTrue( $indexable->put_mapping() );
		$this->assertTrue( \ElasticPress\Elasticsearch::factory()->put_mapping( $site_name, $indexable->generate_mapping() ) );

		foreach ( [ false, true ] as $fresh ) {
			$indices  = $fresh ? [ $main_name ] : [ $main_name, $site_name ];
			$messages = $this->process_inactive_task(
				[
					'indexable'   => 'post',
					'blog_id'     => $site_id,
					'put_mapping' => $fresh,
				],
				$indices
			);
			$this->assertSame( [], $messages );
			$this->assertTrue( $indexable->index_exists( $site_id ) );
			$this->assertTrue( $indexable->index_exists() );
		}

		$indexable->delete_index( $site_id );
		$messages = $this->process_inactive_task(
			[
				'indexable'   => 'post',
				'blog_id'     => $site_id,
				'put_mapping' => true,
			],
			[ $main_name ]
		);
		$this->assertSame( [], $messages );
		$this->assertTrue( $indexable->index_exists() );
	}

	/**
	 * Tasks without a site ID retain current-site and shared-index behavior.
	 *
	 * @group index-helper
	 * @group inactive-cleanup
	 */
	public function test_inactive_cleanup_without_site_id_keeps_existing_scope() {
		foreach ( [ 'post', 'global' ] as $slug ) {
			$indexable = \ElasticPress\Indexables::factory()->get( $slug );
			$indexable->delete_index();
			$this->assertTrue( $indexable->put_mapping() );
			$name     = $indexable->get_index_name();
			$messages = $this->process_inactive_task(
				[
					'indexable'   => $slug,
					'put_mapping' => true,
				],
				[ $name ]
			);
			$this->assertFalse( $indexable->index_exists() );
			$this->assertSame( [ "Index {$name} deleted" ], $messages );
		}
	}

	/**
	 * Finalize an alias using saved job scope, without a site in request arguments.
	 *
	 * @param int|null $site_id Selected site, or null for a network job.
	 * @return array Output messages.
	 */
	private function finalize_post_alias( $site_id ): array {
		$helper   = new \ElasticPress\IndexHelper();
		$messages = [];
		$meta     = new \ReflectionProperty( $helper, 'index_meta' );
		$meta->setAccessible( true );
		$meta->setValue(
			$helper,
			[
				'site_id'       => $site_id,
				'network_alias' => [ 'post' ],
			]
		);
		$args = new \ReflectionProperty( $helper, 'args' );
		$args->setAccessible( true );
		$args->setValue(
			$helper,
			[
				'output_method' => static function ( $message ) use ( &$messages ) {
					$messages[] = $message['message'];
				},
			]
		);
		$method = new \ReflectionMethod( $helper, 'create_network_alias' );
		$method->setAccessible( true );
		$method->invoke( $helper );
		$this->assertSame( [], $meta->getValue( $helper )['network_alias'] );
		\ElasticPress\Utils\delete_option( 'ep_index_meta' );

		return $messages;
	}

	/**
	 * An unrelated missing index must not prevent restoring the selected member.
	 *
	 * @group index-helper
	 * @group selected-alias
	 */
	public function test_selected_alias_restores_saved_target_with_unrelated_missing_index() {
		$target_id  = self::factory()->blog->create();
		$missing_id = self::factory()->blog->create();
		$current_id = get_current_blog_id();
		$indexable  = \ElasticPress\Indexables::factory()->get( 'post' );
		$elastic    = \ElasticPress\Elasticsearch::factory();
		$main_name  = $indexable->get_index_name();
		$target     = $indexable->get_index_name( $target_id );
		$indexable->delete_network_alias();
		$indexable->delete_index();
		$indexable->delete_index( $target_id );
		$indexable->delete_index( $missing_id );
		$this->assertTrue( $indexable->put_mapping() );
		$this->assertTrue( $elastic->put_mapping( $target, $indexable->generate_mapping() ) );
		$this->assertTrue( $indexable->create_network_alias( [ $main_name, $target ] ) );

		// Recreating the selected index drops only that index's alias membership.
		$indexable->delete_index( $target_id );
		$this->assertTrue( $elastic->put_mapping( $target, $indexable->generate_mapping() ) );
		$messages = $this->finalize_post_alias( $target_id );
		$response = $elastic->remote_request( '_alias/' . $indexable->get_network_alias(), [ 'method' => 'GET' ] );
		$members  = array_keys( json_decode( wp_remote_retrieve_body( $response ), true ) );
		$this->assertEqualsCanonicalizing( [ $main_name, $target ], $members );
		$this->assertSame( [ 'Network alias created for posts' ], $messages );
		$this->assertFalse( $indexable->index_exists( $missing_id ) );
		$this->assertSame( $current_id, get_current_blog_id() );
	}

	/**
	 * A job without a selected site still attaches all eligible site indexes.
	 *
	 * @group index-helper
	 * @group selected-alias
	 */
	public function test_network_alias_without_selected_site_retains_network_scope() {
		self::factory()->blog->create();
		$indexable = \ElasticPress\Indexables::factory()->get( 'post' );
		$elastic   = \ElasticPress\Elasticsearch::factory();
		$expected  = [];
		$indexable->delete_network_alias();

		foreach ( \ElasticPress\Utils\get_sites( 0, true ) as $site ) {
			switch_to_blog( $site['blog_id'] );
			$indexable->delete_index();
			$this->assertTrue( $indexable->put_mapping() );
			$expected[] = $indexable->get_index_name();
			restore_current_blog();
		}

		$messages = $this->finalize_post_alias( null );
		$response = $elastic->remote_request( '_alias/' . $indexable->get_network_alias(), [ 'method' => 'GET' ] );
		$members  = array_keys( json_decode( wp_remote_retrieve_body( $response ), true ) );
		$this->assertEqualsCanonicalizing( $expected, $members );
		$this->assertSame( [ 'Network alias created for posts' ], $messages );
	}
}
