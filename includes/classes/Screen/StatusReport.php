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
	 * The formatted/processed reports.
	 *
	 * @since 4.5.0
	 * @var array
	 */
	protected $formatted_reports = [];

	/**
	 * Initialize class
	 */
	public function setup() {
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
		add_action( 'admin_head', array( $this, 'admin_menu_count' ), 11 );
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

		wp_enqueue_script(
			'ep_admin_status_report_scripts',
			EP_URL . 'dist/js/status-report-script.js',
			Utils\get_asset_info( 'status-report-script', 'dependencies' ),
			Utils\get_asset_info( 'status-report-script', 'version' ),
			true
		);

		$reports = $this->get_formatted_reports();

		$plain_text_reports = [];

		foreach ( $reports as $report ) {
			$title  = $report['title'];
			$groups = $report['groups'];

			$plain_text_reports[] = $this->render_copy_paste_report( $title, $groups );
		}

		$plain_text_report = implode( "\n\n", $plain_text_reports );

		wp_localize_script(
			'ep_admin_status_report_scripts',
			'epStatusReport',
			[
				'plainTextReport' => $plain_text_report,
				'reports'         => $reports,
			]
		);

		wp_enqueue_style(
			'ep_status_report_styles',
			EP_URL . 'dist/css/status-report-script.css',
			[ 'wp-components', 'wp-edit-post' ],
			Utils\get_asset_info( 'status-report-script', 'version' )
		);
	}

	/**
	 * Return all reports available
	 *
	 * @return array
	 */
	public function get_reports() : array {
		$reports = [];

		$query_logger = \ElasticPress\get_container()->get( '\ElasticPress\QueryLogger' );

		if ( $query_logger ) {
			$reports['failed-queries'] = new \ElasticPress\StatusReport\FailedQueries( $query_logger );
		}

		if ( Utils\is_epio() ) {
			$reports['autosuggest'] = new \ElasticPress\StatusReport\ElasticPressIo();
		}

		$reports['wordpress']    = new \ElasticPress\StatusReport\WordPress();
		$reports['indexable']    = new \ElasticPress\StatusReport\IndexableContent();
		$reports['elasticpress'] = new \ElasticPress\StatusReport\ElasticPress();
		$reports['indices']      = new \ElasticPress\StatusReport\Indices();
		$reports['last-sync']    = new \ElasticPress\StatusReport\LastSync();
		$reports['features']     = new \ElasticPress\StatusReport\Features();

		/**
		 * Filter the reports executed in the Status Report page.
		 *
		 * @since 4.4.0
		 * @hook ep_status_report_reports
		 * @param {array<Report>} $reports Array of reports
		 * @return {array<Report>} New array of reports
		 */
		$filtered_reports = apply_filters( 'ep_status_report_reports', $reports );

		// phpcs:disable WordPress.Security.NonceVerification
		$skipped_reports = isset( $_GET['ep-skip-reports'] ) ?
			array_map( 'sanitize_text_field', (array) wp_unslash( $_GET['ep-skip-reports'] ) ) :
			[];
		// phpcs:enable WordPress.Security.NonceVerification

		$filtered_reports = array_filter(
			$filtered_reports,
			function( $report_slug ) use ( $skipped_reports ) {
				return ! in_array( $report_slug, $skipped_reports, true );
			},
			ARRAY_FILTER_USE_KEY
		);

		return $filtered_reports;
	}

	/**
	 * Process and format the reports, then store them in the `formatted_reports` attribute.
	 *
	 * @since 4.5.0
	 * @return array
	 */
	protected function get_formatted_reports() : array {
		if ( empty( $this->formatted_reports ) ) {
			$reports = $this->get_reports();

			$this->formatted_reports = array_map(
				function( $report ) {
					return [
						'actions'  => $report->get_actions(),
						'groups'   => $report->get_groups(),
						'messages' => $report->get_messages(),
						'title'    => $report->get_title(),
					];
				},
				$reports
			);
		}
		return $this->formatted_reports;
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

	/**
	 * Display a badge in the admin menu if there's admin notices from
	 * ElasticPress.io.
	 *
	 * @return void
	 */
	public function admin_menu_count() {
		global $menu, $submenu;

		$messages = \ElasticPress\ElasticPressIo::factory()->get_endpoint_messages();

		if ( empty( $messages ) ) {
			return;
		}

		$count = count( $messages );
		$title = sprintf(
			/* translators: %d: Number of messages. */
			_n( '%s message from ElasticPress.io', '%s messages from ElasticPress.io', $count, 'elasticpress' ),
			$count
		);

		foreach ( $menu as $key => $value ) {
			if ( 'elasticpress' === $value[2] ) {
				$menu[ $key ][0] .= sprintf(
					' <span class="update-plugins"><span aria-hidden="true">%1$s</span><span class="screen-reader-text">%2$s</span></span>',
					esc_html( $count ),
					esc_attr( $title )
				);
			}
		}

		if ( ! isset( $submenu['elasticpress'] ) ) {
			return;
		}

		foreach ( $submenu['elasticpress'] as $key => $value ) {
			if ( 'elasticpress-status-report' === $value[2] ) {
				$submenu['elasticpress'][ $key ][0] .= sprintf(
					' <span class="menu-counter"><span aria-hidden="true">%1$s</span><span class="screen-reader-text">%2$s</span></span>',
					esc_html( $count ),
					esc_attr( $title )
				);
			}
		}
	}
}
