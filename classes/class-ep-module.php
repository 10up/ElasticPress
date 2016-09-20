<?php

/**
 * Module class to be initiated for all modules
 *
 * @since  2.1
 * @package elasticpress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class EP_Module {
	/**
	 * Module slug
	 * 
	 * @var string
	 * @since  2.1
	 */
	public $slug;

	/**
	 * Module pretty title
	 * 
	 * @var string
	 * @since  2.1
	 */
	public $title;

	/**
	 * Contains registered callback to execute after setup
	 * 
	 * @since 2.1
	 * @var callback
	 */
	public $setup_cb;

	/**
	 * Contains registered callback to output module summary in module box
	 * 
	 * @since 2.1
	 * @var callback
	 */
	public $module_box_summary_cb;

	/**
	 * Contains registered callback to output module long description in module box
	 * 
	 * @since 2.1
	 * @var callback
	 */
	public $module_box_long_cb;

	/**
	 * Contains registered callback to determine if a modules dependencies are met
	 * 
	 * @since 2.1
	 * @var callback
	 */
	public $dependencies_met_cb;

	/**
	 * Contains registered callback to execute after activation
	 * 
	 * @since 2.1
	 * @var callback
	 */
	public $post_activation_cb;

	/**
	 * True if the module requires content reindexing after activating
	 * 
	 * @since 2.1
	 * @var [type]
	 */
	public $requires_install_reindex;

	/**
	 * True if the module is active
	 * 
	 * @since 2.1
	 * @var boolean
	 */
	public $active;

	/**
	 * Initiate the module, setting all relevant instance variables
	 *
	 * @since  2.1
	 */
	public function __construct( $args ) {
		foreach ( $args as $key => $value ) {
			$this->$key = $value;
		}

		do_action( 'ep_module_create', $this );
	}

	/**
	 * Ran after a function is activated
	 *
	 * @since  2.1
	 */
	public function setup() {
		if ( ! empty( $this->setup_cb ) ) {
			call_user_func( $this->setup_cb );
		}

		$this->active = true;

		do_action( 'ep_module_setup', $this->slug, $this );
	}

	/**
	 * Ran to see if dependencies are met. Returns true or a WP_Error containing an error message
	 * to display
	 *
	 * @since  2.1
	 * @return  bool|WP_Error
	 */
	public function dependencies_met() {
		$ret = true;

		if ( ! empty( $this->dependencies_met_cb ) ) {
			$ret = apply_filters( 'ep_module_dependencies_met', call_user_func( $this->dependencies_met_cb ) );
		}

		return $ret;
	}

	/**
	 * Ran after a module is activated
	 *
	 * @since  2.1
	 */
	public function post_activation() {
		if ( ! empty( $this->post_activation_cb ) ) {
			call_user_func( $this->post_activation_cb );
		}

		do_action( 'ep_module_post_activation', $this->slug, $this );
	}

	/**
	 * Outputs module box
	 *
	 * @since  2.1
	 */
	public function output_module_box() {
		if ( ! empty( $this->module_box_summary_cb ) ) {
			call_user_func( $this->module_box_summary_cb );
		}

		do_action( 'ep_module_box_summary', $this->slug, $this );

		if ( ! empty( $this->module_box_long_cb ) ) {
			?>

			<a class="learn-more"><?php esc_html_e( 'Learn more', 'elasticpress' ); ?></a>

			<div class="long">
				<?php call_user_func( $this->module_box_long_cb ); ?>

				<p><a class="collapse"><?php esc_html_e( 'Collapse', 'elasticpress' ); ?></a></p>
				<?php do_action( 'ep_module_box_long', $this->slug, $this ); ?>

			</div>
			<?php
		}
	}

	/**
	 * Outputs module box long description
	 *
	 * @since  2.1
	 */
	public function output_module_box_full() {
		if ( ! empty( $this->module_box_full_cb ) ) {
			call_user_func( $this->module_box_full_cb );
		}

		do_action( 'ep_module_box_full', $this->slug, $this );
	}

	/**
	 * Returns true if the module is active
	 *
	 * @since  2.1
	 * @return boolean
	 */
	public function is_active() {
		return ( ! empty( $this->active ) );
	}
}