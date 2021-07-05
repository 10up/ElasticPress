<?php
/**
 * Health check
 *
 * @package elasticpress
 * @since   3.6.0
 */

namespace ElasticPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$health_checks = [
	new HealthCheck\HealthCheckElasticsearch(),
];

foreach ( $health_checks as $health_check ) {
	$health_check->register_test();
}
