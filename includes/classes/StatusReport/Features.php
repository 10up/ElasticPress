<?php
/**
 * Feature report class
 *
 * @since 4.4.0
 * @package elasticpress
 */

namespace ElasticPress\StatusReport;

use \ElasticPress\Features as EP_Features;
use \ElasticPress\Feature\Search\Search;

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

			if ( method_exists( $this, "get_{$feature->slug}_extra_fields" ) ) {
				$fields = call_user_func( [ $this, "get_{$feature->slug}_extra_fields" ], $fields, $feature );
			}

			$groups[] = [
				'title'  => $feature->get_short_title(),
				'fields' => $fields,
			];
		}

		return $groups;
	}

	/**
	 * Extra fields for the Search feature
	 *
	 * @since 4.7.1
	 * @param array  $fields  Current fields
	 * @param Search $feature Feature object
	 * @return array New fields
	 */
	protected function get_search_extra_fields( array $fields, Search $feature ) : array {
		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			return $fields;
		}

		return array_merge(
			$fields,
			[
				'synonyms'  => [
					'label' => __( 'Synonyms', 'elasticpress' ),
					'value' => '<pre>' . $feature->synonyms->get_synonyms_raw() . '</pre>',
				],
				'weighting' => [
					'label' => __( 'Search Fields & Weighting', 'elasticpress' ),
					'value' => $feature->weighting->get_weighting_configuration(),
				],
			]
		);
	}
}
