<?php
/**
 * Feature loader
 *
 * @since  2.1
 * @package elasticpress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class EP_Features {

	/**
	 * Stores all features that have been properly included (both active and inactive)
	 * 
	 * @since  2.1
	 * @var array
	 */
	public $registered_features = array();

	/**
	 * Initiate class actions
	 * 
	 * @since 2.1
	 */
	public function setup() {
		add_action( 'plugins_loaded', array( $this, 'handle_feature_activation' ), 12 );
		add_action( 'plugins_loaded', array( $this, 'setup_features' ), 11 );
	}

	/**
	 * Registers a feature for use in ElasticPress
	 * 
	 * @param  string $slug
	 * @param  array  $feature_args
	 * 
	 *         Supported array parameters:
	 *         
	 *         "title" (string) - Pretty title for feature
	 *         "default_settings" (array) - Array of default settings. Only needed if you plan on adding custom settings
	 *         "requirements_status_cb" (callback) - Callback to a function that determines the "requirements" status of
	 *         		the given feature. 0 means everything is okay. 1 means the feature can be used but there is a warning. 
	 *         		2 means the feature cannot be active. This callback needs to return an EP_Feature_Requirements_Status 
	 *         		object where the "code" property is one of the values above.
	 *         "setup_cb" (callback) - Callback to a function to be called on each page load when the feature is activated
	 *         "post_activation_cb" (callback) - Callback to a function to be called after a feature is first activated
	 *         "feature_box_summary_cb" (callback) - Callback to a function that outputs HTML feature box summary (short description of feature)
	 *         "feature_box_long_cb" (callback) - Callback to a function that outputs HTML feature box full description
	 *         "feature_box_settings_cb" (callback) - Callback to a function that outputs custom feature settings fields
	 * 
	 * @since  2.1 
	 * @return boolean
	 */
	public function register_feature( $slug, $feature_args ) {
		if ( empty( $slug ) || empty( $feature_args ) || ! is_array( $feature_args ) ) {
			return false;
		}

		$feature_args['slug'] = $slug;

		$this->registered_features[$slug] = new EP_Feature( $feature_args );

		return true;
	}

	/**
	 * Activate or deactivate a feature
	 * 
	 * @param  string  $slug
	 * @param  array   $settings
	 * @since  2.2
	 * @return array|bool
	 */
	public function update_feature( $slug, $settings ) {
		$feature = ep_get_registered_feature( $slug );

		if ( empty( $feature ) ) {
			return false;
		}
		
		$original_state = $feature->is_active();

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			$feature_settings = get_site_option( 'ep_feature_settings', array() );
		} else {
			$feature_settings = get_option( 'ep_feature_settings', array() );
		}

		if ( empty( $feature_settings[ $slug ] ) ) {
			// If doesn't exist, merge with feature defaults
			$feature_settings[ $slug ] = wp_parse_args( $settings, $feature->default_settings );
		} else {
			// If exist just merge changed values into current
			$feature_settings[ $slug ] = wp_parse_args( $settings, $feature_settings[ $slug ] );
		}

		// Make sure active is a proper bool
		$feature_settings[ $slug ]['active'] = (bool) $feature_settings[ $slug ]['active'];

		$sanitize_feature_settings = apply_filters( 'ep_sanitize_feature_settings', $feature_settings, $feature );

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			update_site_option( 'ep_feature_settings', $sanitize_feature_settings );
		} else {
			update_option( 'ep_feature_settings', $sanitize_feature_settings );
		}

		$data = array(
			'reindex' => false,
		);

		if ( $feature_settings[ $slug ]['active'] && ! $original_state ) {
			if ( ! empty( $feature->requires_install_reindex ) ) {
				$data['reindex'] = true;
			}

			$feature->post_activation();
		}

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

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			$old_requirement_statuses = get_site_option( 'ep_feature_requirement_statuses', false );
		} else {
			$old_requirement_statuses = get_option( 'ep_feature_requirement_statuses', false );
		}

		$new_requirement_statuses = array();

		foreach ( $this->registered_features as $slug => $feature ) {
			$status = $feature->requirements_status();
			$new_requirement_statuses[ $slug ] = (int) $status->code;
		}

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			update_site_option( 'ep_feature_requirement_statuses', $new_requirement_statuses );
		} else {
			update_option( 'ep_feature_requirement_statuses', $new_requirement_statuses );
		}

		/**
		 * If feature settings aren't created, let's create them and finish
		 */

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			$feature_settings = get_site_option( 'ep_feature_settings', false );
		} else {
			$feature_settings = get_option( 'ep_feature_settings', false );
		}

		if ( false === $feature_settings ) {
			$registered_features = $this->registered_features;
			
			foreach ( $registered_features as $slug => $feature ) {
				if ( 0 === $feature->requirements_status()->code ) {
					ep_activate_feature( $slug );
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

		if ( ! empty( $old_requirement_statuses ) ) {
			foreach ( $new_requirement_statuses as $slug => $code ) {
				$feature = ep_get_registered_feature( $slug );

				// This is a new feature
				if ( ! isset( $old_requirement_statuses[ $slug ] ) ) {
					if ( 0 === $code ) {
						ep_activate_feature( $slug );

						if ( $feature->requires_install_reindex ) {
							if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
								update_site_option( 'ep_feature_auto_activated_sync', sanitize_text_field( $slug ) );
							} else {
								update_option( 'ep_feature_auto_activated_sync', sanitize_text_field( $slug ) );
							}
						}
					}
				} else {
					// This feature has a 0 "ok" code when it did not before
					if ( $old_requirement_statuses[ $slug ] !== $code && ( 0 === $code || 2 === $code ) ) {
						$active = ( 0 === $code );

						if ( ! $feature->is_active() && $active ) {
							ep_activate_feature( $slug );

							// Need to activate and maybe set a sync notice
							if ( $feature->requires_install_reindex ) {
								if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
									update_site_option( 'ep_feature_auto_activated_sync', sanitize_text_field( $slug ) );
								} else {
									update_option( 'ep_feature_auto_activated_sync', sanitize_text_field( $slug ) );
								}
							}
						} elseif ( $feature->is_active() && ! $active ) {
							// Just deactivate
							ep_deactivate_feature( $slug );
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

		if ( ! $instance  ) {
			$instance = new self();
			$instance->setup();
		}

		return $instance;
	}
}

EP_Features::factory();

/**
 * Main function for registering new feature. Since comment above for details
 * 
 * @param  string $slug
 * @param  array $feature_args
 * @since  2.1
 * @return bool
 */
function ep_register_feature( $slug, $feature_args ) {
	return EP_Features::factory()->register_feature( $slug, $feature_args );
}

/**
 * Update a feature
 * 
 * @param  string $slug
 * @param  array $settings
 * @since  2.2
 * @return array
 */
function ep_update_feature( $slug, $settings ) {
	return EP_Features::factory()->update_feature( $slug, $settings );
}

/**
 * Activate a feature
 * 
 * @param  string $slug
 * @param  bool   $active
 * @since  2.2
 */
function ep_activate_feature( $slug ) {
	EP_Features::factory()->update_feature( $slug, array( 'active' => true ) );
}

/**
 * Dectivate a feature
 * 
 * @param  string $slug
 * @since  2.2
 */
function ep_deactivate_feature( $slug ) {
	EP_Features::factory()->update_feature( $slug, array( 'active' => false ) );
}

/**
 * Easy access function to get a EP_Feature object from a slug
 * @param  string $slug
 * @since  2.1
 * @return EP_Feature
 */
function ep_get_registered_feature( $slug ) {
	if ( empty( EP_Features::factory()->registered_features[$slug] ) ) {
		return false;
	}
	return EP_Features::factory()->registered_features[$slug];
}
