<?php
/**
 * Sync (Dashboard Index) functionality
 *
 * @since  3.6.0
 * @package elasticpress
 */

namespace ElasticPress\Screen;

use ElasticPress\Features as Features;
use ElasticPress\Screen as Screen;
use ElasticPress\Utils as Utils;
use ElasticPress\Elasticsearch as Elasticsearch;
use ElasticPress\Indexables as Indexables;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Sync
 *
 * @since  3.6.0
 * @package ElasticPress
 */
class Sync {
	/**
	 * Initialize class
	 */
	public function setup() {
		add_action( 'wp_ajax_ep_cli_index', [ $this, 'action_wp_ajax_ep_cli_index' ] );
		add_action( 'wp_ajax_ep_index', [ $this, 'action_wp_ajax_ep_index' ] );
		add_action( 'wp_ajax_ep_cancel_index', [ $this, 'action_wp_ajax_ep_cancel_index' ] );

		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
	}

	/**
	 * Getting the status of ongoing index fired by WP CLI
	 *
	 * @since  3.6.0
	 */
	public function action_wp_ajax_ep_cli_index() {
		if ( ! check_ajax_referer( 'ep_dashboard_nonce', 'nonce', false ) || ! EP_DASHBOARD_SYNC ) {
			wp_send_json_error();
			exit;
		}

		$index_meta = Utils\get_indexing_status();

		if ( isset( $index_meta['method'] ) && 'cli' === $index_meta['method'] ) {
			wp_send_json_success( $index_meta );
		}

		wp_send_json_success( array( 'is_finished' => true ) );
	}

	/**
	 * Perform index
	 *
	 * @since 3.6.0
	 */
	public function action_wp_ajax_ep_index() {
		if ( ! check_ajax_referer( 'ep_dashboard_nonce', 'nonce', false ) || ! EP_DASHBOARD_SYNC ) {
			wp_send_json_error();
			exit;
		}

		$index_meta = Utils\get_indexing_status();

		if ( isset( $index_meta['method'] ) && 'cli' === $index_meta['method'] ) {
			wp_send_json_success( $index_meta );
			exit;
		}

		\ElasticPress\IndexHelper::factory()->full_index(
			[
				'output_method' => [ $this, 'index_output' ],
			]
		);

		$global_indexables     = Indexables::factory()->get_all( true, true );
		$non_global_indexables = Indexables::factory()->get_all( false, true );

		// No current index going on. Let's start over.
		if ( false === $index_meta ) {
			$index_meta = [
				'offset'     => 0,
				'start'      => true,
				'sync_stack' => [],
			];

			Utils\update_option( 'ep_last_sync', time() );
			Utils\delete_option( 'ep_need_upgrade_sync' );
			Utils\delete_option( 'ep_feature_auto_activated_sync' );

			if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
				$sites = Utils\get_sites();

				foreach ( $sites as $site ) {
					if ( ! Utils\is_site_indexable( $site['blog_id'] ) ) {
						continue;
					}

					foreach ( $non_global_indexables as $indexable ) {
						$index_meta['sync_stack'][] = [
							'url'       => untrailingslashit( $site['domain'] . $site['path'] ),
							'blog_id'   => (int) $site['blog_id'],
							'indexable' => $indexable,
						];
					}
				}

				if ( 0 === count( $index_meta['sync_stack'] ) && empty( $global_indexables ) ) {
					wp_send_json_error(
						[
							'found_items' => 0,
							'offset'      => 0,
						]
					);

					return;
				}
			} else {
				foreach ( $non_global_indexables as $indexable ) {
					$index_meta['sync_stack'][] = [
						'url'       => untrailingslashit( home_url() ),
						'blog_id'   => (int) get_current_blog_id(),
						'indexable' => $indexable,
					];
				}
			}

			$index_meta['current_sync_item'] = array_shift( $index_meta['sync_stack'] );

			if ( ! empty( $_POST['feature_sync'] ) ) {
				$index_meta['feature_sync'] = esc_attr( $_POST['feature_sync'] );
			}

			// Handle global indexables case if non globals disabled
			if ( 0 === count( $index_meta['sync_stack'] ) && ! in_array( $index_meta['current_sync_item']['indexable'], $non_global_indexables, true ) ) {
				foreach ( $sites as $site ) {
					foreach ( $global_indexables as $indexable ) {
						$index_meta['sync_stack'][] = [
							'url'       => untrailingslashit( $site['domain'] . $site['path'] ),
							'blog_id'   => (int) $site['blog_id'],
							'indexable' => $indexable,
						];
					}
				}
				$index_meta['current_sync_item'] = array_shift( $index_meta['sync_stack'] );

			} else {
				foreach ( $global_indexables as $indexable ) {
					$index_meta['sync_stack'][] = [
						'indexable' => $indexable,
					];
				}
			}

			/**
			 * Fires at start of new index
			 *
			 * @since  2.1
			 * @hook ep_dashboard_start_index
			 * @param  {array} $index_meta Index meta information
			 */
			do_action( 'ep_dashboard_start_index', $index_meta );
		} elseif ( ! empty( $index_meta['sync_stack'] ) && $index_meta['offset'] >= $index_meta['found_items'] ) {
			$index_meta['start']             = true;
			$index_meta['offset']            = 0;
			$index_meta['current_sync_item'] = array_shift( $index_meta['sync_stack'] );
		} else {
			$index_meta['start'] = false;
		}

		/**
		 * Filter index meta during dashboard sync
		 *
		 * @since  3.0
		 * @hook ep_index_meta
		 * @param  {array} $index_meta Current index meta
		 * @return  {array} New index meta
		 */
		$index_meta = apply_filters( 'ep_index_meta', $index_meta );
		$indexable  = Indexables::factory()->get( $index_meta['current_sync_item']['indexable'] );

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK && ! empty( $index_meta['current_sync_item']['blog_id'] ) ) {
			switch_to_blog( $index_meta['current_sync_item']['blog_id'] );
		}

		if ( ! empty( $index_meta['start'] ) ) {
			/**
			 * Filter whether we should delete index and send new mapping at the start of the sync
			 *
			 * @since  2.1
			 * @hook ep_skip_index_reset
			 * @param  {bool} $skip True means skip
			 * @param  {array} $index_meta Current index meta
			 * @return  {bool} New skip value
			 */
			if ( ! apply_filters( 'ep_skip_index_reset', false, $index_meta ) ) {
				$indexable->delete_index();

				$indexable->put_mapping();

				/**
				 * Fires after dashboard put mapping is completed
				 *
				 * @since  2.1
				 * @hook ep_dashboard_put_mapping
				 * @param  {array} $index_meta Index meta information
				 * @param  {string} $status Current indexing status
				 */
				do_action( 'ep_dashboard_put_mapping', $index_meta, 'start' );
			}
		}

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			$bulk_setting = get_site_option( 'ep_bulk_setting', 350 );
		} else {
			$bulk_setting = get_option( 'ep_bulk_setting', 350 );
		}

		/**
		 * Filter number of items to index per cycle in the dashboard
		 *
		 * @since  2.1
		 * @hook ep_index_default_per_page
		 * @param  {int} Entries per cycle
		 * @return  {int} New number of entries
		 */
		$per_page = apply_filters( 'ep_index_default_per_page', $bulk_setting );

		/**
		 * Fires right before entries are about to be indexed in a dashboard sync
		 *
		 * @since  2.1
		 * @hook ep_pre_dashboard_index
		 * @param  {array} $args Args to query content with
		 */
		do_action( 'ep_pre_dashboard_index', $index_meta, ( $index_meta['start'] ? 'start' : false ), $indexable );

		/**
		 * Filters arguments used to query for content for each indexable
		 *
		 * @since  3.0
		 * @hook ep_dashboard_index_args
		 * @param  {array} $args Args to query content with
		 * @return  {array} New query args
		 */
		$args = apply_filters(
			'ep_dashboard_index_args',
			[
				'posts_per_page' => $per_page,
				'offset'         => $index_meta['offset'],
			]
		);

		$query = $indexable->query_db( $args );

		$index_meta['found_items'] = (int) $query['total_objects'];

		if ( $index_meta['start'] ) {
			if ( ! empty( $query['objects'] ) ) {
				$queued_items = [];

				foreach ( $query['objects'] as $object ) {
					$killed_item_count = 0;

					/**
					 * Filter whether to not sync sepcific item in dashboard or not
					 *
					 * @since  2.1
					 * @hook ep_item_sync_kill
					 * @param  {boolean} $kill False means dont sync
					 * @param  {array} $object Object to sync
					 * @return {Indexable} Indexable that object belongs to
					 */
					if ( apply_filters( 'ep_item_sync_kill', false, $object, $indexable ) ) {
						$killed_item_count++;
					} else {
						$queued_items[ $object->ID ] = true;
					}
				}

				if ( ! empty( $queued_items ) ) {
					$return = $indexable->bulk_index( array_keys( $queued_items ) );

					if ( is_wp_error( $return ) ) {
						header( 'HTTP/1.1 500 Internal Server Error' );
						wp_send_json_error();
						exit;
					}
				}

				$index_meta['offset'] = absint( $index_meta['offset'] + $per_page );

				if ( $index_meta['offset'] >= $index_meta['found_items'] ) {
					$index_meta['offset'] = $index_meta['found_items'];
				}

				if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
					update_site_option( 'ep_index_meta', $index_meta );
				} else {
					update_option( 'ep_index_meta', $index_meta );
				}
			} else {
				// We are done (with this site).
				if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
					if ( empty( $index_meta['sync_stack'] ) ) {
						delete_site_option( 'ep_index_meta' );

						$sites = Utils\get_sites();

						foreach ( $non_global_indexables as $indexable_slug ) {
							$indexes          = [];
							$indexable_object = Indexables::factory()->get( $indexable_slug );

							foreach ( $sites as $site ) {
								switch_to_blog( $site['blog_id'] );
								$indexes[] = $indexable_object->get_index_name();
								restore_current_blog();
							}

							$indexable_object->create_network_alias( $indexes );
						}

							/**
							 * Fires after executing a reindex via Dashboard
							 *
							 * @since  3.5.5
							 * @hook ep_after_dashboard_index
							 */
							do_action( 'ep_after_dashboard_index' );
					} else {
						$index_meta['offset'] = (int) $query['total_objects'];
					}
				} else {
					$index_meta['offset'] = (int) $query['total_objects'];

					delete_option( 'ep_index_meta' );

					/* This action is documented in this file */
					do_action( 'ep_after_dashboard_index' );
				}
			}
		} else {
			Utils\update_option( 'ep_index_meta', $index_meta );
		}

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK && ! empty( $index_meta['current_sync_item']['blog_id'] ) ) {
			restore_current_blog();
		}

		wp_send_json_success( $index_meta );
	}

	/**
	 * Cancel index
	 *
	 * @since 3.6.0
	 */
	public function action_wp_ajax_ep_cancel_index() {
		if ( ! check_ajax_referer( 'ep_dashboard_nonce', 'nonce', false ) || ! EP_DASHBOARD_SYNC ) {
			wp_send_json_error();
			exit;
		}

		$index_meta = Utils\get_indexing_status();

		if ( isset( $index_meta['method'] ) && 'cli' === $index_meta['method'] ) {
			set_transient( 'ep_wpcli_sync_interrupted', true, 5 );
			wp_send_json_success();
			exit;
		}

		Utils\delete_option( 'ep_index_meta' );

		wp_send_json_success();
	}

	/**
	 * Enqueue script.
	 *
	 * @since 3.6.0
	 * @return void
	 */
	public function admin_enqueue_scripts() {
		if ( 'sync' !== Screen::factory()->get_current_screen() ) {
			return;
		}
		wp_enqueue_script( 'ep_dashboard_scripts', EP_URL . 'dist/js/sync-script.min.js', [ 'jquery' ], EP_VERSION, true );

		$data       = array( 'nonce' => wp_create_nonce( 'ep_dashboard_nonce' ) );
		$index_meta = Utils\get_indexing_status();

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			$wpcli_sync           = (bool) get_site_transient( 'ep_wpcli_sync' );
			$install_complete_url = admin_url( 'network/admin.php?page=elasticpress&install_complete' );
			$last_sync            = get_site_option( 'ep_last_sync', false );
		} else {
			$wpcli_sync           = (bool) get_transient( 'ep_wpcli_sync' );
			$install_complete_url = admin_url( 'admin.php?page=elasticpress&install_complete' );
			$last_sync            = get_option( 'ep_last_sync', false );
		}

		if ( ! empty( $wpcli_sync ) ) {
			$index_meta['wpcli_sync'] = true;
		}

		if ( isset( $_GET['do_sync'] ) && ( ! defined( 'EP_DASHBOARD_SYNC' ) || EP_DASHBOARD_SYNC ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$data['auto_start_index'] = true;
		}

		if ( ! empty( $index_meta ) ) {
			$data['index_meta'] = $index_meta;
		}

		/**
		 * Filter indexable labels used in dashboard sync UI
		 *
		 * @since  3.0
		 * @hook ep_dashboard_indexable_labels
		 * @param  {array} $labels Current indexable lables
		 * @return {array} New labels
		 */
		$data['sync_indexable_labels'] = apply_filters(
			'ep_dashboard_indexable_labels',
			[
				'post' => [
					'singular' => esc_html__( 'Post', 'elasticpress' ),
					'plural'   => esc_html__( 'Posts', 'elasticpress' ),
				],
				'term' => [
					'singular' => esc_html__( 'Term', 'elasticpress' ),
					'plural'   => esc_html__( 'Terms', 'elasticpress' ),
				],
				'user' => [
					'singular' => esc_html__( 'User', 'elasticpress' ),
					'plural'   => esc_html__( 'Users', 'elasticpress' ),
				],
			]
		);

		$data['install_sync']         = empty( $last_sync );
		$data['install_complete_url'] = esc_url( $install_complete_url );
		$data['sync_complete']        = esc_html__( 'Sync complete', 'elasticpress' );
		$data['sync_paused']          = esc_html__( 'Sync paused', 'elasticpress' );
		$data['sync_syncing']         = esc_html__( 'Syncing', 'elasticpress' );
		$data['sync_initial']         = esc_html__( 'Starting sync', 'elasticpress' );
		$data['sync_wpcli']           = esc_html__( 'WP CLI sync is occurring.', 'elasticpress' );
		$data['sync_error']           = esc_html__( 'An error occurred while syncing', 'elasticpress' );
		$data['sync_interrupted']     = esc_html__( 'Sync interrupted.', 'elasticpress' );

		wp_localize_script( 'ep_dashboard_scripts', 'epDash', $data );
	}

	/**
	 * Output information received from the index helper class.
	 *
	 * @param array $message Message to be outputted with its status and additional info, if needed.
	 */
	public static function index_output( $message ) {
		switch ( $message['status'] ) {
			case 'success':
				wp_send_json_success( $message );
				break;

			case 'error':
				wp_send_json_error( $message );
				break;

			default:
				wp_send_json( $message );
				break;
		}
		exit;
	}
}
