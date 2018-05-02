<?php
/**
 * ElasticPress utility functions
 *
 * @since  2.6
 * @package elasticpress
 */

namespace ElasticPress\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Determine if ElasticPress is in the middle of an index
 *
 * @since  2.6
 * @return boolean
 */
function is_indexing() {
	if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
		$index_meta = get_site_option( 'ep_index_meta', false );
	} else {
		$index_meta = get_option( 'ep_index_meta', false );
	}

	return apply_filters( 'ep_is_indexing', ( ! empty( $index_meta ) ) );
}

/**
 * Check if wpcli indexing is occurring
 *
 * @since  2.6
 * @return boolean
 */
function is_indexing_wpcli() {
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
function get_host() {

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
 * @since  2.6
 * @return bool
 */
function host_by_option() {
	if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
		$host = get_site_option( 'ep_host', false );
	} else {
		$host = get_option( 'ep_host', false );
	}

	return ( ! empty( $host ) );
}

/**
 * Wrapper function for get_sites - allows us to have one central place for the `ep_indexable_sites` filter
 *
 * @param int $limit The maximum amount of sites retrieved, Use 0 to return all sites
 * @since  2.6
 * @return array
 */
function get_sites( $limit = 0 ) {
	$args = apply_filters( 'ep_indexable_sites_args', array(
		'limit' => $limit,
		'number' => $limit,
	) );

	if ( function_exists( 'get_sites' ) ) {
		$site_objects = get_sites( $args );
		$sites = [];

		foreach ( $site_objects as $site ) {
			$sites[] = array(
				'blog_id' => $site->blog_id,
				'domain'  => $site->domain,
				'path'    => $site->path,
				'site_id' => $site->site_id,
			);
		}
	} else {
		$sites = wp_get_sites( $args );
	}

	return apply_filters( 'ep_indexable_sites', $sites );
}

/**
 * Whether plugin is network activated
 *
 * Determines whether plugin is network activated or just on the local site.
 *
 * @since 2.6
 * @param string $plugin the plugin base name.
 * @return bool True if network activated or false
 */
function is_network_activated( $plugin ) {

	$plugins = get_site_option( 'active_sitewide_plugins');

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
 * 		WP_Term(
 * 	 		name
 * 	   		slug
 * 	     	children ->[
 * 	      		WP_Term()
 * 		    ]
 * 	    ),
 * 	    WP_Term()
 * ]
 *
 * @param  array       $all_terms Pass get_terms() as this argument where terms are objects NOT arrays
 * @param  string|bool $orderby   Can be count|name|false. This is how each tree branch will be ordered
 * @param  string      $order     Can be asc|desc. This is the direction ordering will occur.
 * @param  bool        $flat      If false, a tree will be returned e.g. an array of top level terms
 *                                which children linked within each node. If true, the tree will be
 *                                "flattened"
 * @since  2.5
 * @return array
 */
function get_term_tree( $all_terms, $orderby = 'count', $order = 'desc', $flat = false ) {
	$terms_map = [];
	$terms_tree = [];
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

					$term->level = $parent_level + 1;
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
			$to_process = $term->children;
			while ( ! empty( $to_process ) ) {
				$term = array_shift( $to_process );
				$flat_tree[] = $term;

				if ( ! empty( $term->children ) ) {
					$to_process = $term->children + $to_process;
				}
			}
		}

		return $flat_tree;
	}

	return $terms_tree;
}
