<?php
/**
 * Feature report class
 *
 * @since 4.4.0
 * @package elasticpress
 */

namespace ElasticPress\StatusReport;

use \ElasticPress\Features as EP_Features;

defined( 'ABSPATH' ) || exit;

/**
 * Feature report class
 *
 * @package ElasticPress
 */
class Features extends Report {

	/**
	 * Return the report title
	 *
	 * @return string
	 */
	public function get_title() : string {
		return __( 'Feature Settings', 'elasticpress' );
	}

	/**
	 * Return the report fields
	 *
	 * @return array
	 */
	public function get_groups() : array {
		$features_settings = \ElasticPress\Utils\get_option( 'ep_feature_settings', [] );

		$features = array_filter(
			EP_Features::factory()->registered_features,
			function( $feature ) {
				return $feature->is_active();
			}
		);
		$features = wp_list_sort( $features, 'title' );

		$groups = [];
		foreach ( $features as $feature ) {
			$feature_settings = $features_settings[ $feature->slug ] ?? [];

			$fields = [];
			foreach ( $feature_settings as $feature_setting => $value ) {
				$fields[ $feature_setting ] = [
					'label' => $feature_setting,
					'value' => $value,
				];
			}
			ksort( $fields );

			$groups[] = [
				'title'  => $feature->title,
				'fields' => $fields,
			];
		}

		return $groups;
	}
}
