<?php
/**
 * Handles registering and storing feature instances
 *
 * @since  2.1
 * @package elasticpress
 */

namespace ElasticPress;

use ElasticPress\Utils;

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
	 * Get all registered feature groups.
	 *
	 * This centralizes group definitions and allows extensibility via a filter.
	 *
	 * @since 5.3.0
	 * @return array Array of group slugs and their labels.
	 */
	public function get_feature_groups() {
		$groups = [
			'core-search'         => [
				'label' => esc_html__( 'Core Search', 'elasticpress' ),
			],
			'live-search'         => [
				'label' => esc_html__( 'Live Search', 'elasticpress' ),
			],
			'indexing-options'    => [
				'label' => esc_html__( 'Indexing Options', 'elasticpress' ),
			],
			'woocommerce'         => [
				'label' => esc_html__( 'WooCommerce', 'elasticpress' ),
			],
			'third-party-plugins' => [
				'label' => esc_html__( 'Third Party Plugins', 'elasticpress' ),
			],
		];
		/**
		 * Filter available groups.
		 *
		 * @hook ep_feature_groups
		 * @since 5.3.0
		 * @param  {array} $groups Current groups
		 * @return {array} New groups
		 */
		return apply_filters( 'ep_feature_groups', $groups );
	}

	/**
	 * Activate a feature
	 *
	 * @param string $slug   Feature slug
	 * @param string $target Whether to update a feature settings' draft or current
	 * @since 2.2, 5.0.0 added $target
	 */
	public function activate_feature( $slug, $target = 'current' ) {
		$this->update_feature( $slug, array( 'active' => true ), true, $target );
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
	 * @param  string $slug     Feature slug
	 * @param  array  $settings Array of settings
	 * @param  bool   $force    Whether to force activate/deactivate
	 * @param  string $target   Whether to update a feature settings' draft or current. Changing current will also save the draft.
	 * @since  2.2, 5.0.0 added $target
	 * @return array|bool
	 */
	public function update_feature( $slug, $settings, $force = true, $target = 'current' ) {
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
		$saved_settings   = 'draft' === $target ? $this->get_feature_settings_draft() : $this->get_feature_settings();
		$feature_settings = isset( $saved_settings[ $slug ] ) ? $saved_settings[ $slug ] : [ 'force_inactive' => false ];

		$new_feature_settings = wp_parse_args(
			$feature->default_settings,
			[
				'active'         => false,
				'force_inactive' => false,
			]
		);
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

		Utils\update_option( 'ep_feature_settings_draft', $new_settings );

		// This is as far as we go if saving just a draft
		if ( 'draft' === $target ) {
			return true;
		}

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

		if ( ( ! $is_wp_cli && ! is_admin() ) || empty( $old_requirement_statuses ) ) {
			return;
		}

		foreach ( $new_requirement_statuses as $slug => $code ) {
			$feature = $this->get_registered_feature( $slug );

			// If a feature is forced inactive, do nothing
			$feature_settings = $feature->get_settings();
			if ( is_array( $feature_settings ) && ! empty( $feature_settings['force_inactive'] ) ) {
				continue;
			}

			// By default we will activate the feature in the current settings. If it requires a sync, we'll only update the draft
			$activate_feature_target = 'current';

			// This is a new feature
			if ( ! isset( $old_requirement_statuses[ $slug ] ) ) {
				if ( 0 === $code ) {
					if ( $feature->requires_install_reindex ) {
						$activate_feature_target = 'draft';
						Utils\update_option( 'ep_feature_auto_activated_sync', sanitize_text_field( $slug ) );
					}

					$this->activate_feature( $slug, $activate_feature_target );
				}
			} elseif ( $old_requirement_statuses[ $slug ] !== $code && ( 0 === $code || 2 === $code ) ) {
				// This feature has a 0 "ok" code when it did not before
				$active = ( 0 === $code );

				if ( ! $feature->is_active() && $active ) {
					// Need to activate and maybe set a sync notice
					if ( $feature->requires_install_reindex ) {
						$activate_feature_target = 'draft';
						Utils\update_option( 'ep_feature_auto_activated_sync', sanitize_text_field( $slug ) );
					}

					$this->activate_feature( $slug, $activate_feature_target );
				} elseif ( $feature->is_active() && ! $active ) {
					// Just deactivate, don't force
					$this->deactivate_feature( $slug, false );
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

		foreach ( $this->registered_features as $feature ) {
			$feature->set_i18n_strings();

			$required_features            = (array) $feature->get_required_feature();
			$are_required_features_active = true;
			foreach ( $required_features as $required_feature ) {
				if ( ! $this->get_registered_feature( $required_feature )->is_active() ) {
					$are_required_features_active = false;
					break;
				}
			}

			/**
			 * 2 is the code for "not usable".
			 *
			 * @see FeatureRequirementsStatus
			 */
			$should_setup = $feature->is_active() && $are_required_features_active && 2 !== $feature->requirements_status()->code;

			/**
			 * Filter whether the feature should be setup.
			 *
			 * @since 5.3.0
			 * @hook ep_should_setup_feature
			 * @param {bool} $should_setup Whether the feature should be setup.
			 * @param {Feature} $feature The feature object.
			 * @return {bool} New should_setup value.
			 */
			if ( apply_filters( 'ep_should_setup_feature', $should_setup, $feature ) ) {
				$feature->setup();
			}
		}
	}

	/**
	 * Return current features settings
	 *
	 * @since 5.0.0
	 * @return false|array
	 */
	public function get_feature_settings() {
		return Utils\get_option( 'ep_feature_settings', false );
	}

	/**
	 * Get features settings draft
	 *
	 * @since 5.0.0
	 * @return false|array
	 */
	public function get_feature_settings_draft() {
		return Utils\get_option( 'ep_feature_settings_draft', false );
	}

	/**
	 * Apply settings draft (if present)
	 *
	 * @since 5.0.0
	 */
	public function apply_draft_feature_settings() {
		$draft_settings = Utils\get_option( 'ep_feature_settings_draft', false );
		if ( ! $draft_settings ) {
			return;
		}

		foreach ( $draft_settings as $feature => $settings ) {
			$this->update_feature( $feature, $settings );
		}
		$this->setup_features();

		Utils\delete_option( 'ep_feature_settings_draft' );
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
