<?php
/**
 * Dashboard screen class.
 *
 * @since 5.0.0
 * @package elasticpress
 */

namespace ElasticPress\Screen;

use ElasticPress\Features as FeaturesStore;
use ElasticPress\REST;
use ElasticPress\Screen;
use ElasticPress\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Dashboard screen.
 *
 * @since 5.0.0
 * @package ElasticPress
 */
class Features {
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
		if ( 'dashboard' !== Screen::factory()->get_current_screen() ) {
			return;
		}

		wp_enqueue_script(
			'ep_features_script',
			EP_URL . 'dist/js/features-script.js',
			Utils\get_asset_info( 'features-script', 'dependencies' ),
			Utils\get_asset_info( 'features-script', 'version' ),
			true
		);

		wp_set_script_translations( 'ep_features_script', 'elasticpress' );

		wp_enqueue_style(
			'ep_features_script',
			EP_URL . 'dist/css/features-script.css',
			[ 'wp-components', 'wp-edit-post' ],
			Utils\get_asset_info( 'features-script', 'version' )
		);

		$store = FeaturesStore::factory();

		$features = $store->registered_features;
		$features = array_map( fn( $f ) => $f->get_json(), $features );
		$features = array_values( $features );

		$sync_url = ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) ?
				network_admin_url( 'admin.php?page=elasticpress-sync' ) :
				admin_url( 'admin.php?page=elasticpress-sync' );

		$data = [
			'apiUrl'        => rest_url( 'elasticpress/v1/features' ),
			'epioLogoUrl'   => esc_url( plugins_url( '/images/logo-elasticpress-io.svg', EP_FILE ) ),
			'features'      => $features,
			'indexMeta'     => Utils\get_indexing_status(),
			'settings'      => $store->get_feature_settings(),
			'settingsDraft' => $store->get_feature_settings_draft(),
			'syncUrl'       => $sync_url,
		];

		wp_localize_script( 'ep_features_script', 'epDashboard', $data );
	}

	/**
	 * Register REST API routes.
	 *
	 * @return void
	 */
	public function register_rest_routes() {
		$controller = new REST\Features();
		$controller->register_routes();
	}
}
