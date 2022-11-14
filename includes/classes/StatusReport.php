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
		$reports = [
			'es_server' => new \ElasticPress\StatusReport\ElasticsearchServer(),
			'indices'   => new \ElasticPress\StatusReport\Indices(),
			'post_meta' => new \ElasticPress\StatusReport\PostMeta(),
			'last_sync' => new \ElasticPress\StatusReport\LastSync(),
			'features'  => new \ElasticPress\StatusReport\Features(),
		];

		return $reports;
	}

	/**
	 * Render a report
	 *
	 * @param Report $report Report array
	 */
	public function render_report( Report $report ) {
		?>
		<h3 class="hndle"><?php echo esc_html( $report->get_title() ); ?></h3>
		<?php
		foreach ( $report->get_groups() as $group ) {
			?>
			<div class="postbox posts-info">
				<div class="postbox-header">
					<h2 class="hndle"><?php echo esc_html( $group['title'] ); ?></h2>
				</div>
				<div class="inside">
					<table cellpadding="0" cellspacing="0" class="wp-list-table widefat striped">
						<tbody>
							<?php
							foreach ( $group['fields'] as $slug => $field ) {
								$label = $field['label'] ?? $slug;
								$value = $field['value'] ?? '';
								?>
								<tr>
									<td><?php echo esc_html( $label ); ?></td>
									<td>
										<?php
										echo is_array( $value ) ?
											'<pre>' . esc_html( var_export( $value, true ) ) . '</pre>' : // phpcs:ignore WordPress.PHP.DevelopmentFunctions
											wp_kses_post( $value );
										?>
									</td>
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
}
