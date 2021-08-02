<?php
/**
 * WP-CLI command for ElasticPress
 *
 * phpcs:disable WordPress.WP.I18n.MissingTranslatorsComment
 *
 * @since  3.0
 * @package elasticpress
 */

namespace ElasticPress;

use \WP_CLI_Command as WP_CLI_Command;
use \WP_CLI as WP_CLI;
use \WP_Hook as WP_Hook;
use ElasticPress\Features as Features;
use ElasticPress\Utils as Utils;
use ElasticPress\Elasticsearch as Elasticsearch;
use ElasticPress\Indexables as Indexables;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * CLI Commands for ElasticPress
 */
class Command extends WP_CLI_Command {

	/**
	 * Holds time until transient expires
	 *
	 * @since 2.1.1
	 * @var  array
	 */
	private $transient_expiration = 900; // 15 min

	/**
	 * Holds temporary wp_actions when indexing with pagination
	 *
	 * @since 2.2
	 * @var  array
	 */
	private $temporary_wp_actions = [];

	/**
	 * Create Command
	 *
	 * @since  3.5.2
	 */
	public function __construct() {
		add_filter( 'pre_transient_ep_wpcli_sync_interrupted', [ $this, 'custom_get_transient' ], 10, 2 );
	}

	/**
	 * Activate a feature.
	 *
	 * @synopsis <feature>
	 * @subcommand activate-feature
	 * @since      2.1
	 * @param array $args Positional CLI args.
	 * @param array $assoc_args Associative CLI args.
	 */
	public function activate_feature( $args, $assoc_args ) {
		$this->index_occurring();

		$feature = Features::factory()->get_registered_feature( $args[0] );

		if ( empty( $feature ) ) {
			WP_CLI::error( esc_html__( 'No feature with that slug is registered', 'elasticpress' ) );
		}

		if ( $feature->is_active() ) {
			WP_CLI::error( esc_html__( 'This feature is already active', 'elasticpress' ) );
		}

		$status = $feature->requirements_status();

		if ( 2 === $status->code ) {
			WP_CLI::error( sprintf( esc_html__( 'Feature requirements are not met: %s', 'elasticpress' ), implode( "\n\n", (array) $status->message ) ) );
		} elseif ( 1 === $status->code ) {
			WP_CLI::warning( sprintf( esc_html__( 'Feature is usable but there are warnings: %s', 'elasticpress' ), implode( "\n\n", (array) $status->message ) ) );
		}

		Features::factory()->activate_feature( $feature->slug );

		if ( $feature->requires_install_reindex ) {
			WP_CLI::warning( esc_html__( 'This feature requires a re-index. You may want to run the index command next.', 'elasticpress' ) );
		}

		WP_CLI::success( esc_html__( 'Feature activated', 'elasticpress' ) );
	}

	/**
	 * Dectivate a feature.
	 *
	 * @synopsis <feature>
	 * @subcommand deactivate-feature
	 * @since      2.1
	 * @param array $args Positional CLI args.
	 * @param array $assoc_args Associative CLI args.
	 */
	public function deactivate_feature( $args, $assoc_args ) {
		$this->index_occurring();

		$feature = Features::factory()->get_registered_feature( $args[0] );

		if ( empty( $feature ) ) {
			WP_CLI::error( esc_html__( 'No feature with that slug is registered', 'elasticpress' ) );
		}

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			$active_features = get_site_option( 'ep_feature_settings', [] );
		} else {
			$active_features = get_option( 'ep_feature_settings', [] );
		}

		$key = array_search( $feature->slug, array_keys( $active_features ), true );

		if ( false === $key || empty( $active_features[ $feature->slug ]['active'] ) ) {
			WP_CLI::error( esc_html__( 'Feature is not active', 'elasticpress' ) );
		}

		Features::factory()->deactivate_feature( $feature->slug );

		WP_CLI::success( esc_html__( 'Feature deactivated', 'elasticpress' ) );
	}

	/**
	 * List features (either active or all)
	 *
	 * @synopsis [--all]
	 * @subcommand list-features
	 * @since      2.1
	 * @param array $args Positional CLI args.
	 * @param array $assoc_args Associative CLI args.
	 */
	public function list_features( $args, $assoc_args ) {

		if ( empty( $assoc_args['all'] ) ) {
			if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
				$features = get_site_option( 'ep_feature_settings', [] );
			} else {
				$features = get_option( 'ep_feature_settings', [] );
			}
			WP_CLI::line( esc_html__( 'Active features:', 'elasticpress' ) );

			foreach ( array_keys( $features ) as $feature_slug ) {
				$feature = Features::factory()->get_registered_feature( $feature_slug );

				if ( $feature->is_active() ) {
					WP_CLI::line( $feature_slug );
				}
			}
		} else {
			WP_CLI::line( esc_html__( 'Registered features:', 'elasticpress' ) );
			$features = wp_list_pluck( Features::factory()->registered_features, 'slug' );

			foreach ( $features as $feature ) {
				WP_CLI::line( $feature );
			}
		}
	}

	/**
	 * Add document mappings for every indexable
	 *
	 * @synopsis [--network-wide] [--indexables] [--ep-host] [--ep-prefix]
	 * @subcommand put-mapping
	 * @since      0.9
	 * @param array $args Positional CLI args.
	 * @param array $assoc_args Associative CLI args.
	 */
	public function put_mapping( $args, $assoc_args ) {
		$this->maybe_change_host( $assoc_args );
		$this->maybe_change_index_prefix( $assoc_args );
		$this->connect_check();
		$this->index_occurring();

		if ( ! $this->put_mapping_helper( $args, $assoc_args ) ) {
			exit( 1 );
		}
	}

	/**
	 * Add document mappings for every indexable
	 *
	 * @since 3.0
	 * @param array $args Positional CLI args.
	 * @param array $assoc_args Associative CLI args.
	 * @return boolean
	 */
	private function put_mapping_helper( $args, $assoc_args ) {
		$indexables = null;

		if ( ! empty( $assoc_args['indexables'] ) ) {
			$indexables = explode( ',', str_replace( ' ', '', $assoc_args['indexables'] ) );
		}

		$non_global_indexable_objects = Indexables::factory()->get_all( false );
		$global_indexable_objects     = Indexables::factory()->get_all( true );

		if ( isset( $assoc_args['network-wide'] ) && is_multisite() ) {
			if ( ! is_numeric( $assoc_args['network-wide'] ) ) {
				$assoc_args['network-wide'] = 0;
			}

			$sites = Utils\get_sites( $assoc_args['network-wide'] );

			foreach ( $sites as $site ) {
				if ( ! Utils\is_site_indexable( $site['blog_id'] ) ) {
					continue;
				}

				switch_to_blog( $site['blog_id'] );

				foreach ( $non_global_indexable_objects as $indexable ) {
					/**
					 * If user has called out specific indexables to be indexed, only do those
					 */
					if ( null !== $indexables && ! in_array( $indexable->slug, $indexables, true ) ) {
						continue;
					}

					WP_CLI::line( sprintf( esc_html__( 'Adding %1$s mapping for site %2$d...', 'elasticpress' ), esc_html( strtolower( $indexable->labels['singular'] ) ), (int) $site['blog_id'] ) );

					$indexable->delete_index();
					$result = $indexable->put_mapping();

					/**
					 * Fires after CLI put mapping
					 *
					 * @hook ep_cli_put_mapping
					 * @param  {Indexable} $indexable Indexable involved in mapping
					 * @param  {array} $args CLI command position args
					 * @param {array} $assoc_args CLI command associative args
					 */
					do_action( 'ep_cli_put_mapping', $indexable, $args, $assoc_args );

					if ( $result ) {
						WP_CLI::success( esc_html__( 'Mapping sent', 'elasticpress' ) );
					} else {
						WP_CLI::error( esc_html__( 'Mapping failed', 'elasticpress' ), false );

						return false;
					}
				}

				restore_current_blog();
			}
		} else {
			foreach ( $non_global_indexable_objects as $indexable ) {
				/**
				 * If user has called out specific indexables to be indexed, only do those
				 */
				if ( null !== $indexables && ! in_array( $indexable->slug, $indexables, true ) ) {
					continue;
				}

				WP_CLI::line( sprintf( esc_html__( 'Adding %s mapping...', 'elasticpress' ), esc_html( strtolower( $indexable->labels['singular'] ) ) ) );

				$indexable->delete_index();
				$result = $indexable->put_mapping();

				/**
				 * Fires after CLI put mapping
				 *
				 * @hook ep_cli_put_mapping
				 * @param  {Indexable} $indexable Indexable involved in mapping
				 * @param  {array} $args CLI command position args
				 * @param {array} $assoc_args CLI command associative args
				 */
				do_action( 'ep_cli_put_mapping', $indexable, $args, $assoc_args );

				if ( $result ) {
					WP_CLI::success( esc_html__( 'Mapping sent', 'elasticpress' ) );
				} else {
					WP_CLI::error( esc_html__( 'Mapping failed', 'elasticpress' ), false );

					return false;
				}
			}
		}

		/**
		 * Handle global indexables separately
		 */
		foreach ( $global_indexable_objects as $indexable ) {
			/**
			 * If user has called out specific indexables to be indexed, only do those
			 */
			if ( null !== $indexables && ! in_array( $indexable->slug, $indexables, true ) ) {
				continue;
			}

			WP_CLI::line( sprintf( esc_html__( 'Adding %s mapping...', 'elasticpress' ), esc_html( strtolower( $indexable->labels['singular'] ) ) ) );

			$indexable->delete_index();
			$result = $indexable->put_mapping();

			/**
			 * Fires after CLI put mapping
			 *
			 * @hook ep_cli_put_mapping
			 * @param  {Indexable} $indexable Indexable involved in mapping
			 * @param  {array} $args CLI command position args
			 * @param {array} $assoc_args CLI command associative args
			 */
			do_action( 'ep_cli_put_mapping', $indexable, $args, $assoc_args );

			if ( $result ) {
				WP_CLI::success( esc_html__( 'Mapping sent', 'elasticpress' ) );
			} else {
				WP_CLI::error( esc_html__( 'Mapping failed', 'elasticpress' ), false );

				return false;
			}
		}

		return true;
	}

	/**
	 * Return all indexes from the cluster as json
	 *
	 * @subcommand get-cluster-indexes
	 * @since      3.2
	 * @param array $args Positional CLI args.
	 * @param array $assoc_args Associative CLI args.
	 */
	public function get_cluster_indexes( $args, $assoc_args ) {
		$path = '_cat/indices?format=json';

		$response = Elasticsearch::factory()->remote_request( $path );

		$body = wp_remote_retrieve_body( $response );

		WP_CLI::line( $body );
	}

	/**
	 * Get all index names as json
	 *
	 * @subcommand get-indexes
	 * @since      3.2
	 * @param array $args Positional CLI args.
	 * @param array $assoc_args Associative CLI args.
	 */
	public function get_indexes( $args, $assoc_args ) {
		$sites = ( is_multisite() ) ? Utils\get_sites() : array( 'blog_id' => get_current_blog_id() );

		foreach ( $sites as $site ) {
			$index_names[] = Indexables::factory()->get( 'post' )->get_index_name( $site['blog_id'] );
		}

		$user_indexable = Indexables::factory()->get( 'user' );

		if ( ! empty( $user_indexable ) ) {
			$index_names[] = $user_indexable->get_index_name();
		}

		WP_CLI::line( wp_json_encode( $index_names ) );
	}

	/**
	 * Delete the index for each indexable. !!Warning!! This removes your elasticsearch index(s)
	 * for the entire site.
	 *
	 * @synopsis [--index-name] [--network-wide] [--yes]
	 * @subcommand delete-index
	 * @since      0.9
	 * @param array $args Positional CLI args.
	 * @param array $assoc_args Associative CLI args.
	 */
	public function delete_index( $args, $assoc_args ) {
		$this->connect_check();
		$this->index_occurring();

		WP_CLI::confirm( esc_html__( 'Are you sure you want to delete your Elasticsearch index?', 'elasticpress' ), $assoc_args );

		// If index name is specified, just delete it and end the command.
		if ( ! empty( $assoc_args['index-name'] ) ) {
			$result = Elasticsearch::factory()->delete_index( $assoc_args['index-name'] );

			if ( $result ) {
				WP_CLI::success( esc_html__( 'Index deleted', 'elasticpress' ) );
			} else {
				WP_CLI::error( esc_html__( 'Index delete failed', 'elasticpress' ) );
			}

			return;
		}

		$non_global_indexable_objects = Indexables::factory()->get_all( false );
		$global_indexable_objects     = Indexables::factory()->get_all( true );

		if ( isset( $assoc_args['network-wide'] ) && is_multisite() ) {
			if ( ! is_numeric( $assoc_args['network-wide'] ) ) {
				$assoc_args['network-wide'] = 0;
			}
			$sites = Utils\get_sites( $assoc_args['network-wide'] );

			foreach ( $sites as $site ) {
				switch_to_blog( $site['blog_id'] );

				foreach ( $non_global_indexable_objects as $indexable ) {

					WP_CLI::line( sprintf( esc_html__( 'Deleting %1$s index for site %2$d...', 'elasticpress' ), esc_html( strtolower( $indexable->labels['singular'] ) ), (int) $site['blog_id'] ) );

					$result = $indexable->delete_index();

					if ( $result ) {
						WP_CLI::success( esc_html__( 'Index deleted', 'elasticpress' ) );
					} else {
						WP_CLI::error( esc_html__( 'Delete index failed', 'elasticpress' ) );
					}
				}

				restore_current_blog();
			}
		} else {
			foreach ( $non_global_indexable_objects as $indexable ) {
				WP_CLI::line( sprintf( esc_html__( 'Deleting index for %s...', 'elasticpress' ), esc_html( strtolower( $indexable->labels['plural'] ) ) ) );

				$result = $indexable->delete_index();

				if ( $result ) {
					WP_CLI::success( esc_html__( 'Index deleted', 'elasticpress' ) );
				} else {
					WP_CLI::error( esc_html__( 'Index delete failed', 'elasticpress' ) );
				}
			}
		}

		foreach ( $global_indexable_objects as $indexable ) {
			WP_CLI::line( sprintf( esc_html__( 'Deleting index for %s...', 'elasticpress' ), esc_html( strtolower( $indexable->labels['plural'] ) ) ) );

			$result = $indexable->delete_index();

			if ( $result ) {
				WP_CLI::success( esc_html__( 'Index deleted', 'elasticpress' ) );
			} else {
				WP_CLI::error( esc_html__( 'Index delete failed', 'elasticpress' ) );
			}
		}
	}

	/**
	 * Map network alias to every index in the network for every non-global indexable
	 *
	 * @param array $args Positional CLI args.
	 * @subcommand recreate-network-alias
	 * @since      0.9
	 * @param array $assoc_args Associative CLI args.
	 */
	public function recreate_network_alias( $args, $assoc_args ) {
		$this->connect_check();
		$this->index_occurring();

		$indexables = Indexables::factory()->get_all( false );

		foreach ( $indexables as $indexable ) {
			WP_CLI::line( sprintf( esc_html__( 'Recreating %s network alias...', 'elasticpress' ), esc_html( strtolower( $indexable->labels['singular'] ) ) ) );

			$indexable->delete_network_alias();

			$create_result = $this->create_network_alias_helper( $indexable );

			if ( $create_result ) {
				WP_CLI::success( esc_html__( 'Done.', 'elasticpress' ) );
			} else {
				WP_CLI::error( esc_html__( 'An error occurred', 'elasticpress' ) );
			}
		}
	}

	/**
	 * A WP-CLI wrapper to run Autosuggest::epio_send_autosuggest_public_request().
	 *
	 * @param array $args       Positional CLI args.
	 * @param array $assoc_args Associative CLI args.
	 * @subcommand  epio-set-autosuggest
	 * @since       3.5.x
	 */
	public function epio_set_autosuggest( $args, $assoc_args ) {
		$autosuggest_feature = Features::factory()->get_registered_feature( 'autosuggest' );

		if ( empty( $autosuggest_feature ) || ! $autosuggest_feature->is_active() ) {
			WP_CLI::error( esc_html__( 'Autosuggest is not enabled.', 'elasticpress' ) );
		}

		add_action( 'ep_epio_wp_cli_set_autosuggest', [ $autosuggest_feature, 'epio_send_autosuggest_public_request' ] );

		do_action( 'ep_epio_wp_cli_set_autosuggest', $args, $assoc_args );
	}

	/**
	 * Helper method for creating the network alias for an indexable
	 *
	 * @param  Indexable $indexable Instance of indexable.
	 * @since  0.9
	 * @return array|bool
	 */
	private function create_network_alias_helper( Indexable $indexable ) {
		$sites   = Utils\get_sites();
		$indexes = [];

		foreach ( $sites as $site ) {
			if ( ! Utils\is_site_indexable( $site['blog_id'] ) ) {
				continue;
			}

			switch_to_blog( $site['blog_id'] );

			$indexes[] = $indexable->get_index_name();

			restore_current_blog();
		}

		return $indexable->create_network_alias( $indexes );
	}

	/**
	 * Properly clean up when receiving SIGINT on indexing
	 *
	 * @param int $signal_no Signal number
	 * @since  3.3
	 */
	public function delete_transient_on_int( $signal_no ) {
		if ( SIGINT === $signal_no ) {
			$this->delete_transient();
			WP_CLI::log( esc_html__( 'Indexing cleaned up.', 'elasticpress' ) );
			exit;
		}
	}

	/**
	 * Index all posts for a site or network wide
	 *
	 * @synopsis [--setup] [--network-wide] [--per-page] [--nobulk] [--show-errors] [--offset] [--upper-limit-object-id] [--lower-limit-object-id] [--indexables] [--show-bulk-errors] [--show-nobulk-errors] [--post-type] [--include] [--post-ids] [--ep-host] [--ep-prefix] [--yes]
	 *
	 * @param array $args Positional CLI args.
	 * @since 0.1.2
	 * @param array $assoc_args Associative CLI args.
	 */
	public function index( $args, $assoc_args ) {
		global $wp_actions;

		$setup_option = isset( $assoc_args['setup'] ) ? $assoc_args['setup'] : false;

		if ( true === $setup_option ) {
			WP_CLI::confirm( esc_html__( 'Indexing with setup option needs to delete Elasticsearch index first, are you sure you want to delete your Elasticsearch index?', 'elasticpress' ), $assoc_args );
		}

		if ( ! function_exists( 'pcntl_signal' ) ) {
			WP_CLI::warning( esc_html__( 'Function pcntl_signal not available. Make sure to run `wp elasticpress clear-index` in case the process is killed.', 'elasticpress' ) );
		} else {
			declare( ticks = 1 );
			pcntl_signal( SIGINT, [ $this, 'delete_transient_on_int' ] );
		}

		$this->maybe_change_host( $assoc_args );
		$this->maybe_change_index_prefix( $assoc_args );
		$this->connect_check();
		$this->index_occurring();

		$indexables = null;

		if ( ! empty( $assoc_args['indexables'] ) ) {
			$indexables = explode( ',', str_replace( ' ', '', $assoc_args['indexables'] ) );
		}

		/**
		 * Prior to the index command invoking
		 * Useful for deregistering filters/actions that occur during a query request
		 *
		 * @since 1.4.1
		 */
		/**
		 * Fires before starting a CLI index
		 *
		 * @hook ep_wp_cli_pre_index
		 * @param  {array} $args CLI command position args
		 * @param {array} $assoc_args CLI command associative args
		 */
		do_action( 'ep_wp_cli_pre_index', $args, $assoc_args );

		timer_start();

		add_action( 'ep_cli_put_mapping', [ $this, 'stop_on_failed_mapping' ], 10, 4 );
		add_action( 'ep_index_batch_new_attempt', [ $this, 'should_interrupt_sync' ] );

		$no_bulk = ! empty( $assoc_args['nobulk'] );

		$index_args = [
			'method'         => 'cli',
			'total_attempts' => 3,
			'indexables'     => $indexables,
			'put_mapping'    => ! empty( $setup_option ),
			'output_method'  => [ $this, 'index_output' ],
			'network_wide'   => ( ! empty( $assoc_args['network-wide'] ) ) ? $assoc_args['network-wide'] : null,
			'nobulk'         => $no_bulk,
			'offset'         => ( ! empty( $args['offset'] ) ) ? absint( $args['offset'] ) : 0,
		];

		if ( isset( $assoc_args['show-errors'] ) || ( isset( $assoc_args['show-bulk-errors'] ) && ! $no_bulk ) || ( isset( $assoc_args['show-nobulk-errors'] ) && $no_bulk ) ) {
			$index_args['show_errors'] = true;
		}

		if ( ! empty( $assoc_args['post-ids'] ) ) {
			$assoc_args['include'] = $assoc_args['post-ids'];
		}

		if ( ! empty( $assoc_args['include'] ) ) {
			$include                = explode( ',', str_replace( ' ', '', $assoc_args['include'] ) );
			$index_args['include']  = array_map( 'absint', $include );
			$index_args['per_page'] = count( $index_args['include'] );
		}

		if ( ! empty( $assoc_args['per-page'] ) ) {
			$index_args['per_page'] = absint( $assoc_args['per-page'] );
		}

		if ( ! empty( $assoc_args['post-type'] ) ) {
			$index_args['post_type'] = explode( ',', $assoc_args['post-type'] );
			$index_args['post_type'] = array_map( 'trim', $index_args['post_type'] );
		}

		if ( ! empty( $assoc_args['upper-limit-object-id'] ) && is_numeric( $assoc_args['upper-limit-object-id'] ) ) {
			$index_args['upper_limit_object_id'] = absint( $assoc_args['upper-limit-object-id'] );
		}

		if ( ! empty( $assoc_args['lower-limit-object-id'] ) && is_numeric( $assoc_args['lower-limit-object-id'] ) ) {
			$index_args['lower_limit_object_id'] = absint( $assoc_args['lower-limit-object-id'] );
		}

		\ElasticPress\IndexHelper::factory()->full_index( $index_args );

		remove_action( 'ep_cli_put_mapping', [ $this, 'stop_on_failed_mapping' ] );
		remove_action( 'ep_index_batch_new_attempt', [ $this, 'should_interrupt_sync' ] );

		$index_time = timer_stop();

		/**
		 * Fires after executing a CLI index
		 *
		 * @hook ep_wp_cli_after_index
		 * @param  {array} $args CLI command position args
		 * @param {array} $assoc_args CLI command associative args
		 *
		 * @since 3.5.5
		 */
		do_action( 'ep_wp_cli_after_index', $args, $assoc_args );

		WP_CLI::log( WP_CLI::colorize( '%Y' . esc_html__( 'Total time elapsed: ', 'elasticpress' ) . '%N' . $index_time ) );

		$this->delete_transient();

		WP_CLI::success( esc_html__( 'Done!', 'elasticpress' ) );
	}

	/**
	 * Ping the Elasticsearch server and retrieve a status.
	 *
	 * @since 0.9.1
	 */
	public function status() {
		$this->connect_check();

		$request_args = [ 'headers' => Elasticsearch::factory()->format_request_headers() ];

		$sites = ( is_multisite() ) ? Utils\get_sites() : array( 'blog_id' => get_current_blog_id() );

		$term_indexable = Indexables::factory()->get( 'term' );

		foreach ( $sites as $site ) {
			$index_names[] = Indexables::factory()->get( 'post' )->get_index_name( $site['blog_id'] );

			if ( ! empty( $term_indexable ) ) {
				$index_names[] = $term_indexable->get_index_name( $site['blog_id'] );
			}
		}

		$user_indexable = Indexables::factory()->get( 'user' );

		if ( ! empty( $user_indexable ) ) {
			$index_names[] = $user_indexable->get_index_name();
		}

		$index_names_imploded = implode( ',', $index_names );

		$request = wp_remote_get( trailingslashit( Utils\get_host( true ) ) . $index_names_imploded . '/_recovery/?pretty', $request_args );

		if ( is_wp_error( $request ) ) {
			WP_CLI::error( implode( "\n", $request->get_error_messages() ) );
		}

		$body = wp_remote_retrieve_body( $request );
		WP_CLI::line( '' );
		WP_CLI::line( '====== Status ======' );
		// phpcs:disable
		WP_CLI::line( print_r( $body, true ) );
		// phpcs:enable
		WP_CLI::line( '====== End Status ======' );
	}

	/**
	 * Get stats on the current index.
	 *
	 * @since 0.9.2
	 */
	public function stats() {
		$this->connect_check();

		$request_args = array( 'headers' => Elasticsearch::factory()->format_request_headers() );

		$sites = ( is_multisite() ) ? Utils\get_sites() : array( 'blog_id' => get_current_blog_id() );

		$post_indexable = Indexables::factory()->get( 'post' );
		$term_indexable = Indexables::factory()->get( 'term' );

		foreach ( $sites as $site ) {
			$index_names[] = $post_indexable->get_index_name( $site['blog_id'] );

			if ( ! empty( $term_indexable ) ) {
				$index_names[] = $term_indexable->get_index_name( $site['blog_id'] );
			}
		}

		$user_indexable = Indexables::factory()->get( 'user' );

		if ( ! empty( $user_indexable ) ) {
			$index_names[] = $user_indexable->get_index_name();
		}

		$index_names_imploded = implode( ',', $index_names );

		$request = wp_remote_get( trailingslashit( Utils\get_host( true ) ) . $index_names_imploded . '/_stats/', $request_args );

		if ( is_wp_error( $request ) ) {
			WP_CLI::error( implode( "\n", $request->get_error_messages() ) );
		}
		$body = json_decode( wp_remote_retrieve_body( $request ), true );

		foreach ( $sites as $site ) {
			$current_index = $post_indexable->get_index_name( $site['blog_id'] );

			$this->render_stats( $current_index, $body );

			if ( $term_indexable ) {
				$this->render_stats( $term_indexable->get_index_name( $site['blog_id'] ), $body );
			}
		}

		if ( ! empty( $user_indexable ) ) {
			$user_index = $user_indexable->get_index_name();

			$this->render_stats( $user_index, $body );
		}
	}

	/**
	 * Resets some values to reduce memory footprint.
	 */
	private function stop_the_insanity() {
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
	 * Provide better error messaging for common connection errors
	 *
	 * @since 0.9.3
	 */
	private function connect_check() {
		$host = Utils\get_host();

		if ( empty( $host ) ) {
			WP_CLI::error( esc_html__( 'Elasticsearch host is not set.', 'elasticpress' ) );
		} elseif ( ! Elasticsearch::factory()->get_elasticsearch_version( true ) ) {
			WP_CLI::error( esc_html__( 'Could not connect to Elasticsearch.', 'elasticpress' ) );
		}
	}

	/**
	 * Error out if index is already occurring
	 *
	 * @since 3.0
	 */
	private function index_occurring() {

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			$dashboard_syncing = get_site_option( 'ep_index_meta' );
			$wpcli_syncing     = get_site_transient( 'ep_wpcli_sync' );
		} else {
			$dashboard_syncing = get_option( 'ep_index_meta' );
			$wpcli_syncing     = get_transient( 'ep_wpcli_sync' );
		}

		if ( $dashboard_syncing || $wpcli_syncing ) {
			WP_CLI::error( esc_html__( 'An index is already occuring. Try again later.', 'elasticpress' ) );
		}
	}

	/**
	 * Reset transient while indexing
	 *
	 * @param int    $items_indexed Count of items already indexed.
	 * @param int    $total_items Total number of items to be indexed.
	 * @param string $slug The slug of the indexable.
	 *
	 * @since 2.2
	 */
	private function reset_transient( $items_indexed, $total_items, $slug ) {
		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			set_site_transient( 'ep_wpcli_sync', array( $items_indexed, $total_items, $slug ), $this->transient_expiration );
		} else {
			set_transient( 'ep_wpcli_sync', array( $items_indexed, $total_items, $slug ), $this->transient_expiration );
		}
	}

	/**
	 * Delete transient that indicates indexing is occuring
	 *
	 * @since 3.1
	 */
	private function delete_transient() {
		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			delete_site_transient( 'ep_wpcli_sync' );
			delete_site_transient( 'ep_cli_sync_progress' );
			delete_site_transient( 'ep_wpcli_sync_interrupted' );
		} else {
			delete_transient( 'ep_wpcli_sync' );
			delete_transient( 'ep_cli_sync_progress' );
			delete_transient( 'ep_wpcli_sync_interrupted' );
		}
	}

	/**
	 * If an index was stopped prematurely and won't start again, this will clear this
	 * cached data such that a new index can start.
	 *
	 * @subcommand clear-index
	 * @alias delete-transient
	 * @since      3.4
	 */
	public function clear_index() {
		/**
		 * Fires before the CLI `clear-index` command is executed.
		 *
		 * @hook ep_cli_before_clear_index
		 *
		 * @since 3.5.5
		 */
		do_action( 'ep_cli_before_clear_index' );

		\ElasticPress\IndexHelper::factory()->clear_index_meta();

		$this->delete_transient();

		/**
		 * Fires after the CLI `clear-index` command is executed.
		 *
		 * @hook ep_cli_after_clear_index
		 *
		 * @since 3.5.5
		 */
		do_action( 'ep_cli_after_clear_index' );

		WP_CLI::success( esc_html__( 'Index cleared.', 'elasticpress' ) );
	}

	/**
	 * Returns the status of an ongoing index operation in JSON array.
	 *
	 * Returns the status of an ongoing index operation in JSON array with the following fields:
	 * indexing | boolean | True if index operation is ongoing or false
	 * method | string | 'cli', 'web' or 'none'
	 * items_indexed | integer | Total number of items indexed
	 * total_items | integer | Total number of items indexed or -1 if not yet determined
	 *
	 * @subcommand get-indexing-status
	 */
	public function get_indexing_status() {

		$index_status = array(
			'indexing'      => false,
			'method'        => 'none',
			'items_indexed' => 0,
			'total_items'   => -1,
		);

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {

			$dashboard_syncing = get_site_option( 'ep_index_meta' );
			$wpcli_syncing     = get_site_transient( 'ep_wpcli_sync' );

		} else {

			$dashboard_syncing = get_option( 'ep_index_meta' );
			$wpcli_syncing     = get_transient( 'ep_wpcli_sync' );

		}

		if ( $dashboard_syncing || $wpcli_syncing ) {

			$index_status['indexing'] = true;

			if ( $dashboard_syncing ) {

				$index_status['method']        = 'web';
				$index_status['items_indexed'] = $dashboard_syncing['offset'];
				$index_status['total_items']   = $dashboard_syncing['found_items'];

			} else {

				$index_status['method'] = 'cli';

				if ( is_array( $wpcli_syncing ) ) {

					$index_status['items_indexed'] = $wpcli_syncing[0];
					$index_status['total_items']   = $wpcli_syncing[1];

				}
			}
		}

		WP_CLI::line( wp_json_encode( $index_status ) );

	}

	/**
	 * Returns a JSON array with the results of the last CLI index (if present) of an empty array.
	 *
	 * @synopsis [--clear]
	 * @subcommand get-last-cli-index
	 *
	 * @param array $args Positional CLI args.
	 * @param array $assoc_args Associative CLI args.
	 */
	public function get_last_cli_index( $args, $assoc_args ) {

		$last_sync = get_site_option( 'ep_last_cli_index', array() );

		if ( isset( $assoc_args['clear'] ) ) {
			delete_site_option( 'ep_last_cli_index' );
		}

		WP_CLI::line( wp_json_encode( $last_sync ) );

	}


	/**
	 * maybe change Elastic host on the fly
	 *
	 * @param array $assoc_args Associative CLI args.
	 *
	 * @since 3.4
	 */
	private function maybe_change_host( $assoc_args ) {
		if ( isset( $assoc_args['ep-host'] ) ) {
			add_filter(
				'ep_host',
				function ( $host ) use ( $assoc_args ) {
					return $assoc_args['ep-host'];
				}
			);
		}
	}


	/**
	 * maybe change index prefix on the fly
	 *
	 * @param array $assoc_args Associative CLI args.
	 *
	 * @since 3.4
	 */
	private function maybe_change_index_prefix( $assoc_args ) {
		if ( isset( $assoc_args['ep-prefix'] ) ) {
			add_filter(
				'ep_index_prefix',
				function ( $prefix ) use ( $assoc_args ) {
					return $assoc_args['ep-prefix'];
				}
			);
		}
	}

	/**
	 * Check if sync should be interrupted
	 *
	 * @since 3.5.2
	 */
	public function should_interrupt_sync() {
		$should_interrupt_sync = get_transient( 'ep_wpcli_sync_interrupted' );

		if ( $should_interrupt_sync ) {
			WP_CLI::line( esc_html__( 'Sync was interrupted', 'elasticpress' ) );
			$this->delete_transient_on_int( 2 );
			WP_CLI::halt();
		}
	}

	/**
	 * Stop the indexing operation started from the dashboard.
	 *
	 * @subcommand stop-indexing
	 * @since      3.5.2
	 * @param array $args Positional CLI args.
	 * @param array $assoc_args Associative CLI args.
	 */
	public function stop_indexing( $args, $assoc_args ) {
		$indexing_status = \ElasticPress\Utils\get_indexing_status();

		if ( empty( \ElasticPress\Utils\get_indexing_status() ) ) {
			WP_CLI::warning( esc_html__( 'There is no indexing operation running.', 'elasticpress' ) );
		} else {
			WP_CLI::line( esc_html__( 'Stoping indexing...', 'elasticpress' ) );

			if ( isset( $indexing_status['method'] ) && 'cli' === $indexing_status['method'] ) {
				set_transient( 'ep_wpcli_sync_interrupted', true, 5 );
			} else {
				set_transient( 'ep_sync_interrupted', true, 5 );
			}

			WP_CLI::success( esc_html__( 'Done.', 'elasticpress' ) );
		}
	}

	/**
	 * Set the algorithm version.
	 *
	 * Set the algorithm version through the `ep_search_algorithm_version` option,
	 * that will be used by the filter with same name.
	 * Delete the option if `--default` is passed.
	 *
	 * @synopsis [--version=<version>] [--default]
	 * @subcommand set-algorithm-version
	 *
	 * @since       3.5.4
	 * @param array $args Positional CLI args.
	 * @param array $assoc_args Associative CLI args.
	 */
	public function set_search_algorithm_version( $args, $assoc_args ) {
		/**
		 * Fires before the algorithm version is changed via WP-CLI.
		 *
		 * @hook ep_cli_before_set_search_algorithm_version
		 * @param  {array} $args CLI command position args
		 * @param {array} $assoc_args CLI command associative args
		 *
		 * @since 3.5.5
		 */
		do_action( 'ep_cli_before_set_search_algorithm_version', $args, $assoc_args );

		if ( empty( $assoc_args['version'] ) && ! isset( $assoc_args['default'] ) ) {
			WP_CLI::error( esc_html__( 'This command expects a version number or the --default flag.', 'elasticpress' ) );
		}

		if ( ! empty( $assoc_args['default'] ) ) {
			if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
				delete_site_option( 'ep_search_algorithm_version' );
			} else {
				delete_option( 'ep_search_algorithm_version' );
			}
		} else {
			if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
				update_site_option( 'ep_search_algorithm_version', $assoc_args['version'] );
			} else {
				update_option( 'ep_search_algorithm_version', $assoc_args['version'], false );
			}
		}

		/**
		 * Fires after the algorithm version is changed via WP-CLI.
		 *
		 * @hook ep_cli_after_set_search_algorithm_version
		 * @param  {array} $args CLI command position args
		 * @param {array} $assoc_args CLI command associative args
		 *
		 * @since 3.5.5
		 */
		do_action( 'ep_cli_after_set_search_algorithm_version', $args, $assoc_args );

		WP_CLI::success( esc_html__( 'Done.', 'elasticpress' ) );
	}

	/**
	 * Get the algorithm version.
	 *
	 * Get the value of the `ep_search_algorithm_version` option, or
	 * `default` if empty.
	 *
	 * @subcommand get-algorithm-version
	 *
	 * @since       3.5.4
	 * @param array $args Positional CLI args.
	 * @param array $assoc_args Associative CLI args.
	 */
	public function get_search_algorithm_version( $args, $assoc_args ) {
		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			$value = get_site_option( 'ep_search_algorithm_version', '' );
		} else {
			$value = get_option( 'ep_search_algorithm_version', '' );
		}

		if ( empty( $value ) ) {
			WP_CLI::line( 'default' );
		} else {
			WP_CLI::line( $value );
		}
	}

	/**
	 * Custom get_transient to WP-CLI env.
	 *
	 * We are using the direct SQL query instead of
	 * the regular function call to retrieve the updated
	 * value to stop the sync. Otherwise, we always get
	 * false after the command is running even when the value
	 * is updated.
	 *
	 * @since      3.5.2
	 * @param mixed  $pre_transient The default value.
	 * @param string $transient Transient name.
	 * @return true|null
	 */
	public function custom_get_transient( $pre_transient, $transient ) {
		global $wpdb;

		if ( wp_using_ext_object_cache() ) {
			$should_interrupt_sync = wp_cache_get( $transient, 'transient' );
		} else {
			$options = $wpdb->options;

			$should_interrupt_sync = $wpdb->get_var(
				// phpcs:disable
				$wpdb->prepare(
					"
						SELECT option_value
						FROM $options
						WHERE option_name = %s
						LIMIT 1
					",
					"_transient_{$transient}"
				)
				// phpcs:enable
			);
		}

		return $should_interrupt_sync ? (bool) $should_interrupt_sync : null;
	}

	/**
	 * Utilitary function to render Stats for a given index.
	 *
	 * @since 3.5.6
	 * @param string $current_index The index name.
	 * @param array  $body          The response body.
	 * @return void
	 */
	protected function render_stats( $current_index, $body ) {
		if ( isset( $body['indices'][ $current_index ] ) ) {
			WP_CLI::log( '====== Stats for: ' . $current_index . ' ======' );
			WP_CLI::log( 'Documents:  ' . $body['indices'][ $current_index ]['primaries']['docs']['count'] );
			WP_CLI::log( 'Index Size: ' . size_format( $body['indices'][ $current_index ]['primaries']['store']['size_in_bytes'], 2 ) );
			WP_CLI::log( 'Index Size (including replicas): ' . size_format( $body['indices'][ $current_index ]['total']['store']['size_in_bytes'], 2 ) );
			WP_CLI::log( '====== End Stats ======' );
		} else {
			WP_CLI::warning( $current_index . ' is not currently indexed.' );
		}
	}

	/**
	 * Function used to ouput messages coming from IndexHelper
	 *
	 * @param array  $message    Message data
	 * @param array  $args       Args sent and processed by IndexHelper
	 * @param array  $index_meta Current index state
	 * @param string $context    Context of the message being outputted
	 */
	public function index_output( $message, $args, $index_meta, $context ) {
		static $time_elapsed = 0, $counter = 0;

		switch ( $message['status'] ) {
			case 'success':
				WP_CLI::success( $message['message'] );
				break;

			case 'warning':
				if ( empty( $args['show_errors'] ) ) {
					return;
				}
				WP_CLI::warning( $message['message'] );
				break;

			case 'error':
				$this->clear_index();
				WP_CLI::error( $message['message'] );
				break;

			default:
				WP_CLI::log( $message['message'] );
				break;
		}

		if ( 'index_next_batch' === $context ) {
			$counter++;
			if ( ( $counter % 10 ) === 0 ) {
				$time_elapsed_diff = $time_elapsed > 0 ? ' (+' . (string) ( timer_stop( 0, 2 ) - $time_elapsed ) . ')' : '';
				$time_elapsed      = timer_stop( 0, 2 );
				WP_CLI::log( WP_CLI::colorize( '%Y' . esc_html__( 'Time elapsed: ', 'elasticpress' ) . '%N' . $time_elapsed . $time_elapsed_diff ) );

				$current_memory = round( memory_get_usage() / 1024 / 1024, 2 ) . 'mb';
				$peak_memory    = ' (Peak: ' . round( memory_get_peak_usage() / 1024 / 1024, 2 ) . 'mb)';
				WP_CLI::log( WP_CLI::colorize( '%Y' . esc_html__( 'Memory Usage: ', 'elasticpress' ) . '%N' . $current_memory . $peak_memory ) );
			}
		}
	}

	/**
	 * If put_mapping fails while indexing, stop the index process.
	 *
	 * @param array     $index_meta Index meta info
	 * @param string    $status     Current indexing status
	 * @param Indexable $indexable  Indexable object
	 * @param bool      $result     Whether the request was successful or not
	 */
	public function stop_on_failed_mapping( $index_meta, $status, $indexable, $result ) {
		if ( ! $result ) {
			$this->delete_transient();

			exit( 1 );
		}
	}
}
