<?php
/**
 * Test the Status Report
 *
 * @since 4.4.0
 * @package elasticpress
 */

namespace ElasticPressTest;

use \ElasticPress\Screen\StatusReport;
use \ElasticPress\Utils;

/**
 * Test the Status Report class
 */
class TestStatusReport extends BaseTestCase {

	/**
	 * Test the default behavior of the get_reports method
	 *
	 * @group statusReport
	 */
	public function testGetReports() {
		$status_report = new StatusReport();

		$reports = $status_report->get_reports();
		$this->assertSame(
			[ 'failed-queries', 'wordpress', 'indexable', 'elasticpress', 'indices', 'last-sync', 'features' ],
			array_keys( $reports )
		);
	}

	/**
	 * Test the `ep_status_report_reports` filter in the get_reports method
	 *
	 * @group statusReport
	 */
	public function testGetReportsFilter() {
		$status_report = new StatusReport();

		$add_filter = function( $reports ) {
			$reports['custom'] = new \stdClass();
			return $reports;
		};
		add_filter( 'ep_status_report_reports', $add_filter );

		$reports = $status_report->get_reports();
		$this->assertSame(
			[ 'failed-queries', 'wordpress', 'indexable', 'elasticpress', 'indices', 'last-sync', 'features', 'custom' ],
			array_keys( $reports )
		);
	}

	/**
	 * Test skipping tests in the get_reports method via GET parameter
	 *
	 * @group statusReport
	 */
	public function testGetReportsSkipped() {
		$status_report = new StatusReport();

		parse_str( 'ep-skip-reports[]=wordpress&ep-skip-reports[]=indexable', $_GET ); // phpcs:ignore WordPress.WP.CapitalPDangit.Misspelled

		$reports = $status_report->get_reports();
		$this->assertSame(
			[ 'failed-queries', 'elasticpress', 'indices', 'last-sync', 'features' ],
			array_keys( $reports )
		);
	}

	/**
	 * Tests the WordPress report.
	 *
	 * @group statusReport
	 * @since 4.5.1
	 */
	public function testWordPressReport() {
		global $wp_version;

		$report = new \ElasticPress\StatusReport\WordPress();

		$expected_result = array(
			array(
				'title'  => 'WordPress Environment',
				'fields' => array(
					'wp_version'   => array(
						'label' => 'WordPress Version',
						'value' => $wp_version,
					),
					'home_url'     => array(
						'label' => 'Home URL',
						'value' => get_home_url(),
					),
					'site_url'     => array(
						'label' => 'Site URL',
						'value' => get_site_url(),
					),
					'is_multisite' => array(
						'label' => 'Multisite',
						'value' => is_multisite(),
					),
					'theme'        => array(
						'label' => 'Theme',
						'value' => sprintf( '%s (%s)', wp_get_theme()->get( 'Name' ), wp_get_theme()->get( 'Version' ) ),
					),
					'plugins'      => array(
						'label' => 'Active Plugins',
						'value' => '',
					),
					'revisions'    => array(
						'label' => 'Revisions allowed',
						'value' => WP_POST_REVISIONS === true ? 'all' : (int) WP_POST_REVISIONS,
					),
				),
			),
			array(
				'title'  => 'Server Environment',
				'fields' => array(
					'php_version'  => array(
						'label' => 'PHP Version',
						'value' => phpversion(),
					),
					'memory_limit' => array(
						'label' => 'Memory Limit',
						'value' => WP_MEMORY_LIMIT,
					),
					'timeout'      => array(
						'label' => 'Maximum Execution Time',
						'value' => (int) ini_get( 'max_execution_time' ),
					),
				),
			),
		);

		$this->assertSame( $expected_result, $report->get_groups() );
		$this->assertEquals( 'WordPress', $report->get_title() );
	}

	/**
	 * Tests the Last Sync report.
	 *
	 * @group statusReport
	 * @since 4.5.1
	 */
	public function testLastSyncReport() {
		$report = new \ElasticPress\StatusReport\LastSync();

		// Test when no last sync information is available
		$this->assertEmpty( $report->get_groups() );

		$start_time    = microtime( true );
		$end_date_time = date_create( 'now', wp_timezone() );

		$last_index                    = [];
		$last_index['end_date_time']   = $end_date_time->format( DATE_ATOM );
		$last_index['start_date_time'] = wp_date( DATE_ATOM, (int) $start_time );
		$last_index['end_time_gmt']    = time();
		$last_index['total_time']      = microtime( true ) - $start_time;
		$last_index['method']          = 'cli';
		$last_index['is_full_sync']    = 'Yes';
		Utils\update_option( 'ep_sync_history', [ $last_index ] );

		$expected_result = array(
			array(
				'title'  => wp_date( 'Y/m/d g:i:s a', strtotime( $last_index['start_date_time'] ) ),
				'fields' => array(
					'method'          => array(
						'label' => 'Method',
						'value' => 'WP-CLI',
					),
					'is_full_sync'    => array(
						'label' => 'Full Sync',
						'value' => $last_index['is_full_sync'],
					),
					'start_date_time' => array(
						'label' => 'Start Date Time',
						'value' => wp_date( 'Y/m/d g:i:s a', strtotime( $last_index['start_date_time'] ) ),
					),
					'end_date_time'   => array(
						'label' => 'End Date Time',
						'value' => wp_date( 'Y/m/d g:i:s a', strtotime( $last_index['end_date_time'] ) ),
					),
					'total_time'      => array(
						'label' => 'Total Time',
						'value' => human_readable_duration( gmdate( 'H:i:s', ceil( $last_index['total_time'] ) ) ),
					),
				),
			),
		);

		$this->assertSame( $expected_result, $report->get_groups() );
		$this->assertEquals( 'Last Sync', $report->get_title() );

		Utils\delete_option( 'ep_sync_history' );
	}

	/**
	 * Tests the Indices report.
	 *
	 * @group statusReport
	 * @since 4.5.1
	 */
	public function testIndicesReport() {
		// Make sure the index exists
		\ElasticPress\Indexables::factory()->get( 'post' )->put_mapping();

		$report = new \ElasticPress\StatusReport\Indices();

		$group         = $report->get_groups();
		$expected_keys = [ 'health', 'status', 'index', 'uuid', 'pri', 'rep', 'docs.count', 'docs.deleted', 'store.size', 'pri.store.size', 'total_fields_limit' ];

		foreach ( $expected_keys as $key ) {
			$this->assertArrayHasKey( $key, $group[0]['fields'] );
		}

		$this->assertEquals( 'Elasticsearch Indices', $report->get_title() );
	}

	/**
	 * Tests the Indexable Content report.
	 *
	 * @group statusReport
	 * @since 4.5.1
	 */
	public function testIndexableContentReport() {
		// set screen to status report
		add_filter( 'ep_install_status', '__return_true' );
		$_GET['page'] = 'elasticpress-status-report';
		\ElasticPress\Screen::factory()->determine_screen();

		$post_indexable = \ElasticPress\Indexables::factory()->get( 'post' );
		$post_types     = $post_indexable->get_indexable_post_types();

		$posts_fields       = array();
		$meta_fields        = array();
		$distinct_meta_keys = array();

		foreach ( $post_types as $post_type ) {
			$this->ep_factory->post->create_many(
				10,
				array(
					'post_type'  => $post_type,
					'meta_input' => array(
						'unique_meta_key_' . $post_type => 'foo',
						'shared_meta_key'               => 'bar',
					),
				)
			);

			$post_type_obj                         = get_post_type_object( $post_type );
			$posts_fields[ $post_type . '_count' ] = array(
				'label' => sprintf( '%s (%s)', $post_type_obj->labels->name, $post_type ),
				'value' => '10',
			);

			$meta_fields[ $post_type . '_meta_keys' ] = array(
				'label'       => sprintf( '%s (%s) Meta Keys', $post_type_obj->labels->singular_name, $post_type ),
				'description' => '',
				'value'       => '2',
			);

			$distinct_meta_keys = array_merge( $distinct_meta_keys, array( 'unique_meta_key_' . $post_type, 'shared_meta_key' ) );
		}

		$meta_fields['total-all-post-types'] = array(
			'label' => 'Total Distinct Meta Keys',
			'value' => count( $post_types ) + 1,
		);

		$meta_fields['distinct-meta-keys'] = array(
			'label' => 'Distinct Meta Keys',
			'value' => wp_sprintf( '%l', array_unique( $distinct_meta_keys ) ),
		);

		$expected_result = array(
			array(
				'title'  => sprintf( '%1$s &mdash; %2$s', get_option( 'blogname' ), site_url() ),
				'fields' => array_merge( $posts_fields, $meta_fields ),
			),
		);

		$report = new \ElasticPress\StatusReport\IndexableContent();

		$this->assertSame( $expected_result, $report->get_groups() );
		$this->assertEquals( 'Indexable Content', $report->get_title() );
	}

	/**
	 * Tests the Feature report.
	 *
	 * @group statusReport
	 * @since 4.5.1
	 */
	public function testFeatureReport() {
		// deactivate all feature.
		Utils\delete_option( 'ep_feature_settings' );

		// activate search feature.
		\ElasticPress\Features::factory()->activate_feature( 'search' );

		$report = new \ElasticPress\StatusReport\Features();
		$groups = $report->get_groups();

		$this->assertEquals( 1, count( $groups ) );
		$this->assertEquals( 'Post Search', $groups[0]['title'] );
		$this->assertEquals( 'Feature Settings', $report->get_title() );
	}

	/**
	 * Tests the Failed Queries report.
	 *
	 * @group statusReport
	 * @since 4.5.1
	 */
	public function testFailedQueriesReport() {
		$time_stamp = time();
		$random_no  = wp_rand( 1, 100 );

		add_filter(
			'ep_query_logger_logs',
			function( $logs ) use ( $time_stamp, $random_no ) {
				$logs = array(
					[
						'wp_url'      => 'http://example.com',
						'es_req'      => 'POST http://example.com/_search',
						'request_id'  => $random_no,
						'timestamp'   => $time_stamp,
						'query_time'  => $random_no,
						'wp_args'     => array(
							'post_type' => 'post',
						),
						'status_code' => 404,
						'body'        => wp_json_encode(
							array(
								'from' => 0,
								'size' => 10,
							)
						),
						'result'      => array(
							'error' => array(
								'root_cause' => array(
									array(
										'reason' => 'test',
									),
								),
							),
						),
					],
				);

				return $logs;
			}
		);

		$expected_result = array(
			'error'                => array(
				'label' => 'Error',
				'value' => 'test',
			),
			'recommended_solution' => array(
				'label' => 'Recommended Solution',
				'value' => 'We did not recognize this error. Please consider opening a <a href="https://github.com/10up/ElasticPress/issues/new/choose">GitHub Issue</a> so we can add it to our list of supported errors.',
			),
			'es_req'               => array(
				'label' => 'Elasticsearch Request',
				'value' => 'POST http://example.com/_search',
			),
			'request_id'           => array(
				'label' => 'Request ID',
				'value' => $random_no,
			),
			'query_time'           => array(
				'label' => 'Time Spent (ms)',
				'value' => $random_no,
			),
			'wp_args'              => array(
				'label' => 'WP Query Args',
				'value' => array(
					'post_type' => 'post',
				),
			),
			'status_code'          => array(
				'label' => 'HTTP Status Code',
				'value' => 404,
			),
			'body'                 => array(
				'label' => 'Query Body',
				'value' => '{"from":0,"size":10}',
			),
			'result'               => array(
				'label' => 'Query Result',
				'value' => array(
					'error' => array(
						'root_cause' => array(
							array(
								'reason' => 'test',
							),
						),
					),
				),
			),
		);

		$query_logger = new \ElasticPress\QueryLogger();
		$report       = new \ElasticPress\StatusReport\FailedQueries( $query_logger );

		$this->assertSame( $expected_result, $report->get_groups()[0]['fields'] );
		$this->assertEquals( 'Failed Queries', $report->get_title() );

		// test the actions label
		$this->assertEquals( 'Clear query log', $report->get_actions()[0]['label'] );
	}

	/**
	 * Tests ElasticPress.io report.
	 *
	 * @group statusReport
	 * @since 4.5.1
	 */
	public function testElasticPressIoReport() {
		\ElasticPress\Features::factory()->activate_feature( 'autosuggest' );
		\ElasticPress\Features::factory()->activate_feature( 'instant-results' );

		$report = new \ElasticPress\StatusReport\ElasticPressIo();
		$groups = $report->get_groups();

		$this->assertEquals( 3, count( $groups ) );
		$this->assertEquals( 'Allowed Autosuggest Parameters', $groups[0]['title'] );
		$this->assertEquals( 'Instant Results Template', $groups[1]['title'] );
		$this->assertEquals( 'Orders Search Template', $groups[2]['title'] );
		$this->assertEquals( 'ElasticPress.io', $report->get_title() );
	}

	/**
	 * Tests ElasticPress report.
	 *
	 * @group statusReport
	 * @since 4.5.1
	 */
	public function testElasticPressReport() {
		$report = new \ElasticPress\StatusReport\ElasticPress();
		$groups = $report->get_groups();

		$expected_result = array(
			array(
				'title'  => 'Settings',
				'fields' => array(
					'host'           => array(
						'label' => 'Elasticsearch Host URL',
						'value' => Utils\get_host(),
					),
					'index_prefix'   => array(
						'label' => 'Index Prefix',
						'value' => Utils\get_index_prefix(),
					),
					'language'       => array(
						'label' => 'Elasticsearch Language',
						'value' => Utils\get_language(),
					),
					'per_page'       => array(
						'label' => 'Content Items per Index Cycle',
						'value' => \ElasticPress\IndexHelper::factory()->get_index_default_per_page(),
					),
					'network_active' => array(
						'label' => 'Network Active',
						'value' => is_multisite() && defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK,
					),
				),
			),
			array(
				'title'  => 'Timeouts',
				'fields' => array(
					'request_timeout'        => array(
						'label' => 'Default Requests Timeout (default: 5)',
						'value' => 5,
					),
					'index_document_timeout' => array(
						'label' => 'Index Document Request Timeout (default: 15)',
						'value' => 15,
					),
					'bulk_request_timeout'   => array(
						'label' => 'Default Requests Timeout (default: 30)',
						'value' => 30,
					),
				),
			),
		);

		$this->assertSame( $expected_result, $report->get_groups() );
		$this->assertEquals( 2, count( $groups ) );
	}
}
