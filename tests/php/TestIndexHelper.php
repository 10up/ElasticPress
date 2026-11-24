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
		\ElasticPress\Elasticsearch::factory()->delete_all_indices();
		\ElasticPress\Indexables::factory()->get( 'post' )->put_mapping();

		$post_ids = $this->ep_factory->post->create_many( 4 );

		// A post deleted while the sync runs isn't taken off the total, which was counted when the sync started.
		add_action(
			'ep_pre_index_batch',
			function () use ( $post_ids ) {
				wp_delete_post( min( $post_ids ), true );
			}
		);

		\ElasticPress\IndexHelper::factory()->full_index(
			[
				'method'        => 'cli',
				'indexables'    => [ 'post' ],
				'per_page'      => 2,
				'output_method' => '__return_null',
			]
		);

		$last_sync = \ElasticPress\Utils\get_option( 'ep_last_cli_index' );

		$this->assertSame( 3, $last_sync['synced'] );
	}

	/**
	 * Test that a full sync counts the objects to sync once, not once per batch, and a new sync counts them again
	 *
	 * @since 5.3.7
	 * @group index-helper
	 */
	public function test_full_index_counts_objects_once_per_sync() {
		\ElasticPress\Elasticsearch::factory()->delete_all_indices();
		\ElasticPress\Indexables::factory()->get( 'post' )->put_mapping();

		$this->ep_factory->post->create_many( 3 );

		$counts      = 0;
		$count_posts = function ( $found_posts ) use ( &$counts ) {
			++$counts;
			return $found_posts;
		};
		add_filter( 'found_posts', $count_posts );

		$sync_args = [
			'method'        => 'cli',
			'indexables'    => [ 'post' ],
			'per_page'      => 1,
			'output_method' => '__return_null',
		];

		\ElasticPress\IndexHelper::factory()->full_index( $sync_args );

		$this->assertSame( 1, $counts, 'A sync should count the objects once' );

		\ElasticPress\IndexHelper::factory()->full_index( $sync_args );

		$this->assertSame( 2, $counts, 'A new sync should count the objects again' );
	}

	/**
	 * Test that a full sync paging by ID syncs an object that became indexable below its position after it started
	 *
	 * @since 5.3.7
	 * @group index-helper
	 */
	public function test_full_index_syncs_objects_that_became_indexable_while_it_ran() {
		\ElasticPress\Elasticsearch::factory()->delete_all_indices();
		\ElasticPress\Indexables::factory()->get( 'post' )->put_mapping();

		// The draft has the lowest ID, so the sync reaches it last
		$draft = $this->ep_factory->post->create( [ 'post_status' => 'draft' ] );
		$this->ep_factory->post->create_many( 4 );

		add_action(
			'ep_pre_index_batch',
			function () use ( $draft ) {
				wp_publish_post( $draft );
			}
		);

		\ElasticPress\IndexHelper::factory()->full_index(
			[
				'method'        => 'cli',
				'indexables'    => [ 'post' ],
				'per_page'      => 2,
				'output_method' => '__return_null',
			]
		);

		$last_sync = \ElasticPress\Utils\get_option( 'ep_last_cli_index' );

		$this->assertSame( 5, $last_sync['synced'] );
	}

	/**
	 * Test that a full sync ends at the total when the same batch keeps coming back, instead of going past it forever
	 *
	 * @since 5.3.7
	 * @group index-helper
	 */
	public function test_full_index_ends_when_the_same_batch_keeps_coming_back() {
		\ElasticPress\Elasticsearch::factory()->delete_all_indices();
		\ElasticPress\Indexables::factory()->get( 'post' )->put_mapping();

		$post_ids = $this->ep_factory->post->create_many( 3 );

		// post__in turns off paging by ID, so every batch has the same posts
		$same_posts = function ( $args ) use ( $post_ids ) {
			$args['post__in'] = $post_ids;
			return $args;
		};
		add_filter( 'ep_index_posts_args', $same_posts );

		// Lets the sync end if it doesn't stop on its own
		$batches       = 0;
		$count_batches = function () use ( &$batches, $same_posts ) {
			if ( ++$batches > 3 ) {
				remove_filter( 'ep_index_posts_args', $same_posts );
			}
		};
		add_action( 'ep_pre_index_batch', $count_batches );

		\ElasticPress\IndexHelper::factory()->full_index(
			[
				'method'        => 'cli',
				'indexables'    => [ 'post' ],
				'per_page'      => 2,
				'output_method' => '__return_null',
			]
		);

		// The 3 posts take 2 batches of 2
		$this->assertSame( 2, $batches );
	}

	/**
	 * Test that a sync doesn't count a batch as synced when one of its requests failed
	 *
	 * @since 5.3.7
	 * @group index-helper
	 */
	public function test_full_index_counts_batch_with_failed_request_as_failed() {
		\ElasticPress\Elasticsearch::factory()->delete_all_indices();
		\ElasticPress\Indexables::factory()->get( 'post' )->put_mapping();

		// Each document is larger than the default buffer size, so the batch takes more than one request
		$this->ep_factory->post->create_many( 3, [ 'post_content' => str_repeat( 'lorem ipsum ', 50000 ) ] );

		// Only the first request fails
		$requests = 0;
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( &$requests ) {
				if ( ! str_contains( $url, '/_bulk' ) || ++$requests > 1 ) {
					return $preempt;
				}

				return [
					'body'     => '',
					'response' => [
						'code'    => 503,
						'message' => '',
					],
				];
			},
			10,
			3
		);

		\ElasticPress\IndexHelper::factory()->full_index(
			[
				'method'        => 'cli',
				'indexables'    => [ 'post' ],
				'output_method' => '__return_null',
			]
		);

		$last_sync = \ElasticPress\Utils\get_option( 'ep_last_cli_index' );

		$this->assertGreaterThan( 1, $requests, 'The batch should take more than one request' );
		$this->assertGreaterThan( 0, $last_sync['failed'], 'The batch should be counted as failed' );
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
}
