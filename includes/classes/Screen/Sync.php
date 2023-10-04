<?php
/**
 * Sync (Dashboard Index) functionality
 *
 * @since  3.6.0
 * @package elasticpress
 */

namespace ElasticPress\Screen;

use ElasticPress\Elasticsearch;
use ElasticPress\Indexables;
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

		wp_enqueue_style(
			'ep_sync_style',
			EP_URL . 'dist/css/sync-script.css',
			[ 'wp-components', 'wp-edit-post' ],
			Utils\get_asset_info( 'sync-script', 'version' )
		);

		$indexables = Indexables::factory()->get_all();

		$indices_comparison = Elasticsearch::factory()->get_indices_comparison();
		$indices_missing    = count( $indices_comparison['missing_indices'] ) > 0;

		$post_types = Indexables::factory()->get( 'post' )->get_indexable_post_types();
		$post_types = array_values( $post_types );

		$sync_history = ! $indices_missing ? IndexHelper::factory()->get_sync_history() : [];

		$data = [
			'apiUrl'      => rest_url( 'elasticpress/v1/sync' ),
			'autoIndex'   => isset( $_GET['do_sync'] ) && ( ! defined( 'EP_DASHBOARD_SYNC' ) || EP_DASHBOARD_SYNC ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'indexMeta'   => Utils\get_indexing_status(),
			'indexables'  => array_map( fn( $indexable) => [ $indexable->slug, $indexable->labels['plural'] ], $indexables ),
			'isEpio'      => Utils\is_epio(),
			'nonce'       => wp_create_nonce( 'wp_rest' ),
			'postTypes'   => array_map( fn( $post_type ) => [ $post_type, get_post_type_object( $post_type )->labels->name ], $post_types ),
			'syncHistory' => $sync_history,
		];

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
