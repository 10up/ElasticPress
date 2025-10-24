<?php
/**
 * ElasticPress admin bar handler
 *
 * @since 5.3.0
 * @package elasticpress
 */

namespace ElasticPress;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Admin bar class
 */
class AdminBar {
	/**
	 * Setup actions and filters
	 */
	public function setup() {
		add_action( 'admin_bar_menu', [ $this, 'add_admin_bar_status' ], 500 );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_style' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_style' ] );
		add_action( 'wp_footer', [ $this, 'update_placeholders' ], 500 );
		add_action( 'admin_footer', [ $this, 'update_placeholders' ], 500 );
	}


	/**
	 * Un-setup actions and filters (for multisite).
	 */
	public function tear_down() {
		remove_action( 'admin_bar_menu', [ $this, 'add_admin_bar_status' ], 500 );
		remove_action( 'wp_enqueue_scripts', [ $this, 'enqueue_style' ] );
		remove_action( 'admin_enqueue_scripts', [ $this, 'enqueue_style' ] );
		remove_action( 'wp_footer', [ $this, 'update_placeholders' ], 500 );
		remove_action( 'admin_footer', [ $this, 'update_placeholders' ], 500 );
	}

	/**
	 * Add the document status to the admin bar.
	 *
	 * @param \WP_Admin_Bar $admin_bar WP Admin Bar instance
	 * @return void
	 */
	public function add_admin_bar_status( \WP_Admin_Bar $admin_bar ) {
		if ( ! $this->should_display() ) {
			return;
		}

		$admin_bar->add_menu(
			[
				'id'    => 'ep-basic-status',
				'title' => '<div id="ep-ab-icon">
					<span class="screen-reader-text">ElasticPress</span>
				</div>
				<span id="ep-ab-indicator" class="ep-status-indicator"></span>',
			]
		);

		$admin_bar->add_menu(
			[
				'parent' => 'ep-basic-status',
				'id'     => 'ep-basic-status-summary',
				'title'  => __( 'No calls made to Elasticsearch', 'elasticpress' ),
			]
		);
	}

	/**
	 * Enqueue the style for the admin bar status.
	 */
	public function enqueue_style() {
		wp_enqueue_style(
			'ep_general_styles',
			EP_URL . 'dist/css/general-styles.css',
			Utils\get_asset_info( 'general-styles', 'dependencies' ),
			Utils\get_asset_info( 'general-styles', 'version' )
		);
	}

	/**
	 * Update the placeholders for the admin bar status.
	 */
	public function update_placeholders() {
		global $wp_query;

		if ( ! $this->should_display() ) {
			return;
		}

		$queries = $this->get_queries();
		$status  = $this->get_status( $queries );

		$has_main_query        = ! empty( $wp_query->query_vars['ep_integrate'] );
		$is_main_query_success = $has_main_query && $wp_query->elasticsearch_success;

		$main_query_status = __( 'No', 'elasticpress' );
		if ( $has_main_query ) {
			$main_query_status = $is_main_query_success ? __( 'Yes', 'elasticpress' ) : __( 'Failed', 'elasticpress' );
		}

		$results               = [];
		$results['main_query'] = sprintf(
			/* translators: %s: Yes, Failed, or No */
			__( 'Main query: %s', 'elasticpress' ),
			$main_query_status
		);
		$results['total_queries'] = sprintf(
			/* translators: %s: Total queries */
			__( 'Total queries: %s', 'elasticpress' ),
			count( $queries['filtered'] )
		);
		$results['failed_queries'] = sprintf(
			/* translators: %s: Failed queries */
			__( 'Failed queries: %s', 'elasticpress' ),
			count( $queries['failed'] )
		);

		$results['debugging_article'] = sprintf(
			'<a href="%s">' . __( 'More about debugging', 'elasticpress' ) . '</a>',
			'https://www.elasticpress.io/resources/articles/using-the-elasticpress-debugging-add-on-plugin/'
		);

		$status_and_summary = [
			'status'  => $status,
			'summary' => $results,
		];

		/**
		 * Filter whether to display the admin bar status.
		 *
		 * @since 5.3.0
		 * @hook ep_admin_bar_status_and_summary
		 * @param {array} $status_and_summary     CSS class of status indicator. (success, warning, or error)
		 * @param {array}  $results               Array of results to display in the admin bar.
		 * @param {array}  $queries               Array of filtered and failed queries.
		 * @param {bool}   $has_main_query        Whether the main query was integrated with Elasticsearch.
		 * @param {bool}   $is_main_query_success Whether the main query is successful.
		 * @return {array} New status value
		 */
		$filtered_status_and_summary = apply_filters( 'ep_admin_bar_status_and_summary', $status_and_summary, $queries, $has_main_query, $is_main_query_success );

		if ( ! isset( $filtered_status_and_summary['status'], $filtered_status_and_summary['summary'] ) ) {
			_doing_it_wrong(
				__METHOD__,
				esc_html__( 'The ep_admin_bar_status_and_summary filter must return an array with the status and summary keys.', 'elasticpress' ),
				'ElasticPress 5.3.0'
			);
			return;
		}

		$results_output = implode( '<br>', $filtered_status_and_summary['summary'] );
		$results_output = str_replace( '"', "'", $results_output );

		$final_output = '<script>
			document.addEventListener("DOMContentLoaded", function() {
       			document.getElementById("ep-ab-indicator").classList.add("ep-status-indicator--' . esc_js( $filtered_status_and_summary['status'] ) . '");
				document.querySelector("#wp-admin-bar-ep-basic-status-summary .ab-item").innerHTML = "' . wp_kses_post( $results_output ) . '";
    		});
		</script>';

		/**
		 * Filter the final output of the admin bar status and summary.
		 *
		 * @since 5.3.0
		 * @hook ep_admin_bar_status_and_summary_output
		 * @param {string} $final_output                The final output of the admin bar status and summary.
		 * @param {array}  $filtered_status_and_summary The filtered status and summary.
		 * @param {array}  $queries                     The queries.
		 * @return {string} New final output
		 */
		echo apply_filters( 'ep_admin_bar_status_and_summary_output', $final_output, $filtered_status_and_summary, $queries ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Whether to display the admin bar status.
	 *
	 * @return bool
	 */
	protected function should_display(): bool {
		/**
		 * Filter whether to display the admin bar status.
		 *
		 * @since 5.3.0
		 * @hook ep_admin_bar_should_display
		 * @param {bool} $should_display Whether to display the admin bar status.
		 * @return {bool} New should display value
		 */
		return apply_filters( 'ep_admin_bar_should_display', current_user_can( Utils\get_capability( 'admin-bar' ) ) );
	}

	/**
	 * Get the queries.
	 *
	 * @since 5.3.0
	 * @return array Array of filtered and failed queries.
	 */
	protected function get_queries(): array {
		$queries = \ElasticPress\Elasticsearch::factory()->get_query_log();

		$filtered_queries = array_filter(
			$queries,
			function ( $query ) {
				if ( ! isset( $query['request'] ) ) {
					return false;
				}
				return is_wp_error( $query['request'] ) || empty( $query['request']['is_ep_fake_request'] );
			}
		);
		$failed_queries   = array_filter(
			$filtered_queries,
			function ( $query ) {
				return is_wp_error( $query['request'] ) || ! isset( $query['request']['response'], $query['request']['response']['code'] ) || $query['request']['response']['code'] < 200 || $query['request']['response']['code'] >= 300;
			}
		);

		return [
			'filtered' => $filtered_queries,
			'failed'   => $failed_queries,
		];
	}

	/**
	 * Get the status.
	 *
	 * @since 5.3.0
	 * @param array $queries The queries.
	 * @return string The status.
	 *                 success, error, or empty string.
	 */
	protected function get_status( $queries ): string {
		if ( $queries['filtered'] ) {
			return 'success';
		}
		if ( $queries['failed'] ) {
			return 'error';
		}
		return '';
	}
}
