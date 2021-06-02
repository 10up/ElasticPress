<?php
/**
 * Index Helper
 *
 * @since  3.6.0
 * @package elasticpress
 */

namespace ElasticPress;

use ElasticPress\Utils as Utils;

/**
 * Index Helper Class.
 *
 * @since 3.6.0
 */
class IndexHelper {
	/**
	 * Array to hold all the index sync information.
	 *
	 * @since 3.6.0
	 * @var array|bool
	 */
	protected $index_meta = false;

	/**
	 * Arguments to be used during the index process.
	 *
	 * @var array
	 */
	protected $args = [];

	/**
	 * Queried objects of the current sync item in the stack.
	 *
	 * @since 3.6.0
	 * @var array
	 */
	protected $current_query = [];

	/**
	 * Initialize class.
	 *
	 * @since 3.6.0
	 */
	public function setup() {
		$this->index_meta = Utils\get_indexing_status();
	}

	/**
	 * Method to index everything.
	 *
	 * @since 3.6.0
	 * @param array $args Arguments.
	 */
	public function full_index( $args ) {
		$this->index_meta = Utils\get_indexing_status();
		$this->args       = $args;

		if ( false === $this->index_meta ) {
			$this->build_index_meta();
		}

		while ( $this->has_items_to_be_processed() ) {
			$this->process_sync_item();
		}

		while ( $this->has_network_alias_to_be_created() ) {
			$this->create_network_alias();
		}

		$this->full_index_complete();
	}

	/**
	 * Method to stack everything that needs to be indexed.
	 *
	 * @since 3.6.0
	 */
	protected function build_index_meta() {
		Utils\update_option( 'ep_last_sync', time() );
		Utils\delete_option( 'ep_need_upgrade_sync' );
		Utils\delete_option( 'ep_feature_auto_activated_sync' );

		$this->index_meta = [
			'offset'        => 0,
			'start'         => true,
			'sync_stack'    => [],
			'network_alias' => [],
		];

		$global_indexables     = Indexables::factory()->get_all( true, true );
		$non_global_indexables = Indexables::factory()->get_all( false, true );

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			$sites = Utils\get_sites();

			foreach ( $sites as $site ) {
				if ( ! Utils\is_site_indexable( $site['blog_id'] ) ) {
					continue;
				}

				foreach ( $non_global_indexables as $indexable ) {
					$this->index_meta['sync_stack'][] = [
						'url'         => untrailingslashit( $site['domain'] . $site['path'] ),
						'blog_id'     => (int) $site['blog_id'],
						'indexable'   => $indexable,
						'put_mapping' => true,
					];

					if ( ! in_array( $indexable, $this->index_meta['network_alias'], true ) ) {
						$this->index_meta['network_alias'][] = $indexable;
					}
				}
			}
		} else {
			foreach ( $non_global_indexables as $indexable ) {
				$this->index_meta['sync_stack'][] = [
					'url'         => untrailingslashit( home_url() ),
					'blog_id'     => (int) get_current_blog_id(),
					'indexable'   => $indexable,
					'put_mapping' => true,
				];
			}
		}

		foreach ( $global_indexables as $indexable ) {
			$this->index_meta['sync_stack'][] = [
				'indexable'   => $indexable,
				'put_mapping' => true,
			];
		}
	}

	/**
	 * Check if there are still items to be processed in the stack.
	 *
	 * @since 3.6.0
	 * @return boolean
	 */
	protected function has_items_to_be_processed() {
		return ! empty( $this->index_meta['current_sync_item'] ) || count( $this->index_meta['sync_stack'] ) > 0;
	}

	/**
	 * Method to process the next item in the stack.
	 *
	 * @since 3.6.0
	 */
	protected function process_sync_item() {
		if ( empty( $this->index_meta['current_sync_item'] ) ) {
			$this->index_meta['current_sync_item'] = array_shift( $this->index_meta['sync_stack'] );

			$indexable = Indexables::factory()->get( $this->index_meta['current_sync_item']['indexable'] );

			if ( ! empty( $this->index_meta['current_sync_item']['blog_id'] ) ) {
				$this->output_success(
					sprintf(
						/* translators: 1: Indexable name, 2: Site ID */
						esc_html__( 'Indexing %1$s on site %2$d...', 'elasticpress' ),
						esc_html( strtolower( $indexable->labels['plural'] ) ),
						$this->index_meta['current_sync_item']['blog_id']
					)
				);
			} else {
				$this->output_success(
					sprintf(
						/* translators: 1: Indexable name */
						esc_html__( 'Indexing %1$s (globally)...', 'elasticpress' ),
						esc_html( strtolower( $indexable->labels['plural'] ) )
					)
				);
			}
		}

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK && ! empty( $this->index_meta['current_sync_item']['blog_id'] ) ) {
			switch_to_blog( $this->index_meta['current_sync_item']['blog_id'] );
		}

		if ( $this->index_meta['current_sync_item']['put_mapping'] ) {
			$this->put_mapping();
		}

		$this->index_objects();

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK && ! empty( $this->index_meta['current_sync_item']['blog_id'] ) ) {
			restore_current_blog();
		}
	}

	/**
	 * Delete an index and recreate it sending the mapping.
	 *
	 * @since 3.6.0
	 */
	protected function put_mapping() {
		$indexable = Indexables::factory()->get( $this->index_meta['current_sync_item']['indexable'] );

		$indexable->delete_index();
		$result = $indexable->put_mapping();

		$this->index_meta['current_sync_item']['put_mapping'] = false;

		if ( $result ) {
			$this->output_success( esc_html__( 'Mapping sent', 'elasticpress' ) );
		} else {
			$this->output_error( esc_html__( 'Mapping failed', 'elasticpress' ) );
		}
	}

	/**
	 * Index documents of an index.
	 *
	 * @since 3.6.0
	 */
	protected function index_objects() {
		$this->current_query = $this->get_objects_to_index();

		$this->index_meta['found_items'] = (int) $this->current_query['total_objects'];

		if ( $this->index_meta['found_items'] && $this->index_meta['offset'] < $this->index_meta['found_items'] ) {
			$this->index_next_batch();
		} else {
			$this->index_cleanup();
		}
	}

	/**
	 * Query the next objects to be indexed.
	 *
	 * @since 3.6.0
	 * @return array
	 */
	protected function get_objects_to_index() {
		$indexable = Indexables::factory()->get( $this->index_meta['current_sync_item']['indexable'] );

		/**
		 * Filter number of items to index per cycle in the dashboard
		 *
		 * @since  2.1
		 * @hook ep_index_default_per_page
		 * @param  {int} Entries per cycle
		 * @return  {int} New number of entries
		 */
		$per_page = apply_filters( 'ep_index_default_per_page', Utils\get_option( 'ep_bulk_setting', 350 ) );

		/**
		 * Fires right before entries are about to be indexed in a dashboard sync
		 *
		 * @since  2.1
		 * @hook ep_pre_dashboard_index
		 * @param  {array} $args Args to query content with
		 */
		do_action( 'ep_pre_dashboard_index', $this->index_meta, ( $this->index_meta['start'] ? 'start' : false ), $indexable );

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
				'per_page' => $per_page,
				'offset'   => $this->index_meta['offset'],
			]
		);

		return $indexable->query_db( $args );
	}

	/**
	 * Index the next batch of documents.
	 *
	 * @since 3.6.0
	 */
	protected function index_next_batch() {
		$indexable = Indexables::factory()->get( $this->index_meta['current_sync_item']['indexable'] );

		$queued_items = [];

		foreach ( $this->current_query['objects'] as $object ) {
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

		$this->index_meta['offset'] = absint( $this->index_meta['offset'] + count( $this->current_query['objects'] ) );

		$this->output_success(
			sprintf(
				/* translators: 1. Number of objects indexed, 2. Total number of objects */
				esc_html__( 'Processed %1$d/%2$d...', 'elasticpress' ),
				$this->index_meta['offset'],
				$this->index_meta['found_items']
			)
		);
	}

	/**
	 * Make the necessary clean up after a sync item of the stack was completely done.
	 *
	 * @since 3.6.0
	 * @return void
	 */
	protected function index_cleanup() {
		$this->index_meta['offset']            = 0;
		$this->index_meta['current_sync_item'] = null;
	}

	/**
	 * Make the necessary clean up after everything was sync'd.
	 *
	 * @since 3.6.0
	 */
	protected function full_index_complete() {
		$this->index_meta = null;

		$this->output_success( esc_html__( 'Sync complete', 'elasticpress' ) );
	}

	/**
	 * Check if network aliases need to be created.
	 *
	 * @since 3.6.0
	 * @return boolean
	 */
	protected function has_network_alias_to_be_created() {
		return count( $this->index_meta['network_alias'] ) > 0;
	}

	/**
	 * Create the next network alias.
	 *
	 * @since 3.6.0
	 */
	protected function create_network_alias() {
		$indexes   = [];
		$indexable = Indexables::factory()->get( array_shift( $this->index_meta['network_alias'] ) );

		$sites = Utils\get_sites();

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );
			$indexes[] = $indexable->get_index_name();
			restore_current_blog();
		}

		$result = $indexable->create_network_alias( $indexes );

		if ( $result ) {
			$this->output_success(
				sprintf(
					/* translators: 1: Indexable name */
					esc_html__( 'Network alias created for %1$s ...', 'elasticpress' ),
					esc_html( strtolower( $indexable->labels['plural'] ) )
				)
			);
		} else {
			$this->output_error(
				sprintf(
					/* translators: 1: Indexable name */
					esc_html__( 'Network alias creation failed for %1$s ...', 'elasticpress' ),
					esc_html( strtolower( $indexable->labels['plural'] ) )
				)
			);
		}
	}

	/**
	 * Output a message.
	 *
	 * @since 3.6.0
	 * @param array $message Message to be outputted with its status and additional info, if needed.
	 * @return void
	 */
	protected function output( $message ) {
		if ( $this->index_meta ) {
			Utils\update_option( 'ep_index_meta', $this->index_meta );
		} else {
			Utils\delete_option( 'ep_index_meta' );
		}

		if ( is_callable( $this->args['output_method'] ) ) {
			call_user_func( $this->args['output_method'], $message );
		}
	}

	/**
	 * Wrapper to the `output` method with a success message.
	 *
	 * @since 3.6.0
	 * @param string $message Message string.
	 */
	protected function output_success( $message ) {
		$this->output(
			[
				'message'    => $message,
				'index_meta' => $this->index_meta,
				'status'     => 'success',
			]
		);
	}

	/**
	 * Wrapper to the `output` method with an error message.
	 *
	 * @since 3.6.0
	 * @param string $message Message string.
	 */
	protected function output_error( $message ) {
		$this->output(
			[
				'message' => $message,
				'status'  => 'error',
			]
		);
	}

	/**
	 * Return singleton instance of class.
	 *
	 * @return self
	 * @since 3.6.0
	 */
	public static function factory() {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new self();
			$instance->setup();
		}

		return $instance;
	}
}
