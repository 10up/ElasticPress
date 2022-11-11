<?php
/**
 * Template for ElasticPress Status Report
 *
 * @since 4.4.0
 * @package elasticpress
 */

use ElasticPress\StatusReport;

defined( 'ABSPATH' ) || exit;

$status_report = new StatusReport();

require_once __DIR__ . '/header.php';
?>

<div class="wrap metabox-holder">
	<h1><?php esc_html_e( 'Status Report', 'elasticpress' ); ?></h1>
	<div>
		<?php
		$reports = $status_report->get_reports();
		foreach ( $reports as $report ) {
			$status_report->render_report( $report );
		}
		?>
	</div>
</div>
