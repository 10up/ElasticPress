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
	 * The error interpreter maps both messages below to the same `no such index [???]` key.
	 * The first has no stack trace, so the second one's trace must still be kept.
	 *
	 * @since 5.4.0
	 * @group indexHelper
	 */
	public function testBuildMessageErrorsDataKeepsStackTraceForSharedKey() {
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
	 * @group indexHelper
	 */
	public function testFullIndexEpSyncArgsFilter() {
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
	 * Test get_index_default_per_page
	 *
	 * @group indexHelper
	 */
	public function testGetIndexDefaultPerPage() {
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
