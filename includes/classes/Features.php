<?php
/**
 * Handles registering and storing feature instances
 *
 * @since  2.1
 * @package elasticpress
 */

namespace ElasticPress;

use ElasticPress\Utils as Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class for storing and managing features
 */
class Features {

	/**
	 * Stores all features that have been properly included (both active and inactive)
	 *
	 * @since  2.1
	 * @var array
	 */
	public $registered_features = [];

	/**
	 * Initiate class actions
	 *
	 * @since 2.1
	 */
	public function setup() {
		// hooks order matters, make sure feature activation goes before features setup
		add_action( 'init', array( $this, 'handle_feature_activation' ), 0 );
		add_action( 'init', array( $this, 'setup_features' ), 0 );
	}

	/**
	 * Activate a feature
	 *
	 * @param  string $slug Feature slug
	 * @since  2.2
	 */
	public function activate_feature( $slug ) {
		$this->update_feature( $slug, array( 'active' => true ) );
	}

	/**
	 * Deactivate a feature
	 *
	 * @param  string $slug Feature slug
	 * @param  bool   $force Whether to force deactivation
	 * @since  2.2
	 */
	public function deactivate_feature( $slug, $force = true ) {
		$this->update_feature( $slug, array( 'active' => false ), $force );
	}

	/**
	 * Registers a feature for use in ElasticPress
	 *
	 * @param  Feature $feature An instance of the Feature class
	 * @since  3.0
	 * @return boolean
	 */
	public function register_feature( Feature $feature ) {
		$this->registered_features[ $feature->slug ] = $feature;
		return true;
	}

	/**
	 * Easy access function to get a Feature object from a slug
	 *
	 * @param  string $slug Feature slug
	 * @since  2.1
	 * @return Feature
	 */
	public function get_registered_feature( $slug ) {
		if ( empty( $this->registered_features[ $slug ] ) ) {
			return false;
		}

		return $this->registered_features[ $slug ];
	}

	/**
	 * Activate or deactivate a feature
	 *
	 * @param  string $slug Feature slug
	 * @param  array  $settings Array of settings
	 * @param  bool   $force Whether to force activate/deactivate
	 * @since  2.2
	 * @return array|bool
	 */
	public function update_feature( $slug, $settings, $force = true ) {
		/**
		 * Get the feature being saved.
		 */
		$feature = $this->get_registered_feature( $slug );

		if ( empty( $feature ) ) {
			return false;
		}

		/**
		 * Get whether the feature was already active, and the value of the
		 * setting that requires a reindex, if it exists.
		 */
		$was_active  = $feature->is_active();
		$setting_was = $feature->get_reindex_setting();

		/**
		 * Prepare settings
		 */
		$saved_settings   = Utils\get_option( 'ep_feature_settings', [] );
		$feature_settings = isset( $saved_settings[ $slug ] ) ? $saved_settings[ $slug ] : [ 'force_inactive' => false ];

		$new_feature_settings = $feature->default_settings;
		$new_feature_settings = wp_parse_args( $feature_settings, $new_feature_settings );
		$new_feature_settings = wp_parse_args( $settings, $new_feature_settings );

		$new_feature_settings['active']         = (bool) $new_feature_settings['active'];
		$new_feature_settings['force_inactive'] = $new_feature_settings['active'] ? false : (bool) $new_feature_settings['force_inactive'];

		/**
		 * Flag if the feature was deactivated by a forced update.
		 */
		if ( $force && $was_active && ! $new_feature_settings['active'] ) {
			$new_feature_settings['force_inactive'] = true;
		}

		/**
		 * Save the settings.
		 */
		$new_settings = wp_parse_args( [ $slug => $new_feature_settings ], $saved_settings );
		$new_settings = apply_filters( 'ep_sanitize_feature_settings', $new_settings, $feature );

		Utils\update_option( 'ep_feature_settings', $new_settings );

		/**
		 * Prepare response.
		 */
		$is_active = $new_settings[ $slug ]['active'];

		$data = array(
			'active'  => $is_active,
			'reindex' => false,
			'setting' => '',
		);

		/**
		 * If the feature requires reindexing on activation, return whether
		 * reindexing is required.
		 */
		if ( $is_active && ! $was_active ) {
			if ( ! empty( $feature->requires_install_reindex ) ) {
				$data['reindex'] = true;
			}

			$feature->post_activation();
		}

		/**
		 * If the feature has a setting that requires reindexing, return
		 * whether reindexing is required and the new value of the setting.
		 */
		$setting = $feature->setting_requires_install_reindex;

		if ( $setting ) {
			$setting_is = ! empty( $new_settings[ $slug ][ $setting ] )
				? $new_settings[ $slug ][ $setting ]
				: '';

			$data['setting'] = $setting_is;

			/**
			 * If the setting has changed, a reindex is required.
			 */
			if ( $is_active && $setting_is && $setting_is !== $setting_was ) {
				$data['reindex'] = true;
			}
		}

		/**
		 * Fires after activating, inactivating, or just updating a feature.
		 *
		 * @hook ep_after_update_feature
		 * @param  {string} $feature Feature slug
		 * @param  {array} $settings Feature settings
		 * @param  {array} $data Feature activation data
		 *
		 * @since 3.5.5
		 */
		do_action(
			'ep_after_update_feature',
			$slug,
			$settings,
			$data
		);

		return $data;
	}

	/**
	 * When plugins are adjusted, we need to determine how to activate/deactivate features
	 *
	 * @since 2.2
	 */
	public function handle_feature_activation() {
		/**
		 * Save our current requirement statuses for later
		 */

		$old_requirement_statuses = Utils\get_option( 'ep_feature_requirement_statuses', false );

		$new_requirement_statuses = [];

		foreach ( $this->registered_features as $slug => $feature ) {
			$status                            = $feature->requirements_status();
			$new_requirement_statuses[ $slug ] = (int) $status->code;
		}

		$is_wp_cli = defined( 'WP_CLI' ) && \WP_CLI;

		if ( $is_wp_cli || is_admin() ) {
			Utils\update_option( 'ep_feature_requirement_statuses', $new_requirement_statuses );
		}

		/**
		 * If feature settings aren't created, let's create them and finish
		 */

		$feature_settings = Utils\get_option( 'ep_feature_settings', false );

		if ( false === $feature_settings ) {
			$registered_features = $this->registered_features;

			foreach ( $registered_features as $slug => $feature ) {
				if ( 0 === $feature->requirements_status()->code ) {
					$this->activate_feature( $slug );
				}
			}

			/**
			 * Nothing else to do since we are doing initial activation
			 */
			return;
		}

		/**
		 * If a requirement status changes, we need to handle that by activating/deactivating/showing notification
		 */

		if ( ( $is_wp_cli || is_admin() ) && ! empty( $old_requirement_statuses ) ) {
			foreach ( $new_requirement_statuses as $slug => $code ) {
				$feature = $this->get_registered_feature( $slug );

				// If a feature is forced inactive, do nothing
				$feature_settings = $feature->get_settings();
				if ( is_array( $feature_settings ) && ! empty( $feature_settings['force_inactive'] ) ) {
					continue;
				}

				// This is a new feature
				if ( ! isset( $old_requirement_statuses[ $slug ] ) ) {
					if ( 0 === $code ) {
						$this->activate_feature( $slug );

						if ( $feature->requires_install_reindex ) {
							Utils\update_option( 'ep_feature_auto_activated_sync', sanitize_text_field( $slug ) );
						}
					}
				} else {
					// This feature has a 0 "ok" code when it did not before
					if ( $old_requirement_statuses[ $slug ] !== $code && ( 0 === $code || 2 === $code ) ) {
						$active = ( 0 === $code );

						if ( ! $feature->is_active() && $active ) {
							$this->activate_feature( $slug );

							// Need to activate and maybe set a sync notice
							if ( $feature->requires_install_reindex ) {
								Utils\update_option( 'ep_feature_auto_activated_sync', sanitize_text_field( $slug ) );
							}
						} elseif ( $feature->is_active() && ! $active ) {
							// Just deactivate, don't force
							$this->deactivate_feature( $slug, false );
						}
					}
				}
			}
		}
	}

	/**
	 * Set up all active features
	 *
	 * @since  2.1
	 */
	public function setup_features() {
		/**
		 * Fires before features are setup
		 *
		 * @hook ep_setup_features
		 * @since  2.1
		 */
		do_action( 'ep_setup_features' );

		foreach ( $this->registered_features as $feature_slug => $feature ) {
			if ( $feature->is_active() ) {
				$feature->setup();
			}
		}
	}

	/**
	 * Return singleton instance of class
	 *
	 * @return object
	 * @since 2.1
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
