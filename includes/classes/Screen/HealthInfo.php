<?php

namespace ElasticPress\Screen;

use ElasticPress\Features as Features;
use ElasticPress\IndexHelper;
use ElasticPress\Utils as Utils;

class HealthInfo {
	public function setup() {
		add_action( 'debug_information', [ $this, 'last_sync_health_info' ] );
		add_filter( 'debug_information', [ $this, 'epio_autosuggest_health_check_info' ] );
	}

	/**
	 * Display sync info in site health screen.
	 *
	 * @param array $debug_info The debug info for site health screen.
	 * @return array The debug info for site health screen.
	 */
	public function last_sync_health_info( $debug_info ) {
		$debug_info['ep_last_sync'] = array(
			'label'  => esc_html__( 'ElasticPress - Last Sync', 'elasticpress' ),
			'fields' => [],
		);

		$sync_info = IndexHelper::factory()->get_last_index();

		foreach ( $sync_info as $label => $value ) {
			$debug_info['ep_last_sync']['fields'][ sanitize_title( $label ) ] = [
				'label'   => sanitize_title( $label ),
				'value'   => $value,
				'private' => true,
			];
		}

		return $debug_info;
	}

	/**
	 * Add Autosuggest info for EP.io Users in Health Check Info Screen.
	 *
	 * @since 3.5.x
	 * @param array $debug_info Debug Info set so far.
	 * @return array
	 */
	public function epio_autosuggest_health_check_info( $debug_info ) {
		if ( ! Utils\is_epio() ) {
			return $debug_info;
		}

		$debug_info['epio_autosuggest'] = array(
			'label'  => esc_html__( 'ElasticPress.io - Autosuggest', 'elasticpress' ),
			'fields' => [],
		);

		$autosuggest_feature = Features::factory()->get_registered_feature( 'autosuggest' );
		$allowed_params      = $autosuggest_feature->epio_autosuggest_set_and_get();

		if ( empty( $allowed_params ) ) {
			return $debug_info;
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

		foreach ( $fields as $label => $value ) {
			$debug_info['epio_autosuggest']['fields'][ sanitize_title( $label ) ] = [
				'label'   => sanitize_title( $label ),
				'value'   => $value,
				'private' => true,
			];
		}

		return $debug_info;
	}
}
