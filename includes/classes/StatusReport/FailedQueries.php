<?php
/**
 * Failed Queries report class
 *
 * @since 4.4.0
 * @package elasticpress
 */

namespace ElasticPress\StatusReport;

use \ElasticPress\QueryLogger;

defined( 'ABSPATH' ) || exit;

/**
 * FailedQueries report class
 *
 * @package ElasticPress
 */
class FailedQueries extends Report {

	/**
	 * The logger instance
	 *
	 * @var QueryLogger
	 */
	protected $query_logger;

	/**
	 * Class constructor
	 *
	 * @param QueryLogger $query_logger The logger instance
	 */
	public function __construct( QueryLogger $query_logger ) {
		$this->query_logger = $query_logger;
	}

	/**
	 * Return the report title
	 *
	 * @return string
	 */
	public function get_title() : string {
		return __( 'Failed Queries', 'elasticpress' );
	}

	/**
	 * Return the report fields
	 *
	 * @return array
	 */
	public function get_groups() : array {
		$logs = $this->query_logger->get_logs();

		$labels = [
			'wp_url'     => esc_html__( 'Page URL', 'elasticpress' ),
			'es_url'     => esc_html__( 'Elasticsearch URL', 'elasticpress' ),
			'timestamp'  => esc_html__( 'Time', 'elasticpress' ),
			'query_time' => esc_html__( 'Time Spent (ms)', 'elasticpress' ),
			'wp_args'    => esc_html__( 'WP Query Args', 'elasticpress' ),
			'body'       => esc_html__( 'Query Body', 'elasticpress' ),
			'result'     => esc_html__( 'Query Result', 'elasticpress' ),
		];

		$groups = [];
		foreach ( $logs as $log ) {
			$fields = [];

			foreach ( $log as $field => $value ) {
				$fields[ $field ] = [
					'label' => $labels[ $field ] ?? $field,
					'value' => $value,
				];
			}

			$groups[] = [
				'title'  => sprintf( '%s (%s)', $log['wp_url'], date_i18n( 'Y-m-d H:i:s', $log['timestamp'] ) ),
				'fields' => $fields,
			];
		}

		return $groups;
	}
}
