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

use ElasticPress\FeatureRequirementsStatus as FeatureRequirementsStatus;

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
	 * @var [type]
	 */
	public $requires_install_reindex;

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
	 * Run on every page load for feature to set itself up
	 *
	 * @since  2.1
	 */
	abstract public function setup();

	/**
	 * Implement to output feature box summary
	 *
	 * @since  3.0
	 */
	abstract public function output_feature_box_summary();

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
	 * @since  2.2.1
	 * @return array|bool
	 */
	public function get_settings() {
		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			$feature_settings = get_site_option( 'ep_feature_settings', [] );
		} else {
			$feature_settings = get_option( 'ep_feature_settings', [] );
		}

		return ( ! empty( $feature_settings[ $this->slug ] ) ) ? $feature_settings[ $this->slug ] : false;
	}

	/**
	 * Returns true if feature is active
	 *
	 * @since  2.2
	 * @return boolean
	 */
	public function is_active() {
		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			$feature_settings = get_site_option( 'ep_feature_settings', [] );
		} else {
			$feature_settings = get_option( 'ep_feature_settings', [] );
		}

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

		<a class="learn-more"><?php esc_html_e( 'Learn more', 'elasticpress' ); ?></a>

		<div class="long">
			<?php $this->output_feature_box_long(); ?>

			<p><a class="collapse"><?php esc_html_e( 'Collapse', 'elasticpress' ); ?></a></p>

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
		?>

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

		<h3><?php esc_html_e( 'Settings', 'elasticpress' ); ?></h3>

		<div class="feature-fields">
			<div class="field js-toggle-feature" data-feature="<?php echo esc_attr( $this->slug ); ?>">
				<div class="field-name status"><?php esc_html_e( 'Status', 'elasticpress' ); ?></div>
				<div class="input-wrap <?php if ( 2 === $requirements_status->code ) : ?>disabled<?php endif; ?>">
					<label for="feature_active_<?php echo esc_attr( $this->slug ); ?>_enabled"><input name="feature_active_<?php echo esc_attr( $this->slug ); ?>" id="feature_active_<?php echo esc_attr( $this->slug ); ?>_enabled" data-field-name="active" class="setting-field" <?php if ( 2 === $requirements_status->code ) : ?>disabled<?php endif; ?> type="radio" <?php if ( $this->is_active() ) : ?>checked<?php endif; ?> value="1"><?php esc_html_e( 'Enabled', 'elasticpress' ); ?></label><br>
					<label for="feature_active_<?php echo esc_attr( $this->slug ); ?>_disabled"><input name="feature_active_<?php echo esc_attr( $this->slug ); ?>" id="feature_active_<?php echo esc_attr( $this->slug ); ?>_disabled" data-field-name="active" class="setting-field" <?php if ( 2 === $requirements_status->code ) : ?>disabled<?php endif; ?> type="radio" <?php if ( ! $this->is_active() ) : ?>checked<?php endif; ?> value="0"><?php esc_html_e( 'Disabled', 'elasticpress' ); ?></label>
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

			<a data-feature="<?php echo esc_attr( $this->slug ); ?>" class="<?php if ( 2 === $requirements_status->code || ( $this->requires_install_reindex && defined( 'EP_DASHBOARD_SYNC' ) && ! EP_DASHBOARD_SYNC ) ) : ?>disabled<?php endif; ?> button button-primary save-settings"><?php esc_html_e( 'Save', 'elasticpress' ); ?></a>
		</div>
		<?php
	}
}
