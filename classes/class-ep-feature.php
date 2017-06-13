<?php

/**
 * Feature class to be initiated for all features
 *
 * @since  2.1
 * @package elasticpress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Just an easy way to represent a feature requirements status
 */
class EP_Feature_Requirements_Status {

	/**
	 * Initialize class
	 * 
	 * @param int $code
	 * @param string|array $message
	 * @since  2.2
	 */
	public function __construct( $code, $message = null ) {
		$this->code = $code;

		$this->message = $message;
	}

	/**
	 * Returns the status of a feature
	 *
	 * 0 is no issues
	 * 1 is usable but there are warnngs
	 * 2 is not usable
	 *
	 * @var    int
	 * @since  2.2
	 */
	public $code;

	/**
	 * Optional message to describe status code
	 * 
	 * @var    string|array
	 * @since  2.2
	 */
	public $message;
}

class EP_Feature {
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
	public $default_settings = array();

	/**
	 * Contains registered callback to execute after setup
	 * 
	 * @since 2.1
	 * @var callback
	 */
	public $setup_cb;

	/**
	 * Contains registered callback to output feature summary in feature box
	 * 
	 * @since 2.1
	 * @var callback
	 */
	public $feature_box_summary_cb;

	/**
	 * Contains registered callback to output feature long description in feature box
	 * 
	 * @since 2.1
	 * @var callback
	 */
	public $feature_box_long_cb;

	/**
	 * Output optional extra settings fields
	 *
	 * @since  2.2
	 * @var callback
	 */
	public $feature_box_settings_cb;

	/**
	 * Contains registered callback to execute after activation
	 * 
	 * @since 2.1
	 * @var callback
	 */
	public $post_activation_cb;

	/**
	 * True if the feature requires content reindexing after activating
	 * 
	 * @since 2.1
	 * @var [type]
	 */
	public $requires_install_reindex;

	/**
	 * Initiate the feature, setting all relevant instance variables
	 *
	 * @since  2.1
	 */
	public function __construct( $args ) {
		foreach ( $args as $key => $value ) {
			$this->$key = $value;
		}

		do_action( 'ep_feature_create', $this );
	}

	/**
	 * Run on every page load for feature to set itself up
	 *
	 * @since  2.1
	 */
	public function setup() {
		if ( ! empty( $this->setup_cb ) ) {
			call_user_func( $this->setup_cb, $this );
		}

		do_action( 'ep_feature_setup', $this->slug, $this );
	}

	/**
	 * Returns requirements status of feature
	 *
	 * @since  2.2
	 * @return EP_Feature_Requirements_Status
	 */
	public function requirements_status() {
		$status = new EP_Feature_Requirements_Status( 0 );

		if ( ! empty( $this->requirements_status_cb ) ) {
			$status = call_user_func( $this->requirements_status_cb, $status, $this );
		}

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
			$feature_settings = get_site_option( 'ep_feature_settings', array() );
		} else {
			$feature_settings = get_option( 'ep_feature_settings', array() );
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
			$feature_settings = get_site_option( 'ep_feature_settings', array() );
		} else {
			$feature_settings = get_option( 'ep_feature_settings', array() );
		}

		$active = false;

		if ( ! empty( $feature_settings[ $this->slug ] ) && $feature_settings[ $this->slug ]['active'] ) {
			$active = true;
		}

		return apply_filters( 'ep_feature_active', $active, $feature_settings, $this );
	}

	/**
	 * Ran after a feature is activated
	 *
	 * @since  2.1
	 */
	public function post_activation() {
		if ( ! empty( $this->post_activation_cb ) ) {
			call_user_func( $this->post_activation_cb, $this );
		}

		do_action( 'ep_feature_post_activation', $this->slug, $this );
	}

	/**
	 * Outputs feature box
	 *
	 * @since  2.1
	 */
	public function output_feature_box() {
		if ( ! empty( $this->feature_box_summary_cb ) ) {
			call_user_func( $this->feature_box_summary_cb, $this );
		}

		do_action( 'ep_feature_box_summary', $this->slug, $this );

		if ( ! empty( $this->feature_box_long_cb ) ) {
			?>

			<a class="learn-more"><?php esc_html_e( 'Learn more', 'elasticpress' ); ?></a>

			<div class="long">
				<?php call_user_func( $this->feature_box_long_cb, $this ); ?>

				<p><a class="collapse"><?php esc_html_e( 'Collapse', 'elasticpress' ); ?></a></p>
				<?php do_action( 'ep_feature_box_long', $this->slug, $this ); ?>

			</div>
			<?php
		}
	}

	/**
	 * Outputs feature box long description
	 *
	 * @since  2.1
	 */
	public function output_feature_box_full() {
		if ( ! empty( $this->feature_box_full_cb ) ) {
			call_user_func( $this->feature_box_full_cb, $this );
		}

		do_action( 'ep_feature_box_full', $this->slug, $this );
	}

	public function output_settings_box() {
		$requirements_status = $this->requirements_status();


		?>

		<?php if ( ! empty( $requirements_status->message ) ) : $messages = (array) $requirements_status->message; ?>
			<?php foreach ( $messages as $message ) : ?>
				<div class="requirements-status-notice">
					<?php echo wp_kses_post( $message ); ?>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>

		<h3><?php esc_html_e( 'Settings', 'elasticpress' ); ?></h3>

		<div class="field js-toggle-feature" data-feature="<?php echo esc_attr( $this->slug ); ?>">
			<div class="field-name status"><?php esc_html_e( 'Status', 'elasticpress' ); ?></div>
			<div class="input-wrap <?php if ( 2 === $requirements_status->code ) : ?>disabled<?php endif; ?>">
				<label for="feature_active_<?php echo esc_attr( $this->slug ); ?>_enabled"><input name="feature_active_<?php echo esc_attr( $this->slug ); ?>" id="feature_active_<?php echo esc_attr( $this->slug ); ?>_enabled" data-field-name="active" class="setting-field" <?php if ( 2 === $requirements_status->code ) : ?>disabled<?php endif; ?> type="radio" <?php if ( $this->is_active() ) : ?>checked<?php endif; ?> value="1"><?php esc_html_e( 'Enabled', 'elasticpress' ); ?></label><br>
				<label for="feature_active_<?php echo esc_attr( $this->slug ); ?>_disabled"><input name="feature_active_<?php echo esc_attr( $this->slug ); ?>" id="feature_active_<?php echo esc_attr( $this->slug ); ?>_disabled" data-field-name="active" class="setting-field" <?php if ( 2 === $requirements_status->code ) : ?>disabled<?php endif; ?> type="radio" <?php if ( ! $this->is_active() ) : ?>checked<?php endif; ?> value="0"><?php esc_html_e( 'Disabled', 'elasticpress' ); ?></label>
			</div>
		</div>

		<?php
		if ( ! empty( $this->feature_box_settings_cb ) ) {
			call_user_func( $this->feature_box_settings_cb, $this );
			return;
		}
		do_action( 'ep_feature_box_settings', $this->slug, $this );
		?>

		<div class="action-wrap">
			<span class="no-dash-sync">
				<?php esc_html_e('Setting adjustments to this feature require a re-sync. Use WP-CLI.', 'elasticpress' ); ?>
			</span>

			<a data-feature="<?php echo esc_attr( $this->slug ); ?>" class="<?php if ( $this->requires_install_reindex && defined( 'EP_DASHBOARD_SYNC' ) && ! EP_DASHBOARD_SYNC ) : ?>disabled<?php endif; ?> button button-primary save-settings"><?php esc_html_e( 'Save', 'elasticpress' ); ?></a>
		</div>
		<?php
	}
}
