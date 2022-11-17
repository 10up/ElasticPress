<?php
/**
 * ElasticPress report class
 *
 * @since 4.4.0
 * @package elasticpress
 */

namespace ElasticPress\StatusReport;

use ElasticPress\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * ElasticPress report class
 *
 * @package ElasticPress
 */
class ElasticPress extends Report {

	/**
	 * Return the report title
	 *
	 * @return string
	 */
	public function get_title() : string {
		return __( 'ElasticPress', 'elasticpress' );
	}

	/**
	 * Return the report fields
	 *
	 * @return array
	 */
	public function get_groups() : array {
		return [
			$this->get_basic_settings(),
			$this->get_timeouts(),
		];
	}

	/**
	 * Process ElasticPress's basic settings.
	 *
	 * @return array
	 */
	protected function get_basic_settings() : array {
		$is_epio = Utils\is_epio();

		$fields = [];

		$fields['host'] = [
			'label' => $is_epio ? __( 'ElasticPress.io Host URL', 'elasticpress' ) : __( 'Elasticsearch Host URL', 'elasticpress' ),
			'value' => Utils\get_host(),
		];

		$fields['index_prefix'] = [
			'label' => __( 'Index Prefix', 'elasticpress' ),
			'value' => Utils\get_index_prefix(),
		];

		$fields['language'] = [
			'label' => __( 'Elasticsearch Language', 'elasticpress' ),
			'value' => Utils\get_language(),
		];

		$fields['per_page'] = [
			'label' => __( 'Content Items per Index Cycle', 'elasticpress' ),
			'value' => \ElasticPress\IndexHelper::factory()->get_index_default_per_page(),
		];

		$fields['network_active'] = [
			'label' => __( 'Network Active', 'elasticpress' ),
			'value' => is_multisite() && defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK,
		];

		return [
			'title'  => __( 'Settings', 'elasticpress' ),
			'fields' => $fields,
		];
	}

	/**
	 * Process ElasticPress timeouts.
	 *
	 * @return array
	 */
	protected function get_timeouts() {
		$default_request_timeout   = 5;
		$fields['request_timeout'] = [
			'label' => sprintf(
				/* translators: default time */
				__( 'Default Requests Timeout (default: %s)', 'elasticpress' ),
				$default_request_timeout
			),
			'value' => apply_filters( 'http_request_timeout', $default_request_timeout, Utils\get_host() ),
		];

		$default_index_document_timeout   = 15;
		$fields['index_document_timeout'] = [
			'label' => sprintf(
				/* translators: default time */
				__( 'Index Document Request Timeout (default: %s)', 'elasticpress' ),
				$default_index_document_timeout
			),
			'value' => apply_filters( 'ep_index_document_timeout', $default_index_document_timeout ),
		];

		$default_bulk_request_timeout   = 30;
		$fields['bulk_request_timeout'] = [
			'label' => sprintf(
				/* translators: default time */
				__( 'Default Requests Timeout (default: %s)', 'elasticpress' ),
				$default_bulk_request_timeout
			),
			'value' => apply_filters( 'bulk_request_timeout', $default_bulk_request_timeout ),
		];

		return [
			'title'  => __( 'Timeouts', 'elasticpress' ),
			'fields' => $fields,
		];
	}
}
