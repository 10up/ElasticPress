<?php
/**
 * Report abstract class
 *
 * @since 4.4.0
 * @package elasticpress
 */

namespace ElasticPress\StatusReport;

defined( 'ABSPATH' ) || exit;

/**
 * Report class
 *
 * @package ElasticPress
 */
abstract class Report {

	/**
	 * Return the report title
	 *
	 * @return string
	 */
	abstract public function get_title() : string;

	/**
	 * Return the report group(s) of fields
	 *
	 * @return array
	 */
	abstract public function get_groups() : array;
}
