<?php
/**
 * ElasticPress config functions
 *
 * @since  1.0
 * @package elasticpress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class EP_Config {

	/**
	 * True if EP_HOST has been set via option or false.
	 *
	 * @since 1.8
	 *
	 * @var bool
	 */
	public $option_host = false;

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
	 * Determine if ElasticPress is in the middle of an index
	 *
	 * @since  2.1
	 * @return boolean
	 */
	public function is_indexing() {
		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			$index_meta = get_site_option( 'ep_index_meta', false );
		} else {
			$index_meta = get_option( 'ep_index_meta', false );
		}

		return apply_filters( 'ep_is_indexing', ( ! empty( $index_meta ) ) );
	}

	/**
	 * Check if wpcli indexing is occuring
	 *
	 * @since  2.1
	 * @return boolean
	 */
	public function is_indexing_wpcli() {
		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			$index_meta = get_site_option( 'ep_index_meta', false );
		} else {
			$index_meta = get_option( 'ep_index_meta', false );
		}

		return apply_filters( 'ep_is_indexing_wpcli', ( ! empty( $index_meta ) && ! empty( $index_meta['wpcli'] ) ) );
	}

	/**
	 * Retrieve the appropriate host. Will default to EP_HOST constant if it exists
	 *
	 * @since 2.1
	 * @return string|bool
	 */
	public function get_host() {

		if ( defined( 'EP_HOST' ) && EP_HOST ) {
			$host = EP_HOST;
		} elseif ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			$host = get_site_option( 'ep_host', false );
		} else {
			$host = get_option( 'ep_host', false );
		}

		return $host;
	}

	/**
	 * Determine if host is set in options rather than a constant
	 *
	 * @since  2.1
	 * @return bool
	 */
	public function host_by_option() {
		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			$host = get_site_option( 'ep_host', false );
		} else {
			$host = get_option( 'ep_host', false );
		}

		return ( ! empty( $host ) );
	}

	/**
	 * Generates the index name for the current site
	 *
	 * @param int $blog_id (optional) Blog ID. Defaults to current blog.
	 * @since 0.9
	 * @return string
	 */
	public function get_index_name( $blog_id = null ) {
		if ( ! $blog_id ) {
			$blog_id = get_current_blog_id();
		}

		$site_url = get_site_url( $blog_id );

		if ( ! empty( $site_url ) ) {
			$index_name = preg_replace( '#https?://(www\.)?#i', '', $site_url );
			$index_name = preg_replace( '#[^\w]#', '', $index_name ) . '-' . $blog_id;
		} else {
			$index_name = false;
		}

		if ( defined( 'EP_INDEX_PREFIX' ) && EP_INDEX_PREFIX ) {
			$index_name = EP_INDEX_PREFIX . $index_name;
		}

		return apply_filters( 'ep_index_name', $index_name, $blog_id );
	}

	/**
	 * Returns indexable post types for the current site
	 *
	 * @since 0.9
	 * @return mixed|void
	 */
	public function get_indexable_post_types() {
		$post_types = get_post_types( array( 'public' => true ) );

		return apply_filters( 'ep_indexable_post_types', $post_types );
	}

	/**
	 * Return indexable post_status for the current site
	 *
	 * @since 1.3
	 * @return array
	 */
	public function get_indexable_post_status() {
		return apply_filters( 'ep_indexable_post_status', array( 'publish' ) );
	}

	/**
	 * Generate network index name for alias
	 *
	 * @since 0.9
	 * @return string
	 */
	public function get_network_alias() {
		$url = network_site_url();
		$slug = preg_replace( '#https?://(www\.)?#i', '', $url );
		$slug = preg_replace( '#[^\w]#', '', $slug );

		$alias = $slug . '-global';

		if ( defined( 'EP_INDEX_PREFIX' ) && EP_INDEX_PREFIX ) {
			$alias = EP_INDEX_PREFIX . $alias;
		}

		return apply_filters( 'ep_global_alias', $alias );
	}

	/**
	 * Whether plugin is network activated
	 *
	 * Determines whether plugin is network activated or just on the local site.
	 *
	 * @since 1.8
	 *
	 * @param string $plugin the plugin base name.
	 *
	 * @return bool True if network activated or false
	 */
	public function is_network( $plugin ) {

		$plugins = get_site_option( 'active_sitewide_plugins');

		if ( is_multisite() && isset( $plugins[ $plugin ] ) ) {
			return true;
		}

		return false;

	}
}

EP_Config::factory();

/**
 * Accessor functions for methods in above class. See doc blocks above for function details.
 */

function ep_get_host() {
	return EP_Config::factory()->get_host();
}

function ep_host_by_option() {
	return EP_Config::factory()->host_by_option();
}

function ep_is_indexing() {
	return EP_Config::factory()->is_indexing();
}

function ep_is_indexing_wpcli() {
	return EP_Config::factory()->is_indexing_wpcli();
}

function ep_get_index_name( $blog_id = null ) {
	return EP_Config::factory()->get_index_name( $blog_id );
}

function ep_get_indexable_post_types() {
	return EP_Config::factory()->get_indexable_post_types();
}

function ep_get_indexable_post_status() {
	return EP_Config::factory()->get_indexable_post_status();
}

function ep_get_network_alias() {
	return EP_Config::factory()->get_network_alias();
}

function ep_is_network_activated( $plugin ) {
	return EP_Config::factory()->is_network( $plugin );
}
