<?php
/**
 * Test the Status Report
 *
 * @since 4.4.0
 * @package elasticpress
 */

namespace ElasticPressTest;

use \ElasticPress\Screen\StatusReport;

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

		parse_str( 'ep-skip-reports[]=wordpress&ep-skip-reports[]=indexable', $_GET );

		$reports = $status_report->get_reports();
		$this->assertSame(
			[ 'failed-queries', 'elasticpress', 'indices', 'last-sync', 'features' ],
			array_keys( $reports )
		);
	}
}
