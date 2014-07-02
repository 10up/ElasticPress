<?php

class EP_Config {

	/**
	 * Skeleton to build out single site config array. Not to be used for actually storing values in.
	 *
	 * @since 0.1.0
	 * @var array
	 */
	private static $single_site_config = array(
		'post_types' => array(),
		'host' => '',
		'index_name' => '',
	);

	/**
	 * Skeleton to build out multi site's global config array. Not to be used for actually storing values in.
	 *
	 * @since 0.1.0
	 * @var array
	 */
	private static $global_site_config = array(
		'host' => '',
		'index_name' => '',
		'cross_site_search_active' => 0,
	);

	/**
	 * Placeholder method
	 *
	 * @since 0.1.0
	 */
	public function __construct() { }

	/**
	 * Return options for a specific site or globally. We use get_site_option since
	 * it defaults to get_option in the event of a non multi-site install. We store
	 * settings for all sites including the global one in a single option key.
	 *
	 * null => use current site id
	 * 0 => use global site
	 * 0 => use a specific site
	 *
	 * @param int $site_id
	 * @since 0.1.0
	 * @return array
	 */
	public function get_option( $site_id = null ) {
		$option = get_site_option( 'ep_config_by_site', array() );

		if ( $site_id === null ) {
			$site_id = get_current_blog_id();
		}

		if ( isset( $option[$site_id] ) ) {
			return $option[$site_id];
		} else {
			if ( $site_id === 0 ) {
				return self::$global_site_config;
			}

			return self::$single_site_config;
		}
	}

	/**
	 * Update options globally ($site_id = 0) or for a specific site instance.
	 * We use update_site_option since it will default to update_option for
	 * non multi-site installs.
	 *
	 * @param int|string|array|object $config
	 * @param int $site_id
	 * @since 0.1.0
	 * @return bool
	 */
	public function update_option( $config, $site_id = null ) {
		$option = get_site_option( 'ep_config_by_site', array() );

		if ( $site_id === null ) {
			$site_id = get_current_blog_id();
		}

		$option[$site_id] = $config;

		return update_site_option( 'ep_config_by_site', $option );
	}

	/**
	 * Get a singleton instance of the class
	 *
	 * @since 0.1.0
	 * @return EP_Config
	 */
	public static function factory() {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new self();
		}

		return $instance;
	}

	/**
	 * Get the url for the configured index for a given site or the global setup
	 *
	 * @param int $site_id
	 * @since 0.1.0
	 * @return bool|string
	 */
	public function get_index_url( $site_id = null ) {
		$option = $this->get_option( $site_id );

		if ( ! isset( $option['index_name'] ) || ! isset( $option['host'] ) ) {
			return false;
		}

		return untrailingslashit( $option['host'] ) . '/' . $option['index_name'];
	}

	/**
	 * Check if a site is properly setup. We can check individual sites or the global one.
	 *
	 * @param int $site_id
	 * @since 0.1.0
	 * @return bool
	 */
	public function is_setup( $site_id = null ) {
		$option = $this->get_option( $site_id );

		// @todo: Check an make sure at least one post type is marked for indexing
		
		if ( empty( $option['index_name'] ) || empty( $option['host'] ) ) {
			return false;
		}

		return true;
	}
}

EP_Config::factory();

/**
 * Accessor functions for methods in above class. See doc blocks above for function details.
 */

function ep_get_option( $site_id = null ) {
	return EP_Config::factory()->get_option( $site_id );
}

function ep_update_option( $config, $site_id = null ) {
	return EP_Config::factory()->update_option( $config, $site_id );
}

function ep_get_index_url( $site_id = null ) {
	return EP_Config::factory()->get_index_url( $site_id );
}

function ep_is_setup( $site_id = null ) {
	return EP_Config::factory()->is_setup( $site_id );
}