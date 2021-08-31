<?php
/**
 * ElasticPress utility functions
 *
 * @since  3.0
 * @package elasticpress
 */

namespace ElasticPress\Utils;

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
	if ( defined( 'EP_INDEX_PREFIX' ) && EP_INDEX_PREFIX ) {
		$prefix = EP_INDEX_PREFIX;
	} elseif ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK && is_epio() ) {
		$prefix = get_site_option( 'ep_prefix', false );
	} elseif ( is_epio() ) {
		$prefix = get_option( 'ep_prefix', false );

		if ( '-' !== substr( $prefix, - 1 ) ) {
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
	return preg_match( '#elasticpress\.io#i', get_host() );
}

/**
 * Determine if we should index a blog/site
 *
 * @param  int $blog_id Blog/site id.
 * @since  3.2
 * @return boolean
 */
function is_site_indexable( $blog_id = null ) {
	if ( is_multisite() ) {
		$site = get_site( $blog_id );

		$is_indexable = get_blog_option( (int) $blog_id, 'ep_indexable', 'yes' );

		if ( 'no' === $is_indexable || $site['deleted'] || $site['archived'] || $site['spam'] ) {
			return false;
		}
	}

	return true;
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
	if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
		$index_meta = get_site_option( 'ep_index_meta', false );
		$wpcli_sync = get_site_transient( 'ep_wpcli_sync' );
	} else {
		$index_meta = get_option( 'ep_index_meta', false );
		$wpcli_sync = get_transient( 'ep_wpcli_sync' );
	}

	/**
	 * Filter whether an index is occurring in dashboard or CLI
	 *
	 * @since  3.0
	 * @hook ep_is_indexing
	 * @param  {bool} $indexing True for indexing
	 * @return {bool} New indexing value
	 */
	return apply_filters( 'ep_is_indexing', ( ! empty( $index_meta ) || ! empty( $wpcli_sync ) ) );
}

/**
 * Check if wpcli indexing is occurring
 *
 * @since  3.0
 * @return boolean
 */
function is_indexing_wpcli() {
	if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
		$is_indexing = (bool) get_site_transient( 'ep_wpcli_sync' );
	} else {
		$is_indexing = (bool) get_transient( 'ep_wpcli_sync', false );
	}

	/**
	 * Filter whether a CLI sync is occuring
	 *
	 * @since  3.0
	 * @hook ep_is_indexing_wpcli
	 * @param  {bool} $indexing True for indexing
	 * @return {bool} New indexing value
	 */
	return apply_filters( 'ep_is_indexing_wpcli', $is_indexing );
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
	} elseif ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
		$host = get_site_option( 'ep_host', false );
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
 * @param int $limit The maximum amount of sites retrieved, Use 0 to return all sites.
 * @since  3.0
 * @return array
 */
function get_sites( $limit = 0 ) {

	if ( ! is_multisite() ) {
		return [];
	}

	/**
	 * Filter arguments to use to query for sites on network
	 *
	 * @since  2.1
	 * @hook ep_indexable_sites_args
	 * @param  {array} $args Array of args to query sites with. See WP_Site_Query
	 * @return {array} New arguments
	 */
	$args = apply_filters(
		'ep_indexable_sites_args',
		array(
			'limit'  => $limit,
			'number' => $limit,
		)
	);

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

			if ( empty( $term->parent ) ) {
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
 * Returns the defaiult language for ES mapping.
 *
 * @return string Default EP language.
 */
function get_language() {
	if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
		$ep_language = get_site_option( 'ep_language' );
	} else {
		$ep_language = get_option( 'ep_language' );
	}

	$ep_language = ! empty( $ep_language ) ? $ep_language : get_locale();

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

	if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {

		$dashboard_syncing = get_site_option( 'ep_index_meta', false );
		$wpcli_syncing     = get_site_transient( 'ep_wpcli_sync' );

		if ( $wpcli_syncing ) {
			$site = \get_site();
			$url  = $site->domain . $site->path;
		}
	} else {

		$dashboard_syncing = get_option( 'ep_index_meta', false );
		$wpcli_syncing     = get_transient( 'ep_wpcli_sync' );

	}

	if ( $dashboard_syncing || $wpcli_syncing ) {

		if ( $dashboard_syncing ) {

			$index_status = $dashboard_syncing;

			$should_interrupt_sync = filter_var(
				get_transient( 'ep_sync_interrupted' ),
				FILTER_VALIDATE_BOOLEAN
			);

			$index_status['should_interrupt_sync'] = $should_interrupt_sync;
		} else {
			$index_status = array(
				'indexing'      => false,
				'method'        => 'none',
				'items_indexed' => 0,
				'total_items'   => -1,
				'url'           => $url,
			);

			$index_status['indexing'] = true;

			$index_status['method'] = 'cli';

			if ( is_array( $wpcli_syncing ) ) {

				$index_status['items_indexed'] = $wpcli_syncing[0];
				$index_status['total_items']   = $wpcli_syncing[1];
				$index_status['slug']          = $wpcli_syncing[2];
			}
		}
	}

	return $index_status;

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
