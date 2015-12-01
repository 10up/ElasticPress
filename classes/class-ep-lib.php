<?php
/**
 * ElasticPress utility functions
 *
 * @package elasticpress
 *
 * @since   1.7
 *
 * @author  Chris Wiegman <chris.wiegman@10up.com>
 */

/**
 * ElasticPress utility functions
 *
 * Various utility functions for use throughout ElasticPress
 */
class EP_Lib {

	/**
	 * Add the document mapping
	 *
	 * Creates the document mapping for the index.
	 *
	 * @since      1.7
	 *
	 * @param bool $network_wide whether to index network wide or not.
	 *
	 * @return bool true on success or false
	 */
	public static function put_mapping( $network_wide = false ) {

		ep_check_host();

		if ( true === $network_wide && is_multisite() ) {

			$sites   = ep_get_sites();
			$success = array();

			foreach ( $sites as $site ) {

				switch_to_blog( $site['blog_id'] );

				// Deletes index first.
				ep_delete_index();

				$result = ep_put_mapping();

				if ( $result ) {

					$success[ $site['blog_id'] ] = true;

				} else {

					$success[ $site['blog_id'] ] = false;

				}

				restore_current_blog();
			}

			if ( array_search( false, $success ) ) {
				return $success;
			}

			return true;

		} else {

			// Deletes index first.
			ep_delete_index();

			$result = ep_put_mapping();

			if ( $result ) {
				return true;
			}

			return false;

		}
	}


}
