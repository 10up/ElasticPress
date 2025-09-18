<?php
/**
 * Feature class to be initiated for all features.
 *
 * All features extend this class.
 *
 * @since  2.1
 * @package elasticpress
 */

namespace ElasticPress;

use ElasticPress\FeatureRequirementsStatus;
use ElasticPress\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Feature abstract class
 */
abstract class Feature {
	/**
	 * Feature slug
	 *
	 * @var string
	 * @since  2.1
	 */
	public $slug;

	/**
	 * Feature pretty title
	 *
	 * @var string
	 * @since  2.1
	 */
	public $title;

	/**
	 * Short title
	 *
	 * @var string
	 * @since 4.4.1
	 */
	public $short_title;

	/**
	 * Feature summary
	 *
	 * @var string
	 * @since  4.0.0
	 */
	public $summary;

	/**
	 * URL to feature documentation.
	 *
	 * @var string
	 * @since  4.0.0
	 */
	public $docs_url;

	/**
	 * Optional feature default settings
	 *
	 * @since  2.2
	 * @var  array
	 */
	public $default_settings = [];

	/**
	 * True if the feature requires content reindexing after activating
	 *
	 * @since 2.1
	 * @var bool
	 */
	public $requires_install_reindex = false;

	/**
	 * The slug of a setting that requires content reindexing after activating.
	 *
	 * @since 4.5.0
	 * @var string
	 */
	public $setting_requires_install_reindex = '';

	/**
	 * The order in the features screen
	 *
	 * @var int
	 * @since  3.6.0
	 */
	public $order;

	/**
	 * Set if a feature should be on the left or right side
	 *
	 * @var string
	 * @since  3.6.0
	 */
	public $group_order;

	/**
	 * True if activation of this feature should be available during
	 * installation.
	 *
	 * @since 4.0.0
	 * @var boolean
	 */
	public $available_during_installation = false;

	/**
	 * Whether the feature should be always visible in the dashboard
	 *
	 * @since 4.5.0
	 * @var boolean
	 */
	protected $is_visible = true;

	/**
	 * Settings description
	 *
	 * @since 5.0.0
	 * @var array
	 */
	protected $settings_schema = [];

	/**
	 * The slug, or array of slugs, of a feature that is required to be active.
	 *
	 * @since 5.0.0
	 * @var false|string|array
	 */
	protected $requires_feature = false;

	/**
	 * Whether the feature is using ElasticPress.io.
	 *
	 * @since 5.0.0
	 * @var boolean
	 */
	protected $is_powered_by_epio = false;

	/**
	 * The name of a group that a feature may belong to.
	 *
	 * @since 5.3.0
	 * @var false|string
	 */
	public $group = false;

	/**
	 * Field groups available to a feature
	 *
	 * @since 5.3.0
	 * @var array
	 */
	protected $field_group_map = [];

	/**
	 * Run on every page load for feature to set itself up
	 *
	 * @since  2.1
	 */
	abstract public function setup();

	/**
	 * Create feature
	 *
	 * @since  3.0
	 */
	public function __construct() {
		/**
		 * Fires when Feature object is created
		 *
		 * @hook ep_feature_create
		 * @param {Feature} $feature Current feature
		 * @since  3.0
		 */
		do_action( 'ep_feature_create', $this );
	}

	/**
	 * Returns requirements status of feature
	 *
	 * @since  2.2
	 * @return FeatureRequirementsStatus
	 */
	public function requirements_status() {
		$status = new FeatureRequirementsStatus( 0 );

		/**
		 * Filter feature requirement status
		 *
		 * @hook ep_{indexable_slug}_index_kill
		 * @param  {FeatureRequirementStatus} $status Current feature requirement status
		 * @param {Feature} $feature Current feature
		 * @since  2.2
		 * @return {FeatureRequirementStatus}  New status
		 */
		return apply_filters( 'ep_feature_requirements_status', $status, $this );
	}

	/**
	 * Return feature settings
	 *
	 * @since  2.2.1, 4.5.0 started using default settings
	 * @return array
	 */
	public function get_settings() {
		$all_settings = Utils\get_option( 'ep_feature_settings', [] );

		$feature_settings = ( ! empty( $all_settings[ $this->slug ] ) ) ? (array) $all_settings[ $this->slug ] : [];

		$feature_settings = wp_parse_args( $feature_settings, $this->default_settings );

		return $feature_settings;
	}

	/**
	 * Return a specific setting of the feature
	 *
	 * @since 4.5.0
	 * @param string $setting_name The setting name
	 * @return mixed
	 */
	public function get_setting( string $setting_name ) {
		$settings = $this->get_settings();

		return isset( $settings[ $setting_name ] ) ? $settings[ $setting_name ] : null;
	}

	/**
	 * Returns true if feature is active
	 *
	 * @since  2.2
	 * @return boolean
	 */
	public function is_active() {
		$feature_settings = Utils\get_option( 'ep_feature_settings', [] );

		$active = false;

		if ( ! empty( $feature_settings[ $this->slug ] ) && $feature_settings[ $this->slug ]['active'] ) {
			$active = true;
		}

		/**
		 * Filter whether a feature is active or not
		 *
		 * @hook ep_feature_active
		 * @param  {bool} $active Whether feature is active or not
		 * @param {array} $feature_settings Current feature settings
		 * @param  {Feature} $feature Current feature
		 * @since  2.2
		 * @return {bool}  New active value
		 */
		return apply_filters( 'ep_feature_active', $active, $feature_settings, $this );
	}

	/**
	 * Get the value of the setting that requires a reindex, if it exists.
	 *
	 * @since 4.5.0
	 * @return mixed
	 */
	public function get_reindex_setting() {
		$settings = $this->get_settings();
		$setting  = $this->setting_requires_install_reindex;

		return $settings && $setting && ! empty( $settings[ $setting ] )
			? $settings[ $setting ]
			: '';
	}

	/**
	 * To be run after initial feature activation
	 *
	 * @since 2.1
	 */
	public function post_activation() {
		/**
		 * Fires after feature is activated
		 *
		 * @hook ep_feature_post_activation
		 * @param  {string} $slug Feature slug
		 * @param {Feature} $feature Current feature
		 * @since  2.1
		 */
		do_action( 'ep_feature_post_activation', $this->slug, $this );
	}

	/**
	 * Returns the feature title.
	 *
	 * @since 4.4.1
	 * @return string
	 */
	public function get_title(): string {
		return $this->title;
	}

	/**
	 * Returns the feature short title.
	 *
	 * @since 4.4.1
	 * @return string
	 */
	public function get_short_title(): string {
		if ( ! empty( $this->short_title ) ) {
			return $this->short_title;
		}

		return $this->get_title();
	}

	/**
	 * Returns whether the feature is visible in the dashboard or not.
	 *
	 * By default, all active features are visible.
	 *
	 * @since 4.5.0
	 * @return boolean
	 */
	public function is_visible() {
		/**
		 * Filter whether a feature is visible or not in the dashboard.
		 *
		 * Example:
		 * ```
		 * add_filter(
		 *     'ep_feature_is_visible',
		 *     function ( $is_visible, $feature_slug ) {
		 *         return 'terms' === $feature_slug ? true : $is_visible;
		 *     },
		 *     10,
		 *     2
		 * );
		 * ```
		 *
		 * @hook ep_feature_is_visible
		 * @param {bool}    $is_visible   True to display the feature
		 * @param {string}  $feature_slug Feature slug
		 * @param {Feature} $feature      Feature object
		 * @since 4.5.0
		 * @return {bool} New $is_visible value
		 */
		return apply_filters( 'ep_feature_is_visible', $this->is_visible || $this->is_active(), $this->slug, $this );
	}

	/**
	 * Returns whether the feature is available or not.
	 *
	 * @since 4.5.0
	 * @return boolean
	 */
	public function is_available(): bool {
		$requirements_status = $this->requirements_status();
		/**
		 * Filter whether a feature is available or not.
		 *
		 * Example:
		 * ```
		 * add_filter(
		 *     'ep_feature_is_available',
		 *     function ( $is_available, $feature_slug ) {
		 *         return 'terms' === $feature_slug ? true : $is_available;
		 *     },
		 *     10,
		 *     2
		 * );
		 * ```
		 *
		 * @hook ep_feature_is_available
		 * @param {bool}    $is_available True if the feature is available
		 * @param {string}  $feature_slug Feature slug
		 * @param {Feature} $feature      Feature object
		 * @since 4.5.0
		 * @return {bool} New $is_available value
		 */
		return apply_filters( 'ep_feature_is_available', $this->is_visible() && 2 !== $requirements_status->code, $this->slug, $this );
	}

	/**
	 * Get a JSON representation of the feature
	 *
	 * @since 5.0.0
	 * @return string
	 */
	public function get_json() {
		$requirements_status = $this->requirements_status();

		$feature_desc = [
			'slug'              => $this->slug,
			'title'             => $this->get_title(),
			'shortTitle'        => $this->get_short_title(),
			'summary'           => $this->summary,
			'docsUrl'           => $this->docs_url,
			'defaultSettings'   => $this->default_settings,
			'order'             => $this->order,
			'isAvailable'       => $this->is_available(),
			'isPoweredByEpio'   => $this->is_powered_by_epio,
			'isVisible'         => $this->is_visible(),
			'reqStatusCode'     => $requirements_status->code,
			'reqStatusMessages' => (array) $requirements_status->message,
			'settingsSchema'    => $this->get_settings_schema(),
			'group'             => $this->group,
			'requiredFeature'   => $this->get_required_feature(),
			'fieldGroups'       => $this->get_field_group_map(),
		];

		return $feature_desc;
	}

	/**
	 * Return the feature settings schema
	 *
	 * @since 5.0.0
	 * @return array
	 */
	public function get_settings_schema() {
		// Settings were not set yet.
		if ( [] === $this->settings_schema ) {
			$this->set_settings_schema();
		}

		$active = [
			'default'          => false,
			'key'              => 'active',
			'label'            => __( 'Enable', 'elasticpress' ),
			'requires_feature' => $this->get_required_feature(),
			'requires_sync'    => $this->requires_install_reindex,
			'type'             => 'toggle',
		];

		$settings_schema = [
			$active,
			...$this->settings_schema,
		];

		/**
		 * Filter the settings schema of a feature
		 *
		 * @hook ep_feature_is_available
		 * @since 5.0.0
		 * @param {array}   $settings_schema True if the feature is available
		 * @param {string}  $feature_slug    Feature slug
		 * @param {Feature} $feature         Feature object
		 * @return {array} New $settings_schema value
		 */
		return apply_filters( 'ep_feature_settings_schema', $settings_schema, $this->slug, $this );
	}

	/**
	 * Default implementation of `set_settings_schema` based on the `default_settings` attribute
	 *
	 * @since 5.0.0
	 */
	protected function set_settings_schema() {
		if ( [] === $this->default_settings ) {
			return;
		}

		foreach ( $this->default_settings as $key => $default_value ) {
			$type = 'text';
			if ( in_array( $default_value, [ '0', '1' ], true ) ) {
				$type = 'checkbox';
			}
			if ( is_bool( $default_value ) ) {
				$type = 'toggle';
			}

			$this->settings_schema[] = [
				'default' => $default_value,
				'key'     => $key,
				'label'   => $key,
				'type'    => $type,
			];
		}
	}

	/**
	 * Sets the i18n strings for the feature.
	 *
	 * @return void
	 * @since 5.2.0
	 */
	public function set_i18n_strings(): void {
	}

	/**
	 * Get all features required by this feature
	 *
	 * @since 5.3.0
	 * @return array List of required feature slugs
	 */
	public function get_required_feature() {
		return $this->requires_feature ? array_unique( (array) $this->requires_feature ) : [];
	}

	/**
	 * Get the field group map for the feature.
	 *
	 * @since 5.3.0
	 * @return array
	 */
	public function get_field_group_map(): array {
		/**
		 * Filter available field groups.
		 *
		 * @hook ep_feature_field_groups
		 * @since 5.3.0
		 * @param  {array} $field_groups Current field groups
		 * @return {array} New field groups
		 */
		return apply_filters( 'ep_feature_field_groups', $this->field_group_map );
	}

	/**
	 * Get the feature slug.
	 *
	 * @since 5.3.0
	 * @return string Feature slug.
	 */
	public function get_feature_slug(): string {
		return $this->slug;
	}
}
