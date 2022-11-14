<?php
/**
 * ElasticPress Status Report class
 *
 * @since 4.4.0
 * @package elasticpress
 */

namespace ElasticPress\Screen;

use \ElasticPress\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Status Report class
 *
 * @package ElasticPress
 */
class StatusReport {
	/**
	 * Initialize class
	 */
	public function setup() {
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
	}

	/**
	 * Enqueue script.
	 *
	 * @return void
	 */
	public function admin_enqueue_scripts() {
		if ( 'status-report' !== \ElasticPress\Screen::factory()->get_current_screen() ) {
			return;
		}

		$script_deps = Utils\get_asset_info( 'status-report-script', 'dependencies' );

		wp_enqueue_script(
			'ep_admin_status_report_scripts',
			EP_URL . 'dist/js/status-report-script.js',
			array_merge( $script_deps, [ 'clipboard' ] ),
			Utils\get_asset_info( 'status-report-script', 'version' ),
			true
		);
	}

	/**
	 * Return all reports available
	 *
	 * @return array
	 */
	public function get_reports() : array {
		$reports = [
			'wordpress' => new \ElasticPress\StatusReport\WordPress(),
			'es_server' => new \ElasticPress\StatusReport\ElasticsearchServer(),
			'indices'   => new \ElasticPress\StatusReport\Indices(),
			'post_meta' => new \ElasticPress\StatusReport\PostMeta(),
			'last_sync' => new \ElasticPress\StatusReport\LastSync(),
			'features'  => new \ElasticPress\StatusReport\Features(),
		];

		return $reports;
	}

	/**
	 * Render all reports (HTML and Copy & Paste button)
	 */
	public function render_reports() {
		$reports = $this->get_reports();

		$html_output       = [];
		$copy_paste_output = [];

		foreach ( $reports as $report ) {
			$title  = $report->get_title();
			$groups = $report->get_groups();

			$html_output[]       = $this->render_html_report( $title, $groups );
			$copy_paste_output[] = $this->render_copy_paste_report( $title, $groups );
		}

		?>
		<button class="button" type="button" id="ep-copy-report" data-clipboard-text="<?php echo esc_attr( implode( "\n\n", $copy_paste_output ) ); ?>"><?php esc_html_e( 'Copy Status Report', 'elasticpress' ); ?></button>
		<div id="ep-copy-success">
			<span class="dashicons dashicons-yes"></span><?php esc_html_e( 'Report Copied!', 'elasticpress' ); ?>
		</div>
		<?php
		echo wp_kses_post( implode( '', $html_output ) );
	}

	/**
	 * Render the HTML of a report
	 *
	 * @param string $title  Report title
	 * @param array  $groups Report groups
	 * @return string
	 */
	public function render_html_report( string $title, array $groups ) : string {
		ob_start();
		?>
		<h2><?php echo esc_html( $title ); ?></h2>
		<?php
		foreach ( $groups as $group ) {
			?>
			<h3><?php echo esc_html( $group['title'] ); ?></h3>
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
								<?php echo wp_kses_post( $this->render_value( $value ) ); ?>
							</td>
						</tr>
						<?php
					}
					?>
				</tbody>
			</table>
			<?php
		}

		return ob_get_clean();
	}

	/**
	 * Render the copy & paste report
	 *
	 * @param string $title  Report title
	 * @param array  $groups Report groups
	 * @return string
	 */
	protected function render_copy_paste_report( string $title, array $groups ) : string {
		$output = "## {$title} ##\n\n";

		foreach ( $groups as $group ) {
			$output .= "### {$group['title']} ###\n";
			foreach ( $group['fields'] as $slug => $field ) {
				$value = $field['value'] ?? '';

				$output .= "{$slug}: ";
				$output .= $this->render_value( $value );
				$output .= "\n";
			}
			$output .= "\n";
		}

		return $output;
	}

	/**
	 * Render a value based on its type
	 *
	 * @param mixed $value The value
	 * @return string
	 */
	protected function render_value( $value ) {
		if ( is_array( $value ) || is_object( $value ) ) {
			return var_export( $value, true ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		}

		if ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		}

		return (string) $value;
	}
}
