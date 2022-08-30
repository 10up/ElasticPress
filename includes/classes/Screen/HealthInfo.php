<?php
/**
 * Health info screen.
 *
 * @package ElasticPress
 */

namespace ElasticPress\Screen;

use ElasticPress\Features as Features;
use ElasticPress\IndexHelper;
use ElasticPress\Utils as Utils;

/**
 * Health info screen Class.
 */
class HealthInfo {

	/**
	 * Initialize class.
	 */
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

		if ( empty( $sync_info ) ) {
			$debug_info['ep_last_sync']['fields']['not_available'] = [
				'label'   => esc_html__( 'Last Sync', 'elasticpress' ),
				'value'   => esc_html__( 'Last sync info not available.', 'elasticpress' ),
				'private' => true,
			];
			return $debug_info;
		}

		if ( ! empty( $sync_info['end_time_gmt'] ) ) {
			unset( $sync_info['end_time_gmt'] );
		}

		if ( ! empty( $sync_info['total_time'] ) ) {
			$sync_info['total_time'] = human_readable_duration( gmdate( 'H:i:s', ceil( $sync_info['total_time'] ) ) );
		}

		if ( ! empty( $sync_info['end_date_time'] ) ) {
			$sync_info['end_date_time'] = wp_date(
				'Y/m/d g:i:s a',
				strtotime( $sync_info['end_date_time'] )
			);
		}

		if ( ! empty( $sync_info['start_date_time'] ) ) {
			$sync_info['start_date_time'] = wp_date(
				'Y/m/d g:i:s a',
				strtotime( $sync_info['start_date_time'] )
			);
		}

		if ( ! empty( $sync_info['method'] ) ) {
			$methods = [
				'web' => esc_html__( 'WP Dashboard', 'elasticpress' ),
				'cli' => esc_html__( 'WP-CLI', 'elasticpress' ),
			];

			$sync_info['method'] = $methods[ $sync_info['method'] ] ?? $sync_info['method'];
		}

		$labels = [
			'total'           => esc_html__( 'Total', 'elasticpress' ),
			'synced'          => esc_html__( 'Synced', 'elasticpress' ),
			'skipped'         => esc_html__( 'Skipped', 'elasticpress' ),
			'failed'          => esc_html__( 'Failed', 'elasticpress' ),
			'errors'          => esc_html__( 'Errors', 'elasticpress' ),
			'method'          => esc_html__( 'Method', 'elasticpress' ),
			'end_date_time'   => esc_html__( 'End Date Time', 'elasticpress' ),
			'start_date_time' => esc_html__( 'Start Date Time', 'elasticpress' ),
			'total_time'      => esc_html__( 'Total Time', 'elasticpress' ),
		];

		/**
		 * Apply a custom order to the table rows.
		 *
		 * As some rows could be unavailable (if the last sync was done using an older version of the plugin, for example),
		 * the usual `array_replace(array_flip())` strategy to reorder an array adds a wrong numeric value to the
		 * non-existent row.
		 */
		$preferred_order   = [ 'method', 'start_date_time', 'end_date_time', 'total_time', 'total', 'synced', 'skipped', 'failed', 'errors' ];
		$ordered_sync_info = [];
		foreach ( $preferred_order as $field ) {
			if ( array_key_exists( $field, $sync_info ) ) {
				$ordered_sync_info[ $field ] = $sync_info[ $field ] ?? esc_html_x( 'N/A', 'Sync info not available', 'elasticpress' );
				unset( $sync_info[ $field ] );
			}
		}
		$sync_info = $ordered_sync_info + $sync_info;

		foreach ( $sync_info as $label => $value ) {
			$debug_info['ep_last_sync']['fields'][ sanitize_title( $label ) ] = [
				'label'   => $labels[ $label ] ?? $label,
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
				'label'   => $label,
				'value'   => $value,
				'private' => true,
			];
		}

		return $debug_info;
	}
}
