<?php
/**
 * Feature report class
 *
 * @since 4.4.0
 * @package elasticpress
 */

namespace ElasticPress\StatusReport;

defined( 'ABSPATH' ) || exit;

/**
 * Feature report class
 *
 * @package ElasticPress
 */
class Feature extends Report {

	/**
	 * Class constructor
	 *
	 * @param \ElasticPress\Feature $feature The feature to be processed
	 */
	public function __construct( protected \ElasticPress\Feature $feature ) {}

	/**
	 * Return the report title
	 *
	 * @return string
	 */
	public function get_title() : string {
		return sprintf(
			/* translators: Feature title */
			_x( 'Feature - %s', 'Status report meta box title', 'elasticpress' ),
			$this->feature->title
		);
	}

	/**
	 * Return the report fields
	 *
	 * @return array
	 */
	public function get_fields() : array {
		$features_settings = \ElasticPress\Utils\get_option( 'ep_feature_settings', [] );
		$feature_settings  = $features_settings[ $this->feature->slug ] ?? [];

		$fields = [];
		foreach ( $feature_settings as $feature_setting => $value ) {
			$fields[ $feature_setting ] = [
				'label' => $feature_setting,
				'value' => $value,
			];
		}
		ksort( $fields );

		return $fields;
	}
}
