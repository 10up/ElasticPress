<?php
/**
 * Elasticsearch Server report class
 *
 * @since 4.4.0
 * @package elasticpress
 */

namespace ElasticPress\StatusReport;

use ElasticPress\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * ElasticsearchServer report class
 *
 * @package ElasticPress
 */
class ElasticsearchServer extends Report {

	/**
	 * Return the report title
	 *
	 * @return string
	 */
	public function get_title() : string {
		return __( 'Elasticsearch', 'elasticpress' );
	}

	/**
	 * Return the report fields
	 *
	 * @return array
	 */
	public function get_groups() : array {
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
			[
				'title'  => __( 'Elasticsearch Environment', 'elasticpress' ),
				'fields' => $fields,
			],
		];
	}
}
