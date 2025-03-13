<?php
/**
 * AjaxReport abstract class
 *
 * @package elasticpress
 *
 * @since 5.2.0
 */

namespace ElasticPress\StatusReport;

defined( 'ABSPATH' ) || exit;

/**
 * AjaxReport class
 *
 * @package ElasticPress
 */
abstract class AjaxReport extends Report {
	/**
	 * Groups must return empty array in Ajax context. Always
	 *
	 * @return array
	 */
	final public function get_groups(): array {
		return [];
	}

	/**
	 * Return the report groups in an AJAX context
	 *
	 * @return string
	 */
	abstract public function get_groups_ajax(): array;
}
