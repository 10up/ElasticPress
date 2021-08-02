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
	 * Holds temporary wp_actions when indexing with pagination
	 *
	 * @since 3.6.0
	 * @var  array
	 */
	private $temporary_wp_actions = [];

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
			'offset'        => ! empty( $this->args['offset'] ) ? absint( $this->args['offset'] ) : 0,
			'start'         => true,
			'sync_stack'    => [],
			'network_alias' => [],
			'start_time'    => microtime( true ),
			'totals'        => [
				'total'      => 0,
				'synced'     => 0,
				'skipped'    => 0,
				'failed'     => 0,
				'total_time' => 0,
				'errors'     => [],
			],
		];

		$global_indexables     = $this->filter_indexables( Indexables::factory()->get_all( true, true ) );
		$non_global_indexables = $this->filter_indexables( Indexables::factory()->get_all( false, true ) );

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			if ( empty( $this->args['network_wide'] ) || ! is_numeric( $this->args['network_wide'] ) ) {
				$this->args['network_wide'] = 0;
			}

			$sites = Utils\get_sites( $this->args['network_wide'] );

			foreach ( $sites as $site ) {
				if ( ! Utils\is_site_indexable( $site['blog_id'] ) ) {
					continue;
				}

				foreach ( $non_global_indexables as $indexable ) {
					$this->index_meta['sync_stack'][] = [
						'url'         => untrailingslashit( $site['domain'] . $site['path'] ),
						'blog_id'     => (int) $site['blog_id'],
						'indexable'   => $indexable,
						'put_mapping' => ! empty( $this->args['put_mapping'] ),
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
					'put_mapping' => ! empty( $this->args['put_mapping'] ),
				];
			}
		}

		foreach ( $global_indexables as $indexable ) {
			$this->index_meta['sync_stack'][] = [
				'indexable'   => $indexable,
				'put_mapping' => ! empty( $this->args['put_mapping'] ),
			];
		}

		/**
		 * Fires at start of new index
		 *
		 * @since 2.1
		 * @hook ep_dashboard_start_index
		 * @param  {array} $index_meta Index meta information
		 */
		do_action( 'ep_dashboard_start_index', $this->index_meta );

		/**
		 * Filter index meta during dashboard sync
		 *
		 * @since  3.0
		 * @hook ep_index_meta
		 * @param  {array} $index_meta Current index meta
		 * @return  {array} New index meta
		 */
		$this->index_meta = apply_filters( 'ep_index_meta', $this->index_meta );
	}

	/**
	 * Given an array of indexables, check if they are part of the indexable args or not.
	 *
	 * @since 3.6.0
	 * @param array $indexables Indexable slugs.
	 * @return array
	 */
	protected function filter_indexables( $indexables ) {
		return array_filter(
			$indexables,
			function( $indexable ) {
				return empty( $this->args['indexables'] ) || in_array( $indexable, $this->args['indexables'], true );
			}
		);
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
			$this->index_meta['current_sync_item'] = array_merge(
				array_shift( $this->index_meta['sync_stack'] ),
				[
					'total'   => 0,
					'synced'  => 0,
					'skipped' => 0,
					'failed'  => 0,
					'errors'  => [],
				]
			);

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
		$this->index_meta['current_sync_item']['put_mapping'] = false;

		/**
		 * Filter whether we should delete index and send new mapping at the start of the sync
		 *
		 * @since  2.1
		 * @hook ep_skip_index_reset
		 * @param  {bool} $skip True means skip
		 * @param  {array} $index_meta Current index meta
		 * @return  {bool} New skip value
		 */
		if ( apply_filters( 'ep_skip_index_reset', false, $this->index_meta ) ) {
			return;
		}

		$indexable = Indexables::factory()->get( $this->index_meta['current_sync_item']['indexable'] );

		$indexable->delete_index();
		$result = $indexable->put_mapping();

		/**
		 * Fires after dashboard put mapping is completed
		 *
		 * @since 2.1   Previously called only as 'ep_dashboard_put_mapping'
		 * @since 3.6.0 Added $indexable and $result
		 *
		 * @hook ep_{$index_method}_put_mapping
		 * @param  {array} $index_meta Index meta information
		 * @param  {string} $status Current indexing status
		 * @param  {Indexable} $indexable Indexable object
		 * @param  {bool} $result Whether the request was successful or not
		 */
		do_action( "ep_{$this->args['method']}_put_mapping", $this->index_meta, 'start', $indexable, $result );

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
		global $wp_actions;
		// Hold original wp_actions.
		$this->temporary_wp_actions = $wp_actions;

		$this->current_query = $this->get_objects_to_index();

		$this->index_meta['found_items'] = (int) $this->current_query['total_objects'];

		$this->index_meta['current_sync_item']['total'] = $this->index_meta['found_items'];

		if ( $this->index_meta['found_items'] && $this->index_meta['offset'] < $this->index_meta['found_items'] ) {
			$this->index_next_batch();
		} else {
			$this->index_cleanup();
		}

		usleep( 500 );

		// Avoid running out of memory.
		$this->stop_the_insanity();
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
		 * Fires right before entries are about to be indexed in a dashboard sync
		 *
		 * @since  2.1
		 * @hook ep_pre_dashboard_index
		 * @param  {array} $args Args to query content with
		 */
		do_action( 'ep_pre_dashboard_index', $this->index_meta, ( $this->index_meta['start'] ? 'start' : false ), $indexable );

		/**
		 * Filter number of items to index per cycle in the dashboard
		 *
		 * @since  2.1
		 * @hook ep_index_default_per_page
		 * @param  {int} Entries per cycle
		 * @return  {int} New number of entries
		 */
		$per_page = apply_filters( 'ep_index_default_per_page', Utils\get_option( 'ep_bulk_setting', 350 ) );

		if ( ! empty( $this->args['per_page'] ) ) {
			$per_page = $this->args['per_page'];
		}

		if ( ! empty( $this->args['nobulk'] ) ) {
			$per_page = 1;
		}

		$args = [
			'per_page' => absint( $per_page ),
			'offset'   => $this->index_meta['offset'],
		];

		if ( ! empty( $this->args['post-ids'] ) ) {
			$args['include'] = $this->args['post-ids'];
		}

		if ( ! empty( $this->args['include'] ) ) {
			$include          = explode( ',', str_replace( ' ', '', $this->args['include'] ) );
			$args['include']  = array_map( 'absint', $include );
			$args['per_page'] = count( $args['include'] );
		}

		if ( ! empty( $this->args['post-type'] ) ) {
			$args['post_type'] = explode( ',', $this->args['post-type'] );
			$args['post_type'] = array_map( 'trim', $args['post_type'] );
		}

		// Start of advanced pagination arguments.
		if ( ! empty( $this->args['upper_limit_object_id'] ) && is_numeric( $this->args['upper_limit_object_id'] ) ) {
			$args['ep_indexing_upper_limit_object_id'] = $this->args['upper_limit_object_id'];
			$args['ep_indexing_advanced_pagination']   = ( $per_page > 1 );
		}

		if ( ! empty( $this->args['lower_limit_object_id'] ) && is_numeric( $this->args['lower_limit_object_id'] ) ) {
			$args['ep_indexing_lower_limit_object_id'] = $this->args['lower_limit_object_id'];
			$args['ep_indexing_advanced_pagination']   = ( $per_page > 1 );
		}

		if ( $args['ep_indexing_advanced_pagination'] &&
			! empty( $this->index_meta['current_sync_item']['last_processed_object_id'] ) &&
			is_numeric( $this->index_meta['current_sync_item']['last_processed_object_id'] )
		) {
			$args['ep_indexing_last_processed_object_id'] = $this->index_meta['current_sync_item']['last_processed_object_id'];
		}
		// End of advanced pagination arguments.

		/**
		 * Filters arguments used to query for content for each indexable
		 *
		 * @since  3.0
		 * @hook ep_dashboard_index_args
		 * @param  {array} $args Args to query content with
		 * @return  {array} New query args
		 */
		$args = apply_filters( 'ep_dashboard_index_args', $args );

		return $indexable->query_db( $args );
	}

	/**
	 * Index the next batch of documents.
	 *
	 * @since 3.6.0
	 */
	protected function index_next_batch() {
		$indexable = Indexables::factory()->get( $this->index_meta['current_sync_item']['indexable'] );

		/**
		 * Fires right before entries are about to be indexed in a dashboard sync
		 *
		 * @since  3.6.0
		 * @hook ep_pre_index_batch
		 * @param  {array} $index_meta Index meta
		 */
		do_action( 'ep_pre_index_batch', $this->index_meta );

		$queued_items = [];

		foreach ( $this->current_query['objects'] as $object ) {
			if ( $this->should_skip_object_index( $object, $indexable ) ) {
				$this->index_meta['current_sync_item']['skipped']++;
			} else {
				$queued_items[ $object->ID ] = true;
			}
		}

		$this->index_meta['offset'] = absint( $this->index_meta['offset'] + count( $this->current_query['objects'] ) );

		if ( ! empty( $queued_items ) ) {
			$total_attempts = ( ! empty( $this->args['total_attempts'] ) ) ? absint( $this->args['total_attempts'] ) : 1;

			/**
			 * Filters the number of times the index will try before failing.
			 *
			 * @since  3.0
			 * @hook ep_index_batch_attempts_number
			 * @param  {int} $total_attempts Number of attempts
			 * @return  {int} New number of attempts
			 */
			$total_attempts = apply_filters( 'ep_index_batch_attempts_number', $total_attempts );

			for ( $attempts = 1; $attempts <= $total_attempts; $attempts++ ) {
				$nobulk         = ! empty( $this->args['nobulk'] );
				$failed_objects = [];

				/**
				 * Fires before each attempt of indexing objects
				 *
				 * @hook ep_index_batch_new_attempt
				 * @param {int} $attempts Current attempt
				 * @param {int} $total_attempts Total number of attempts
				 */
				do_action( 'ep_index_batch_new_attempt', $attempts, $total_attempts );

				if ( $nobulk ) {
					$object_id = key( $queued_items );
					$return    = $indexable->index( $object_id, true );

					/**
					 * Fires after one by one indexing an object
					 *
					 * @hook ep_cli_object_index
					 * @param  {int} $object_id Object to index
					 * @param {Indexable} $indexable Current indexable
					 * @param {mixed} $return Return of the index() call
					 */
					do_action( 'ep_cli_object_index', $object_id, $indexable, $return );

					if ( is_object( $return ) && ! empty( $return->error ) ) {
						if ( ! empty( $return->error->reason ) ) {
							$failed_objects[ $object->ID ] = (array) $return->error;
						} else {
							$failed_objects[ $object->ID ] = null;
						}
					}
				} else {
					$return = $indexable->bulk_index( array_keys( $queued_items ) );

					/**
					 * Fires after bulk indexing
					 *
					 * @hook ep_cli_{indexable_slug}_bulk_index
					 * @param  {array} $objects Objects being indexed
					 * @param  {array} response Elasticsearch bulk index response
					 */
					do_action( "ep_cli_{$indexable->slug}_bulk_index", $queued_items, $return );

					if ( is_array( $return ) && isset( $return['errors'] ) && true === $return['errors'] ) {
						$failed_objects = array_filter(
							$return['items'],
							function( $item ) {
								return ! empty( $item['index']['error'] );
							}
						);
					}
				}

				// Things worked, we don't need to try again.
				if ( ! is_wp_error( $return ) && ! count( $failed_objects ) ) {
					break;
				}
			}

			$this->index_meta['current_sync_item']['last_processed_object_id'] = end( array_keys( $queued_items ) );

			if ( is_wp_error( $return ) ) {
				$this->index_meta['current_sync_item']['failed'] += count( $queued_items );
				$this->index_meta['current_sync_item']['errors']  = array_merge( $this->index_meta['current_sync_item']['errors'], $return->get_error_messages() );

				$this->output( implode( "\n", $return->get_error_messages() ), 'warning' );
			} elseif ( count( $failed_objects ) ) {
				$errors_output = $this->output_index_errors( $failed_objects );

				$this->index_meta['current_sync_item']['synced'] += count( $queued_items ) - count( $failed_objects );
				$this->index_meta['current_sync_item']['failed'] += count( $failed_objects );
				$this->index_meta['current_sync_item']['errors']  = array_merge( $this->index_meta['current_sync_item']['errors'], $errors_output );

				$this->output( $errors_output, 'warning' );
			} else {
				$this->index_meta['current_sync_item']['synced'] += count( $queued_items );
			}
		}

		$this->output(
			sprintf(
				/* translators: 1. Number of objects indexed, 2. Total number of objects, 3. Last object ID */
				esc_html__( 'Processed %1$d/%2$d. Last Object ID: %3$d', 'elasticpress' ),
				$this->index_meta['offset'],
				$this->index_meta['found_items'],
				$this->index_meta['current_sync_item']['last_processed_object_id']
			),
			'info',
			'index_next_batch'
		);
	}

	/**
	 * Make the necessary clean up after a sync item of the stack was completely done.
	 *
	 * @since 3.6.0
	 * @return void
	 */
	protected function index_cleanup() {
		wp_reset_postdata();

		$indexable = Indexables::factory()->get( $this->index_meta['current_sync_item']['indexable'] );

		$current_sync_item = $this->index_meta['current_sync_item'];

		$this->index_meta['totals']['total']   += $current_sync_item['total'];
		$this->index_meta['totals']['synced']  += $current_sync_item['synced'];
		$this->index_meta['totals']['skipped'] += $current_sync_item['skipped'];
		$this->index_meta['totals']['failed']  += $current_sync_item['failed'];
		$this->index_meta['totals']['errors']   = array_merge(
			$this->index_meta['totals']['errors'],
			$current_sync_item['errors']
		);

		if ( $current_sync_item['failed'] ) {
			$this->index_meta['current_sync_item']['failed'] = 0;

			if ( ! empty( $current_sync_item['blog_id'] ) ) {
				$message = sprintf(
					/* translators: 1: indexable (plural), 2: Blog ID, 3: number of failed objects */
					esc_html__( 'Number of %1$s index errors on site %2$d: %3$d', 'elasticpress' ),
					esc_html( strtolower( $indexable->labels['plural'] ) ),
					$current_sync_item['blog_id'],
					$current_sync_item['failed']
				);
			} else {
				$message = sprintf(
					/* translators: 1: indexable (plural), 2: number of failed objects */
					esc_html__( 'Number of %1$s index errors: %2$d', 'elasticpress' ),
					esc_html( strtolower( $indexable->labels['plural'] ) ),
					$current_sync_item['failed']
				);
			}

			$this->output( $message, 'warning' );
		}

		$this->index_meta['offset']            = 0;
		$this->index_meta['current_sync_item'] = null;

		if ( ! empty( $current_sync_item['blog_id'] ) ) {
			$message = sprintf(
				/* translators: 1: indexable (plural), 2: Blog ID, 3: number of synced objects */
				esc_html__( 'Number of %1$s indexed on site %2$d: %3$d', 'elasticpress' ),
				esc_html( strtolower( $indexable->labels['plural'] ) ),
				$current_sync_item['blog_id'],
				$current_sync_item['synced']
			);
		} else {
			$message = sprintf(
				/* translators: 1: indexable (plural), 2: number of synced objects */
				esc_html__( 'Number of %1$s indexed: %2$d', 'elasticpress' ),
				esc_html( strtolower( $indexable->labels['plural'] ) ),
				$current_sync_item['synced']
			);
		}

		$this->output_success( $message );
	}

	/**
	 * Make the necessary clean up after everything was sync'd.
	 *
	 * @since 3.6.0
	 */
	protected function full_index_complete() {
		$totals = $this->index_meta['totals'];

		$this->index_meta = null;

		$totals['end_time_gmt'] = time();
		$totals['total_time']   = microtime( true ) - $totals['start_time'];
		Utils\update_option( 'ep_last_cli_index', $totals, false );
		Utils\update_option( 'ep_last_index', $totals, false );

		/**
		 * Fires after executing a reindex via Dashboard
		 *
		 * @since  3.5.5
		 * @hook ep_after_dashboard_index
		 */
		do_action( 'ep_after_dashboard_index' );

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
	 * @param string $message_text Message to be outputted
	 * @param string $type         Type of message
	 * @param string $context      Context of the output
	 * @return void
	 */
	protected function output( $message_text, $type = 'info', $context = '' ) {
		if ( $this->index_meta ) {
			Utils\update_option( 'ep_index_meta', $this->index_meta );
		} else {
			Utils\delete_option( 'ep_index_meta' );
		}

		$message = [
			'message'    => $message_text,
			'index_meta' => $this->index_meta,
			'status'     => $type,
		];

		if ( is_callable( $this->args['output_method'] ) ) {
			call_user_func( $this->args['output_method'], $message, $this->args, $this->index_meta, $context );
		}
	}

	/**
	 * Wrapper to the `output` method with a success message.
	 *
	 * @since 3.6.0
	 * @param string $message Message string.
	 * @param string $context Context of the output.
	 */
	protected function output_success( $message, $context = '' ) {
		$this->output( $message, 'success', $context );
	}

	/**
	 * Wrapper to the `output` method with an error message.
	 *
	 * @since 3.6.0
	 * @param string $message Message string.
	 * @param string $context Context of the output.
	 */
	protected function output_error( $message, $context = '' ) {
		$this->output( $message, 'error', $context );
	}

	/**
	 * Output index errors of failed objects.
	 *
	 * @since 3.6.0
	 * @param array $failed_objects Failed objects
	 */
	protected function output_index_errors( $failed_objects ) {
		$indexable = Indexables::factory()->get( $this->index_meta['current_sync_item']['indexable'] );

		$error_text = esc_html__( "The following failed to index:\r\n\r\n", 'elasticpress' );

		foreach ( $failed_objects as $object ) {
			$error_text .= '- ' . $object['index']['_id'] . ' (' . $indexable->labels['singular'] . '): ' . "\r\n";
			$error_text .= '[' . $object['index']['error']['type'] . '] ' . $object['index']['error']['reason'] . "\r\n";
		}

		return $error_text;
	}

	/**
	 * Utilitary function to check if the indexable is being fully reindexed, i.e.,
	 * the index was deleted, a new mapping was sent and content is being reindexed.
	 *
	 * @param string   $indexable_slug Indexable slug.
	 * @param int|null $blog_id        Blog ID
	 * @return boolean
	 */
	public function is_full_reindexing( $indexable_slug, $blog_id = null ) {
		if ( empty( $this->index_meta ) ) {
			/**
			 * Filter if a fully reindex is being done to an indexable
			 *
			 * @since  3.6.0
			 * @hook ep_is_full_reindexing_{$indexable_slug}
			 * @param  {bool} $is_full_reindexing If is fully reindexing
			 * @return  {bool} New value
			 */
			return apply_filters( "ep_is_full_reindexing_{$indexable_slug}", false );
		}

		$sync_stack        = ( ! empty( $this->index_meta['sync_stack'] ) ) ? $this->index_meta['sync_stack'] : [];
		$current_sync_item = ( ! empty( $this->index_meta['current_sync_item'] ) ) ? $this->index_meta['current_sync_item'] : [];

		$is_full_reindexing = false;

		$all_items = array_merge( $sync_stack, $current_sync_item );
		foreach ( $all_items as $sync_item ) {
			if ( $sync_item['indexable'] !== $indexable_slug ) {
				continue;
			}

			if ( empty( $sync_item['put_mapping'] ) ) {
				break;
			}

			if (
				( empty( $sync_item['blog_id'] ) && ! $blog_id ) ||
				(int) $sync_item['blog_id'] === $blog_id
			) {
				$is_full_reindexing = true;
			}
		}

		/* this filter is documented above */
		apply_filters( "ep_is_full_reindexing_{$indexable_slug}", $is_full_reindexing );
	}

	/**
	 * Check if an object should be indexed or skipped.
	 *
	 * We used to have two different filters for this (one for the dashboard, another for CLI),
	 * this method combines both.
	 *
	 * @param {stdClass}  $object Object to be checked
	 * @param {Indexable} $indexable Indexable
	 * @return boolean
	 */
	protected function should_skip_object_index( $object, $indexable ) {
		/**
		 * Filter whether to not sync specific item in dashboard or not
		 *
		 * @since  2.1
		 * @hook ep_item_sync_kill
		 * @param  {boolean} $kill False means dont sync
		 * @param  {array} $object Object to sync
		 * @return {Indexable} Indexable that object belongs to
		 */
		$ep_item_sync_kill = apply_filters( 'ep_item_sync_kill', false, $object, $indexable );

		/**
		 * Conditionally kill indexing for a post
		 *
		 * @hook ep_{indexable_slug}_index_kill
		 * @param  {bool} $index True means dont index
		 * @param  {int} $object_id Object ID
		 * @return {bool} New value
		 */
		$ep_indexable_sync_kill = apply_filters( 'ep_' . $indexable->slug . '_index_kill', false, $object->ID );

		return $ep_item_sync_kill || $ep_indexable_sync_kill;
	}

	/**
	 * Resets some values to reduce memory footprint.
	 */
	protected function stop_the_insanity() {
		global $wpdb, $wp_object_cache, $wp_actions, $wp_filter;

		$wpdb->queries = [];

		if ( is_object( $wp_object_cache ) ) {
			$wp_object_cache->group_ops      = [];
			$wp_object_cache->stats          = [];
			$wp_object_cache->memcache_debug = [];

			// Make sure this is a public property, before trying to clear it.
			try {
				$cache_property = new \ReflectionProperty( $wp_object_cache, 'cache' );
				if ( $cache_property->isPublic() ) {
					$wp_object_cache->cache = [];
				}
				unset( $cache_property );
			} catch ( \ReflectionException $e ) {
				// No need to catch.
			}

			/*
			 * In the case where we're not using an external object cache, we need to call flush on the default
			 * WordPress object cache class to clear the values from the cache property
			 */
			if ( ! wp_using_ext_object_cache() ) {
				wp_cache_flush();
			}

			if ( is_callable( $wp_object_cache, '__remoteset' ) ) {
				call_user_func( [ $wp_object_cache, '__remoteset' ] );
			}
		}

		// Prevent wp_actions from growing out of control.
		// phpcs:disable
		$wp_actions = $this->temporary_wp_actions;
		// phpcs:enable

		// WP_Query class adds filter get_term_metadata using its own instance
		// what prevents WP_Query class from being destructed by PHP gc.
		// if ( $q['update_post_term_cache'] ) {
		// add_filter( 'get_term_metadata', array( $this, 'lazyload_term_meta' ), 10, 2 );
		// }
		// It's high memory consuming as WP_Query instance holds all query results inside itself
		// and in theory $wp_filter will not stop growing until Out Of Memory exception occurs.
		if ( isset( $wp_filter['get_term_metadata'] ) ) {
			/*
			 * WordPress 4.7 has a new Hook infrastructure, so we need to make sure
			 * we're accessing the global array properly
			 */
			if ( class_exists( 'WP_Hook' ) && $wp_filter['get_term_metadata'] instanceof WP_Hook ) {
				$filter_callbacks = &$wp_filter['get_term_metadata']->callbacks;
			} else {
				$filter_callbacks = &$wp_filter['get_term_metadata'];
			}
			if ( isset( $filter_callbacks[10] ) ) {
				foreach ( $filter_callbacks[10] as $hook => $content ) {
					if ( preg_match( '#^[0-9a-f]{32}lazyload_term_meta$#', $hook ) ) {
						unset( $filter_callbacks[10][ $hook ] );
					}
				}
			}
		}
	}

	/**
	 * Utilitary function to delete the index meta option.
	 *
	 * @since 3.6.0
	 */
	public function clear_index_meta() {
		Utils\delete_option( 'ep_index_meta', false );
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
