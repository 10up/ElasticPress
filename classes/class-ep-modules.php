<?php
/**
 * Module loader
 *
 * @since  2.1
 * @package elasticpress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class EP_Modules {

	/**
	 * Stores all modules that have been properly included (both active and inactive)
	 * 
	 * @since  2.1
	 * @var array
	 */
	public $registered_modules = array();

	/**
	 * Initiate class actions
	 * 
	 * @since 2.1
	 */
	public function setup() {
		add_action( 'plugins_loaded', array( $this, 'setup_modules' ) );
	}

	/**
	 * Registers a module for use in ElasticPress
	 * 
	 * @param  string $slug
	 * @param  array  $module_args
	 * 
	 *         Supported array parameters:
	 *         
	 *         "title" (string) - Pretty title for module
	 *         "default_settings" (array) - Array of default settings. Only needed if you plan on adding custom settings
	 *         "requirements_status_cb" (callback) - Callback to a function that determines the "requirements" status of
	 *         		the given module. 0 means everything is okay. 1 means the module can be used but there is a warning. 
	 *         		2 means the module cannot be active. This callback needs to return an EP_Module_Requirements_Status 
	 *         		object where the "code" property is one of the values above.
	 *         "setup_cb" (callback) - Callback to a function to be called on each page load when the module is activated
	 *         "post_activation_cb" (callback) - Callback to a function to be called after a module is first activated
	 *         "module_box_summary_cb" (callback) - Callback to a function that outputs HTML module box summary (short description of module)
	 *         "module_box_long_cb" (callback) - Callback to a function that outputs HTML module box full description
	 *         "module_box_settings_cb" (callback) - Callback to a function that outputs custom module settings fields
	 * 
	 * @since  2.1 
	 * @return boolean
	 */
	public function register_module( $slug, $module_args ) {
		if ( empty( $slug ) || empty( $module_args ) || ! is_array( $module_args ) ) {
			return false;
		}

		$module_args['slug'] = $slug;

		$this->registered_modules[$slug] = new EP_Module( $module_args );

		return true;
	}

	/**
	 * Set up all active modules
	 *
	 * @since  2.1
	 */
	public function setup_modules() {
		foreach ( $this->registered_modules as $module_slug => $module ) {
			if ( $module->is_active() ) {
				$module->setup();
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

EP_Modules::factory();

/**
 * Main function for registering new module. Since comment above for details
 * 
 * @param  string $slug
 * @param  array $module_args
 * @since  2.1
 * @return bool
 */
function ep_register_module( $slug, $module_args ) {
	return EP_Modules::factory()->register_module( $slug, $module_args );
}

/**
 * Easy access function to get a EP_Module object from a slug
 * @param  string $slug
 * @since  2.1
 * @return EP_Module
 */
function ep_get_registered_module( $slug ) {
	if ( empty( EP_Modules::factory()->registered_modules[$slug] ) ) {
		return false;
	}
	return EP_Modules::factory()->registered_modules[$slug];
}
