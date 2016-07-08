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
		add_action( 'after_setup_theme', array( $this, 'setup_modules' ) );
	}

	/**
	 * [ep_register_module description]
	 * @param  string $slug
	 * @param  array  $module_args
	 * 
	 *         Supported array parameters:
	 *         
	 *         "title" (string) - Pretty title for module
	 *         "requires_install_reindex" (boolean) - Setting to true will force a reindex after the module is activated
	 *         "setup_cb" (callback) - Callback to a function to be called on each page load when the module is activated
	 *         "post_activation_cb" (callback) - Callback to a function to be called after a module is first activated
	 *         "module_box_summary_cb" (callback) - Callback to a function that outputs HTML module box summary (short description of module)
	 *         "module_box_long_cb" (callback) - Callback to a function that outputs HTML module box full description
	 *         "dependencies_met_cb" (callback) - Callback to a function that determines if the modules dependencies are met. True 
	 *         		means yes, WP_Error means no. If no, WP_Error message will be printed to the screen.
	 *         
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
	 * A convenient function to programmatically activate a module
	 *
	 * @param  string $slug
	 * @since  2.1
	 */
	public function activate_module( $slug ) {
		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			$modules = get_site_option( 'ep_active_modules', array() );
		} else {
			$modules = get_option( 'ep_active_modules', array() );
		}

		if ( false === array_search( $slug, $modules ) ) {
			$modules[] = $slug;
			if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
				update_site_option( 'ep_active_modules', $modules );
			} else {
				update_option( 'ep_active_modules', $modules );
			}
		}
	}

	/**
	 * Returns all active modules
	 *
	 * @since  2.1
	 * @return array Array of slugs mapped to EP_Module objects
	 */
	public function get_active_modules() {
		$active = array();

		foreach ( $this->registered_modules as $module ) {
			if ( $module->active ) {
				$active[$module->slug] = $module;
			}
		}

		return $active;
	}

	/**
	 * Set up all active modules that are stored in options
	 *
	 * @since  2.1
	 */
	public function setup_modules() {
		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			$modules = get_site_option( 'ep_active_modules', array() );
		} else {
			$modules = get_option( 'ep_active_modules', array() );
		}
		$modules = apply_filters( 'ep_active_modules', $modules );

		foreach ( $modules as $module_slug ) {
			if ( empty( $this->registered_modules[$module_slug] ) ) {
				continue;
			}

			$this->registered_modules[$module_slug]->setup();
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

function ep_activate_module( $slug ) {
	return EP_Modules::factory()->activate_module( $slug );
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
