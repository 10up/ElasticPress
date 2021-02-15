<?php
/**
 * Elasticsearch health check
 *
 * @since  3.6.0
 * @package elasticpress
 */

namespace ElasticPress\HealthCheck;

use ElasticPress\HealthCheck as HealthCheck;
use ElasticPress\Utils as Utils;
use ElasticPress\Elasticsearch as Elasticsearch;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * HealthCheckElasticsearch class
 */
class HealthCheckElasticsearch extends HealthCheck {

	/**
	 * Create Elasticsearch health check.
	 */
	public function __construct() {
		$this->test_name = 'elasticpress-health-check-elasticsearch';
		$this->async     = true;
	}

	/**
	 * Runs the test.
	 *
	 * @return array Data about the result of the test.
	 */
	public function run() {
		$result = [
			'label'       => __( 'Your site can connect to Elasticsearch.', 'elasticpress' ),
			'status'      => 'good',
			'badge'       => [
				'label' => __( 'ElasticPress', 'elasticpress' ),
				'color' => 'green',
			],
			'description' => __( 'You can have a fast and flexible search and query engine for WordPress using ElasticPress.', 'elasticpress' ),
			'actions'     => '',
			'test'        => $this->test_name,
		];

		$host = Utils\get_host();

		if ( empty( $host ) ) {
			$result['label']          = __( 'Your site could not connect to Elasticsearch', 'elasticpress' );
			$result['status']         = 'critical';
			$result['badge']['color'] = 'red';
			$result['description']    = __( 'The Elasticsearch host is not set.', 'elasticpress' );
			$result['actions']        = sprintf(
				'<p><a href="%s">%s</a></p>',
				esc_url( admin_url( 'admin.php?page=elasticpress-settings' ) ),
				__( 'Add a host', 'elasticpress' )
			);
		} elseif ( ! Elasticsearch::factory()->get_elasticsearch_version( true ) ) {
			$result['label']          = __( 'Your site could not connect to Elasticsearch', 'elasticpress' );
			$result['status']         = 'critical';
			$result['badge']['color'] = 'red';
			$result['actions']        = sprintf(
				'<p><a href="%s">%s</a></p>',
				esc_url( admin_url( 'admin.php?page=elasticpress-settings' ) ),
				__( 'Update your settings', 'elasticpress' )
			);

			if ( Utils\is_epio() ) {
				$result['description'] = __( 'Check if your credentials to ElasticPress.io host are correct.', 'elasticpress' );
			} else {
				$result['description'] = __( 'Check if your Elasticsearch host URL is correct and you have the right access to the host.', 'elasticpress' );
			}
		}

		return $result;
	}
}
