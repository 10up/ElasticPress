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
	 * Return the report messages.
	 *
	 * @return array
	 */
	public function get_messages(): array {
		if ( isset( $_POST['action'] ) && 'ep_load_groups' === $_POST['action'] ) { // phpcs:ignore WordPress.Security.NonceVerification
			return [];
		}

		$messages = [
			[
				'type'    => 'warning',
				'message' => sprintf(
					__( 'To see this report, please generate a full report first by clicking the "Generate Full Status Report" button.', 'elasticpress' ),
				),
			],
		];

		return $messages;
	}

	/**
	 * Return the report groups in an AJAX context
	 *
	 * @return string
	 */
	abstract public function get_groups_ajax(): array;
}
