<?php

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
	 * Retrieve the appropriate EP_HOST
	 *
	 * Looks at the defined EP_HOST or a backup global should the defined host failed.
	 * Priority is given to the EP_HOST constand with the backups only used when needed.
	 *
	 * @since 1.6
	 *
	 * @global array $ep_backup_host   array of backup hosts
	 *
	 * @param bool   $force            Whether to force a new lookup or not
	 * @param bool   $use_only_backups Forces the use of only the backup array, no others
	 *
	 * @return string|WP_Error the host to use or an error
	 */
	public function get_ep_host( $force = false, $use_only_backups = false ) {

		global $ep_backup_host;

		// Delete the transient if we want to force a new good host lookup
		if ( true === $force ) {
			delete_site_transient( 'ep_last_good_host' );
		}

		$last_good_host = get_site_transient( 'ep_last_good_host' );

		if ( $last_good_host ) {
			return $last_good_host;
		}

		// If nothing is defined just return an error
		if ( ! defined( 'EP_HOST' ) && ! $ep_backup_host ) {
			return new WP_Error( 'elasticpress', __( 'No running host available.', 'elasticpress' ) );
		}

		$hosts = array();

		if ( defined( 'EP_HOST' ) && false === $use_only_backups ) {
			$hosts[] = EP_HOST;
		}

		// If no backups are defined just return the host
		if ( $ep_backup_host && is_array( $ep_backup_host ) ) {
			$hosts = array_merge( $hosts, $ep_backup_host );
		}

		foreach ( $hosts as $host ) {

			if ( true === ep_elasticsearch_alive( $host ) ) {

				set_site_transient( 'ep_last_good_host', $host, apply_filters( 'ep_last_good_host_timeout', 3600 ) );

				return $host;

			}
		}

		return new WP_Error( 'elasticpress', __( 'No running host available.', 'elasticpress' ) );

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

		return apply_filters( 'ep_index_name', $index_name, $blog_id );
	}

	/**
	 * Returns indexable post types for the current site
	 *
	 * @since 0.9
	 * @return mixed|void
	 */
	public function get_indexable_post_types() {
		$post_types = get_post_types( array( 'exclude_from_search' => false ) );

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

		return apply_filters( 'ep_global_alias', $alias );
	}

	/**
	 * Check if connection is alive.
	 *
	 * Provide better error messaging for common connection errors
	 *
	 * @since 1.7
	 *
	 * @return bool|WP_Error true on success or WP_Error
	 */
	public function check_host() {

		global $ep_backup_host;

		if ( ! defined( 'EP_HOST' ) && ! is_array( $ep_backup_host ) ) {
			ep_set_host();
		}

		if ( ! defined( 'EP_HOST' ) && ! is_array( $ep_backup_host ) ) {
			return new WP_Error( 'elasticpress', esc_html__( 'EP_HOST is not defined! Check wp-config.php', 'elasticpress' ) );
		}

		if ( false === ep_elasticsearch_alive() ) {
			return new WP_Error( 'elasticpress', esc_html__( 'Unable to reach Elasticsearch Server! Check that service is running.', 'elasticpress' ) );
		}

		return true;

	}

	/**
	 * Set EP_API_KEY if needed
	 *
	 * Retrieves the value set in options the api key and defines EP_API_KEY constant.
	 *
	 * @since 0.3.0
	 *
	 * @return string The set API key.
	 */
	public function set_api_key() {

		$ep_api_key = get_site_option( 'ep_api_key' );

		if ( $ep_api_key && ! defined( 'EP_API_KEY' ) ) {
			define( 'EP_API_KEY', $ep_api_key );
		}

		if ( defined( 'EP_API_KEY' ) ) {
			return EP_API_KEY;
		}

		return '';

	}

	/**
	 * Set EP_HOST if needed
	 *
	 * Retrieves the value set in options the host and defines EP_HOST constant.
	 *
	 * @since 1.7
	 *
	 * @return string The set host.
	 */
	public function set_host() {

		$ep_host           = get_site_option( 'ep_host' );

		if ( $ep_host && ! defined( 'EP_HOST' ) ) {
			$this->option_host = true;
			define( 'EP_HOST', $ep_host );
		}

		if ( defined( 'EP_HOST' ) ) {
			return EP_HOST;
		}

		return '';

	}

	/**
	 * Tracks how EP_HOST was set
	 *
	 * Tracks how EP_HOST was set for easer use.
	 *
	 * @since 1.8
	 *
	 * @return bool True if option is used to set host or false.
	 */
	public function host_by_option() {

		return $this->option_host;

	}
}

EP_Config::factory();

/**
 * Accessor functions for methods in above class. See doc blocks above for function details.
 */

function ep_get_host( $force = false, $use_only_backups = false ) {
	return EP_Config::factory()->get_ep_host( $force, $use_only_backups );
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

function ep_check_host() {
	return EP_Config::factory()->check_host();
}

function ep_set_host() {
	return EP_Config::factory()->set_host();
}

function ep_set_api_key() {
	return EP_Config::factory()->set_api_key();
}

function ep_host_by_option() {
	return EP_Config::factory()->host_by_option();
}
