<?php
/**
 * Template for ElasticPress Status Report
 *
 * @since 4.4.0
 * @package elasticpress
 */

defined( 'ABSPATH' ) || exit;

$status_report = \ElasticPress\Screen::factory()->status_report;

require_once __DIR__ . '/header.php';
?>

<div class="wrap">
	<h1><?php esc_html_e( 'Status Report', 'elasticpress' ); ?></h1>
	<div class="ep-status-report">
		<?php $status_report->render_reports(); ?>
		<div id="ep-status-reports"></div>
	</div>
</div>
