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
		if ( empty( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'ep-clear-logged-queries' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		$this->query_logger->clear_logs();
	}

	/**
	 * Given a log, try to find the error and its solution
	 *
	 * @param array $log The log
	 * @return array The error in index 0, solution in index 1
	 */
	public function analyze_log( $log ) {
		$error = '';

		if ( ! empty( $log['result']['error'] ) && ! empty( $log['result']['error']['root_cause'][0]['reason'] ) ) {
			$error = $log['result']['error']['root_cause'][0]['reason'];
		}

		if ( ! empty( $log['result']['errors'] ) && ! empty( $log['result']['items'] ) && ! empty( $log['result']['items'][0]['index']['error']['reason'] ) ) {
			$error = $log['result']['items'][0]['index']['error']['reason'];
		}

		$solution = ( ! empty( $error ) ) ?
			$this->maybe_suggest_solution_for_es( $error ) :
			'';

		return [ $error, $solution ];
	}

	/**
	 * Given an Elasticsearch error, try to suggest a solution
	 *
	 * @param string $error The error
	 * @return string
	 */
	protected function maybe_suggest_solution_for_es( $error ) {
		$sync_url = Utils\get_sync_url();

		if ( preg_match( '/no such index \[(.*?)\]/', $error, $matches ) ) {
			return sprintf(
				/* translators: 1. Index name; 2. Sync Page URL */
				__( 'It seems the %1$s index is missing. <a href="%2$s">Delete all data and sync</a> to fix the issue.', 'elasticpress' ),
				'<code>' . $matches[1] . '</code>',
				$sync_url
			);
		}

		if ( preg_match( '/No mapping found for \[(.*?)\] in order to sort on/', $error, $matches ) ) {
			return sprintf(
				/* translators: 1. Index name; 2. Sync Page URL */
				__( 'The field %1$s was not found. Make sure it is added to the list of indexed fields and run <a href="%2$s">a new sync</a> to fix the issue.', 'elasticpress' ),
				'<code>' . $matches[1] . '</code>',
				$sync_url
			);
		}

		/* translators: 1. Field name; 2. Sync Page URL */
		$field_type_solution = __( 'It seems you saved a post without doing a full sync first because <code>%1$s</code> is missing the correct mapping type. <a href="%2$s">Delete all data and sync</a> to fix the issue.', 'elasticpress' );

		if ( preg_match( '/Fielddata is disabled on text fields by default. Set fielddata=true on \[(.*?)\]/', $error, $matches ) ) {
			return sprintf( $field_type_solution, $matches[1], $sync_url );
		}

		if ( preg_match( '/field \[(.*?)\] is of type \[(.*?)\], but only numeric types are supported./', $error, $matches ) ) {
			return sprintf( $field_type_solution, $matches[1], $sync_url );
		}

		if ( preg_match( '/Alternatively, set fielddata=true on \[(.*?)\] in order to load field data by uninverting the inverted index./', $error, $matches ) ) {
			return sprintf( $field_type_solution, $matches[1], $sync_url );
		}

		if ( preg_match( '/Limit of total fields \[(.*?)\] in index \[(.*?)\] has been exceeded/', $error, $matches ) ) {
			return sprintf(
				/* translators: Elasticsearch or ElasticPress.io; 2. Link to article; 3. Link to article */
				__( 'Your website content has more public custom fields than %1$s is able to store. Check our articles about <a href="%2$s">Elasticsearch field limitations</a> and <a href="%3$s">how to index just the custom fields you need</a> and sync again.', 'elasticpress' ),
				Utils\is_epio() ? __( 'ElasticPress.io', 'elasticpress' ) : __( 'Elasticsearch', 'elasticpress' ),
				'https://elasticpress.zendesk.com/hc/en-us/articles/360051401212-I-get-the-error-Limit-of-total-fields-in-index-has-been-exceeded-',
				'https://elasticpress.zendesk.com/hc/en-us/articles/360052019111'
			);
		}

		// field limit

		if ( Utils\is_epio() ) {
			return sprintf(
				/* translators: ElasticPress.io My Account URL */
				__( 'We did not recognize this error. Please open an ElasticPress.io <a href="%s">support ticket</a> so we can troubleshoot further.', 'elasticpress' ),
				'https://www.elasticpress.io/my-account/'
			);
		}

		return sprintf(
			/* translators: New GitHub issue URL */
			__( 'We did not recognize this error. Please consider opening a <a href="%s">GitHub Issue</a> so we can add it to our list of supported errors. ', 'elasticpress' ),
			'https://github.com/10up/ElasticPress/issues/new/choose'
		);
	}
}
