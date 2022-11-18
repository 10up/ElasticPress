<?php
/**
 * Failed Queries report class
 *
 * @since 4.4.0
 * @package elasticpress
 */

namespace ElasticPress\StatusReport;

use \ElasticPress\QueryLogger;
use \ElasticPress\Utils;

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
			'es_req'     => esc_html__( 'Elasticsearch Request', 'elasticpress' ),
			'timestamp'  => esc_html__( 'Time', 'elasticpress' ),
			'query_time' => esc_html__( 'Time Spent (ms)', 'elasticpress' ),
			'wp_args'    => esc_html__( 'WP Query Args', 'elasticpress' ),
			'body'       => esc_html__( 'Query Body', 'elasticpress' ),
			'result'     => esc_html__( 'Query Result', 'elasticpress' ),
		];

		$groups = [];
		foreach ( $logs as $log ) {
			list( $error, $solution ) = $this->analyze_log( $log );

			$fields = [
				[
					'label' => __( 'Error', 'elasticpress' ),
					'value' => $error,
				],
				[
					'label' => __( 'Recommended Solution', 'elasticpress' ),
					'value' => $solution,
				],
			];

			foreach ( $log as $field => $value ) {
				// Already outputted in the title
				if ( in_array( $field, [ 'wp_url', 'timestamp' ], true ) ) {
					continue;
				}

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

	/**
	 * Given a log, try to find the error and its solution
	 *
	 * @param array $log The log
	 * @return array The error in index 0, solution in index 1
	 */
	protected function analyze_log( $log ) {
		$error    = '';
		$solution = '';

		if ( ! empty( $log['result']['error'] ) && ! empty( $log['result']['error']['root_cause'][0]['reason'] ) ) {
			$error    = $log['result']['error']['root_cause'][0]['reason'];
			$solution = $this->maybe_suggest_solution_for_es( $error );
		}

		return [ $error, $solution ];
	}

	/**
	 * Given an Elasticsearch error, try to suggest a solution
	 *
	 * @param string $error The error
	 * @return string
	 */
	protected function maybe_suggest_solution_for_es( $error ) {
		if ( preg_match( '/no such index \[(.*?)\]/', $error, $matches ) ) {
			return sprintf(
				/* translators: 1. Index name; 2. Sync Page URL */
				__( 'It seems the %1$s index is missing. <a href="%2$s">Delete all data and sync</a> to fix the issue.', 'elasticpress' ),
				'<code>' . $matches[1] . '</code>',
				Utils\get_sync_url()
			);
		}

		if ( preg_match( '/No mapping found for \[(.*?)\] in order to sort on/', $error, $matches ) ) {
			return sprintf(
				/* translators: 1. Index name; 2. Sync Page URL */
				__( 'The field %1$s was not found. Make sure it is added to the list of indexed fields and run <a href="%2$s">a new sync</a> to fix the issue.', 'elasticpress' ),
				'<code>' . $matches[1] . '</code>',
				Utils\get_sync_url()
			);
		}

		if ( false !== strpos( 'Fielddata is disabled on text fields by default. Set fielddata=true on [post_date] in order to load fielddata in memory by uninverting the inverted index. Note that this can however use significant memory. Alternatively use a keyword field instead.', $error ) ) {
			return sprintf(
				/* translators: 1. Index name; 2. Sync Page URL */
				__( 'It seems you saved a post without doing a full sync first. <a href="%2$s">Delete all data and sync</a> to fix the issue.', 'elasticpress' ),
				'<code>' . $matches[1] . '</code>',
				Utils\get_sync_url()
			);
		}

		return '';
	}
}
