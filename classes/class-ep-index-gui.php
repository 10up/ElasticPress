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

		// Add JavaScripts.
		add_action( 'admin_enqueue_scripts', array( $this, 'action_admin_enqueue_scripts' ) );

		// Add Ajax Actions.
		add_action( 'wp_ajax_ep_launch_index', array( $this, 'action_wp_ajax_ep_launch_index' ) );
		add_action( 'ep_do_settings_meta', array( $this, 'action_ep_do_settings_meta' ) );

		return $this;

	}

	/**
	 * Register and Enqueue JavaScripts
	 *
	 * Registers and enqueues the necessary JavaScripts for the interface.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function action_admin_enqueue_scripts() {

		// Enqueue more easily debugged version if applicable.
		if ( defined( 'WP_DEBUG' ) && true === WP_DEBUG ) {

			wp_register_script( 'ep_index', EP_URL . 'assets/js/elasticpress-index-admin.js', array( 'jquery', 'jquery-ui-progressbar' ), EP_VERSION );

		} else {

			wp_register_script( 'ep_index', EP_URL . 'assets/js/elasticpress-index-admin.min.js', array( 'jquery', 'jquery-ui-progressbar' ), EP_VERSION );

		}

		// Only add the following to the settings page.
		if ( isset( get_current_screen()->id ) && strpos( get_current_screen()->id, 'settings_page_elasticpress' ) !== false ) {

			wp_enqueue_script( 'ep_index' );

			$running      = 0;
			$total_posts  = 0;
			$synced_posts = 0;

			if ( false !== get_transient( 'ep_index_offset' ) ) {

				$running      = 1;
				$synced_posts = get_transient( 'ep_index_synced' );
				$total_posts  = get_transient( 'ep_post_count' );

			}

			wp_localize_script(
				'ep_index',
				'ep',
				array(
					'nonce'               => wp_create_nonce( 'ep_manual_index' ),
					'running_index_text'  => esc_html__( 'Running Index...', 'elasticpress' ),
					'index_complete_text' => esc_html__( 'Run Index', 'elasticpress' ),
					'items_indexed'       => esc_html__( 'items indexed', 'elasticpress' ),
					'sites_to_index'      => esc_html__( 'site(s) remain to be indexed', 'elasticpress' ),
					'mapping_sites'       => esc_html__( 'We are settings up your site(s) for indexing. Please be patient.', 'elasticpress' ),
					'counting_items'      => esc_html__( 'We\'re Still counting total items for the index. Please be patient', 'elasticpress' ),
					'index_running'       => $running,
					'total_posts'         => isset( $total_posts['total'] ) ? $total_posts['total'] : 0,
					'synced_posts'        => $synced_posts,
				)
			);

		}
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

		$network = false;

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			$network = true;
		}

		if ( false === get_transient( 'ep_index_offset' ) ) {

			// Deactivate our search integration.
			ep_deactivate();

			$mapping_success = ep_process_site_mappings( $network );

			if ( true !== $mapping_success ) {

				if ( false === $mapping_success ) {
					wp_send_json_error( esc_html__( 'Mappings could not be completed. If the error persists contact your system administrator', 'elasticpress' ) );
				}

				wp_send_json_success( $mapping_success );
				exit();

			}
		}

		$indexer       = new EP_Index_Worker();
		$index_success = $indexer->index( $network );

		if ( ! $index_success ) {
			wp_send_json_error( esc_html__( 'Indexing could not be completed. If the error persists contact your system administrator', 'elasticpress' ) );
		}

		if ( false === get_transient( 'ep_index_offset' ) ) {

			// Reactivate our search integration.
			ep_activate();

			$data = array(
				'ep_sync_complete' => 1,
			);

		} else {

			$total = get_transient( 'ep_post_count' );

			$data = array(
				'ep_sync_complete' => 0,
				'ep_posts_synced'  => get_transient( 'ep_index_synced' ),
				'ep_posts_total'   => absint( $total['total'] ),
			);
		}

		wp_send_json_success( $data );

	}
}
