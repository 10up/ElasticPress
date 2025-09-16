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

		$has_main_query        = ! empty( $wp_query->query_vars['ep_integrate'] );
		$is_main_query_success = $has_main_query && $wp_query->elasticsearch_success;

		$queries = \ElasticPress\Elasticsearch::factory()->get_query_log();

		$filtered_queries = array_filter(
			$queries,
			function ( $query ) {
				return ! isset( $query['request'], $query['request']['is_ep_fake_request'] ) || ! $query['request']['is_ep_fake_request'];
			}
		);
		$failed_queries   = array_filter(
			$filtered_queries,
			function ( $query ) {
				return ! isset( $query['request'], $query['request']['response'], $query['request']['response']['code'] ) || $query['request']['response']['code'] < 200 || $query['request']['response']['code'] >= 300;
			}
		);

		$status = '';
		if ( $filtered_queries ) {
			$status = 'success';
		}
		if ( $failed_queries ) {
			$status = 'error';
		}

		$main_query_status = __( 'No', 'elasticpress' );
		if ( $has_main_query ) {
			$main_query_status = $is_main_query_success ? __( 'Yes', 'elasticpress' ) : __( 'Failed', 'elasticpress' );
		}

		$results   = [];
		$results[] = sprintf(
			/* translators: %s: Yes, Failed, or No */
			__( 'Main query: %s', 'elasticpress' ),
			$main_query_status
		);
		$results[] = sprintf(
			/* translators: %s: Total queries */
			__( 'Total queries: %s', 'elasticpress' ),
			count( $filtered_queries )
		);
		$results[] = sprintf(
			/* translators: %s: Failed queries */
			__( 'Failed queries: %s', 'elasticpress' ),
			count( $failed_queries )
		);

		$results[] = sprintf(
			/* translators: %s: Debugging Article URL */
			__( '<a href="%s">More about debugging</a>', 'elasticpress' ),
			'https://www.elasticpress.io/resources/articles/using-the-elasticpress-debugging-add-on-plugin/'
		);

		$results_output = implode( '<br>', $results );
		$results_output = str_replace( '"', "'", $results_output );

		echo '<script>
			document.addEventListener("DOMContentLoaded", function() {
       			document.getElementById("ep-ab-indicator").classList.add("ep-status-indicator--' . esc_js( $status ) . '");
				document.querySelector("#wp-admin-bar-ep-basic-status-summary .ab-item").innerHTML = "' . wp_kses_post( $results_output ) . '";
    		});
		</script>';
	}

	/**
	 * Whether to display the admin bar status.
	 *
	 * @return bool
	 */
	protected function should_display() {
		/**
		 * Filter whether to display the admin bar status.
		 *
		 * @since 5.3.0
		 * @hook ep_admin_bar_should_display
		 * @param {bool} $should_display Whether to display the admin bar status. Default true.
		 * @return {bool} New should display value
		 */
		return apply_filters( 'ep_admin_bar_should_display', true );
	}
}
