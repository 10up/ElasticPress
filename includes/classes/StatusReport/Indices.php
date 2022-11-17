<?php
/**
 * Indices report class
 *
 * @since 4.4.0
 * @package elasticpress
 */

namespace ElasticPress\StatusReport;

defined( 'ABSPATH' ) || exit;

/**
 * Indices report class
 *
 * @package ElasticPress
 */
class Indices extends Report {

	/**
	 * Return the report title
	 *
	 * @return string
	 */
	public function get_title() : string {
		return __( 'Elasticsearch Indices', 'elasticpress' );
	}

	/**
	 * Return the report fields
	 *
	 * @return array
	 */
	public function get_groups() : array {
		$elasticsearch = \ElasticPress\Elasticsearch::factory();

		$should_have_indices   = $elasticsearch->get_index_names();
		$indices_in_es         = $elasticsearch->get_cluster_indices();
		$indices_in_es_by_name = [];

		if ( ! empty( $indices_in_es ) ) {
			foreach ( $indices_in_es as $index ) {
				$indices_in_es_by_name[ $index['index'] ] = $index;
			}
		}

		$groups = [];

		foreach ( $should_have_indices as $index_name ) {
			if ( empty( $indices_in_es_by_name[ $index_name ] ) ) {
				$groups[] = [
					'title'  => $index_name,
					'fields' => [
						[
							'label' => 'Missing',
							'value' => true,
						],
					],
				];
				continue;
			}

			$index = $indices_in_es_by_name[ $index_name ];

			$fields = array_reduce(
				array_keys( $index ),
				function ( $fields, $label ) use ( $index ) {
					$fields[ $label ] = [
						'label' => $label,
						'value' => $index[ $label ],
					];
					return $fields;
				},
				[]
			);

			$fields['total_fields_limit'] = [
				'label' => 'total_fields_limit',
				'value' => $elasticsearch->get_index_total_fields_limit( $index['index'] ),
			];

			$groups[] = [
				'title'  => $index['index'],
				'fields' => $fields,
			];
		}

		return $groups;
	}
}
