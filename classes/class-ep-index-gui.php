<?php
/**
 * Jovo Indexing Interface
 *
 * @package Jovosearch
 *
 * @since   0.1.0
 *
 * @author  Chris Wiegman <chris.wiegman@10up.com>
 */

/**
 * Adds a minimal UI to ElasticPress
 *
 * Adds a minimal UI to ElasticPress including the ability to index from within the WordPress
 * Dashboard as well as the ability to retrieve basic statistics on the state of the
 * index.
 */
class EP_Index_GUI {

	/**
	 * Path to the class file
	 *
	 * @since 0.1.0
	 *
	 * @var string
	 */
	protected $path;

	/**
	 * Load hooks and other required information
	 *
	 * Loads various hook functions required to build the
	 * Easy EP interface.
	 *
	 * @since 0.1.0
	 *
	 * @return EP_Index_GUI
	 */
	public function __construct() {

		$this->path = trailingslashit( dirname( __FILE__ ) );

		// Load the class files.
		require( dirname( __FILE__ ) . '/class-ep-index-worker.php' );

		// Add Ajax Actions.
		add_action( 'wp_ajax_ep_launch_index', array( $this, 'action_wp_ajax_ep_launch_index' ) );
		add_action( 'wp_ajax_ep_get_site_stats', array( $this, 'action_wp_ajax_ep_get_site_stats' ) );
		add_action( 'ep_do_settings_meta', array( $this, 'action_ep_do_settings_meta' ) );

		return $this;

	}

	/**
	 * Add index settings box
	 *
	 * Adds a meta box for allowing remote indexing.
	 *
	 * @since 0.4.0
	 *
	 * @param Jovo_Settings $ep_settings Instance of Jovo_Settings.
	 *
	 * @return void
	 */
	public function action_ep_do_settings_meta( $ep_settings ) {

		add_meta_box(
			'ep-contentbox-3', 'Index Site',
			array(
				$ep_settings,
				'load_view',
			),
			$ep_settings->options_page,
			'normal',
			'core',
			array( 'index.php' )
		);

	}

	protected function _run_index() {

		$post_count    = array( 'total' => 0 );
		$post_types    = ep_get_indexable_post_types();
		$post_statuses = ep_get_indexable_post_status();

		foreach ( $post_types as $type ) {

			$type_count          = wp_count_posts( $type );
			$post_count[ $type ] = 0;

			foreach ( $post_statuses as $status ) {

				$count = absint( $type_count->$status );

				$post_count['total'] += $count;
				$post_count[ $type ] += $count;

			}
		}

		set_transient( 'ep_post_count', $post_count, 600 );

		if ( false === get_transient( 'ep_index_offset' ) ) {

			// Deactivate our search integration.
			ep_deactivate();

			$mapping_success = ep_process_site_mappings();

			if ( true !== $mapping_success ) {

				if ( false === $mapping_success ) {
					wp_send_json_error( esc_html__( 'Mappings could not be completed. If the error persists contact your system administrator', 'elasticpress' ) );
				}

				wp_send_json_success( $mapping_success );
				exit();

			}
		}

		$indexer       = new EP_Index_Worker();
		$index_success = $indexer->index();

		if ( ! $index_success ) {
			return new WP_Error( esc_html__( 'Indexing could not be completed. If the error persists contact your system administrator', 'elasticpress' ) );
		}

		$total = get_transient( 'ep_post_count' );

		if ( false === get_transient( 'ep_index_offset' ) ) {

			$data = array(
				'ep_sync_complete'  => true,
				'ep_posts_synced'   => ( false === get_transient( 'ep_index_synced' ) ? 0 : absint( get_transient( 'ep_index_synced' ) ) ),
				'ep_posts_total'    => absint( $total['total'] ),
				'ep_current_synced' => $index_success['current_synced'],
		);

		} else {

			$data = array(
				'ep_sync_complete'  => false,
				'ep_posts_synced'   => ( false === get_transient( 'ep_index_synced' ) ? 0 : absint( get_transient( 'ep_index_synced' ) ) ),
				'ep_posts_total'    => absint( $total['total'] ),
				'ep_current_synced' => $index_success['current_synced'],
		);
		}

		return $data;

	}

	/**
	 * Process manual indexing
	 *
	 * Processes the action when the manual indexing button is clicked.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function action_wp_ajax_ep_launch_index() {

		// Verify nonce and make sure this is run by an admin.
		if ( ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'ep_manual_index' ) || ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Security error!', 'elasticpress' ) );
		}

		$network = false;
		$site    = false;
		$sites   = false;
		$indexes = false;

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			$network = true;
		}

		if ( true === $network ) {

			$last_run = get_site_transient( 'ep_sites_to_index' );

			if ( false === $last_run ) {

				$sites   = ep_get_sites();
				$success = array();
				$indexes = array();

			} else {

				$sites   = ( isset( $last_run['sites'] ) ) ? $last_run['sites'] : ep_get_sites();
				$success = ( isset( $last_run['success'] ) ) ? $last_run['success'] : array();
				$indexes = ( isset( $last_run['indexes'] ) ) ? $last_run['indexes'] : array();

			}

			$site_info = array_pop( $sites );
			$site      = absint( $site_info['blog_id'] );
		}

		if ( false !== $site ) {
			switch_to_blog( $site );
		}

		$result = $this->_run_index();

		if ( false !== $site ) {

			$indexes[] = ep_get_index_name();

			if ( is_array( $result ) && isset( $result['ep_sync_complete'] ) && true === $result['ep_sync_complete'] ) {

				delete_transient( 'ep_index_synced' );
				delete_transient( 'ep_post_count' );

			}

			restore_current_blog();

		} else {

			if ( is_array( $result ) && isset( $result['ep_sync_complete'] ) && true === $result['ep_sync_complete'] ) {

				delete_transient( 'ep_index_synced' );
				delete_transient( 'ep_post_count' );

			}
		}

		if ( is_array( $result ) && isset( $result['ep_sync_complete'] ) ) {

			if ( true === $result['ep_sync_complete'] ) {

				if ( $network ) {

					$success[] = $site;

					$last_run = array(
						'sites'   => $sites,
						'success' => $success,
						'indexes' => $indexes,
					);

					set_site_transient( 'ep_sites_to_index', $last_run, 600 );

					if ( ! empty( $sites ) ) {

						$result['ep_sync_complete'] = 0;

					} else {

						$result['ep_sync_complete'] = 1;
						delete_site_transient( 'ep_sites_to_index' );
						ep_create_network_alias( $indexes );

					}
				} else {

					$result['ep_sync_complete'] = ( true === $result['ep_sync_complete'] ) ? 1 : 0;

				}

				ep_activate();

			}
		}

		if ( ! empty( $sites ) ) {

			$result['ep_sites_remaining'] = sizeof( $sites );

		} else {

			$result['ep_sites_remaining'] = 0;

		}

		$result['is_network'] = ( true === $network ) ? 1 : 0;

		wp_send_json_success( $result );

	}

	/**
	 * Process site stats
	 *
	 * Returns the HTML for stats for an individual site.
	 *
	 * @since 1.8
	 *
	 * @return void
	 */
	public function action_wp_ajax_ep_get_site_stats() {

		// Verify nonce and make sure this is run by an admin.
		if ( ! wp_verify_nonce( sanitize_text_field( $_POST['nonce'] ), 'ep_site_stats' ) || ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Security error!', 'elasticpress' ) );
		}

		$site = intval( $_POST['site'] );

		$index_stats  = ep_get_index_status( $site );
		$search_stats = ep_get_search_status( $site );

		$stats = '<div id="ep_' . $site . '" class="ep_site">';

		if ( $index_stats['status'] ) {

			$stats .= '<div class="search_stats">';
			$stats .= sprintf( '<h3>%s</h3>', esc_html__( 'Search Stats', 'elasticpress' ) );
			$stats .= '<ul>';
			$stats .= '<li>';
			$stats .= '<strong>' . esc_html__( 'Total Queries:', 'elasticpress' ) . ' </strong> ' . esc_html( $search_stats->query_total );
			$stats .= '</li>';
			$stats .= '<li>';
			$stats .= '<strong>' . esc_html__( 'Query Time:', 'elasticpress' ) . ' </strong> ' . esc_html( $search_stats->query_time_in_millis ) . 'ms';
			$stats .= '</li>';
			$stats .= '<li>';
			$stats .= '<strong>' . esc_html__( 'Total Fetches:', 'elasticpress' ) . ' </strong> ' . esc_html( $search_stats->fetch_total );
			$stats .= '</li>';
			$stats .= '<li>';
			$stats .= '<strong>' . esc_html__( 'Fetch Time:', 'elasticpress' ) . ' </strong> ' . esc_html( $search_stats->fetch_time_in_millis ) . 'ms';
			$stats .= '</li>';
			$stats .= '</ul>';
			$stats .= '</div>';
			$stats .= '<div class="index_stats">';
			$stats .= sprintf( '<h3>%s</h3>', esc_html__( 'Index Stats', 'elasticpress' ) );
			$stats .= '<ul>';
			$stats .= '<li>';
			$stats .= '<strong>' . esc_html__( 'Index Total:', 'elasticpress' ) . ' </strong> ' . esc_html( $index_stats['data']->index_total );
			$stats .= '</li>';
			$stats .= '<li>';
			$stats .= '<strong>' . esc_html__( 'Index Time:', 'elasticpress' ) . ' </strong> ' . esc_html( $index_stats['data']->index_time_in_millis ) . 'ms';
			$stats .= '</li>';
			$stats .= '</ul>';
			$stats .= '</div>';
		}
		$stats .= '</div>';

		wp_send_json_success( $stats );

	}
}
