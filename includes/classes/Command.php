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
	 * Holds temporary wp_actions when indexing with pagination
	 *
	 * @since 2.2
	 * @var  array
	 */
	private $temporary_wp_actions = [];

	/**
	 * Holds CLI command position args.
	 *
	 * Useful to share arguments to methods called by hooks.
	 *
	 * @since 4.0.0
	 * @var array
	 */
	protected $args = [];

	/**
	 * Holds CLI command associative args
	 *
	 * Useful to share arguments to methods called by hooks.
	 *
	 * @since 4.0.0
	 * @var array
	 */
	protected $assoc_args = [];

	/**
	 * Internal timer.
	 *
	 * @since 4.2.0
	 *
	 * @var float
	 */
	protected $time_start = null;

	/**
	 * Create Command
	 *
	 * @since  3.5.2
	 */
	public function __construct() {
		add_filter( 'pre_transient_ep_wpcli_sync_interrupted', [ $this, 'custom_get_transient' ], 10, 2 );
	}

	/**
	 * Activate a feature. If a re-indexing is required, you will need to do it manually.
	 *
	 * ## OPTIONS
	 *
	 * <feature-slug>
	 * : The feature slug
	 *
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
	 * ## OPTIONS
	 *
	 * <feature-slug>
	 * : The feature slug
	 *
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

		$active_features = Utils\get_option( 'ep_feature_settings', [] );
		// VIP: Backfill option
		if ( function_exists( 'vip_maybe_backfill_ep_option' ) ) { // TODO: Remove
			$active_features = \vip_maybe_backfill_ep_option( $active_features, 'ep_feature_settings' );
		}

		$key = array_search( $feature->slug, array_keys( $active_features ), true );

		if ( false === $key || empty( $active_features[ $feature->slug ]['active'] ) ) {
			WP_CLI::error( esc_html__( 'Feature is not active', 'elasticpress' ) );
		}

		Features::factory()->deactivate_feature( $feature->slug );

		WP_CLI::success( esc_html__( 'Feature deactivated', 'elasticpress' ) );
	}

	/**
	 * List features (either active or all).
	 *
	 * ## OPTIONS
	 *
	 * [--all]
	 * : Show all registered features
	 *
	 * @subcommand list-features
	 * @since      2.1
	 * @param array $args Positional CLI args.
	 * @param array $assoc_args Associative CLI args.
	 */
	public function list_features( $args, $assoc_args ) {

		if ( empty( $assoc_args['all'] ) ) {
			$features = Utils\get_option( 'ep_feature_settings', [] );
			// VIP: Backfill feature option
			if ( function_exists( 'vip_maybe_backfill_ep_option' ) ) { // TODO: Remove
				$features = \vip_maybe_backfill_ep_option( $features, 'ep_feature_settings' );
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
	 * Add document mappings for every indexable.
	 *
	 * Sends plugin put mapping to the current Indexables indices (this will delete the indices.)
	 *
	 * ## OPTIONS
	 *
	 * [--network-wide]
	 * : Force mappings to be sent for every index in the network.
	 *
	 * [--indexables=<indexables>]
	 * : List of indexables
	 *
	 * [--ep-host=<host>]
	 * : Custom Elasticsearch host
	 *
	 * [--ep-prefix=<prefix>]
	 * : Custom ElasticPress prefix
	 *
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

					WP_CLI::line( sprintf( esc_html__( 'Adding %1$s mapping for site %2$d…', 'elasticpress' ), esc_html( strtolower( $indexable->labels['singular'] ) ), (int) $site['blog_id'] ) );

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

				WP_CLI::line( sprintf( esc_html__( 'Adding %s mapping…', 'elasticpress' ), esc_html( strtolower( $indexable->labels['singular'] ) ) ) );

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

			WP_CLI::line( sprintf( esc_html__( 'Adding %s mapping…', 'elasticpress' ), esc_html( strtolower( $indexable->labels['singular'] ) ) ) );

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
	 * Return the mapping as a JSON object. If an index is specified, return its mapping only.
	 *
	 * ## OPTIONS
	 *
	 * [--index-name=<index_name>]
	 * : The name of the index for which to return the mapping. If not passed, all mappings will be returned
	 *
	 * [--pretty]
	 * : Use this flag to render a pretty-printed version of the JSON response.
	 *
	 * @subcommand get-mapping
	 * @since      3.6.4, `--pretty` introduced in 4.1.0
	 * @param array $args Positional CLI args.
	 * @param array $assoc_args Associative CLI args.
	 */
	public function get_mapping( $args, $assoc_args ) {
		$index_names = (array) ( isset( $assoc_args['index-name'] ) ? $assoc_args['index-name'] : $this->get_index_names() );

		$path = join( ',', $index_names ) . '/_mapping';

		$response = Elasticsearch::factory()->remote_request( $path );

		$this->print_json_response( $response, ! empty( $assoc_args['pretty'] ) );
	}

	/**
	 * Return all indexes from the cluster as a JSON object.
	 *
	 * ## OPTIONS
	 *
	 * [--pretty]
	 * : Use this flag to render a pretty-printed version of the JSON response.
	 *
	 * @subcommand get-cluster-indexes
	 * @since      3.2, `--pretty` introduced in 4.1.0
	 * @param array $args Positional CLI args.
	 * @param array $assoc_args Associative CLI args.
	 */
	public function get_cluster_indexes( $args, $assoc_args ) {
		$path = '_cat/indices?format=json';

		$response = Elasticsearch::factory()->remote_request( $path );

		$this->print_json_response( $response, ! empty( $assoc_args['pretty'] ) );
	}

	/**
	 * Return all index names as a JSON object.
	 *
	 * ## OPTIONS
	 *
	 * [--pretty]
	 * : Use this flag to render a pretty-printed version of the JSON response.
	 *
	 * @subcommand get-indexes
	 * @since      3.2, `--pretty` introduced in 4.1.0
	 * @param array $args Positional CLI args.
	 * @param array $assoc_args Associative CLI args.
	 */
	public function get_indexes( $args, $assoc_args ) {
		$index_names = $this->get_index_names();

		$this->pretty_json_encode( $index_names, ! empty( $assoc_args['pretty'] ) );
	}

	/**
	 * Get all index names.
	 *
	 * @since 3.6.4
	 * @return array
	 */
	protected function get_index_names() {
		$sites = ( is_multisite() ) ? Utils\get_sites() : array( array( 'blog_id' => get_current_blog_id() ) );

		$all_indexables = Indexables::factory()->get_all();

		$global_indexes     = [];
		$non_global_indexes = [];
		foreach ( $all_indexables as $indexable ) {
			if ( $indexable->global ) {
				$global_indexes[] = $indexable->get_index_name();
				continue;
			}

			foreach ( $sites as $site ) {
				if ( ! Utils\is_site_indexable( $site['blog_id'] ) ) {
					continue;
				}
				$non_global_indexes[] = $indexable->get_index_name( $site['blog_id'] );
			}
		}

		return array_merge( $non_global_indexes, $global_indexes );
	}

	/**
	 * Delete the index for each indexable. !!Warning!! This removes your elasticsearch index(s) for the entire site.
	 *
	 * ## OPTIONS
	 *
	 * [--index-name=<index_name>]
	 * : The name of the index to be deleted. If not passed, all indexes will be deleted
	 *
	 * [--network-wide]
	 * : Force every index on the network to be deleted.
	 *
	 * [--yes]
	 * : Skip confirmation
	 *
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

					WP_CLI::line( sprintf( esc_html__( 'Deleting %1$s index for site %2$d…', 'elasticpress' ), esc_html( strtolower( $indexable->labels['singular'] ) ), (int) $site['blog_id'] ) );

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
				WP_CLI::line( sprintf( esc_html__( 'Deleting index for %s…', 'elasticpress' ), esc_html( strtolower( $indexable->labels['plural'] ) ) ) );

				$result = $indexable->delete_index();

				if ( $result ) {
					WP_CLI::success( esc_html__( 'Index deleted', 'elasticpress' ) );
				} else {
					WP_CLI::error( esc_html__( 'Index delete failed', 'elasticpress' ) );
				}
			}
		}

		foreach ( $global_indexable_objects as $indexable ) {
			WP_CLI::line( sprintf( esc_html__( 'Deleting index for %s…', 'elasticpress' ), esc_html( strtolower( $indexable->labels['plural'] ) ) ) );

			$result = $indexable->delete_index();

			if ( $result ) {
				WP_CLI::success( esc_html__( 'Index deleted', 'elasticpress' ) );
			} else {
				WP_CLI::error( esc_html__( 'Index delete failed', 'elasticpress' ) );
			}
		}
	}

	/**
	 * Recreates the alias index which points to every index in the network.
	 *
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
			WP_CLI::line( sprintf( esc_html__( 'Recreating %s network alias…', 'elasticpress' ), esc_html( strtolower( $indexable->labels['singular'] ) ) ) );

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
	 * A WP-CLI wrapper to run `Autosuggest::epio_send_autosuggest_public_request()`.
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
	 * Index all posts for a site or network wide.
	 *
	 * ## OPTIONS
	 *
	 * [--network-wide]
	 * : Force indexing on all the blogs in the network. `--network-wide` takes an optional argument to limit the number of blogs to be indexed across where 0 is no limit. For example, `--network-wide=5` would limit indexing to only 5 blogs on the network
	 *
	 * [--setup]
	 * : Clear the index first and re-send the put mapping. Use `--yes` to skip the confirmation
	 *
	 * [--per-page=<per_page_number>]
	 * : Determine the amount of posts to be indexed per bulk index (or cycle)
	 *
	 * [--nobulk]
	 * : Disable bulk indexing
	 *
	 * [--static-bulk]
	 * : Do not use dynamic bulk requests, i.e., send only one request per batch of documents.
	 *
	 * [--show-errors]
	 * : Show all errors
	 *
	 * [--show-bulk-errors]
	 * : Display the error message returned from Elasticsearch when a post fails to index using the /_bulk endpoint
	 *
	 * [--show-nobulk-errors]
	 * : Display the error message returned from Elasticsearch when a post fails to index while not using the /_bulk endpoint
	 *
	 * [--offset=<offset_number>]
	 * : Skip the first n posts (don't forget to remove the `--setup` flag when resuming or the index will be emptied before starting again).
	 *
	 * [--indexables=<indexables>]
	 * : Specify the Indexable(s) which will be indexed
	 *
	 * [--post-type=<post_types>]
	 * : Specify which post types will be indexed (by default: all indexable post types are indexed). For example, `--post-type="my_custom_post_type"` would limit indexing to only posts from the post type "my_custom_post_type". Accepts multiple post types separated by comma
	 *
	 * [--include=<IDs>]
	 * : Choose which object IDs to include in the index
	 *
	 * [--post-ids=<IDs>]
	 * : Choose which post_ids to include when indexing the Posts Indexable (deprecated)
	 *
	 * [--upper-limit-object-id=<ID>]
	 * : Upper limit of a range of IDs to be indexed. If indexing IDs from 30 to 45, this should be 45
	 *
	 * [--lower-limit-object-id=<ID>]
	 * : Lower limit of a range of IDs to be indexed. If indexing IDs from 30 to 45, this should be 30
	 *
	 * [--ep-host=<host>]
	 * : Custom Elasticsearch host
	 *
	 * [--ep-prefix=<prefix>]
	 * : Custom ElasticPress prefix
	 *
	 * [--yes]
	 * : Skip confirmation needed by `--setup`
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

		$this->timer_start();

		add_action( 'ep_sync_put_mapping', [ $this, 'stop_on_failed_mapping' ], 10, 3 );
		add_action( 'ep_sync_put_mapping', [ $this, 'call_ep_cli_put_mapping' ], 10, 2 );
		add_action( 'ep_index_batch_new_attempt', [ $this, 'should_interrupt_sync' ] );

		$no_bulk = ! empty( $assoc_args['nobulk'] );

		$index_args = [
			'method'         => 'cli',
			'total_attempts' => 1,
			'indexables'     => $indexables,
			'put_mapping'    => ! empty( $setup_option ),
			'output_method'  => [ $this, 'index_output' ],
			'network_wide'   => ( ! empty( $assoc_args['network-wide'] ) ) ? $assoc_args['network-wide'] : null,
			'nobulk'         => $no_bulk,
			'offset'         => ( ! empty( $assoc_args['offset'] ) ) ? absint( $assoc_args['offset'] ) : 0,
			'static_bulk'    => ( ! empty( $assoc_args['static-bulk'] ) ) ? $assoc_args['static-bulk'] : null,
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
			// If post-type was passed, only index the Post indexable.
			$index_args['indexables'] = [ 'post' ];
		}

		if ( ! empty( $assoc_args['upper-limit-object-id'] ) && is_numeric( $assoc_args['upper-limit-object-id'] ) ) {
			$index_args['upper_limit_object_id'] = absint( $assoc_args['upper-limit-object-id'] );
		}

		if ( ! empty( $assoc_args['lower-limit-object-id'] ) && is_numeric( $assoc_args['lower-limit-object-id'] ) ) {
			$index_args['lower_limit_object_id'] = absint( $assoc_args['lower-limit-object-id'] );
		}

		\ElasticPress\IndexHelper::factory()->full_index( $index_args );

		remove_action( 'ep_sync_put_mapping', [ $this, 'stop_on_failed_mapping' ] );
		remove_action( 'ep_sync_put_mapping', [ $this, 'call_ep_cli_put_mapping' ], 10, 2 );
		remove_action( 'ep_index_batch_new_attempt', [ $this, 'should_interrupt_sync' ] );

		$sync_time_in_ms = $this->timer_stop();

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

		WP_CLI::log( WP_CLI::colorize( '%Y' . esc_html__( 'Total time elapsed: ', 'elasticpress' ) . '%N' . $this->timer_format( $sync_time_in_ms ) ) );

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

		$registered_index_names = $this->get_index_names();

		$response_cat_indices = Elasticsearch::factory()->remote_request( '_cat/indices?format=json' );

		if ( is_wp_error( $response_cat_indices ) ) {
			WP_CLI::error( implode( "\n", $response_cat_indices->get_error_messages() ) );
		}

		$indexes_from_cat_indices_api = json_decode( wp_remote_retrieve_body( $response_cat_indices ), true );

		if ( is_array( $indexes_from_cat_indices_api ) ) {
			$indexes_from_cat_indices_api = wp_list_pluck( $indexes_from_cat_indices_api, 'index' );

			$index_names = array_intersect( $registered_index_names, $indexes_from_cat_indices_api );
		} else {
			WP_CLI::error( esc_html__( 'Failed to return status.', 'elasticpress' ) );
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

		$registered_index_names = $this->get_index_names();

		$response_cat_indices = Elasticsearch::factory()->remote_request( '_cat/indices?format=json' );

		if ( is_wp_error( $response_cat_indices ) ) {
			WP_CLI::error( implode( "\n", $response_cat_indices->get_error_messages() ) );
		}

		$indexes_from_cat_indices_api = json_decode( wp_remote_retrieve_body( $response_cat_indices ), true );

		if ( is_array( $indexes_from_cat_indices_api ) ) {
			$indexes_from_cat_indices_api = wp_list_pluck( $indexes_from_cat_indices_api, 'index' );

			$index_names = array_intersect( $registered_index_names, $indexes_from_cat_indices_api );
		} else {
			WP_CLI::error( esc_html__( 'Failed to return stats.', 'elasticpress' ) );
		}

		$index_names_imploded = implode( ',', $index_names );

		$request = wp_remote_get( trailingslashit( Utils\get_host( true ) ) . $index_names_imploded . '/_stats/', $request_args );

		if ( is_wp_error( $request ) ) {
			WP_CLI::error( implode( "\n", $request->get_error_messages() ) );
		}
		$body = json_decode( wp_remote_retrieve_body( $request ), true );

		foreach ( $registered_index_names as $index_name ) {
			$this->render_stats( $index_name, $body );
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
		if ( Utils\is_indexing() ) {
			WP_CLI::error( esc_html__( 'An index is already occurring. Try again later.', 'elasticpress' ) );
		}
	}

	/**
	 * Delete transient that indicates indexing is occurring
	 *
	 * @since 3.1
	 */
	private function delete_transient() {
		\ElasticPress\IndexHelper::factory()->clear_index_meta();

		// VIP: We're not using network transients, but just per-site transients
		delete_transient( 'ep_cli_sync_progress' );
		delete_transient( 'ep_wpcli_sync_interrupted' );
	}

	/**
	 * Clear a sync/index process.
	 *
	 * If an index was stopped prematurely and won't start again, this will clear this cached data such that a new index can start.
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

		$this->delete_transient();

		/**
		 * Fires after the CLI `clear-index` command is executed.
		 *
		 * @hook ep_cli_after_clear_index
		 *
		 * @since 3.5.5
		 */
		do_action( 'ep_cli_after_clear_index' );

		WP_CLI::log( esc_html__( 'Index cleared.', 'elasticpress' ) );
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
	 * ## OPTIONS
	 *
	 * [--pretty]
	 * : Use this flag to render a pretty-printed version of the JSON response.
	 *
	 * @subcommand get-indexing-status
	 * @since 3.5.1, `--pretty` introduced in 4.1.0
	 * @param array $args Positional CLI args.
	 * @param array $assoc_args Associative CLI args.
	 */
	public function get_indexing_status( $args, $assoc_args ) {
		$indexing_status = Utils\get_indexing_status();

		if ( empty( $indexing_status ) ) {
			$indexing_status = [
				'indexing'      => false,
				'method'        => 'none',
				'items_indexed' => 0,
				'total_items'   => -1,
			];
		}

		$this->pretty_json_encode( $indexing_status, ! empty( $assoc_args['pretty'] ) );
	}

	/**
	 * Returns a JSON array with the results of the last index (if present) or an empty array.
	 *
	 * ## OPTIONS
	 *
	 * [--pretty]
	 * : Use this flag to render a pretty-printed version of the JSON response.
	 *
	 * @subcommand get-last-sync
	 * @alias      get-last-index
	 * @since 4.2.0
	 * @param array $args Positional CLI args.
	 * @param array $assoc_args Associative CLI args.
	 */
	public function get_last_sync( $args, $assoc_args ) {
		$last_sync = \ElasticPress\IndexHelper::factory()->get_last_index();

		$this->pretty_json_encode( $last_sync, ! empty( $assoc_args['pretty'] ) );
	}

	/**
	 * Returns a JSON array with the results of the last CLI index (if present) or an empty array.
	 *
	 * ## OPTIONS
	 *
	 * [--clear]
	 * : Clear the `ep_last_cli_index` option.
	 *
	 * [--pretty]
	 * : Use this flag to render a pretty-printed version of the JSON response.
	 *
	 * @subcommand get-last-cli-index
	 * @since 3.5.1, `--pretty` introduced in 4.1.0
	 * @param array $args Positional CLI args.
	 * @param array $assoc_args Associative CLI args.
	 */
	public function get_last_cli_index( $args, $assoc_args ) {
		$last_sync = Utils\get_option( 'ep_last_cli_index', array() );

		if ( isset( $assoc_args['clear'] ) ) {
			Utils\delete_option( 'ep_last_cli_index' );
		}

		$this->pretty_json_encode( $last_sync, ! empty( $assoc_args['pretty'] ) );
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
			WP_CLI::line( esc_html__( 'Stopping indexing…', 'elasticpress' ) );

			if ( isset( $indexing_status['method'] ) && 'cli' === $indexing_status['method'] ) {
				set_transient( 'ep_wpcli_sync_interrupted', true, MINUTE_IN_SECONDS );
			} else {
				set_transient( 'ep_sync_interrupted', true, MINUTE_IN_SECONDS );
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
	 * ## OPTIONS
	 *
	 * [--version=<version>]
	 * : Version name
	 *
	 * [--default]
	 * : Use to set the default version
	 *
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
			Utils\delete_option( 'ep_search_algorithm_version' );
		} else {
			Utils\update_option( 'ep_search_algorithm_version', $assoc_args['version'] );
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
		$value = Utils\get_option( 'ep_search_algorithm_version', '' );

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
			/**
			* When external object cache is used we need to make sure to force a remote fetch,
			* so that the value from the local memory is discarded.
			*/
			$should_interrupt_sync = wp_cache_get( $transient, 'transient', true );
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
				$time_elapsed_diff = $time_elapsed > 0 ? ' (+' . (string) ( $this->timer_stop() - $time_elapsed ) . ')' : '';
				$time_elapsed      = $this->timer_stop( 2 );
				WP_CLI::log( WP_CLI::colorize( '%Y' . esc_html__( 'Time elapsed: ', 'elasticpress' ) . '%N' . $this->timer_format( $time_elapsed ) . $time_elapsed_diff ) );

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
	 * @param Indexable $indexable  Indexable object
	 * @param bool      $result     Whether the request was successful or not
	 */
	public function stop_on_failed_mapping( $index_meta, $indexable, $result ) {
		if ( ! $result ) {
			$this->delete_transient();

			exit( 1 );
		}
	}

	/**
	 * Ties the `ep_cli_put_mapping` action to `ep_sync_put_mapping`.
	 *
	 * @since 4.0.0
	 *
	 * @param array     $index_meta Index meta information
	 * @param Indexable $indexable  Indexable object
	 * @return void
	 */
	public function call_ep_cli_put_mapping( $index_meta, $indexable ) {
		/**
		 * Fires after CLI put mapping
		 *
		 * @hook ep_cli_put_mapping
		 * @param  {Indexable} $indexable Indexable involved in mapping
		 * @param  {array} $args CLI command position args
		 * @param {array} $assoc_args CLI command associative args
		 */
		do_action( 'ep_cli_put_mapping', $indexable, $this->args, $this->assoc_args );
	}

	/**
	 * Send a HTTP request to Elasticsearch
	 *
	 * ## OPTIONS
	 *
	 * <path>
	 * : Path of the request. Example: `_cat/indices`
	 *
	 * [--method=<method>]
	 * : HTTP Method (GET, POST, etc.)
	 *
	 * [--body=<json-body>]
	 * : Request body
	 *
	 * [--debug-http-request]
	 * : Enable debugging
	 *
	 * [--pretty]
	 * : Use this flag to render a pretty-printed version of the JSON response.
	 *
	 * @subcommand request
	 *
	 * @since 3.6.6, `--pretty` introduced in 4.1.0
	 *
	 * @param array $args Positional CLI args.
	 * @param array $assoc_args Associative CLI args.
	 */
	public function request( $args, $assoc_args ) {
		$path         = $args[0];
		$method       = isset( $assoc_args['method'] ) ? $assoc_args['method'] : 'GET';
		$body         = isset( $assoc_args['body'] ) ? $assoc_args['body'] : '';
		$request_args = [
			'method' => $method,
		];
		if ( 'GET' !== $method && ! empty( $body ) ) {
			$request_args['body'] = $body;
		}

		if ( ! empty( $assoc_args['debug-http-request'] ) ) {
			add_filter(
				'http_api_debug',
				function ( $response, $context, $transport, $request_args, $url ) {
					// phpcs:disable WordPress.PHP.DevelopmentFunctions
					WP_CLI::line(
						sprintf(
							/* translators: URL of the request */
							esc_html__( 'URL: %s', 'elasticpress' ),
							$url
						)
					);
					WP_CLI::line(
						sprintf(
							/* translators: Request arguments (outputted with print_r()) */
							esc_html__( 'Request Args: %s', 'elasticpress' ),
							print_r( $request_args, true )
						)
					);
					WP_CLI::line(
						sprintf(
							/* translators: HTTP transport used */
							esc_html__( 'Transport: %s', 'elasticpress' ),
							$transport
						)
					);
					WP_CLI::line(
						sprintf(
							/* translators: Context under which the http_api_debug hook is fired */
							esc_html__( 'Context: %s', 'elasticpress' ),
							$context
						)
					);
					WP_CLI::line(
						sprintf(
							/* translators: HTTP response (outputted with print_r()) */
							esc_html__( 'Response: %s', 'elasticpress' ),
							print_r( $response, true )
						)
					);
					// phpcs:enable WordPress.PHP.DevelopmentFunctions
				},
				10,
				5
			);
		}
		$response = Elasticsearch::factory()->remote_request( $path, $request_args, [], 'wp_cli_request' );

		if ( is_wp_error( $response ) ) {
			WP_CLI::error( $response->get_error_message() );
		}

		$this->print_json_response( $response, ! empty( $assoc_args['pretty'] ) );
	}

	/**
	 * Reset all ElasticPress settings stored in WP options and transients.
	 *
	 * This command will not delete any index or content stored in Elasticsearch but will force users to go through the installation process again.
	 *
	 * ## OPTIONS
	 *
	 * [--yes]
	 * : Skip confirmation
	 *
	 * @subcommand settings-reset
	 *
	 * @since 4.2.0
	 *
	 * @param array $args Positional CLI args.
	 * @param array $assoc_args Associative CLI args.
	 */
	public function settings_reset( $args, $assoc_args ) {
		WP_CLI::confirm( esc_html__( 'Are you sure you want to delete all ElasticPress settings?', 'elasticpress' ), $assoc_args );

		define( 'EP_MANUAL_SETTINGS_RESET', true );
		include EP_PATH . '/uninstall.php';

		WP_CLI::line( esc_html__( 'Settings deleted.', 'elasticpress' ) );
	}

	/**
	 * Starts the timer.
	 *
	 * @since 4.2.0
	 * @return true
	 */
	protected function timer_start() {
		$this->time_start = microtime( true );
		return true;
	}

	/**
	 * Stops the timer.
	 *
	 * @since 4.2.0
	 * @param int $precision The number of digits from the right of the decimal to display. Default 3.
	 * @return float Time spent so far
	 */
	protected function timer_stop( $precision = 3 ) {
		$diff = microtime( true ) - $this->time_start;
		return (float) number_format( (float) $diff, $precision );
	}

	/**
	 * Given a timestamp in microseconds, returns it in the given format.
	 *
	 * @since 4.2.0
	 * @param float  $microtime Unix timestamp in ms
	 * @param string $format    Desired format
	 * @return string
	 */
	protected function timer_format( $microtime, $format = 'H:i:s.u' ) {
		$microtime_date = \DateTime::createFromFormat( 'U.u', number_format( (float) $microtime, 3, '.', '' ) );
		return $microtime_date->format( $format );
	}

	/**
	 * Print an HTTP response.
	 *
	 * @since 4.1.0
	 * @param array   $response HTTP Response.
	 * @param boolean $pretty   Whether the JSON response should be formatted or not.
	 */
	protected function print_json_response( $response, $pretty ) {
		$response_body = wp_remote_retrieve_body( $response );

		$content_type = wp_remote_retrieve_header( $response, 'Content-Type' );

		if ( ! $pretty || ! preg_match( '/json/', $content_type ) ) {
			WP_CLI::line( $response_body );
			return;
		}

		// Re-encode the JSON to add space formatting
		$response_body_obj = json_decode( $response_body );

		$this->pretty_json_encode( $response_body_obj, JSON_PRETTY_PRINT );
	}

	/**
	 * Output a JSON object. Conditionally format it before doing so.
	 *
	 * @since 4.1.0
	 * @param array   $json_obj          The JSON object or array.
	 * @param boolean $pretty_print_flag Whether it should or not be formatted.
	 */
	protected function pretty_json_encode( $json_obj, $pretty_print_flag ) {
		$flag = $pretty_print_flag ? JSON_PRETTY_PRINT : 0;
		WP_CLI::line( wp_json_encode( $json_obj, $flag ) );
	}
}
