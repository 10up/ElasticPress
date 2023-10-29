<?php
/**
 * Handle upgrades.
 *
 * @since  3.x
 * @package elasticpress
 */

namespace ElasticPress;

use ElasticPress\Features;
use ElasticPress\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Upgrades
 *
 * @package ElasticPress
 */
class Upgrades {

	/**
	 * Store the version number before performing upgrades.
	 * Set in the `setup()` method.
	 *
	 * @var null|string
	 */
	protected $old_version;

	/**
	 * Initialize class
	 */
	public function setup() {
		$this->old_version = Utils\get_option( 'ep_version', false );

		/**
		 * An array with the upgrades routines.
		 * Indexes are the ElasticPress version and values
		 * are an array with the method name and, if needed,
		 * the action name where it should be hooked.
		 */
		$routines = [
			'3.5.2' => [ 'upgrade_3_5_2', 'init' ],
			'3.5.3' => [ 'upgrade_3_5_3', 'init' ],
			'3.6.6' => [ 'upgrade_3_6_6', 'init' ],
			'4.2.2' => [ 'upgrade_4_2_2', 'init' ],
			'4.4.0' => [ 'upgrade_4_4_0', 'init' ],
			'4.5.0' => [ 'upgrade_4_5_0', 'init' ],
			'4.7.0' => [ 'upgrade_4_7_0', 'init' ],
			'5.0.0' => [ 'upgrade_5_0_0', 'init' ],
		];

		array_walk( $routines, [ $this, 'run_upgrade_routine' ] );

		/**
		 * Check if a reindex is needed.
		 */
		add_action( 'plugins_loaded', [ $this, 'check_reindex_needed' ], 5 );

		/**
		 * Update the version number.
		 * Note: if a upgrade routine method is hooked to some action,
		 * this code will be executed *earlier* than the routine method.
		 */
		Utils\update_option( 'ep_version', sanitize_text_field( EP_VERSION ) );

		add_filter( 'ep_admin_notices', [ $this, 'resync_notice_4_0_0_instant_results' ] );
	}

	/**
	 * Run the upgrade routine, if needed.
	 *
	 * @param array  $routine Array with the info about the method to call.
	 *                        If needed to be run in an action, it'll contain the action name.
	 * @param string $version The version number to be tested.
	 */
	protected function run_upgrade_routine( $routine, $version ) {
		if ( version_compare( $this->old_version, $version, '<' ) ) {
			$function_name = $routine[0];
			$action_tag    = $routine[1];
			$priority      = ( ! empty( $routine[2] ) ) ? $routine[2] : 10;
			if ( $action_tag ) {
				add_action( $action_tag, [ $this, $function_name ], $priority );
			} else {
				$this->$function_name();
			}
		}
	}

	/**
	 * Upgrade routine of v3.5.2.
	 *
	 * If weighting options exist and the WooCommerce feature is enabled,
	 * this method will enable the SKU field.
	 */
	public function upgrade_3_5_2() {
		$weighting_options = get_option( 'elasticpress_weighting', [] );
		if ( empty( $weighting_options ) ) {
			return;
		}

		$woocommerce = Features::factory()->get_registered_feature( 'woocommerce' );
		if ( ! $woocommerce->is_active() ) {
			return;
		}

		if ( empty( $weighting_options['product'] ) ) {
			return;
		}

		if ( ! empty( $weighting_options['product']['author_name'] ) ) {
			unset( $weighting_options['product']['author_name'] );
		}

		$weighting_options['product']['meta._sku.value'] = array(
			'enabled' => true,
			'weight'  => 1,
		);

		update_option( 'elasticpress_weighting', $weighting_options );
	}

	/**
	 * Upgrade routine of v3.5.3.
	 *
	 * Check if synonyms post has the correct post type, otherwise,
	 * change it to the correct one.
	 */
	public function upgrade_3_5_3() {
		delete_option( 'elasticpress_synonyms_post_id' );
		delete_site_option( 'elasticpress_synonyms_post_id' );
	}

	/**
	 * Upgrade routine of v3.6.6.
	 *
	 * Delete all synonyms that have the post content identical to the example we set.
	 * In previous versions we had a bug that created several posts with it.
	 *
	 * @see https://github.com/10up/ElasticPress/issues/2516
	 */
	public function upgrade_3_6_6() {
		global $wpdb;

		$synonyms = \ElasticPress\Features::factory()->get_registered_feature( 'search' )->synonyms;

		if ( ! $synonyms ) {
			return;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$synonyms_example_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_content = %s LIMIT 100",
				$synonyms::POST_TYPE_NAME,
				$synonyms->example_synonym_list()
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		if ( ! $synonyms_example_ids ) {
			return;
		}

		foreach ( $synonyms_example_ids as $synonym_post_id ) {
			wp_delete_post( $synonym_post_id, true );
		}
	}

	/**
	 * Upgrade routine of v4.2.2.
	 *
	 * Delete the transient with ES info, so EP is forced to fetch it again,
	 * determining the correct software type (elasticsearch or opensearch, for example)
	 *
	 * @see https://github.com/10up/ElasticPress/issues/2882
	 */
	public function upgrade_4_2_2() {
		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			delete_site_transient( 'ep_es_info' );
		} else {
			delete_transient( 'ep_es_info' );
		}
	}

	/**
	 * Upgrade routine of v4.4.0.
	 *
	 * Delete the ep_prefix option, as that is now obtained via ep_credentials
	 *
	 * @see https://github.com/10up/ElasticPress/issues/2739
	 */
	public function upgrade_4_4_0() {
		Utils\delete_option( 'ep_prefix' );
	}

	/**
	 * Upgrade routine of v4.5.0.
	 *
	 * Add the ElasticPress capability to admins
	 *
	 * @see https://github.com/10up/ElasticPress/pull/3313
	 */
	public function upgrade_4_5_0() {
		setup_roles();
	}

	/**
	 * Upgrade routine of v4.7.0.
	 *
	 * Remove old total_fields_limit transients
	 * Remove cached autosuggest requests
	 *
	 * @see https://github.com/10up/ElasticPress/pull/3552
	 */
	public function upgrade_4_7_0() {
		global $wpdb;

		if ( is_multisite() ) {
			$sites = \get_sites( [ 'number' => 0 ] );
			foreach ( $sites as $site ) {
				$blog_option = get_blog_option( $site->blog_id, 'ep_indexable' );
				if ( $blog_option ) {
					update_site_meta( $site->blog_id, 'ep_indexable', $blog_option );
				}
			}
		}

		$transients = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT option_name
			FROM {$wpdb->prefix}options
			WHERE option_name LIKE '_transient_ep_total_fields_limit_%'"
		);

		foreach ( $transients as $transient ) {
			$transient_name = str_replace( '_transient_', '', $transient );
			delete_site_transient( $transient_name );
			delete_transient( $transient_name );
		}

		if ( function_exists( 'wp_cache_supports' ) && wp_cache_supports( 'flush_group' ) ) {
			wp_cache_flush_group( 'ep_autosuggest' );
		}
		delete_transient( 'ep_autosuggest_query_request_cache' );
	}

	/**
	 * Upgrade routine of v5.0.0.
	 */
	public function upgrade_5_0_0() {
		$features_in_settings = Features::factory()->get_feature_settings();
		foreach ( $features_in_settings as $feature_slug => $feature_settings ) {
			$feature = Features::factory()->get_registered_feature( $feature_slug );
			if ( ! $feature ) {
				continue;
			}

			$settings_schema = $feature->get_settings_schema();
			foreach ( $settings_schema as $setting_schema ) {
				if ( ! isset( $feature_settings[ $setting_schema['key'] ] ) ) {
					continue;
				}

				$value = $feature_settings[ $setting_schema['key'] ];

				if ( ! in_array( $setting_schema['type'], [ 'checkbox', 'radio' ], true ) || ! is_bool( $value ) ) {
					continue;
				}

				$features_in_settings[ $feature_slug ][ $setting_schema['key'] ] = $value ? '1' : '0';
			}
		}
		Utils\update_option( 'ep_feature_settings', $features_in_settings );

		/**
		 * Remove the 'ep_last_index' option and store it as an entry of 'ep_sync_history'
		 */
		$last_sync = Utils\get_option( 'ep_last_index', [] );
		Utils\delete_option( 'ep_last_index' );
		Utils\update_option( 'ep_sync_history', [ $last_sync ] );
	}

	/**
	 * Adjust the upgrade sync notice to warn users about Instant Results.
	 *
	 * As 4.0.0 introduces this new feature and it requires a resync, admin users
	 * might want to enable the feature before the resync (and then resync only once.)
	 *
	 * @since 4.0.0
	 * @param array $notices All admin notices
	 * @return array
	 */
	public function resync_notice_4_0_0_instant_results( $notices ) {
		if ( ! isset( $notices['upgrade_sync'] ) ) {
			return $notices;
		}

		$instant_results = \ElasticPress\Features::factory()->get_registered_feature( 'instant-results' );
		if ( $instant_results->is_active() ) {
			return $notices;
		}

		$feature_status   = $instant_results->requirements_status();
		$appended_message = '';
		if ( 1 >= $feature_status->code ) {
			if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
				$features_url = admin_url( 'network/admin.php?page=elasticpress' );
			} else {
				$features_url = admin_url( 'admin.php?page=elasticpress' );
			}

			$appended_message = wp_kses_post(
				sprintf(
					/* translators: 1: <a> tag (Zendesk article); 2. </a>; 3: <a> tag (link to Features screen); 4. </a>; */
					__( '%1$sInstant Results%2$s is now available in ElasticPress, but requires a re-sync before activation. If you would like to use Instant Results, click %3$shere%4$s to activate the feature and start your sync.', 'elasticpress' ),
					'<a href="https://elasticpress.zendesk.com/hc/en-us/articles/360050447492#instant-results">',
					'</a>',
					'<a href="' . $features_url . '">',
					'</a>'
				)
			);
		} else {
			$appended_message = wp_kses_post(
				sprintf(
					/* translators: 1: <a> tag (Zendesk article about Instant Results); 2. </a>; 3: <a> tag (Zendesk article about self hosted Elasticsearch setups); 4. </a>; */
					__( '%1$sInstant Results%2$s is now available in ElasticPress, but requires a re-sync before activation. If you would like to use Instant Results, since you are not using ElasticPress.io, you will also need to %3$sinstall and configure a PHP proxy%4$s.', 'elasticpress' ),
					'<a href="https://elasticpress.zendesk.com/hc/en-us/articles/360050447492#instant-results">',
					'</a>',
					'<a href="https://elasticpress.zendesk.com/hc/en-us/articles/4413938931853-Considerations-for-self-hosted-Elasticsearch-setups">',
					'</a>'
				)
			);
		}

		$notices['upgrade_sync']['html'] .= '<br><br>' . $appended_message;

		return $notices;
	}

	/**
	 * Check if a reindex is needed based on the version number.
	 */
	public function check_reindex_needed() {
		if ( ! is_admin() || defined( 'DOING_AJAX' ) ) {
			return;
		}

		$last_sync = Utils\get_option( 'ep_last_sync', 'never' );

		// No need to upgrade since we've never synced.
		if ( empty( $last_sync ) || 'never' === $last_sync ) {
			return;
		}

		/**
		 * Reindex if we cross a reindex version in the upgrade
		 */
		$reindex_versions = apply_filters(
			'ep_reindex_versions',
			array(
				'2.2',
				'2.3.1',
				'2.4',
				'2.5.1',
				'2.6',
				'2.7',
				'3.0',
				'3.1',
				'3.3',
				'3.4',
				'3.6.0',
				'3.6.1',
				'4.0.0-beta.1',
				'4.0.0',
			)
		);

		$need_upgrade_sync = false;

		if ( false !== $this->old_version ) {
			$last_reindex_version = $reindex_versions[ count( $reindex_versions ) - 1 ];

			if ( -1 === version_compare( $this->old_version, $last_reindex_version ) && 0 <= version_compare( EP_VERSION, $last_reindex_version ) ) {
				$need_upgrade_sync = true;
			}
		}

		if ( $need_upgrade_sync ) {
			Utils\update_option( 'ep_need_upgrade_sync', true );
		}
	}

	/**
	 * Return singleton instance of class
	 *
	 * @return self
	 */
	public static function factory() {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new self();
			$instance->setup();
		}

		return $instance;
	}
}
