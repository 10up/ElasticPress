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
<div id="ep-status-reports" class="wrap"></div>
