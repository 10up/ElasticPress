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
			'label'       => esc_html__( 'Your site can connect to Elasticsearch.', 'elasticpress' ),
			'status'      => 'good',
			'badge'       => [
				'label' => esc_html__( 'ElasticPress', 'elasticpress' ),
				'color' => 'green',
			],
			'description' => esc_html__( 'You can have a fast and flexible search and query engine for WordPress using ElasticPress.', 'elasticpress' ),
			'actions'     => '',
			'test'        => $this->test_name,
		];

		$host = Utils\get_host();

		$elasticpress_settings_url = defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ? admin_url( 'network/admin.php?page=elasticpress-settings' ) : admin_url( 'admin.php?page=elasticpress-settings' );

		if ( empty( $host ) ) {
			$result['label']          = esc_html__( 'Your site could not connect to Elasticsearch', 'elasticpress' );
			$result['status']         = 'critical';
			$result['badge']['color'] = 'red';
			$result['description']    = esc_html__( 'The Elasticsearch host is not set.', 'elasticpress' );
			$result['actions']        = sprintf(
				'<p><a href="%s">%s</a></p>',
				esc_url( $elasticpress_settings_url ),
				esc_html__( 'Add a host', 'elasticpress' )
			);
		} elseif ( ! Elasticsearch::factory()->get_elasticsearch_version( true ) ) {
			$result['label']          = esc_html__( 'Your site could not connect to Elasticsearch', 'elasticpress' );
			$result['status']         = 'critical';
			$result['badge']['color'] = 'red';
			$result['actions']        = sprintf(
				'<p><a href="%s">%s</a></p>',
				esc_url( $elasticpress_settings_url ),
				esc_html__( 'Update your settings', 'elasticpress' )
			);

			if ( Utils\is_epio() ) {
				$result['description'] = esc_html__( 'Check if your credentials to ElasticPress.io host are correct.', 'elasticpress' );
			} else {
				$result['description'] = esc_html__( 'Check if your Elasticsearch host URL is correct and you have the right access to the host.', 'elasticpress' );
			}
		}

		return $result;
	}
}
