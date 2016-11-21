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
		add_action( 'plugins_loaded', array( $this, 'setup_features' ) );
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
	 * Set up all active features
	 *
	 * @since  2.1
	 */
	public function setup_features() {
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

/**
 * All features that meet their requirements with no warnings should be activated.
 *
 * @since 2.2
 */
function ep_auto_activate_features() {
	if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
		$feature_settings = get_site_option( 'ep_feature_settings', false );
	} else {
		$feature_settings = get_option( 'ep_feature_settings', false );
	}
	
	if ( false === $feature_settings ) {
		$registered_features = EP_Features::factory()->registered_features;
		
		foreach ( $registered_features as $slug => $feature ) {
			if ( 0 === $feature->requirements_status()->code ) {
				$feature_settings[ $slug ] = ( ! empty( $feature->default_settings ) ) ? $feature->default_settings : array();
				$feature_settings[ $slug ]['active'] = true;
				
				$feature->post_activation();
			}
		}
		
		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			update_site_option( 'ep_feature_settings', $feature_settings );
			delete_site_option( 'ep_index_meta' );
		} else {
			update_option( 'ep_feature_settings', $feature_settings );
			delete_option( 'ep_index_meta' );
		}
	}
}
