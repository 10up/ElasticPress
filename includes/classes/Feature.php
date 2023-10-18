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
	 * The slug of a feature that is required to be active.
	 *
	 * @since 5.0.0
	 * @var false|string
	 */
	protected $requires_feature = false;

	/**
	 * Whether the feature is using ElasticPress.io.
	 *
	 * @since 5.0.0
	 * @var boolean
	 */
	protected $is_epio = false;

	/**
	 * Run on every page load for feature to set itself up
	 *
	 * @since  2.1
	 */
	abstract public function setup();

	/**
	 * Output feature box summary
	 *
	 * @since 2.1
	 */
	public function output_feature_box_summary() {
		if ( $this->summary ) {
			echo wp_kses_post( $this->summary );
		}
	}

	/**
	 * Implement to output feature box long text
	 *
	 * @since  3.0
	 */
	abstract public function output_feature_box_long();

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
	 * Outputs feature box
	 *
	 * @since  2.1
	 */
	public function output_feature_box() {
		$this->output_feature_box_summary();

		/**
		 * Fires before feature box summary is shown
		 *
		 * @hook ep_feature_box_summary
		 * @param  {string} $slug Feature slug
		 * @param {Feature} $feature Current feature
		 * @since  2.1
		 */
		do_action( 'ep_feature_box_summary', $this->slug, $this );
		?>

		<button aria-expanded="false" class="learn-more button button-secondary button-small" type="button"><?php esc_html_e( 'Learn more', 'elasticpress' ); ?></button>

		<div class="long">
			<?php $this->output_feature_box_long(); ?>

			<p><button aria-expanded="true" class="collapse button button-secondary button-small" type="button"><?php esc_html_e( 'Collapse', 'elasticpress' ); ?></button></p>

			<?php
			/**
			 * Fires after feature long description
			 *
			 * @hook ep_feature_box_long
			 * @param  {string} $slug Feature slug
			 * @param {Feature} $feature Current feature
			 * @since  2.1
			 */
			do_action( 'ep_feature_box_long', $this->slug, $this );
			?>

		</div>
		<?php
	}

	/**
	 * Output extra feature box settings.
	 *
	 * By default this does nothing. Override to add additional settings.
	 *
	 * @since  3.0
	 */
	public function output_feature_box_settings() {
		/**
		 * Optionally override
		 */
	}

	/**
	 * Output feature settings
	 *
	 * @since  3.0
	 */
	public function output_settings_box() {
		$requirements_status = $this->requirements_status();
		$sync_url            = ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK )
			? network_admin_url( 'admin.php?page=elasticpress-sync' )
			: admin_url( 'admin.php?page=elasticpress-sync' );
		?>

		<form>
			<?php
			if ( ! empty( $requirements_status->message ) ) :
				$messages = (array) $requirements_status->message;
				?>
				<?php foreach ( $messages as $message ) : ?>
					<div class="requirements-status-notice">
						<?php echo wp_kses_post( $message ); ?>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>

			<?php if ( $this->requires_install_reindex || $this->setting_requires_install_reindex ) : ?>
				<div class="requirements-status-notice requirements-status-notice--reindex" role="status">
					<?php esc_html_e( 'Enabling this feature will require re-syncing your content.', 'elasticpress' ); ?>
				</div>
			<?php endif; ?>

			<div class="requirements-status-notice requirements-status-notice--syncing" role="alert">
				<?php
				printf(
					'%1$s <a href="%2$s">%3$s</a>',
					esc_html__( 'Settings not saved. Cannot save settings while a sync is in progress.', 'elasticpress' ),
					esc_url( $sync_url ),
					esc_html__( 'View sync status.', 'elasticpress' )
				);
				?>
			</div>

			<h3><?php esc_html_e( 'Settings', 'elasticpress' ); ?></h3>

			<div class="feature-fields">
				<div class="field js-toggle-feature">
					<div class="field-name status"><?php esc_html_e( 'Status', 'elasticpress' ); ?></div>
					<div class="input-wrap <?php if ( 2 === $requirements_status->code ) : ?>disabled<?php endif; ?>">
						<label><input name="settings[active]" <?php disabled( 2 === $requirements_status->code ); ?> type="radio" <?php checked( $this->is_active() ); ?> value="1"><?php esc_html_e( 'Enabled', 'elasticpress' ); ?></label><br>
						<label><input name="settings[active]" <?php disabled( 2 === $requirements_status->code ); ?> type="radio" <?php checked( ! $this->is_active() ); ?> value="0"><?php esc_html_e( 'Disabled', 'elasticpress' ); ?></label>
					</div>
				</div>

				<?php
				$this->output_feature_box_settings();
				?>
			</div>

			<div class="action-wrap">
				<span class="no-dash-sync">
					<?php esc_html_e( 'Setting adjustments to this feature require a re-sync. Use WP-CLI.', 'elasticpress' ); ?>
				</span>

				<input type="hidden" name="action" value="ep_save_feature">
				<input type="hidden" name="feature" value="<?php echo esc_attr( $this->slug ); ?>">
				<input type="hidden" name="requires_reindex" value="<?php echo $this->requires_install_reindex ? '1' : '0'; ?>">
				<input type="hidden" name="was_active" value="<?php echo $this->is_active() ? '1' : '0'; ?>">
				<input type="hidden" name="setting_requires_reindex" value="<?php echo esc_attr( $this->setting_requires_install_reindex ); ?>">
				<input type="hidden" name="setting_requires_reindex_was" value="<?php echo esc_attr( $this->get_reindex_setting() ); ?>">
				<?php wp_nonce_field( 'ep_dashboard_nonce', 'nonce' ); ?>

				<button name="submit" <?php disabled( 2 === $requirements_status->code || ( $this->requires_install_reindex && defined( 'EP_DASHBOARD_SYNC' ) && ! EP_DASHBOARD_SYNC ) ); ?> class="button button-primary" type="submit">
					<?php esc_html_e( 'Save', 'elasticpress' ); ?>
				</button>
			</div>
		</form>

		<?php
	}

	/**
	 * Returns the ElasticPress.io logo.
	 *
	 * @since 4.4.1
	 * @return string
	 */
	public function get_epio_logo() : string {
		return sprintf( '<img class="feature-epio-logo" alt="ElasticPresss.io logo" src="%s" width="110" height="20">', esc_url( plugins_url( '/images/logo-elasticpress-io.svg', EP_FILE ) ) );
	}

	/**
	 * Returns the feature title.
	 *
	 * @since 4.4.1
	 * @return string
	 */
	public function get_title() : string {
		return $this->title;
	}

	/**
	 * Returns the feature short title.
	 *
	 * @since 4.4.1
	 * @return string
	 */
	public function get_short_title() : string {
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
	public function is_available() : bool {
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
		return apply_filters( 'ep_feature_is_available', $this->is_visible && 2 !== $requirements_status->code, $this->slug, $this );
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
			'isEpio'            => $this->is_epio(),
			'isVisible'         => $this->is_visible(),
			'reqStatusCode'     => $requirements_status->code,
			'reqStatusMessages' => (array) $requirements_status->message,
			'settingsSchema'    => $this->get_settings_schema(),
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
		$req_status = $this->requirements_status();

		$active = [
			'default'          => false,
			'key'              => 'active',
			'label'            => __( 'Enable', 'elasticpress' ),
			'requires_feature' => $this->requires_feature,
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
}
