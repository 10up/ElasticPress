<?php
/**
 * Handles registering and storing feature instances
 *
 * @since  2.1
 * @package elasticpress
 */

namespace ElasticPress;

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
	 * Dectivate a feature
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
		$feature_args['slug'] = $feature->slug;

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
		$feature = $this->get_registered_feature( $slug );

		if ( empty( $feature ) ) {
			return false;
		}

		$original_state = $feature->is_active();

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			$feature_settings = get_site_option( 'ep_feature_settings', [] );
		} else {
			$feature_settings = get_option( 'ep_feature_settings', [] );
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

		if ( $feature_settings[ $slug ]['active'] ) {
			$feature_settings[ $slug ]['force_inactive'] = false;
		}

		// This means someone has explicitly deactivated the feature
		if ( $force ) {
			if ( ! (bool) $settings['active'] && $original_state ) {
				$feature_settings[ $slug ]['force_inactive'] = true;
			}
		}

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

		$new_requirement_statuses = [];

		foreach ( $this->registered_features as $slug => $feature ) {
			$status                            = $feature->requirements_status();
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

		if ( ! empty( $old_requirement_statuses ) ) {
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
							$this->activate_feature( $slug );

							// Need to activate and maybe set a sync notice
							if ( $feature->requires_install_reindex ) {
								if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
									update_site_option( 'ep_feature_auto_activated_sync', sanitize_text_field( $slug ) );
								} else {
									update_option( 'ep_feature_auto_activated_sync', sanitize_text_field( $slug ) );
								}
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
