<?php
/**
 * ElasticPress utility functions
 *
 * @since  3.0
 * @package elasticpress
 */

namespace ElasticPress\Utils;

use ElasticPress\IndexHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Retrieve the EPIO subscription credentials.
 *
 * @since 2.5
 * @return array
 */
function get_epio_credentials() {
	if ( defined( 'EP_CREDENTIALS' ) && EP_CREDENTIALS ) {
		$raw_credentials = explode( ':', EP_CREDENTIALS );
		if ( is_array( $raw_credentials ) && 2 === count( $raw_credentials ) ) {
			$credentials = array(
				'username' => $raw_credentials[0],
				'token'    => $raw_credentials[1],
			);
		}
		$credentials = sanitize_credentials( $credentials );
	} elseif ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK && is_epio() ) {
		$credentials = sanitize_credentials( get_site_option( 'ep_credentials', false ) );
	} elseif ( is_epio() ) {
		$credentials = sanitize_credentials( get_option( 'ep_credentials', false ) );
	} else {
		$credentials = [
			'username' => '',
			'token'    => '',
		];
	}

	if ( ! is_array( $credentials ) ) {
		return [
			'username' => '',
			'token'    => '',
		];
	}

	return $credentials;
}

/**
 * Get WP capability needed for a user to interact with ElasticPress in the admin
 *
 * @since 4.5.0
 * @return string
 */
function get_capability() : string {
	/**
	 * Filter the WP capability needed to interact with ElasticPress in the admin
	 *
	 * @since 4.5.0
	 * @hook ep_capability
	 * @param  {string} $capability Capability name. Defaults to `'manage_elasticpress'`
	 * @return {string} New capability value
	 */
	return apply_filters( 'ep_capability', 'manage_elasticpress' );
}

/**
 * Get WP capability needed for a user to interact with ElasticPress in the network admin
 *
 * @since 4.5.0
 * @return string
 */
function get_network_capability() : string {
	/**
	 * Filter the WP capability needed to interact with ElasticPress in the network admin
	 *
	 * @since 4.5.0
	 * @hook ep_network_capability
	 * @param  {string} $capability Capability name. Defaults to `'manage_network_elasticpress'`
	 * @return {string} New capability value
	 */
	return apply_filters( 'ep_network_capability', 'manage_network_elasticpress' );
}

/**
 * Get mapped capabilities for post types
 *
 * @since 4.5.0
 * @return array
 */
function get_post_map_capabilities() : array {
	$capability = get_capability();

	return [
		'edit_post'          => $capability,
		'edit_posts'         => $capability,
		'edit_others_posts'  => $capability,
		'publish_posts'      => $capability,
		'read_post'          => $capability,
		'read_private_posts' => $capability,
		'delete_post'        => $capability,
	];
}

/**
 * Get shield credentials
 *
 * @since  3.0
 * @return string|bool
 */
function get_shield_credentials() {
	if ( defined( 'ES_SHIELD' ) && ES_SHIELD ) {
		return ES_SHIELD;
	} elseif ( is_epio() ) {
		$credentials = get_epio_credentials();

		return $credentials['username'] . ':' . $credentials['token'];
	}

	return false;
}

/**
 * Retrieve the appropriate index prefix. Will default to EP_INDEX_PREFIX constant if it exists
 * AKA Subscription ID.
 *
 * @since 2.5
 * @return string|bool
 */
function get_index_prefix() {
	if ( defined( 'EP_INDEX_PREFIX' ) && \EP_INDEX_PREFIX ) {
		$prefix = \EP_INDEX_PREFIX;
	} elseif ( is_epio() ) {
		$credentials = get_epio_credentials();
		$prefix      = $credentials['username'];
		if (
			( ! defined( 'EP_IS_NETWORK' ) || ! EP_IS_NETWORK ) &&
			( '-' !== substr( $prefix, - 1 ) )
		) {
			$prefix .= '-';
		}
	} else {
		$prefix = '';
	}

	/**
	 * Filter index prefix. Defaults to nothing
	 *
	 * @since  2.5
	 * @hook ep_index_prefix
	 * @param  {string} $prefix Current prefix
	 * @return  {string} New prefix
	 */
	return apply_filters( 'ep_index_prefix', $prefix );
}

/**
 * Check if the host is ElasticPress.io.
 *
 * @since  2.6
 * @return bool
 */
function is_epio() {
	return filter_var( preg_match( '#elasticpress\.io#i', get_host() ), FILTER_VALIDATE_BOOLEAN );
}

/**
 * Determine if we should index a blog/site
 *
 * @param  int $blog_id Blog/site id.
 * @since  3.2
 * @return boolean
 */
function is_site_indexable( $blog_id = null ) {
	if ( ! is_multisite() ) {
		return true;
	}

	$site = get_site( $blog_id );

	$is_indexable = get_site_meta( $site['blog_id'], 'ep_indexable', true );

	return 'no' !== $is_indexable && ! $site['deleted'] && ! $site['archived'] && ! $site['spam'];
}

/**
 * Sanitize EPIO credentials prior to storing them.
 *
 * @param array $credentials Array containing username and token.
 * @since  2.6
 * @return array
 */
function sanitize_credentials( $credentials ) {
	if ( ! is_array( $credentials ) ) {
		return [
			'username' => '',
			'token'    => '',
		];
	}

	return [
		'username' => ( isset( $credentials['username'] ) ) ? sanitize_text_field( $credentials['username'] ) : '',
		'token'    => ( isset( $credentials['token'] ) ) ? sanitize_text_field( $credentials['token'] ) : '',
	];
}

/**
 * Determine if ElasticPress is in the middle of an index
 *
 * @since  3.0
 * @return boolean
 */
function is_indexing() {
	/**
	 * Filter whether an index is occurring in dashboard or CLI
	 *
	 * @since  3.0
	 * @hook ep_is_indexing
	 * @param  {bool} $indexing True for indexing
	 * @return {bool} New indexing value
	 */
	return apply_filters( 'ep_is_indexing', ! empty( IndexHelper::factory()->get_index_meta() ) );
}

/**
 * Check if wpcli indexing is occurring
 *
 * @since  3.0
 * @return boolean
 */
function is_indexing_wpcli() {
	$index_meta = IndexHelper::factory()->get_index_meta();

	/**
	 * Filter whether a CLI sync is occurring
	 *
	 * @since  3.0
	 * @hook ep_is_indexing_wpcli
	 * @param  {bool} $indexing True for indexing
	 * @return {bool} New indexing value
	 */
	return apply_filters( 'ep_is_indexing_wpcli', ( ! empty( $index_meta ) && 'cli' === $index_meta['method'] ) );
}

/**
 * Retrieve the appropriate host. Will default to EP_HOST constant if it exists
 *
 * @since 2.1
 * @return string|bool
 */
function get_host() {

	if ( defined( 'EP_HOST' ) && EP_HOST ) {
		$host = EP_HOST;
	} else {
		$host = get_option( 'ep_host', false );
	}

	/**
	 * Filter ElasticPress host to use
	 *
	 * @since  2.1
	 * @hook ep_host
	 * @param  {string} $host Current EP host
	 * @return  {string} Host to use
	 */
	return apply_filters( 'ep_host', $host );
}

/**
 * Get a site. Wraps get_site for formatting purposes
 *
 * @param  int $site_id Site/blog id
 * @since 3.2
 * @return array
 */
function get_site( $site_id ) {
	$site = \get_site( $site_id );

	return [
		'blog_id'  => $site->blog_id,
		'domain'   => $site->domain,
		'path'     => $site->path,
		'site_id'  => $site->site_id,
		'deleted'  => $site->deleted,
		'archived' => $site->archived,
		'spam'     => $site->spam,
	];
}

/**
 * Wrapper function for get_sites - allows us to have one central place for the `ep_indexable_sites` filter
 *
 * @param int  $limit          The maximum amount of sites retrieved, Use 0 to return all sites.
 * @param bool $only_indexable Whether should be returned only indexable sites or not.
 * @since 3.0, 4.7.0 added `$only_indexable`
 * @return array
 */
function get_sites( $limit = 0, $only_indexable = false ) {
	if ( ! is_multisite() ) {
		return [];
	}

	$args = [
		'limit'  => $limit,
		'number' => $limit,
	];

	if ( $only_indexable ) {
		$args = array_merge(
			$args,
			[
				'spam'       => 0,
				'deleted'    => 0,
				'archived'   => 0,
				'meta_query' => [
					'relation' => 'OR',
					[
						'key'     => 'ep_indexable',
						'value'   => 'no',
						'compare' => '!=',
					],
					[
						'key'     => 'ep_indexable',
						'compare' => 'NOT EXISTS',
					],
				],
			]
		);
	}

	/**
	 * Filter arguments to use to query for sites on network
	 *
	 * @since  2.1
	 * @hook ep_indexable_sites_args
	 * @param  {array} $args Array of args to query sites with. See WP_Site_Query
	 * @return {array} New arguments
	 */
	$args = apply_filters( 'ep_indexable_sites_args', $args );

	$site_objects = \get_sites( $args );
	$sites        = [];

	foreach ( $site_objects as $site ) {
		$sites[] = get_site( $site->blog_id );
	}

	/**
	 * Filter indexable sites
	 *
	 * @since  3.0
	 * @hook ep_indexable_sites
	 * @param  {array} $sites Current sites. Instances of WP_Site
	 * @return  {array} New array of sites
	 */
	return apply_filters( 'ep_indexable_sites', $sites );
}

/**
 * Whether plugin is network activated
 *
 * Determines whether plugin is network activated or just on the local site.
 *
 * @since 3.0
 * @param string $plugin the plugin base name.
 * @return bool True if network activated or false.
 */
function is_network_activated( $plugin ) {

	$plugins = get_site_option( 'active_sitewide_plugins' );

	if ( is_multisite() && isset( $plugins[ $plugin ] ) ) {
		return true;
	}

	return false;
}


/**
 * Performant utility function for building a term tree.
 *
 * Tree will look like this:
 * [
 *      WP_Term(
 *          name
 *          slug
 *          children ->[
 *              WP_Term()
 *          ]
 *      ),
 *      WP_Term()
 * ]
 *
 * @param  array       $all_terms Pass get_terms() as this argument where terms are objects NOT arrays.
 * @param  string|bool $orderby   Can be count|name|false. This is how each tree branch will be ordered.
 * @param  string      $order     Can be asc|desc. This is the direction ordering will occur.
 * @param  bool        $flat      If false, a tree will be returned e.g. an array of top level terms
 *                                which children linked within each node. If true, the tree will be
 *                                "flattened".
 * @since  2.5
 * @return array
 */
function get_term_tree( $all_terms, $orderby = 'count', $order = 'desc', $flat = false ) {
	$terms_map    = [];
	$terms_tree   = [];
	$iteration_id = 0;

	while ( true ) {
		if ( empty( $all_terms ) ) {
			break;
		}

		foreach ( $all_terms as $key => $term ) {
			$iteration_id++;

			if ( ! isset( $term->children ) ) {
				$term->children = [];
			}

			if ( ! isset( $terms_map[ $term->term_id ] ) ) {
				$terms_map[ $term->term_id ] = $term;
			}

			$parent_term = get_term( $term->parent, $term->taxonomy );

			if ( empty( $term->parent ) || is_wp_error( $parent_term ) || ! $parent_term ) {
				$term->level = 0;

				if ( empty( $orderby ) ) {
					$terms_tree[] = $term;
				} elseif ( 'count' === $orderby ) {
					/**
					 * We add this weird number to get past terms with the same count
					 */
					$terms_tree[ ( ( $term->count * 10000000 ) + $iteration_id ) ] = $term;
				} elseif ( 'name' === $orderby ) {
					$terms_tree[ strtolower( $term->name ) ] = $term;
				}

				unset( $all_terms[ $key ] );
			} else {
				if ( ! empty( $terms_map[ $term->parent ] ) && isset( $terms_map[ $term->parent ]->level ) ) {

					if ( empty( $orderby ) ) {
						$terms_map[ $term->parent ]->children[] = $term;
					} elseif ( 'count' === $orderby ) {
						$terms_map[ $term->parent ]->children[ ( ( $term->count * 10000000 ) + $iteration_id ) ] = $term;
					} elseif ( 'name' === $orderby ) {
						$terms_map[ $term->parent ]->children[ $term->name ] = $term;
					}

					$parent_level = ( $terms_map[ $term->parent ]->level ) ? $terms_map[ $term->parent ]->level : 0;

					$term->level       = $parent_level + 1;
					$term->parent_term = $terms_map[ $term->parent ];

					unset( $all_terms[ $key ] );
				}
			}
		}
	}

	if ( ! empty( $orderby ) ) {
		if ( 'asc' === $order ) {
			ksort( $terms_tree );
		} else {
			krsort( $terms_tree );
		}

		foreach ( $terms_map as $term ) {
			if ( 'asc' === $order ) {
				ksort( $term->children );
			} else {
				krsort( $term->children );
			}

			$term->children = array_values( $term->children );
		}

		$terms_tree = array_values( $terms_tree );
	}

	if ( $flat ) {
		$flat_tree = [];

		foreach ( $terms_tree as $term ) {
			$flat_tree[] = $term;
			$to_process  = $term->children;
			while ( ! empty( $to_process ) ) {
				$term        = array_shift( $to_process );
				$flat_tree[] = $term;

				if ( ! empty( $term->children ) ) {
					$to_process = array_merge( $term->children, $to_process );
				}
			}
		}

		return $flat_tree;
	}

	return $terms_tree;
}

/**
 * Returns the default language for ES mapping.
 *
 * @return string Default EP language.
 */
function get_language() {
	$ep_language = get_option( 'ep_language' );
	$ep_language = ! empty( $ep_language ) ? $ep_language : 'site-default';

	/**
	 * Filter the default language to use at index time
	 *
	 * @since  3.1
	 * @param {string} The current language.
	 * @hook ep_default_language
	 * @return  {string} New language
	 */
	return apply_filters( 'ep_default_language', $ep_language );
}

/**
 * Returns the status of an ongoing index operation.
 *
 * Returns the status of an ongoing index operation in array with the following fields:
 * indexing | boolean | True if index operation is ongoing or false
 * method | string | 'cli', 'web' or 'none'
 * items_indexed | integer | Total number of items indexed
 * total_items | integer | Total number of items indexed or -1 if not yet determined
 * slug | string | The slug of the indexable
 *
 * @since  3.5.2
 * @return array|boolean
 */
function get_indexing_status() {

	$index_status = false;

	$index_meta = IndexHelper::factory()->get_index_meta();

	if ( ! empty( $index_meta ) ) {
		$index_status = $index_meta;

		$index_status['indexing'] = true;

		if ( ! empty( $index_meta['current_sync_item'] ) ) {
			$index_status['items_indexed'] = $index_meta['current_sync_item']['synced'];
			$index_status['url']           = $index_meta['current_sync_item']['url'] ?? ''; // Global indexables won't have a url.
			$index_status['total_items']   = $index_meta['current_sync_item']['total'];
			$index_status['slug']          = $index_meta['current_sync_item']['indexable'];
		}

		// Change method name for retrocompatibility.
		// `dashboard` is used mainly because hooks names depend on that.
		if ( ! empty( $index_status['method'] ) && 'dashboard' === $index_status['method'] ) {
			$index_status['method'] = 'web';
		}

		if ( ! empty( $index_status['method'] ) && 'web' === $index_status['method'] ) {
			$should_interrupt_sync = filter_var(
				get_transient( 'ep_sync_interrupted' ),
				FILTER_VALIDATE_BOOLEAN
			);

			$index_status['should_interrupt_sync'] = $should_interrupt_sync;
		}
	}

	return $index_status;

}

/**
 * Use the correct update option function depending on the context (multisite or not)
 *
 * @since 3.6.0
 * @param string $option   Name of the option to update.
 * @param mixed  $value    Option value.
 * @param mixed  $autoload Whether to load the option when WordPress starts up.
 * @return bool
 */
function update_option( $option, $value, $autoload = null ) {
	if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
		return \update_site_option( $option, $value );
	}
	return \update_option( $option, $value, $autoload );
}

/**
 * Use the correct get option function depending on the context (multisite or not)
 *
 * @since 3.6.0
 * @param string $option        Name of the option to get.
 * @param mixed  $default_value Default value.
 * @return mixed
 */
function get_option( $option, $default_value = false ) {
	if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
		return \get_site_option( $option, $default_value );
	}
	return \get_option( $option, $default_value );
}

/**
 * Use the correct delete option function depending on the context (multisite or not)
 *
 * @since 3.6.0
 * @param string $option Name of the option to delete.
 * @return bool
 */
function delete_option( $option ) {
	if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
		return \delete_site_option( $option );
	}
	return \delete_option( $option );
}

/**
 * Check if queries for the current request are going to be integrated with
 * ElasticPress.
 *
 * Public requests and REST API requests are integrated by default, but admin
 * requests will only be integrated in if the `ep_admin_wp_query_integration`
 * filter returns `true`, and and admin-ajax.php requests will only be
 * integrated if the `ep_ajax_wp_query_integration` filter returns `true`.
 *
 * If specific types of requests are passed, true will only be returned if the
 * current request also matches one of the passed types.
 *
 * This function is used by features to determine whether they should hook into
 * the current request.
 *
 * @param string   $context Slug of the feature that is performing the check.
 *                          Passed to the `ep_is_integrated_request` filter.
 * @param string[] $types   Which types of request to check. Any of 'admin',
 *                          'ajax', 'public', and 'rest'. Defaults to all
 *                          types.
 * @return bool Whether the current request supports ElasticPress integration
 *              and is of a given type.
 *
 * @since 3.6.0
 */
function is_integrated_request( $context, $types = [] ) {
	if ( empty( $types ) ) {
		$types = [ 'admin', 'ajax', 'public', 'rest' ];
	}

	$is_admin_request             = is_admin();
	$is_ajax_request              = defined( 'DOING_AJAX' ) && DOING_AJAX;
	$is_rest_request              = defined( 'REST_REQUEST' ) && REST_REQUEST;
	$is_integrated_admin_request  = false;
	$is_integrated_ajax_request   = false;
	$is_integrated_public_request = false;
	$is_integrated_rest_request   = false;

	if ( $is_admin_request && ! $is_ajax_request && in_array( 'admin', $types, true ) ) {

		/**
		 * Filter whether to integrate with admin queries.
		 *
		 * @hook ep_admin_wp_query_integration
		 * @param bool $integrate True to integrate.
		 * @return bool New value.
		 */
		$is_integrated_admin_request = apply_filters( 'ep_admin_wp_query_integration', false );
	}

	if ( $is_ajax_request && in_array( 'ajax', $types, true ) ) {

		/**
		 * Filter to integrate with admin ajax queries.
		 *
		 * @hook ep_ajax_wp_query_integration
		 * @param bool $integrate True to integrate.
		 * @return bool New value.
		 */
		$is_integrated_ajax_request = apply_filters( 'ep_ajax_wp_query_integration', false );
	}

	if ( $is_rest_request && in_array( 'rest', $types, true ) ) {
		$is_integrated_rest_request = true;
	}

	if ( ! $is_admin_request && ! $is_ajax_request && ! $is_rest_request && in_array( 'public', $types, true ) ) {
		$is_integrated_public_request = true;
	}

	/**
	 * Is the current request any of the supported requests.
	 */
	$is_integrated = (
		$is_integrated_admin_request ||
		$is_integrated_ajax_request ||
		$is_integrated_public_request ||
		$is_integrated_rest_request
	);

	/**
	 * Filter whether the queries for the current request should be integrated.
	 *
	 * @hook ep_is_integrated_request
	 * @param bool   $is_integrated Whether queries for the request will be
	 *                              integrated.
	 * @param string $context       Context for the original check. Usually the
	 *                              slug of the feature doing the check.
	 * @param array  $types         Which requests types are being checked.
	 * @return bool Whether queries for the request will be integrated.
	 *
	 * @since 3.6.2
	 */
	return apply_filters( 'ep_is_integrated_request', $is_integrated, $context, $types );
}

/**
 * Get asset info from extracted asset files
 *
 * @param string $slug Asset slug as defined in build/webpack configuration
 * @param string $attribute Optional attribute to get. Can be version or dependencies
 * @return string|array
 */
function get_asset_info( $slug, $attribute = null ) {
	if ( file_exists( EP_PATH . 'dist/js/' . $slug . '.asset.php' ) ) {
		$asset = require EP_PATH . 'dist/js/' . $slug . '.asset.php';
	} elseif ( file_exists( EP_PATH . 'dist/css/' . $slug . '.asset.php' ) ) {
		$asset = require EP_PATH . 'dist/css/' . $slug . '.asset.php';
	} else {
		return null;
	}

	if ( ! empty( $attribute ) && isset( $asset[ $attribute ] ) ) {
		return $asset[ $attribute ];
	}

	return $asset;
}

/**
 * Return the Sync Page URL.
 *
 * @since 4.4.0
 * @param boolean $do_sync Whether the link should or should not start a resync.
 * @return string
 */
function get_sync_url( bool $do_sync = false ) : string {
	$page = 'admin.php?page=elasticpress-sync';
	if ( $do_sync ) {
		$page .= '&do_sync';
	}
	return ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) ?
		network_admin_url( $page ) :
		admin_url( $page );
}

/**
 * Generate a common prefix to be used while generating a request ID.
 *
 * Uses the return of `get_index_prefix()` by default.
 *
 * @since 4.5.0
 * @return string
 */
function get_request_id_base() {
	/**
	 * Filter the base of requests IDs. Uses the return of `get_index_prefix()` by default.
	 *
	 * @hook ep_request_id_base
	 * @since 4.5.0
	 * @param {string} $request_id_base Request ID base
	 * @return {string} New Request ID base
	 */
	return apply_filters( 'ep_request_id_base', str_replace( '-', '', get_index_prefix() ) );
}

/**
 * Generate a Request ID.
 *
 * The function concatenates the indices prefix to a random UUID4.
 *
 * @since 4.5.0
 * @return string
 */
function generate_request_id() : string {
	$uuid = str_replace( '-', '', wp_generate_uuid4() );

	/**
	 * Filter the ID generated to identify a request.
	 *
	 * @hook ep_request_id
	 * @since 4.5.0
	 * @param {string} $request_id Request ID. By default formed by the indices prefix and a random UUID4.
	 * @return {string} New Request ID
	 */
	return apply_filters( 'ep_request_id', get_request_id_base() . $uuid );
}

/**
 * Given an Elasticsearch response, try to find an error message.
 *
 * @since 4.6.0
 * @param mixed $response The Elasticsearch response
 * @return string
 */
function get_elasticsearch_error_reason( $response ) : string {
	if ( is_string( $response ) ) {
		return $response;
	}

	if ( ! is_array( $response ) ) {
		return var_export( $response, true ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
	}

	if ( ! empty( $response['reason'] ) ) {
		return (string) $response['reason'];
	}

	if ( ! empty( $response['result']['error'] ) && ! empty( $response['result']['error']['root_cause'][0]['reason'] ) ) {
		return (string) $response['result']['error']['root_cause'][0]['reason'];
	}

	if ( ! empty( $response['result']['errors'] ) && ! empty( $response['result']['items'] ) && ! empty( $response['result']['items'][0]['index']['error']['reason'] ) ) {
		return (string) $response['result']['items'][0]['index']['error']['reason'];
	}

	return '';
}

/**
 * Use the correct set_transient option function depending on the context (multisite or not)
 *
 * @since 4.7.0
 * @param string $transient  Transient name. Expected to not be SQL-escaped.
 *                           Must be 172 characters or fewer in length.
 * @param mixed  $value      Transient value. Must be serializable if non-scalar.
 *                           Expected to not be SQL-escaped.
 * @param int    $expiration Optional. Time until expiration in seconds. Default 0 (no expiration).
 * @return bool True if the value was set, false otherwise.
 */
function set_transient( $transient, $value, $expiration = 0 ) {
	if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
		return \set_site_transient( $transient, $value, $expiration );
	}
	return \set_transient( $transient, $value, $expiration );
}

/**
 * Use the correct get_transient function depending on the context (multisite or not)
 *
 * @since 4.7.0
 * @param string $transient Transient name. Expected to not be SQL-escaped.
 * @return mixed Value of transient.
 */
function get_transient( $transient ) {
	if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
		return \get_site_transient( $transient );
	}
	return \get_transient( $transient );
}

/**
 * Use the correct delete_transient function depending on the context (multisite or not)
 *
 * @since 4.7.0
 * @param string $transient Transient name. Expected to not be SQL-escaped.
 * @return bool True if the transient was deleted, false otherwise.
 */
function delete_transient( $transient ) {
	if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
		return \delete_site_transient( $transient );
	}
	return \delete_transient( $transient );
}

/**
 * Whether we are in the top level admin context or not.
 *
 * In a single site, the top level admin context would be `is_admin()`,
 * in a multisite, it would be `is_network_admin()`.
 *
 * @since 5.0.0
 * @return boolean
 */
function is_top_level_admin_context() {
	$is_network = defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK;
	return $is_network ? is_network_admin() : is_admin();
}
