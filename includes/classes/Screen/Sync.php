<?php
/**
 * Sync (Dashboard Index) functionality
 *
 * @since  3.6.0
 * @package elasticpress
 */

namespace ElasticPress\Screen;

use ElasticPress\Elasticsearch;
use ElasticPress\IndexHelper;
use ElasticPress\REST;
use ElasticPress\Screen;
use ElasticPress\Stats;
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
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
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
			EP_URL . 'dist/js/sync-script.js',
			Utils\get_asset_info( 'sync-script', 'dependencies' ),
			Utils\get_asset_info( 'sync-script', 'version' ),
			true
		);

		wp_set_script_translations( 'ep_sync_scripts', 'elasticpress' );

		wp_enqueue_style( 'wp-components' );
		wp_enqueue_style( 'wp-edit-post' );

		wp_enqueue_style(
			'ep_sync_style',
			EP_URL . 'dist/css/sync-styles.css',
			Utils\get_asset_info( 'sync-styles', 'dependencies' ),
			Utils\get_asset_info( 'sync-styles', 'version' )
		);

		$data       = array( 'nonce' => wp_create_nonce( 'wp_rest' ) );
		$index_meta = Utils\get_indexing_status();
		$last_sync  = Utils\get_option( 'ep_last_sync', false );

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			$install_complete_url = admin_url( 'network/admin.php?page=elasticpress&install_complete' );
		} else {
			$install_complete_url = admin_url( 'admin.php?page=elasticpress&install_complete' );
		}

		if ( isset( $_GET['do_sync'] ) && ( ! defined( 'EP_DASHBOARD_SYNC' ) || EP_DASHBOARD_SYNC ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$data['auto_start_index'] = true;
		}

		if ( ! empty( $index_meta ) ) {
			$data['index_meta'] = $index_meta;
		}

		$ep_last_index = IndexHelper::factory()->get_last_index();

		$indices_comparison = Elasticsearch::factory()->get_indices_comparison();
		$sync_required      = count( $indices_comparison['missing_indices'] ) > 0;

		if ( ! empty( $ep_last_index ) && ! $sync_required ) {
			$data['ep_last_sync_date']   = ! empty( $ep_last_index['end_date_time'] ) ? $ep_last_index['end_date_time'] : false;
			$data['ep_last_sync_failed'] = ! empty( $ep_last_index['failed'] ) || ! empty( $ep_last_index['errors'] ) ? true : false;
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

		$data['api_url']              = rest_url( 'elasticpress/v1/sync' );
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
	 * Register REST API routes.
	 *
	 * @since 5.0.0
	 * @return void
	 */
	public function register_rest_routes() {
		$controller = new REST\Sync();
		$controller->register_routes();
	}
}
