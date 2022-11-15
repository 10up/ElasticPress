<?php
/**
 * Autosuggest report class
 *
 * @since 4.4.0
 * @package elasticpress
 */

namespace ElasticPress\StatusReport;

defined( 'ABSPATH' ) || exit;

/**
 * Autosuggest report class
 *
 * @package ElasticPress
 */
class Autosuggest extends Report {

	/**
	 * Return the report title
	 *
	 * @return string
	 */
	public function get_title() : string {
		return __( 'ElasticPress.io - Autosuggest', 'elasticpress' );
	}

	/**
	 * Return the report fields
	 *
	 * @return array
	 */
	public function get_groups() : array {
		$title = __( 'Allowed parameters', 'elasticpress' );

		$autosuggest_feature = \ElasticPress\Features::factory()->get_registered_feature( 'autosuggest' );
		$allowed_params      = $autosuggest_feature->epio_autosuggest_set_and_get();

		if ( empty( $allowed_params ) ) {
			return [
				'title'  => $title,
				'fields' => [],
			];
		}

		$allowed_params = wp_parse_args(
			$allowed_params,
			[
				'postTypes'    => [],
				'postStatus'   => [],
				'searchFields' => [],
				'returnFields' => '',
			]
		);

		$fields = [
			'Post Types'      => wp_sprintf( esc_html__( '%l', 'elasticpress' ), $allowed_params['postTypes'] ),
			'Post Status'     => wp_sprintf( esc_html__( '%l', 'elasticpress' ), $allowed_params['postStatus'] ),
			'Search Fields'   => wp_sprintf( esc_html__( '%l', 'elasticpress' ), $allowed_params['searchFields'] ),
			'Returned Fields' => wp_sprintf( esc_html( var_export( $allowed_params['returnFields'], true ) ) ), // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
		];

		$formatted_fields = [];

		foreach ( $fields as $label => $value ) {
			$formatted_fields[ sanitize_title( $label ) ] = [
				'label' => $label,
				'value' => $value,
			];
		}

		return [
			[
				'title'  => $title,
				'fields' => $formatted_fields,
			],
		];
	}
}
