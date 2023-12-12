<?php
/**
 * Index Helper
 *
 * NOTE: As explained in the doc linked below, the dashboard sync exits after each output()
 * call, to respond to the AJAX request. That means this script will be called several times
 * while syncing via dashboard, relying on the index_meta to pick it up where it stopped.
 *
 * @since 4.0.0
 * @see https://elasticpress.zendesk.com/hc/en-us/articles/16672117103501-Sync-Process
 * @package elasticpress
 */

namespace ElasticPress;

use ElasticPress\Utils;

/**
 * Index Helper Class.
 *
 * @since 4.0.0
 */
class IndexHelper {
	/**
	 * Array to hold all the index sync information.
	 *
	 * @since 4.0.0
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
	 * @since 4.0.0
	 * @var array
	 */
	protected $current_query = [];

	/**
	 * Holds temporary wp_actions when indexing with pagination
	 *
	 * @since 4.0.0
	 * @var  array
	 */
	private $temporary_wp_actions = [];

	/**
	 * Initialize class.
	 *
	 * @since 4.0.0
	 */
	public function setup() {
		$this->index_meta = Utils\get_indexing_status();
	}

	/**
	 * Method to index everything.
	 *
	 * @since 4.0.0
	 * @param array $args Arguments.
	 */
	public function full_index( $args ) {
		register_shutdown_function( [ $this, 'handle_index_error' ] );
		add_filter( 'wp_php_error_message', [ $this, 'wp_handle_index_error' ], 10, 2 );

		$this->index_meta = Utils\get_indexing_status();

		/**
		 * Filter the sync arguments
		 *
		 * @since 4.5.0
		 * @hook ep_sync_args
		 * @param {array} $args Sync arguments
		 * @param {array} $index_meta Current index meta
		 * @return {array} New sync arguments
		 */
		$this->args = apply_filters( 'ep_sync_args', $args, $this->index_meta );

		if ( false === $this->index_meta ) {
			$this->maybe_apply_feature_settings();
			$this->build_index_meta();
		}

		// For the dashboard, this will be called and exit the script until the queue is empty again.
		$this->flush_messages_queue();

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
	 * @since 4.0.0
	 */
	protected function build_index_meta() {
		Utils\update_option( 'ep_last_sync', time() );
		Utils\delete_option( 'ep_need_upgrade_sync' );
		Utils\delete_option( 'ep_feature_auto_activated_sync' );
		delete_transient( 'ep_sync_interrupted' );

		$start_date_time = date_create( 'now', wp_timezone() );

		/**
		 * There are two ways to control pagination of things that need to be indexed:
		 * - offset:   The number of items to skip on each iteration
		 * - id range: Given an ID range, process a batch and set the upper limit as the last processed ID -1
		 *
		 * Although in the first case offset is updated to really control the flow, in the
		 * second it is updated to simply output the number of items processed.
		 */
		$pagination_method = ( ! empty( $this->args['offset'] ) || ! empty( $this->args['post-ids'] ) || ! empty( $this->args['include'] ) ) ?
			'offset' :
			'id_range';

		$starting_indices = array_intersect(
			Elasticsearch::factory()->get_index_names( 'all' ),
			wp_list_pluck( Elasticsearch::factory()->get_cluster_indices(), 'index' )
		);

		$this->index_meta = [
			'method'            => ! empty( $this->args['method'] ) ? $this->args['method'] : 'web',
			'put_mapping'       => ! empty( $this->args['put_mapping'] ),
			'offset'            => ! empty( $this->args['offset'] ) ? absint( $this->args['offset'] ) : 0,
			'pagination_method' => $pagination_method,
			'start'             => true,
			'sync_stack'        => [],
			'network_alias'     => [],
			'start_time'        => microtime( true ),
			'start_date_time'   => $start_date_time ? $start_date_time->format( DATE_ATOM ) : false,
			'starting_indices'  => $starting_indices,
			'messages_queue'    => [],
			'trigger'           => ! empty( $this->args['trigger'] ) ? sanitize_text_field( $this->args['trigger'] ) : null,
			'totals'            => [
				'total'      => 0,
				'synced'     => 0,
				'skipped'    => 0,
				'failed'     => 0,
				'total_time' => 0,
				'errors'     => [],
			],
		];

		$global_indexables     = $this->filter_indexables( Indexables::factory()->get_all( true, true, 'all' ) );
		$non_global_indexables = $this->filter_indexables( Indexables::factory()->get_all( false, true, 'all' ) );

		$is_network_wide = isset( $this->args['network_wide'] ) && ! is_null( $this->args['network_wide'] );

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK && $is_network_wide ) {
			if ( ! is_numeric( $this->args['network_wide'] ) ) {
				$this->args['network_wide'] = 0;
			}

			$sites = Utils\get_sites( $this->args['network_wide'], true );

			foreach ( $sites as $site ) {
				switch_to_blog( $site['blog_id'] );

				foreach ( $non_global_indexables as $indexable ) {
					$this->add_sync_item_to_stack(
						[
							'url'       => untrailingslashit( $site['domain'] . $site['path'] ),
							'blog_id'   => (int) $site['blog_id'],
							'indexable' => $indexable,
						]
					);

					if ( Indexables::factory()->is_active( $indexable ) && ! in_array( $indexable, $this->index_meta['network_alias'], true ) ) {
						$this->index_meta['network_alias'][] = $indexable;
					}
				}
			}

			restore_current_blog();
		} else {
			foreach ( $non_global_indexables as $indexable ) {
				$this->add_sync_item_to_stack(
					[
						'url'       => untrailingslashit( home_url() ),
						'blog_id'   => (int) get_current_blog_id(),
						'indexable' => $indexable,
					]
				);
			}
		}

		foreach ( $global_indexables as $indexable ) {
			$this->add_sync_item_to_stack(
				[
					'indexable' => $indexable,
				]
			);
		}

		$this->index_meta['current_sync_item'] = false;
		/**
		 * Fires at start of new index
		 *
		 * @since 4.0.0
		 *
		 * @hook ep_sync_start_index
		 * @param  {array} $index_meta Index meta information
		 */
		do_action( 'ep_sync_start_index', $this->index_meta );

		/**
		 * Fires at start of new index
		 *
		 * @since 2.1 Previously called only as 'ep_dashboard_start_index'
		 * @since 4.0.0 Made available for all methods
		 *
		 * @hook ep_{$index_method}_start_index
		 * @param  {array} $index_meta Index meta information
		 */
		do_action( "ep_{$this->args['method']}_start_index", $this->index_meta );

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
	 * @since 4.0.0
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
	 * @since 4.0.0
	 * @return boolean
	 */
	protected function has_items_to_be_processed() {
		return ! empty( $this->index_meta['current_sync_item'] ) || count( $this->index_meta['sync_stack'] ) > 0;
	}

	/**
	 * Method to process the next item in the stack.
	 *
	 * @since 4.0.0
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

			$indexable_slug = $this->index_meta['current_sync_item']['indexable'];
			$indexable      = Indexables::factory()->get( $this->index_meta['current_sync_item']['indexable'] );

			if ( ! Indexables::factory()->is_active( $indexable_slug ) ) {
				return $this->process_not_active_indexable_sync_item();
			} elseif ( ! empty( $this->index_meta['current_sync_item']['blog_id'] ) && defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
				$this->output_success(
					sprintf(
						/* translators: 1: Indexable name, 2: Site ID */
						esc_html__( 'Indexing %1$s on site %2$d…', 'elasticpress' ),
						esc_html( strtolower( $indexable->labels['plural'] ) ),
						$this->index_meta['current_sync_item']['blog_id']
					)
				);
			} else {
				$message_string = ( $indexable->global ) ?
					/* translators: 1: Indexable name */
					esc_html__( 'Indexing %1$s (globally)…', 'elasticpress' ) :
					/* translators: 1: Indexable name */
					esc_html__( 'Indexing %1$s…', 'elasticpress' );

				$this->output_success(
					sprintf(
						/* translators: 1: Indexable name */
						$message_string,
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
	 * @since 4.0.0
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
		$result = $indexable->put_mapping( 'raw' );

		/**
		 * Fires after sync put mapping is completed
		 *
		 * @since 4.0.0
		 *
		 * @hook ep_sync_put_mapping
		 * @param  {array} $index_meta Index meta information
		 * @param  {Indexable} $indexable Indexable object
		 * @param  {bool} $result Whether the request was successful or not
		 */
		do_action( 'ep_sync_put_mapping', $this->index_meta, $indexable, $result );

		/**
		 * Fires after dashboard put mapping is completed
		 *
		 * In this particular case, developer aiming a specific method should rely on
		 * `$index_meta['method']`, as historically `ep_dashboard_put_mapping` and
		 * `ep_cli_put_mapping` receive different parameters.
		 *
		 * @see Command::call_ep_cli_put_mapping()
		 *
		 * @since  2.1
		 * @hook ep_dashboard_put_mapping
		 * @param  {array} $index_meta Index meta information
		 * @param  {string} $status Current indexing status
		 */
		do_action( 'ep_dashboard_put_mapping', $this->index_meta, 'start' );

		if ( is_wp_error( $result ) ) {
			$this->on_error_update_and_clean( array( 'message' => $result->get_error_message() ), 'mapping' );
			return;
		}

		$index_exists = in_array( $indexable->get_index_name(), $this->index_meta['starting_indices'], true );
		if ( $index_exists ) {
			$message = esc_html__( 'Mapping sent', 'elasticpress' );
		} else {
			$message = esc_html__( 'Index not present. Mapping sent', 'elasticpress' );
		}

		$this->output_success( $message );
	}

	/**
	 * Index documents of an index.
	 *
	 * @since 4.0.0
	 */
	protected function index_objects() {
		global $wp_actions;
		// Hold original wp_actions.
		$this->temporary_wp_actions = $wp_actions;

		$this->current_query = $this->get_objects_to_index();

		$this->index_meta['from']                       = $this->index_meta['offset'];
		$this->index_meta['found_items']                = (int) $this->current_query['total_objects'];
		$this->index_meta['current_sync_item']['total'] = (int) $this->index_meta['current_sync_item']['found_items'];

		if ( 'offset' === $this->index_meta['pagination_method'] ) {
			$indexable = Indexables::factory()->get( $this->index_meta['current_sync_item']['indexable'] );

			if ( empty( $this->index_meta['current_sync_item']['shown_skip_message'] ) ) {
				$this->index_meta['current_sync_item']['shown_skip_message'] = true;

				$this->output(
					sprintf(
						/* translators: 1. Number of objects skipped 2. Indexable type */
						esc_html__( 'Skipping %1$d %2$s…', 'elasticpress' ),
						$this->index_meta['from'],
						esc_html( strtolower( $indexable->labels['plural'] ) )
					),
					'info',
					'index_objects'
				);
			}
		}

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
	 * @since 4.0.0
	 * @return array
	 */
	protected function get_objects_to_index() {
		$indexable = Indexables::factory()->get( $this->index_meta['current_sync_item']['indexable'] );

		/**
		 * Fires right before entries are about to be indexed.
		 *
		 * @since 4.0.0
		 *
		 * @hook ep_pre_sync_index
		 * @param  {array} $args Args to query content with
		 */
		do_action( 'ep_pre_sync_index', $this->index_meta, ( $this->index_meta['start'] ? 'start' : false ), $indexable );

		/**
		 * Fires right before entries are about to be indexed.
		 *
		 * @since 2.1 Previously called only as 'ep_pre_dashboard_index'
		 * @since 4.0.0 Made available for all methods
		 *
		 * @hook ep_pre_{$index_method}_index
		 * @param  {array} $args Args to query content with
		 */
		do_action( "ep_pre_{$this->args['method']}_index", $this->index_meta, ( $this->index_meta['start'] ? 'start' : false ), $indexable );

		$per_page = $this->get_index_default_per_page();

		if ( ! empty( $this->args['per_page'] ) ) {
			$per_page = $this->args['per_page'];
		}

		if ( ! empty( $this->args['nobulk'] ) ) {
			$per_page = 1;
		}

		$args = [
			'per_page'   => absint( $per_page ),
			'ep_sync_id' => uniqid(),
		];

		if ( ! $indexable->support_indexing_advanced_pagination || 'offset' === $this->index_meta['pagination_method'] ) {
			$args['offset'] = $this->index_meta['offset'];
		}

		if ( ! empty( $this->args['post-ids'] ) ) {
			$args['include'] = $this->args['post-ids'];
		}

		if ( ! empty( $this->args['include'] ) ) {
			$include          = ( is_array( $this->args['include'] ) ) ? $this->args['include'] : explode( ',', str_replace( ' ', '', $this->args['include'] ) );
			$args['include']  = array_map( 'absint', $include );
			$args['per_page'] = count( $args['include'] );
		}

		if ( ! empty( $this->args['post_type'] ) ) {
			$args['post_type'] = ( is_array( $this->args['post_type'] ) ) ? $this->args['post_type'] : explode( ',', $this->args['post_type'] );
			$args['post_type'] = array_map( 'trim', $args['post_type'] );
		}

		// Start of advanced pagination arguments.
		if ( ! empty( $this->args['upper_limit_object_id'] ) && is_numeric( $this->args['upper_limit_object_id'] ) ) {
			$args['ep_indexing_upper_limit_object_id'] = $this->args['upper_limit_object_id'];
		}

		if ( ! empty( $this->args['lower_limit_object_id'] ) && is_numeric( $this->args['lower_limit_object_id'] ) ) {
			$args['ep_indexing_lower_limit_object_id'] = $this->args['lower_limit_object_id'];
		}

		if ( ! empty( $this->index_meta['current_sync_item']['last_processed_object_id'] ) &&
			is_numeric( $this->index_meta['current_sync_item']['last_processed_object_id'] )
		) {
			$args['ep_indexing_last_processed_object_id'] = $this->index_meta['current_sync_item']['last_processed_object_id'];
		}
		// End of advanced pagination arguments.

		/**
		 * Filters arguments used to query for content for each indexable
		 *
		 * @since 4.0.0
		 *
		 * @hook ep_sync_index_args
		 * @param  {array} $args Args to query content with
		 * @return  {array} New query args
		 */
		$args = apply_filters( 'ep_sync_index_args', $args );

		/**
		 * Filters arguments used to query for content for each indexable
		 *
		 * @since  3.0 Previously called only as 'ep_dashboard_index_args'
		 *
		 * @hook ep_{$index_method}_index_args
		 * @param  {array} $args Args to query content with
		 * @return  {array} New query args
		 */
		$args = apply_filters( "ep_{$this->args['method']}_index_args", $args );

		return $indexable->query_db( $args );
	}

	/**
	 * Index the next batch of documents.
	 *
	 * @since 4.0.0
	 */
	protected function index_next_batch() {
		$indexable = Indexables::factory()->get( $this->index_meta['current_sync_item']['indexable'] );

		/**
		 * Fires right before entries are about to be indexed in a dashboard sync
		 *
		 * @since  4.0.0
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
			$total_attempts   = ( ! empty( $this->args['total_attempts'] ) ) ? absint( $this->args['total_attempts'] ) : 1;
			$queued_items_ids = array_keys( $queued_items );

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

				$should_retry = false;

				if ( $nobulk ) {
					$object_id = reset( $queued_items_ids );
					$return    = $indexable->index( $object_id, true );

					/**
					 * Fires after one by one indexing an object
					 *
					 * @since 4.0.0
					 *
					 * @hook ep_sync_object_index
					 * @param  {int} $object_id Object to index
					 * @param {Indexable} $indexable Current indexable
					 * @param {mixed} $return Return of the index() call
					 */
					do_action( 'ep_sync_object_index', $object_id, $indexable, $return );

					/**
					 * Fires after one by one indexing an object
					 *
					 * @since 3.0 Previously called only as 'ep_cli_object_index'
					 * @since 4.0.0 Made available for all methods
					 *
					 * @hook ep_{$index_method}_object_index
					 * @param  {int} $object_id Object to index
					 * @param {Indexable} $indexable Current indexable
					 * @param {mixed} $return Return of the index() call
					 */
					do_action( "ep_{$this->args['method']}_object_index", $object_id, $indexable, $return );

					if ( is_object( $return ) && ! empty( $return->error ) ) {
						if ( ! empty( $return->error->reason ) ) {
							$failed_objects[ $object->ID ] = (array) $return->error;
						} else {
							$failed_objects[ $object->ID ] = null;
						}
					}

					if ( is_wp_error( $return ) ) {
						$should_retry = true;
					}
				} else {
					if ( ! empty( $this->args['static_bulk'] ) ) {
						$bulk_requests = [ $indexable->bulk_index( $queued_items_ids ) ];
					} else {
						$bulk_requests = $indexable->bulk_index_dynamically( $queued_items_ids );
					}

					$failed_objects = [];
					foreach ( $bulk_requests as $return ) {
						/**
						 * Fires after bulk indexing
						 *
						 * @hook ep_cli_{indexable_slug}_bulk_index
						 * @param  {array} $objects Objects being indexed
						 * @param  {array} response Elasticsearch bulk index response
						 */
						do_action( "ep_cli_{$indexable->slug}_bulk_index", $queued_items, $return );

						if ( is_wp_error( $return ) ) {
							$should_retry = true;
						}
						if ( is_array( $return ) && isset( $return['errors'] ) && true === $return['errors'] ) {
							$failed_objects = array_merge(
								$failed_objects,
								array_filter(
									$return['items'],
									function( $item ) {
										return ! empty( $item['index']['error'] );
									}
								)
							);
						}
					}
				}

				// Things worked, we don't need to try again.
				if ( ! $should_retry && ! count( $failed_objects ) ) {
					break;
				}
			}

			if ( is_wp_error( $return ) ) {
				$this->index_meta['current_sync_item']['failed'] += count( $queued_items );

				$wp_error_messages = $return->get_error_messages();

				$this->maybe_process_error_limit(
					count( $this->index_meta['current_sync_item']['errors'] ) + count( $wp_error_messages ),
					count( $this->index_meta['current_sync_item']['errors'] ),
					$wp_error_messages
				);

				$this->queue_message( $wp_error_messages, 'warning' );
			} elseif ( count( $failed_objects ) ) {
				$errors_output = $this->output_index_errors( $failed_objects );

				$this->index_meta['current_sync_item']['synced'] += count( $queued_items ) - count( $failed_objects );

				$this->maybe_process_error_limit(
					$this->index_meta['current_sync_item']['failed'] + count( $failed_objects ),
					$this->index_meta['current_sync_item']['failed'],
					$errors_output
				);

				$this->index_meta['current_sync_item']['failed'] += count( $failed_objects );
				$error_type                                       = ! empty( $this->args['stop_on_error'] ) ? 'error' : 'warning';

				$this->queue_message( $errors_output, $error_type );
			} else {
				$this->index_meta['current_sync_item']['synced'] += count( $queued_items );
			}
		}

		$this->index_meta['current_sync_item']['last_processed_object_id'] = end( $this->current_query['objects'] )->ID;

		$summary = sprintf(
			/* translators: 1. Indexable type 2. Offset start, 3. Offset end, 4. Found items 5. Last object ID */
			esc_html__( 'Processed %1$s %2$d - %3$d of %4$d. Last Object ID: %5$d', 'elasticpress' ),
			esc_html( strtolower( $indexable->labels['plural'] ) ),
			$this->index_meta['from'],
			$this->index_meta['offset'],
			$this->index_meta['found_items'],
			$this->index_meta['current_sync_item']['last_processed_object_id']
		);

		$this->queue_message( $summary, 'info', 'index_next_batch' );
		$this->flush_messages_queue();
	}

	/**
	 * If the number of errors is greater than the limit, slice the array to the limit.
	 * If the number of errors is less than or equal the limit, add the error message to the array (if it's not there).
	 * Merges the new errors with the existing errors.
	 *
	 * @since  4.5.1
	 * @param int   $count Number of errors.
	 * @param int   $num Number of errors to subtract from $limit.
	 * @param array $errors Array of errors.
	 */
	protected function maybe_process_error_limit( $count, $num, $errors ) {
		$error_store_msg = __( 'Reached maximum number of errors to store', 'elasticpress' );

		/**
		 * Filter the number of errors of a current sync that should be stored.
		 *
		 * @since  4.5.1
		 * @hook ep_current_sync_number_of_errors_stored
		 * @param  {int} $number Number of errors to be logged.
		 * @return {int} New value
		 */
		$limit = (int) apply_filters( 'ep_current_sync_number_of_errors_stored', 50 );

		if ( $limit > 0 && $count > $limit ) {
			$diff = $limit - $num;
			if ( $diff > 0 ) {
				$errors = array_slice( $errors, 0, $diff );
			} else {
				$errors = [];
				if ( end( $this->index_meta['current_sync_item']['errors'] ) !== $error_store_msg ) {
					$this->index_meta['current_sync_item']['errors'][] = $error_store_msg;
				}
			}
		}

		$this->index_meta['current_sync_item']['errors'] = array_merge( $this->index_meta['current_sync_item']['errors'], $errors );
	}

	/**
	 * Update the sync info with the totals from the last sync item.
	 *
	 * @since 4.2.0
	 */
	protected function update_totals_from_current_sync_item() {
		$current_sync_item = $this->index_meta['current_sync_item'];

		$errors = array_merge(
			$this->index_meta['totals']['errors'],
			$current_sync_item['errors']
		);

		/**
		 * Filter the number of errors of a sync that should be stored.
		 *
		 * @since  4.2.0
		 * @hook ep_sync_number_of_errors_stored
		 * @param  {int} $number Number of errors to be logged.
		 * @return {int} New value
		 */
		$logged_errors = (int) apply_filters( 'ep_sync_number_of_errors_stored', 50 );

		$this->index_meta['totals']['total']   += $current_sync_item['total'];
		$this->index_meta['totals']['synced']  += $current_sync_item['synced'];
		$this->index_meta['totals']['skipped'] += $current_sync_item['skipped'];
		$this->index_meta['totals']['failed']  += $current_sync_item['failed'];
		$this->index_meta['totals']['errors']   = array_slice( $errors, $logged_errors * -1 );
	}

	/**
	 * Make the necessary clean up after a sync item of the stack was completely done.
	 *
	 * @since 4.0.0
	 * @return void
	 */
	protected function index_cleanup() {
		wp_reset_postdata();

		$this->update_totals_from_current_sync_item();

		$indexable = Indexables::factory()->get( $this->index_meta['current_sync_item']['indexable'] );

		$current_sync_item = $this->index_meta['current_sync_item'];

		$this->index_meta['current_sync_item'] = null;
		$this->index_meta['offset']            = 0;

		if ( $current_sync_item['failed'] ) {
			if ( ! empty( $current_sync_item['blog_id'] ) && defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
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

		if ( ! empty( $current_sync_item['blog_id'] ) && defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
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
	 * Update last sync info.
	 *
	 * @since 4.2.0
	 * @param string $final_status Optional final status
	 */
	protected function update_last_index( string $final_status = '' ) {
		$is_full_sync = $this->index_meta['put_mapping'];
		$method       = $this->index_meta['method'];
		$start_time   = $this->index_meta['start_time'];
		$totals       = $this->index_meta['totals'];
		$trigger      = $this->index_meta['trigger'];

		$this->index_meta = null;

		$end_date_time  = date_create( 'now', wp_timezone() );
		$start_time_sec = (int) $start_time;

		// Time related info
		$totals['end_date_time']   = $end_date_time ? $end_date_time->format( DATE_ATOM ) : false;
		$totals['start_date_time'] = $start_time ? wp_date( DATE_ATOM, $start_time_sec ) : false;
		$totals['end_time_gmt']    = time();
		$totals['total_time']      = microtime( true ) - $start_time;

		// Additional info
		$totals['is_full_sync'] = $is_full_sync;
		$totals['method']       = $method;
		$totals['trigger']      = $trigger;

		// Final status
		if ( '' !== $final_status ) {
			$totals['final_status'] = $final_status;
		} elseif ( ! empty( $totals['failed'] ) ) {
			$totals['final_status'] = 'with_errors';
		} else {
			$totals['final_status'] = 'success';
		}

		Utils\update_option( 'ep_last_cli_index', $totals, false );

		$this->add_last_sync( $totals );
	}

	/**
	 * Add a sync to the list of all past syncs
	 *
	 * @since 5.0.0
	 * @param array $last_sync_info The latest sync info to be added to the log
	 * @return void
	 */
	protected function add_last_sync( array $last_sync_info ) {
		// Remove error messages from previous syncs - we only store msgs for the newest one.
		$last_syncs = array_map(
			function( $sync ) {
				unset( $sync['errors'] );
				return $sync;
			},
			$this->get_sync_history()
		);

		/**
		 * Filter the number of past syncs to keep info
		 *
		 * @since  5.0.0
		 * @hook ep_syncs_to_keep_info
		 * @param {int} $number Number of past syncs to keep info
		 * @return {int} New number
		 */
		$syncs_to_keep = (int) apply_filters( 'ep_syncs_to_keep_info', 5 );

		$last_syncs = array_slice( $last_syncs, 0, $syncs_to_keep - 1 );
		array_unshift( $last_syncs, $last_sync_info );

		Utils\update_option( 'ep_sync_history', $last_syncs, false );
	}

	/**
	 * Make the necessary clean up after everything was sync'd.
	 *
	 * @since 4.0.0
	 */
	protected function full_index_complete() {
		$this->update_last_index();

		/**
		 * Fires after executing a reindex
		 *
		 * @since 4.0.0
		 * @hook ep_after_sync_index
		 */
		do_action( 'ep_after_sync_index' );

		/**
		 * Fires after executing a reindex
		 *
		 * @since 3.5.5 Previously called only as 'ep_after_dashboard_index'
		 * @since 4.0.0 Made available for all methods
		 * @hook ep_after_{$index_method}_index
		 */
		do_action( "ep_after_{$this->args['method']}_index" );

		$this->output_success( esc_html__( 'Sync complete', 'elasticpress' ) );
	}

	/**
	 * Check if network aliases need to be created.
	 *
	 * @since 4.0.0
	 * @return boolean
	 */
	protected function has_network_alias_to_be_created() {
		return count( $this->index_meta['network_alias'] ) > 0;
	}

	/**
	 * Create the next network alias.
	 *
	 * @since 4.0.0
	 */
	protected function create_network_alias() {
		$indexes   = [];
		$indexable = Indexables::factory()->get( array_shift( $this->index_meta['network_alias'] ) );

		$sites = Utils\get_sites( 0, true );

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
					esc_html__( 'Network alias created for %1$s', 'elasticpress' ),
					esc_html( strtolower( $indexable->labels['plural'] ) )
				)
			);
		} else {
			$this->output_error(
				sprintf(
					/* translators: 1: Indexable name */
					esc_html__( 'Network alias creation failed for %1$s', 'elasticpress' ),
					esc_html( strtolower( $indexable->labels['plural'] ) )
				)
			);
		}
	}

	/**
	 * Output a message.
	 *
	 * @since 4.0.0
	 * @param string|array $message_text Message to be outputted
	 * @param string       $type         Type of message
	 * @param string       $context      Context of the output
	 * @return void
	 */
	protected function output( $message_text, $type = 'info', $context = '' ) {
		if ( $this->index_meta ) {
			Utils\update_option( 'ep_index_meta', $this->index_meta );
		} else {
			Utils\delete_option( 'ep_index_meta' );
			$totals = $this->get_last_sync();
		}

		$message = [
			'message'    => ( is_array( $message_text ) ) ? implode( "\n", $message_text ) : $message_text,
			'index_meta' => $this->index_meta,
			'totals'     => $totals ?? [],
			'status'     => $type,
		];

		if ( in_array( $type, [ 'warning', 'error' ], true ) ) {
			$message['errors'] = $this->build_message_errors_data( $message_text );
		}

		if ( is_callable( $this->args['output_method'] ) ) {
			call_user_func( $this->args['output_method'], $message, $this->args, $this->index_meta, $context );
		}
	}

	/**
	 * Wrapper to the `output` method with a success message.
	 *
	 * @since 4.0.0
	 * @param string $message Message string.
	 * @param string $context Context of the output.
	 */
	protected function output_success( $message, $context = '' ) {
		$this->output( $message, 'success', $context );
	}

	/**
	 * Wrapper to the `output` method with an error message.
	 *
	 * @since 4.0.0
	 * @param string $message Message string.
	 * @param string $context Context of the output.
	 */
	protected function output_error( $message, $context = '' ) {
		$this->output( $message, 'error', $context );
	}

	/**
	 * Output index errors of failed objects.
	 *
	 * @since 4.0.0
	 * @param array $failed_objects Failed objects
	 */
	protected function output_index_errors( $failed_objects ) {
		$indexable = Indexables::factory()->get( $this->index_meta['current_sync_item']['indexable'] );

		$error_text = [];

		foreach ( $failed_objects as $object ) {
			$error_text[] = ! empty( $object['index'] ) ? $object['index']['_id'] . ' (' . $indexable->labels['singular'] . '): [' . $object['index']['error']['type'] . '] ' . $object['index']['error']['reason'] : (string) $object;
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
		if ( empty( $this->index_meta ) || empty( $this->index_meta['put_mapping'] ) ) {
			/**
			 * Filter if a fully reindex is being done to an indexable
			 *
			 * @since  4.0.0
			 * @hook ep_is_full_reindexing_{$indexable_slug}
			 * @param  {bool} $is_full_reindexing If is fully reindexing
			 * @return  {bool} New value
			 */
			return apply_filters( "ep_is_full_reindexing_{$indexable_slug}", false );
		}

		$sync_stack        = ( ! empty( $this->index_meta['sync_stack'] ) ) ? $this->index_meta['sync_stack'] : [];
		$current_sync_item = ( ! empty( $this->index_meta['current_sync_item'] ) ) ? $this->index_meta['current_sync_item'] : [];

		$is_full_reindexing = false;

		$all_items = $sync_stack;
		if ( ! empty( $current_sync_item ) ) {
			$all_items += [ $current_sync_item ];
		}

		foreach ( $all_items as $sync_item ) {
			if ( $sync_item['indexable'] !== $indexable_slug ) {
				continue;
			}

			if (
				( empty( $sync_item['blog_id'] ) && ! $blog_id ) ||
				(int) $sync_item['blog_id'] === $blog_id
			) {
				$is_full_reindexing = true;
			}
		}

		/* this filter is documented above */
		return apply_filters( "ep_is_full_reindexing_{$indexable_slug}", $is_full_reindexing );
	}

	/**
	 * Get the previous syncs meta information.
	 *
	 * @since 5.0.0
	 * @return array
	 */
	public function get_sync_history() : array {
		return Utils\get_option( 'ep_sync_history', [] );
	}

	/**
	 * Get the last sync meta information.
	 *
	 * @since 5.0.0
	 * @return array
	 */
	public function get_last_sync() : array {
		$syncs = $this->get_sync_history();
		if ( empty( $syncs ) ) {
			return [];
		}
		return array_shift( $syncs );
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
	 * Given an array, create a new sync item and add it to the stack.
	 *
	 * @since 4.5.0
	 * @param array $sync_stack_item The new sync item
	 */
	protected function add_sync_item_to_stack( array $sync_stack_item ) {
		$indexable_slug   = $sync_stack_item['indexable'];
		$indexable_object = Indexables::factory()->get( $indexable_slug );

		if ( ! $indexable_object ) {
			return;
		}

		$index_exists = in_array( $indexable_object->get_index_name(), $this->index_meta['starting_indices'], true );

		$sync_stack_item['put_mapping'] = ! empty( $this->args['put_mapping'] ) || ! $index_exists;

		if ( ! Indexables::factory()->is_active( $indexable_slug ) ) {
			array_unshift( $this->index_meta['sync_stack'], $sync_stack_item );
			return;
		}

		// This is needed, because get_objects_to_index() calculates its total based on the current sync item.
		$this->index_meta['current_sync_item'] = $sync_stack_item;

		$objects_to_index = $this->get_objects_to_index();

		$sync_stack_item['found_items'] = $objects_to_index['total_objects'] ?? 0;

		$this->index_meta['sync_stack'][] = $sync_stack_item;
	}

	/**
	 * Processes an indexable that is not active.
	 *
	 * If running a full sync, delete the index of an unused indexable.
	 *
	 * @since 4.5.0
	 */
	protected function process_not_active_indexable_sync_item() {
		$current_sync_item = $this->index_meta['current_sync_item'];

		$this->index_meta['current_sync_item'] = null;

		if ( empty( $current_sync_item['put_mapping'] ) ) {
			return;
		}

		$indexable = Indexables::factory()->get( $current_sync_item['indexable'] );

		if ( ! in_array( $indexable->get_index_name(), $this->index_meta['starting_indices'], true ) ) {
			return;
		}

		$indexable->delete_index();

		$this->output_success(
			sprintf(
				/* translators: Index name */
				esc_html__( 'Index %s deleted', 'elasticpress' ),
				$indexable->get_index_name()
			)
		);
	}

	/**
	 * Resets some values to reduce memory footprint.
	 */
	protected function stop_the_insanity() {
		global $wpdb, $wp_object_cache, $wp_actions;

		$wpdb->queries = [];

		/*
		 * Runtime flushing was introduced in WordPress 6.0 and will flush only the
		 * in-memory cache for persistent object caches
		 */
		if ( function_exists( 'wp_cache_flush_runtime' ) ) {
			wp_cache_flush_runtime();
		} else {
			/*
			 * In the case where we're not using an external object cache, we need to call flush on the default
			 * WordPress object cache class to clear the values from the cache property
			 */
			if ( ! wp_using_ext_object_cache() ) {
				wp_cache_flush();
			}
		}

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

			if ( is_callable( $wp_object_cache, '__remoteset' ) ) {
				call_user_func( [ $wp_object_cache, '__remoteset' ] );
			}
		}

		// Prevent wp_actions from growing out of control.
		// phpcs:disable
		$wp_actions = $this->temporary_wp_actions;
		// phpcs:enable

		// It's high memory consuming as WP_Query instance holds all query results inside itself
		// and in theory $wp_filter will not stop growing until Out Of Memory exception occurs.
		remove_filter( 'get_term_metadata', [ wp_metadata_lazyloader(), 'lazyload_term_meta' ] );

		/**
		 * Fires after reducing the memory footprint
		 *
		 * @since 4.3.0
		 * @hook ep_stop_the_insanity
		 */
		do_action( 'ep_stop_the_insanity' );
	}

	/**
	 * Utilitary function to delete the index meta option.
	 *
	 * @since 4.0.0
	 */
	public function clear_index_meta() {
		if ( ! empty( $this->index_meta ) ) {
			$this->update_last_index( 'aborted' );
		}
		$this->index_meta = false;
		Utils\delete_option( 'ep_index_meta', false );
	}

	/**
	 * Utilitary function to get the index meta option.
	 *
	 * @return array
	 * @since 4.0.0
	 */
	public function get_index_meta() {
		return Utils\get_option( 'ep_index_meta', [] );
	}

	/**
	 * Handle fatal errors during syncs.
	 *
	 * Added by register_shutdown_function. It will not be called if `WP_DISABLE_FATAL_ERROR_HANDLER` is false (default.)
	 *
	 * @since 4.2.0
	 */
	public function handle_index_error() {
		$error = error_get_last();
		if ( empty( $error['type'] ) || E_ERROR !== $error['type'] ) {
			return;
		}

		$this->on_error_update_and_clean( $error );
	}

	/**
	 * Handle fatal errors during syncs.
	 *
	 * Added via the `wp_php_error_message` filter. It will be called only if `WP_DISABLE_FATAL_ERROR_HANDLER` is false (default.)
	 *
	 * @since 4.2.0
	 * @param bool  $message HTML error message to display.
	 * @param array $error   Error information retrieved from error_get_last().
	 * @return bool
	 */
	public function wp_handle_index_error( $message, $error ) {
		$this->on_error_update_and_clean( $error );
		return $message;
	}

	/**
	 * Logs the error and clears the sync status, preventing the sync status from being stuck.
	 *
	 * @since 4.2.0
	 * @param array  $error Error information retrieved from error_get_last().
	 * @param string $context Context of the error.
	 */
	protected function on_error_update_and_clean( $error, $context = 'sync' ) {
		$this->update_totals_from_current_sync_item();

		$totals = $this->index_meta['totals'];

		$this->index_meta['totals']['errors'][] = $error['message'];
		$this->index_meta['totals']['failed']   = $totals['total'] - ( $totals['synced'] + $totals['skipped'] );
		$this->update_last_index( 'failed' );

		/**
		 * Fires after a sync failed due to a PHP fatal error.
		 *
		 * @since 4.2.0
		 * @hook ep_after_sync_error
		 * @param {array} $error The error
		 */
		do_action( 'ep_after_sync_error', $error );

		switch ( $context ) {
			case 'mapping':
				$message = sprintf(
					/* translators: Error message */
					esc_html__( 'Mapping failed: %s', 'elasticpress' ),
					Utils\get_elasticsearch_error_reason( $error['message'] )
				);
				$message .= "\n";
				$message .= esc_html__( 'Mapping has failed, which will cause ElasticPress search results to be incorrect. Please click `Delete all Data and Start a Fresh Sync` to retry mapping.', 'elasticpress' );
				break;
			default:
				/* translators: Error message */
				$message = sprintf( esc_html__( 'Index failed: %s', 'elasticpress' ), $error['message'] );
				break;
		}

		$this->output_error( $message );
	}

	/**
	 * Return the default number of documents to be sent to Elasticsearch on each batch.
	 *
	 * @since 4.4.0
	 * @return integer
	 */
	public function get_index_default_per_page() : int {
		/**
		 * Filter number of items to index per cycle in the dashboard
		 *
		 * @since  2.1
		 * @hook ep_index_default_per_page
		 * @param  {int} Entries per cycle
		 * @return  {int} New number of entries
		 */
		return (int) apply_filters( 'ep_index_default_per_page', Utils\get_option( 'ep_bulk_setting', 350 ) );
	}

	/**
	 * Add a message to the queue
	 *
	 * @since 4.7.0
	 * @param string|array $message_text Message to be outputted
	 * @param string       $type         Type of message
	 * @param string       $context      Context of the output
	 */
	protected function queue_message( $message_text, string $type, string $context = '' ) {
		$this->index_meta['messages_queue'][] = [
			'text'    => $message_text,
			'type'    => $type,
			'context' => $context,
		];
	}

	/**
	 * Display messages in the queue.
	 *
	 * NOTE: As the dashboard sync exits after every output call (to respond the AJAX request),
	 * this will just output one message. As the method is called every time the script is called,
	 * all messages will be displayed but one at a time.
	 *
	 * @since 4.7.0
	 */
	protected function flush_messages_queue() {
		if ( ! is_array( $this->index_meta['messages_queue'] ) ) {
			return;
		}

		$messages_count = count( $this->index_meta['messages_queue'] );
		if ( 0 === $messages_count ) {
			return;
		}

		for ( $i = 0; $i < $messages_count; $i++ ) {
			$next_message = array_shift( $this->index_meta['messages_queue'] );
			$this->output( $next_message['text'], $next_message['type'], $next_message['context'] );
		}
	}

	/**
	 * Get data for a given error message(s)
	 *
	 * @since 5.0.0
	 * @param string|array $messages Messages
	 * @return array
	 */
	protected function build_message_errors_data( $messages ) : array {
		$messages          = (array) $messages;
		$error_interpreter = new \ElasticPress\ElasticsearchErrorInterpreter();

		$errors_list = [];
		foreach ( $messages as $message ) {
			$error = $error_interpreter->maybe_suggest_solution_for_es( $message );

			if ( ! isset( $errors_list[ $error['error'] ] ) ) {
				$errors_list[ $error['error'] ] = [
					'solution' => $error['solution'],
					'count'    => 1,
				];
			} else {
				$errors_list[ $error['error'] ]['count']++;
			}
		}
		return $errors_list;
	}

	/**
	 * If this is a full sync, apply the draft feature settings
	 *
	 * @since 5.0.0
	 */
	protected function maybe_apply_feature_settings() {
		if ( empty( $this->args['put_mapping'] ) ) {
			return;
		}

		Features::factory()->apply_draft_feature_settings();
	}

	/**
	 * Return singleton instance of class.
	 *
	 * @return self
	 * @since 4.0.0
	 */
	public static function factory() {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new self();
			$instance->setup();
		}

		return $instance;
	}

	/**
	 * DEPRECATED. Get the last index/sync meta information.
	 *
	 * @since 4.2.0
	 * @deprecated 5.0.0
	 * @return array
	 */
	public function get_last_index() {
		_deprecated_function( __METHOD__, '5.0.0', '\ElasticPress\IndexHelper::get_last_sync' );
		return $this->get_last_sync();
	}
}
