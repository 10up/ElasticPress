<?php
/**
 * Sync (Dashboard Index) functionality
 *
 * @since  3.6.0
 * @package elasticpress
 */

namespace ElasticPress\Screen;

use ElasticPress\IndexHelper;
use ElasticPress\Screen;
use ElasticPress\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Sync
 *
 * @since  3.6.0
 * @package ElasticPress
 */
class Sync {
	/**
	 * Initialize class
	 */
	public function setup() {
		add_action( 'wp_ajax_ep_index', [ $this, 'action_wp_ajax_ep_index' ] );
		add_action( 'wp_ajax_ep_index_status', [ $this, 'action_wp_ajax_ep_index_status' ] );
		add_action( 'wp_ajax_ep_cancel_index', [ $this, 'action_wp_ajax_ep_cancel_index' ] );

		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
	}

	/**
	 * Getting the status of ongoing index fired by WP CLI
	 *
	 * @since  3.6.0
	 */
	public function action_wp_ajax_ep_index_status() {
		if ( ! check_ajax_referer( 'ep_dashboard_nonce', 'nonce', false ) || ! EP_DASHBOARD_SYNC ) {
			wp_send_json_error( null, 403 );
			exit;
		}

		$index_meta = Utils\get_indexing_status();

		if ( isset( $index_meta['method'] ) && 'cli' === $index_meta['method'] ) {
			wp_send_json_success(
				[
					'message'    => sprintf(
						/* translators: 1. Number of objects indexed, 2. Total number of objects, 3. Last object ID */
						esc_html__( 'Processed %1$d/%2$d. Last Object ID: %3$d', 'elasticpress' ),
						$index_meta['offset'],
						$index_meta['found_items'],
						$index_meta['current_sync_item']['last_processed_object_id']
					),
					'index_meta' => $index_meta,
				]
			);
		}

		wp_send_json_success(
			[
				'is_finished' => true,
				'totals'      => Utils\get_option( 'ep_last_index' ),
			]
		);
	}

	/**
	 * Perform index
	 *
	 * @since 3.6.0
	 */
	public function action_wp_ajax_ep_index() {
		if ( ! check_ajax_referer( 'ep_dashboard_nonce', 'nonce', false ) || ! EP_DASHBOARD_SYNC ) {
			wp_send_json_error( null, 403 );
			exit;
		}

		$index_meta = Utils\get_indexing_status();

		if ( isset( $index_meta['method'] ) && 'cli' === $index_meta['method'] ) {
			$this->action_wp_ajax_ep_index_status();
			exit;
		}

		IndexHelper::factory()->full_index(
			[
				'method'        => 'dashboard',
				'put_mapping'   => ! empty( $_REQUEST['put_mapping'] ),
				'output_method' => [ $this, 'index_output' ],
				'show_errors'   => true,
				'network_wide'  => 0,
			]
		);
	}

	/**
	 * Cancel index
	 *
	 * @since 3.6.0
	 */
	public function action_wp_ajax_ep_cancel_index() {
		if ( ! check_ajax_referer( 'ep_dashboard_nonce', 'nonce', false ) || ! EP_DASHBOARD_SYNC ) {
			wp_send_json_error( null, 403 );
			exit;
		}

		$index_meta = Utils\get_indexing_status();

		if ( isset( $index_meta['method'] ) && 'cli' === $index_meta['method'] ) {
			set_transient( 'ep_wpcli_sync_interrupted', true, MINUTE_IN_SECONDS );
			wp_send_json_success();
			exit;
		}

		Utils\delete_option( 'ep_index_meta' );

		wp_send_json_success();
	}

	/**
	 * Enqueue script.
	 *
	 * @since 3.6.0
	 * @return void
	 */
	public function admin_enqueue_scripts() {
		if ( 'sync' !== Screen::factory()->get_current_screen() ) {
			return;
		}

		wp_enqueue_script(
			'ep_sync_scripts',
			EP_URL . 'dist/js/sync-script.min.js',
			Utils\get_asset_info( 'sync-script', 'dependencies' ),
			Utils\get_asset_info( 'sync-script', 'version' ),
			true
		);

		wp_enqueue_style( 'wp-components' );
		wp_enqueue_style( 'wp-edit-post' );

		wp_enqueue_style(
			'ep_sync_style',
			EP_URL . 'dist/css/sync-styles.min.css',
			Utils\get_asset_info( 'sync-styles', 'dependencies' ),
			Utils\get_asset_info( 'sync-styles', 'version' )
		);

		$data       = array( 'nonce' => wp_create_nonce( 'ep_dashboard_nonce' ) );
		$index_meta = Utils\get_indexing_status();

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			$install_complete_url = admin_url( 'network/admin.php?page=elasticpress&install_complete' );
			$last_sync            = get_site_option( 'ep_last_sync', false );
		} else {
			$install_complete_url = admin_url( 'admin.php?page=elasticpress&install_complete' );
			$last_sync            = get_option( 'ep_last_sync', false );
		}

		if ( isset( $_GET['do_sync'] ) && ( ! defined( 'EP_DASHBOARD_SYNC' ) || EP_DASHBOARD_SYNC ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$data['auto_start_index'] = true;
		}

		if ( ! empty( $index_meta ) ) {
			$data['index_meta'] = $index_meta;
		}

		$ep_last_index = IndexHelper::factory()->get_last_index();

		if ( ! empty( $ep_last_index ) ) {
			$data['ep_last_sync_date']   = ! empty( $ep_last_index['end_date_time'] ) ? $ep_last_index['end_date_time'] : false;
			$data['ep_last_sync_failed'] = ! empty( $ep_last_index['failed'] ) ? true : false;
		}

		/**
		 * Filter indexable labels used in dashboard sync UI
		 *
		 * @since  3.0
		 * @hook ep_dashboard_indexable_labels
		 * @param  {array} $labels Current indexable lables
		 * @return {array} New labels
		 */
		$data['sync_indexable_labels'] = apply_filters(
			'ep_dashboard_indexable_labels',
			[
				'post'    => [
					'singular' => esc_html__( 'Post', 'elasticpress' ),
					'plural'   => esc_html__( 'Posts', 'elasticpress' ),
				],
				'term'    => [
					'singular' => esc_html__( 'Term', 'elasticpress' ),
					'plural'   => esc_html__( 'Terms', 'elasticpress' ),
				],
				'user'    => [
					'singular' => esc_html__( 'User', 'elasticpress' ),
					'plural'   => esc_html__( 'Users', 'elasticpress' ),
				],
				'comment' => [
					'singular' => esc_html__( 'Comment', 'elasticpress' ),
					'plural'   => esc_html__( 'Comments', 'elasticpress' ),
				],
			]
		);

		$data['ajax_url']             = admin_url( 'admin-ajax.php' );
		$data['install_sync']         = empty( $last_sync );
		$data['install_complete_url'] = esc_url( $install_complete_url );
		$data['sync_complete']        = esc_html__( 'Sync complete', 'elasticpress' );
		$data['sync_paused']          = esc_html__( 'Sync paused', 'elasticpress' );
		$data['sync_syncing']         = esc_html__( 'Syncing', 'elasticpress' );
		$data['sync_initial']         = esc_html__( 'Starting sync', 'elasticpress' );
		$data['sync_wpcli']           = esc_html__( 'WP CLI sync is occurring.', 'elasticpress' );
		$data['sync_error']           = esc_html__( 'An error occurred while syncing', 'elasticpress' );
		$data['sync_interrupted']     = esc_html__( 'Sync interrupted.', 'elasticpress' );
		$data['is_epio']              = Utils\is_epio();

		wp_localize_script( 'ep_sync_scripts', 'epDash', $data );
	}

	/**
	 * Output information received from the index helper class.
	 *
	 * @param array $message Message to be outputted with its status and additional info, if needed.
	 */
	public static function index_output( $message ) {
		switch ( $message['status'] ) {
			case 'success':
				wp_send_json_success( $message );
				break;

			case 'error':
				wp_send_json_error( $message );
				break;

			default:
				wp_send_json( [ 'data' => $message ] );
				break;
		}
		exit;
	}
}
