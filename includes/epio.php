<?php
/**
 * Setup ElasticPress.io things
 *
 * @package  elasticpress
 */

namespace ElasticPress\EPIO;

use ElasticPress\Utils;

/**
 * Setup ES prefix constant
 *
 * @since  3.0
 */
function setup_prefix_constant() {
	if ( ! defined( 'EP_INDEX_PREFIX' ) ) {
		$index_prefix = Utils\get_index_prefix();

		if ( $index_prefix ) {
			$index_prefix = ( Utils\is_epio() && '-' !== substr( $index_prefix, - 1 ) ) ? $index_prefix . '-' : $index_prefix;

			define( 'EP_INDEX_PREFIX', $index_prefix );
		}
	}
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\setup_prefix_constant' );

/**
 * Setup ES Shield
 *
 * @since  3.0
 */
function setup_shield() {
	if ( ! defined( 'ES_SHIELD' ) && Utils\is_epio() ) {
		$credentials = Utils\get_epio_credentials();

		define( 'ES_SHIELD', $credentials['username'] . ':' . $credentials['token'] );
	}
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\setup_shield' );
