<?php
/**
 * Error Interpreter Class File
 *
 * @since 5.0.0
 * @package elasticpress
 */

namespace ElasticPress;

defined( 'ABSPATH' ) || exit;

/**
 * ElasticsearchErrorInterpreter class
 *
 * @package ElasticPress
 */
class ElasticsearchErrorInterpreter {
	/**
	 * Format the error message and provide a solution
	 *
	 * @param string $error Error message
	 * @return array Array containing `error` and `solution`
	 */
	public function maybe_suggest_solution_for_es( $error ) {
		$sync_url = Utils\get_sync_url();

		if ( preg_match( '/no such index \[(.*?)\]/', $error, $matches ) ) {
			return [
				'error'    => 'no such index [???]',
				'solution' => sprintf(
					/* translators: 1. Index name; 2. Sync Page URL */
					__( 'It seems the %1$s index is missing. <a href="%2$s">Delete all data and sync</a> to fix the issue.', 'elasticpress' ),
					'<code>' . $matches[1] . '</code>',
					$sync_url
				),
			];
		}

		if ( preg_match( '/No mapping found for \[(.*?)\] in order to sort on/', $error, $matches ) ) {
			return [
				'error'    => 'No mapping found for [???] in order to sort on',
				'solution' => sprintf(
					/* translators: 1. Index name; 2. Sync Page URL */
					__( 'The field %1$s was not found. Make sure it is added to the list of indexed fields and run <a href="%2$s">a new sync</a> to fix the issue.', 'elasticpress' ),
					'<code>' . $matches[1] . '</code>',
					$sync_url
				),
			];
		}

		/* translators: 1. Field name; 2. Sync Page URL */
		$field_type_solution = __( 'It seems you saved a post without doing a full sync first because <code>%1$s</code> is missing the correct mapping type. <a href="%2$s">Delete all data and sync</a> to fix the issue.', 'elasticpress' );

		if ( preg_match( '/Fielddata is disabled on text fields by default. Set fielddata=true on \[(.*?)\]/', $error, $matches ) ) {
			return [
				'error'    => 'Fielddata is disabled on text fields by default. Set fielddata=true on [???]',
				'solution' => sprintf( $field_type_solution, $matches[1], $sync_url ),
			];
		}

		if ( preg_match( '/field \[(.*?)\] is of type \[(.*?)\], but only numeric types are supported./', $error, $matches ) ) {
			return [
				'error'    => 'field [???] is of type [???], but only numeric types are supported.',
				'solution' => sprintf( $field_type_solution, $matches[1], $sync_url ),
			];
		}

		if ( preg_match( '/Alternatively, set fielddata=true on \[(.*?)\] in order to load field data by uninverting the inverted index./', $error, $matches ) ) {
			return [
				'error'    => 'Alternatively, set fielddata=true on [???] in order to load field data by uninverting the inverted index.',
				'solution' => sprintf( $field_type_solution, $matches[1], $sync_url ),
			];
		}

		if ( preg_match( '/Limit of total fields \[(.*?)\] in index \[(.*?)\] has been exceeded/', $error, $matches ) ) {
			return [
				'error'    => 'Limit of total fields [???] in index [???] has been exceeded',
				'solution' => sprintf(
					/* translators: Elasticsearch or ElasticPress.io; 2. Link to article; 3. Link to article */
					__( 'Your website content has more public custom fields than %1$s is able to store. Check our articles about <a href="%2$s">Elasticsearch field limitations</a> and <a href="%3$s">how to index just the custom fields you need</a> and sync again.', 'elasticpress' ),
					Utils\is_epio() ? __( 'ElasticPress.io', 'elasticpress' ) : __( 'Elasticsearch', 'elasticpress' ),
					'https://elasticpress.zendesk.com/hc/en-us/articles/360051401212-I-get-the-error-Limit-of-total-fields-in-index-has-been-exceeded-',
					'https://elasticpress.zendesk.com/hc/en-us/articles/360052019111'
				),
			];
		}

		if ( Utils\is_epio() ) {
			return [
				'error'    => $error,
				'solution' => sprintf(
					/* translators: ElasticPress.io My Account URL */
					__( 'We did not recognize this error. Please open an ElasticPress.io <a href="%s">support ticket</a> so we can troubleshoot further.', 'elasticpress' ),
					'https://www.elasticpress.io/my-account/'
				),
			];
		}

		return [
			'error'    => $error,
			'solution' => sprintf(
				/* translators: New GitHub issue URL */
				__( 'We did not recognize this error. Please consider opening a <a href="%s">GitHub Issue</a> so we can add it to our list of supported errors.', 'elasticpress' ),
				'https://github.com/10up/ElasticPress/issues/new/choose'
			),
		];
	}
}
