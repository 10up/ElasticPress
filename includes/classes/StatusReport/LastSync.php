<?php
/**
 * Last sync report class
 *
 * @since 4.4.0
 * @package elasticpress
 */

namespace ElasticPress\StatusReport;

defined( 'ABSPATH' ) || exit;

/**
 * Last sync report class
 *
 * @package ElasticPress
 */
class LastSync extends Report {

	/**
	 * Return the report title
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Last Sync', 'elasticpress' );
	}

	/**
	 * Return the report fields
	 *
	 * @return array
	 */
	public function get_groups(): array {
		$fields = [];

		$sync_info = \ElasticPress\IndexHelper::factory()->get_last_sync();

		if ( empty( $sync_info ) ) {
			return [];
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

		if ( isset( $sync_info['is_full_sync'] ) ) {
			$sync_info['is_full_sync'] = $sync_info['is_full_sync'] ? esc_html__( 'Fresh rebuild', 'elasticpress' ) : esc_html__( 'Ordinary sync', 'elasticpress' );
		}

		$sync_info['scope'] = $this->format_scope( $sync_info['scope'] ?? null );

		$labels = [
			'total'           => esc_html__( 'Total', 'elasticpress' ),
			'synced'          => esc_html__( 'Synced', 'elasticpress' ),
			'skipped'         => esc_html__( 'Skipped', 'elasticpress' ),
			'failed'          => esc_html__( 'Failed', 'elasticpress' ),
			'errors'          => esc_html__( 'Errors', 'elasticpress' ),
			'method'          => esc_html__( 'Method', 'elasticpress' ),
			'is_full_sync'    => esc_html__( 'Sync mode', 'elasticpress' ),
			'scope'           => esc_html__( 'Target scope', 'elasticpress' ),
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
		$preferred_order   = [ 'method', 'scope', 'is_full_sync', 'start_date_time', 'end_date_time', 'total_time', 'total', 'synced', 'skipped', 'failed', 'errors' ];
		$ordered_sync_info = [];
		foreach ( $preferred_order as $field ) {
			if ( array_key_exists( $field, $sync_info ) ) {
				$ordered_sync_info[ $field ] = $sync_info[ $field ] ?? esc_html_x( 'N/A', 'Sync info not available', 'elasticpress' );
				unset( $sync_info[ $field ] );
			}
		}
		$sync_info = $ordered_sync_info + $sync_info;

		foreach ( $sync_info as $label => $value ) {
			$fields[ sanitize_title( $label ) ] = [
				'label' => $labels[ $label ] ?? $label,
				'value' => $value,
			];
		}
		$title = $sync_info['start_date_time'] ?? '';
		if ( false !== \ElasticPress\Utils\get_indexing_status() ) {
			/* translators: last sync title */
			$title = sprintf( __( '%s (In Progress)', 'elasticpress' ), $title );
		}
		if ( 'status-report' === \ElasticPress\Screen::factory()->get_current_screen() ) {
			unset( $fields['start_date_time'] );
		}

		return [
			[
				'title'  => $title,
				'fields' => $fields,
			],
		];
	}

	/**
	 * Format saved scope without looking up sites that may have changed since syncing.
	 *
	 * @param array|null $scope Saved scope, or null for older records.
	 * @return string Readable target scope.
	 */
	protected function format_scope( ?array $scope ): string {
		if ( null === $scope ) {
			return __( 'Not recorded', 'elasticpress' );
		}

		$parts = [];
		$sites = array_map(
			static fn( $site ) => sprintf(
				/* translators: 1: Site name, 2: Site address, 3: Site ID. */
				__( '%1$s (%2$s, ID %3$d)', 'elasticpress' ),
				$site['name'],
				$site['url'],
				$site['id']
			),
			$scope['sites']
		);

		if ( $sites ) {
			/* translators: List of targeted sites. */
			$parts[] = sprintf( __( 'Sites: %s', 'elasticpress' ), implode( '; ', $sites ) );
		}

		if ( $scope['shared'] ) {
			/* translators: List of shared indexable names. */
			$parts[] = sprintf( __( 'Shared network content: %s', 'elasticpress' ), implode( ', ', $scope['shared'] ) );
		}

		return $parts ? esc_html( implode( '; ', $parts ) ) : esc_html__( 'No targets', 'elasticpress' );
	}
}
