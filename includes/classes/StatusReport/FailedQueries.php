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
		$this->maybe_clear_logs();

		$logs = $this->query_logger->get_logs( false );

		$labels = [
			'wp_url'      => esc_html__( 'Page URL', 'elasticpress' ),
			'es_req'      => esc_html__( 'Elasticsearch Request', 'elasticpress' ),
			'request_id'  => esc_html__( 'Request ID', 'elasticpress' ),
			'timestamp'   => esc_html__( 'Time', 'elasticpress' ),
			'query_time'  => esc_html__( 'Time Spent (ms)', 'elasticpress' ),
			'wp_args'     => esc_html__( 'WP Query Args', 'elasticpress' ),
			'status_code' => esc_html__( 'HTTP Status Code', 'elasticpress' ),
			'body'        => esc_html__( 'Query Body', 'elasticpress' ),
			'result'      => esc_html__( 'Query Result', 'elasticpress' ),
		];

		$groups = [];
		foreach ( $logs as $log ) {
			list( $error, $solution ) = $this->analyze_log( $log );

			$fields = [
				'error'                => [
					'label' => __( 'Error', 'elasticpress' ),
					'value' => $error,
				],
				'recommended_solution' => [
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
	 * Return the output of a button to clear the logged queries
	 *
	 * @return string
	 */
	public function get_actions() : array {
		global $wp;

		$logs = $this->query_logger->get_logs( false );

		if ( empty( $logs ) ) {
			return [];
		}

		$label = __( 'Clear query log', 'elasticpress' );
		$href  = wp_nonce_url( add_query_arg( [ $_GET ], $wp->request ), 'ep-clear-logged-queries', '_wpnonce' ); // phpcs:ignore WordPress.Security.NonceVerification

		return [
			[
				'href'  => $href,
				'label' => $label,
			],
		];
	}

	/**
	 * If a nonce is present, clear the logs
	 */
	protected function maybe_clear_logs() {
		if ( empty( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'ep-clear-logged-queries' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		$this->query_logger->clear_logs();

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			$redirect_url = network_admin_url( 'admin.php?page=elasticpress-status-report' );
		} else {
			$redirect_url = admin_url( 'admin.php?page=elasticpress-status-report' );
		}

		wp_safe_redirect( $redirect_url );
		exit();
	}

	/**
	 * Given a log, try to find the error and its solution
	 *
	 * @param array $log The log
	 * @return array The error in index 0, solution in index 1
	 */
	public function analyze_log( $log ) {
		$error = Utils\get_elasticsearch_error_reason( $log );

		$solution = ( ! empty( $error ) ) ?
			( new \ElasticPress\ElasticsearchErrorInterpreter() )->maybe_suggest_solution_for_es( $error )['solution'] :
			'';

		return [ $error, $solution ];
	}

	/**
	 * DEPRECATED. Given an Elasticsearch error, try to suggest a solution
	 *
	 * @deprecated 5.0.0
	 * @param string $error The error
	 * @return string
	 */
	protected function maybe_suggest_solution_for_es( $error ) {
		_deprecated_function( __METHOD__, '5.0.0', '\ElasticPress\ElasticsearchErrorInterpreter::maybe_suggest_solution_for_es()' );

		return ( new \ElasticPress\ElasticsearchErrorInterpreter() )->maybe_suggest_solution_for_es( $error )['solution'];
	}
}
