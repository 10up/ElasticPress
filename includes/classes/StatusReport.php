<?php
/**
 * ElasticPress Status Report class
 *
 * @since 4.4.0
 * @package elasticpress
 */

namespace ElasticPress;

use \ElasticPress\StatusReport\Report;

defined( 'ABSPATH' ) || exit;

/**
 * Status Report class
 *
 * @package ElasticPress
 */
class StatusReport {
	/**
	 * Return all reports available
	 *
	 * @return array
	 */
	public function get_reports() : array {
		$reports = [];

		$features = array_filter(
			Features::factory()->registered_features,
			function( $feature ) {
				return $feature->is_active();
			}
		);
		$features = wp_list_sort( $features, 'title' );
		foreach ( $features as $feature ) {
			$reports[ 'feature_' . $feature->slug ] = new \ElasticPress\StatusReport\Feature( $feature );
		}

		$reports['post_meta'] = new \ElasticPress\StatusReport\PostMeta();
		$reports['last_sync'] = new \ElasticPress\StatusReport\LastSync();

		return $reports;
	}

	/**
	 * Render a report
	 *
	 * @param Report $report Report array
	 */
	public function render_report( Report $report ) {
		?>
		<div class="postbox posts-info">
			<div class="postbox-header">
				<h2 class="hndle"><?php echo esc_html( $report->get_title() ); ?></h2>
			</div>
			<div class="inside">
				<table cellpadding="0" cellspacing="0" class="wp-list-table widefat striped">
					<tbody>
						<?php
						foreach ( $report->get_fields() as $slug => $field ) {
							$label = $field['label'] ?? $slug;
							$value = $field['value'] ?? '';
							?>
							<tr>
								<td><?php echo esc_html( $label ); ?></td>
								<td><?php echo is_array( $value ) ? '<pre>' . esc_html( var_export( $value, true ) ) . '</pre>' : esc_html( $value ); ?></td>
							</tr>
							<?php
						}
						?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}
}
