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
	 * Retrieve Index status
	 *
	 * Retrieves index stats from ElasticSearch.
	 *
	 * @since 1.7
	 *
	 * @param int $blog_id Id of blog to get stats.
	 *
	 * @return array Contains the status message or the returned statistics.
	 */
	public static function ep_get_index_status( $blog_id = null ) {

		return EP_Lib::get_index_status( $blog_id );

	}

	/**
	 * Retrieve cluster stats
	 *
	 * Retrieves cluster stats from ElasticSearch.
	 *
	 * @since 1.7
	 *
	 * @return array Contains the status message or the returned statistics.
	 */
	public static function ep_get_cluster_status() {

		return EP_Lib::get_cluster_status();

	}

	/**
	 * Retrieve search stats
	 *
	 * Retrieves search stats from ElasticSearch.
	 *
	 * @since 1.7
	 *
	 * @param int $blog_id Id of blog to get stats.
	 *
	 * @return array Contains the status message or the returned statistics.
	 */
	public static function ep_get_search_status( $blog_id = null ) {

		return EP_Lib::get_search_status( $blog_id );

	}

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

	/**
	 * Get cluster status
	 *
	 * Retrieves cluster stats from ElasticSearch.
	 *
	 * @since 1.7
	 *
	 * @return array Contains the status message or the returned statistics.
	 */
	public static function get_cluster_status() {

		if ( is_wp_error( ep_get_host() ) ) {

			return array(
				'status' => false,
				'msg'    => esc_html__( 'ElasticSearch Host is not available.', 'elasticpress' ),
			);

		} else {

			$request = ep_remote_request( '_cluster/stats', array( 'method' => 'GET' ) );

			if ( ! is_wp_error( $request ) ) {

				$response = json_decode( wp_remote_retrieve_body( $request ) );

				return $response;

			}

			return array(
				'status' => false,
				'msg'    => $request->get_error_message(),
			);

		}
	}

	/**
	 * Get index status
	 *
	 * Retrieves index stats from ElasticSearch.
	 *
	 * @since 1.7
	 *
	 * @param int $blog_id Id of blog to get stats.
	 *
	 * @return array Contains the status message or the returned statistics.
	 */
	public static function get_index_status( $blog_id = null ) {

		if ( is_wp_error( ep_get_host( true ) ) ) {

			return array(
				'status' => false,
				'msg'    => esc_html__( 'ElasticSearch Host is not available.', 'elasticpress' ),
			);

		} else {

			if ( is_multisite() && null === $blog_id ) {

				$path = ep_get_network_alias() . '/_stats/indexing/';

			} else {

				$path = ep_get_index_name( $blog_id ) . '/_stats/indexing/';

			}

			$request = ep_remote_request( $path, array( 'method' => 'GET' ) );

		}

		if ( ! is_wp_error( $request ) ) {

			$response = json_decode( wp_remote_retrieve_body( $request ) );

			return ep_parse_api_resonse( $response );

		}

		return array(
			'status' => false,
			'msg'    => $request->get_error_message(),
		);

	}

	/**
	 * Retrieves search stats from ElasticSearch.
	 *
	 * Retrieves various search statistics from the ES server.
	 *
	 * @since 1.0.0
	 *
	 * @param int $blog_id Id of blog to get stats.
	 *
	 * @return array Contains the status message or the returned statistics.
	 */
	public static function get_search_status( $blog_id = null ) {

		if ( is_wp_error( ep_get_host() ) ) {

			return array(
				'status' => false,
				'msg'    => esc_html__( 'ElasticSearch Host is not available.', 'elasticpress' ),
			);

		} else {

			if ( is_multisite() && null === $blog_id ) {

				$path = ep_get_network_alias() . '/_stats/search/';

			} else {

				$path = ep_get_index_name( $blog_id ) . '/_stats/search/';

			}

			$request = ep_remote_request( $path, array( 'method' => 'GET' ) );

		}

		if ( ! is_wp_error( $request ) ) {

			$stats = json_decode( wp_remote_retrieve_body( $request ) );

			if ( isset( $stats->_all ) ) {
				return $stats->_all->primaries->search;
			}

			return false;

		}

		return array(
			'status' => false,
			'msg'    => $request->get_error_message(),
		);

	}
}
