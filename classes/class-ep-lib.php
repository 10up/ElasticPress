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
	 * Easily read bytes
	 *
	 * Converts bytes to human-readable format.
	 *
	 * @since 1.7
	 *
	 * @param int $bytes The raw bytes to convert.
	 * @param int $precision The precision with which to display the conversion.
	 *
	 * @return string
	 */
	public static function ep_byte_size( $bytes, $precision = 2 ) {

		$kilobyte = 1024;
		$megabyte = $kilobyte * 1024;
		$gigabyte = $megabyte * 1024;
		$terabyte = $gigabyte * 1024;

		if ( ( $bytes >= 0 ) && ( $bytes < $kilobyte ) ) {

			return $bytes . ' B';

		} elseif ( ( $bytes >= $kilobyte ) && ( $bytes < $megabyte ) ) {

			return round( $bytes / $kilobyte, $precision ) . ' KB';

		} elseif ( ( $bytes >= $megabyte ) && ( $bytes < $gigabyte ) ) {

			return round( $bytes / $megabyte, $precision ) . ' MB';

		} elseif ( ( $bytes >= $gigabyte ) && ( $bytes < $terabyte ) ) {

			return round( $bytes / $gigabyte, $precision ) . ' GB';

		} elseif ( $bytes >= $terabyte ) {

			return round( $bytes / $terabyte, $precision ) . ' TB';

		} else {

			return $bytes . ' B';

		}
	}

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
