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
	 * Holds the objects that will be bulk indexed.
	 *
	 * @since 0.9
	 * @var  array
	 */
	private $objects = [];

	/**
	 * Holds all of the objects that failed to index during a bulk index.
	 *
	 * @since 0.9
	 * @var  array
	 */
	private $failed_objects = [];

	/**
	 * Holds error messages for individual objects that failed to index (assuming they're available).
	 *
	 * @since 1.7
	 * @var  array
	 */
	private $failed_objects_message = [];

	/**
	 * Holds whether it's network transient or not
	 *
	 * @since 2.1.1
	 * @var  array
	 */
	private $is_network_transient = false;

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
	 * Activate a feature.
	 *
	 * @synopsis <feature> [--network-wide]
	 * @subcommand activate-feature
	 * @since      2.1
	 * @param array $args Positional CLI args.
	 * @param array $assoc_args Associative CLI args.
	 */
	public function activate_feature( $args, $assoc_args ) {
		$this->index_occurring( $assoc_args );

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
	 * @synopsis <feature> [--network-wide]
	 * @subcommand deactivate-feature
	 * @since      2.1
	 * @param array $args Positional CLI args.
	 * @param array $assoc_args Associative CLI args.
	 */
	public function deactivate_feature( $args, $assoc_args ) {
		$this->index_occurring( $assoc_args );

		$feature = Features::factory()->get_registered_feature( $args[0] );

		if ( empty( $feature ) ) {
			WP_CLI::error( esc_html__( 'No feature with that slug is registered', 'elasticpress' ) );
		}

		if ( ! empty( $assoc_args['network-wide'] ) ) {
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
	 * @synopsis [--all] [--network-wide]
	 * @subcommand list-features
	 * @since      2.1
	 * @param array $args Positional CLI args.
	 * @param array $assoc_args Associative CLI args.
	 */
	public function list_features( $args, $assoc_args ) {

		if ( empty( $assoc_args['all'] ) ) {
			if ( ! empty( $assoc_args['network-wide'] ) ) {
				$features = get_site_option( 'ep_feature_settings', [] );
			} else {
				$features = get_option( 'ep_feature_settings', [] );
			}
			WP_CLI::line( esc_html__( 'Active features:', 'elasticpress' ) );

			foreach ( $features as $key => $feature ) {
				if ( $feature['active'] ) {
					WP_CLI::line( $key );
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
		$this->index_occurring( $assoc_args );

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
	 * @synopsis [--index-name] [--network-wide]
	 * @subcommand delete-index
	 * @since      0.9
	 * @param array $args Positional CLI args.
	 * @param array $assoc_args Associative CLI args.
	 */
	public function delete_index( $args, $assoc_args ) {
		$this->connect_check();
		$this->index_occurring( $assoc_args );

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
		$this->index_occurring( $assoc_args );

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
			switch_to_blog( $site['blog_id'] );

			$indexes[] = $indexable->get_index_name();

			restore_current_blog();
		}

		return $indexable->create_network_alias( $indexes );
	}

	/**
	 * Index all posts for a site or network wide
	 *
	 * @synopsis [--setup] [--network-wide] [--per-page] [--nobulk] [--offset] [--indexables] [--show-bulk-errors] [--post-type] [--include] [--post-ids] [--ep-host] [--ep-prefix]
	 *
	 * @param array $args Positional CLI args.
	 * @since 0.1.2
	 * @param array $assoc_args Associative CLI args.
	 */
	public function index( $args, $assoc_args ) {
		global $wp_actions;

		$this->maybe_change_host( $assoc_args );
		$this->maybe_change_index_prefix( $assoc_args );
		$this->connect_check();
		$this->index_occurring( $assoc_args );

		$indexables = null;

		if ( ! empty( $assoc_args['indexables'] ) ) {
			$indexables = explode( ',', str_replace( ' ', '', $assoc_args['indexables'] ) );
		}

		$total_indexed = 0;

		// Hold original wp_actions.
		$this->temporary_wp_actions = $wp_actions;

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

		if ( isset( $assoc_args['network-wide'] ) && is_multisite() ) {
			$this->is_network_transient = true;
			set_site_transient( 'ep_wpcli_sync', true, $this->transient_expiration );
		} else {
			set_transient( 'ep_wpcli_sync', true, $this->transient_expiration );
		}

		timer_start();

		// This clears away dashboard notifications.
		if ( isset( $assoc_args['network-wide'] ) && is_multisite() ) {
			update_site_option( 'ep_last_sync', time() );
			delete_site_option( 'ep_need_upgrade_sync' );
			delete_site_option( 'ep_feature_auto_activated_sync' );
		} else {
			update_option( 'ep_last_sync', time() );
			delete_option( 'ep_need_upgrade_sync' );
			delete_option( 'ep_feature_auto_activated_sync' );
		}

		// Run setup if flag was passed.
		if ( isset( $assoc_args['setup'] ) && true === $assoc_args['setup'] ) {

			// Right now setup is just the put_mapping command, as this also deletes the index(s) first.
			if ( ! $this->put_mapping_helper( $args, $assoc_args ) ) {
				$this->delete_transient();

				exit( 1 );
			}
		}

		$all_indexables               = Indexables::factory()->get_all();
		$non_global_indexable_objects = Indexables::factory()->get_all( false );
		$global_indexable_objects     = Indexables::factory()->get_all( true );

		if ( isset( $assoc_args['network-wide'] ) && is_multisite() ) {
			if ( ! is_numeric( $assoc_args['network-wide'] ) ) {
				$assoc_args['network-wide'] = 0;
			}

			WP_CLI::log( esc_html__( 'Indexing objects network-wide...', 'elasticpress' ) );

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

					WP_CLI::log( sprintf( esc_html__( 'Indexing %1$s on site %2$d...', 'elasticpress' ), esc_html( strtolower( $indexable->labels['plural'] ) ), (int) $site['blog_id'] ) );

					$result = $this->index_helper( $indexable, $assoc_args );

					$total_indexed += $result['synced'];

					WP_CLI::log( sprintf( esc_html__( 'Number of %1$s indexed on site %2$d: %3$d', 'elasticpress' ), esc_html( strtolower( $indexable->labels['plural'] ) ), $site['blog_id'], $result['synced'] ) );

					if ( ! empty( $result['errors'] ) ) {
						$this->delete_transient();

						WP_CLI::error( sprintf( esc_html__( 'Number of %1$s index errors on site %2$d: %3$d', 'elasticpress' ), esc_html( strtolower( $indexable->labels['singular'] ) ), $site['blog_id'], count( $result['errors'] ) ) );
					}
				}

				restore_current_blog();
			}

			/**
			 * Index global indexables e.g. useres
			 */
			foreach ( $global_indexable_objects as $indexable ) {
				/**
				 * If user has called out specific indexables to be indexed, only do those
				 */
				if ( null !== $indexables && ! in_array( $indexable->slug, $indexables, true ) ) {
					continue;
				}

				WP_CLI::log( sprintf( esc_html__( 'Indexing %s...', 'elasticpress' ), esc_html( strtolower( $indexable->labels['plural'] ) ) ) );

				$result = $this->index_helper( $indexable, $assoc_args );

				$total_indexed += $result['synced'];

				WP_CLI::log( sprintf( esc_html__( 'Number of %1$s indexed: %2$d', 'elasticpress' ), esc_html( strtolower( $indexable->labels['plural'] ) ), $result['synced'] ) );

				if ( ! empty( $result['errors'] ) ) {
					$this->delete_transient();

					WP_CLI::error( sprintf( esc_html__( 'Number of %1$s index errors: %2$d', 'elasticpress' ), esc_html( strtolower( $indexable->labels['singular'] ) ), count( $result['errors'] ) ) );
				}
			}

			/**
			 * Handle network aliases separately as they don't depend on blog ID
			 */
			foreach ( $non_global_indexable_objects as $indexable ) {
				/**
				 * If user has called out specific indexables to be indexed, only do those
				 */
				if ( null !== $indexables && ! in_array( $indexable->slug, $indexables, true ) ) {
					continue;
				}

				WP_CLI::log( sprintf( esc_html__( 'Recreating %s network alias...', 'elasticpress' ), esc_html( strtolower( $indexable->labels['singular'] ) ) ) );

				$this->create_network_alias_helper( $indexable );
			}
		} else {
			/**
			 * Run indexing for each indexable one by one
			 */
			foreach ( $all_indexables as $indexable ) {
				/**
				 * If user has called out specific indexables to be indexed, only do those
				 */
				if ( null !== $indexables && ! in_array( $indexable->slug, $indexables, true ) ) {
					continue;
				}

				WP_CLI::log( sprintf( esc_html__( 'Indexing %s...', 'elasticpress' ), esc_html( strtolower( $indexable->labels['plural'] ) ) ) );

				$result = $this->index_helper( $indexable, $assoc_args );

				WP_CLI::log( sprintf( esc_html__( 'Number of %1$s indexed: %2$d', 'elasticpress' ), esc_html( strtolower( $indexable->labels['plural'] ) ), $result['synced'] ) );

				if ( ! empty( $result['errors'] ) ) {
					$this->delete_transient();

					WP_CLI::error( sprintf( esc_html__( 'Number of %1$s index errors: %2$d', 'elasticpress' ), esc_html( strtolower( $indexable->labels['singular'] ) ), count( $result['errors'] ) ) );
				}
			}
		}

		WP_CLI::log( WP_CLI::colorize( '%Y' . esc_html__( 'Total time elapsed: ', 'elasticpress' ) . '%N' . timer_stop() ) );

		$this->delete_transient();

		WP_CLI::success( esc_html__( 'Done!', 'elasticpress' ) );
	}

	/**
	 * Helper method for indexing documents for an indexable
	 *
	 * @param  Indexable $indexable Instance of indexable.
	 * @param  array     $args Query arguments to be based to object query.
	 * @since  0.9
	 * @return array
	 */
	private function index_helper( Indexable $indexable, $args ) {
		$synced = 0;
		$errors = [];

		$no_bulk = false;

		if ( isset( $args['nobulk'] ) ) {
			$no_bulk = true;
		}

		if ( isset( $args['ep-host'] ) ) {
			add_filter(
				'ep_host',
				function ( $host ) use ( $args ) {
					return $args['ep-host'];
				}
			);
		}

		if ( isset( $args['ep-prefix'] ) ) {
			add_filter(
				'ep_index_prefix',
				function ( $prefix ) use ( $args ) {
					return $args['ep-prefix'];
				}
			);
		}

		$show_bulk_errors = false;

		if ( isset( $args['show-bulk-errors'] ) ) {
			$show_bulk_errors = true;
		}

		$query_args = [];

		$query_args['offset'] = 0;

		if ( ! empty( $args['offset'] ) ) {
			$query_args['offset'] = absint( $args['offset'] );
		}

		if ( ! empty( $args['post-ids'] ) ) {
			$args['include'] = $args['post-ids'];
		}

		if ( ! empty( $args['include'] ) ) {
			$include               = explode( ',', str_replace( ' ', '', $args['include'] ) );
			$query_args['include'] = array_map( 'absint', $include );
			$args['per-page']      = count( $query_args['include'] );
		}

		$per_page = $indexable->get_bulk_items_per_page();

		if ( ! empty( $args['per-page'] ) ) {
			$query_args['per_page'] = absint( $args['per-page'] );
			$per_page               = $query_args['per_page'];
		}

		if ( ! empty( $args['post-type'] ) ) {
			$query_args['post_type'] = explode( ',', $args['post-type'] );
			$query_args['post_type'] = array_map( 'trim', $query_args['post_type'] );
		}

		while ( true ) {
			$query = $indexable->query_db( $query_args );

			/**
			 * Reset bulk object queue
			 */
			$this->objects = [];

			if ( ! empty( $query['objects'] ) ) {

				foreach ( $query['objects'] as $object ) {

					if ( $no_bulk ) {
						/**
						 * Index objects one by one
						 */
						$result = $indexable->index( $object->ID, true );

						$this->reset_transient();

						/**
						 * Fires after one by one indexing an object in CLI
						 *
						 * @hook ep_cli_object_index
						 * @param  {int} $object_id Object to index
						 * @param {Indexable} $indexable Current indexable
						 */
						do_action( 'ep_cli_object_index', $object->ID, $indexable );

						WP_CLI::log( sprintf( esc_html__( 'Processed %1$d/%2$d...', 'elasticpress' ), ( $synced + 1 ), (int) $query['total_objects'] ) );
					} else {
						$result = $this->queue_object( $indexable, $object->ID, count( $query['objects'] ), $show_bulk_errors );
					}

					if ( ! $result ) {
						$errors[] = $object->ID;
					} elseif ( true === $result || isset( $result->_index ) ) {
						$synced ++;
					}
				}
			} else {
				break;
			}

			if ( ! $no_bulk ) {
				WP_CLI::log( sprintf( esc_html__( 'Processed %1$d/%2$d...', 'elasticpress' ), (int) ( count( $query['objects'] ) + $query_args['offset'] ), (int) $query['total_objects'] ) );
			}

			$query_args['offset'] += $per_page;

			usleep( 500 );

			// Avoid running out of memory.
			$this->stop_the_insanity();

		}

		if ( ! $no_bulk ) {
			$this->send_bulk_errors();
		}

		wp_reset_postdata();

		return [
			'synced' => $synced,
			'errors' => $errors,
		];
	}

	/**
	 * Queues up an object for bulk indexing
	 *
	 * @param  Indexable $indexable Indexable instance.
	 * @param  int       $object_id Object to queue.
	 * @param  int       $bulk_trigger Number of posts to trigger index on.
	 * @param  bool      $show_bulk_errors True to show individual post error messages for bulk.
	 * @since  3.0
	 * @return bool|int true if successfully synced, false if not or 2 if object was killed before sync
	 */
	private function queue_object( Indexable $indexable, $object_id, $bulk_trigger, $show_bulk_errors = false ) {
		static $killed_object_count = 0;

		$killed_object = false;

		/**
		 * Kill switch to skip an object
		 */

		/**
		 * Conditionally kill indexing for a post
		 *
		 * @hook ep_{indexable_slug}_index_kill
		 * @param  {bool} $index True means dont index
		 * @param  {int} $object_id Object ID
		 * @return {bool} New value
		 */
		if ( apply_filters( 'ep_' . $indexable->slug . '_index_kill', false, $object_id ) ) {

			$killed_object_count++;
			$killed_object = true; // Save status for return.

		} else {

			/**
			 * Put object in queue
			 */
			$this->objects[ $object_id ] = true;

		}

		// If we have hit the trigger, initiate the bulk request.
		if ( ( count( $this->objects ) + $killed_object_count ) === absint( $bulk_trigger ) ) {
			// Don't waste time if we've killed all the posts.
			if ( ! empty( $this->objects ) ) {
				$this->bulk_index( $indexable, $show_bulk_errors );
			}

			// reset killed count.
			$killed_object_count = 0;

			// reset the objects.
			$this->objects = [];
		}

		if ( true === $killed_object ) {
			return 2;
		}

		return true;

	}

	/**
	 * Perform the bulk index operation
	 *
	 * @param  Indexable $indexable Indexable instance.
	 * @param bool      $show_bulk_errors True to show individual post error messages for bulk errors.
	 *
	 * @since 0.9.2
	 */
	private function bulk_index( Indexable $indexable, $show_bulk_errors = false ) {
		// monitor how many times we attempt to add this particular bulk request.
		static $attempts = 0;

		// augment the attempts.
		$attempts++;

		// make sure we actually have something to index.
		if ( empty( $this->objects ) ) {
			$this->delete_transient();

			WP_CLI::error( 'There are no objects to index.' );
		}

		$response = $indexable->bulk_index( array_keys( $this->objects ) );

		$this->reset_transient();

		/**
		 * Fires after bulk indexing in CLI
		 *
		 * @hook ep_cli_{indexable_slug}_bulk_index
		 * @param  {array} $objects Objects being indexed
		 */
		do_action( 'ep_cli_' . $indexable->slug . '_bulk_index', $this->objects );

		if ( is_wp_error( $response ) ) {
			$this->delete_transient();

			WP_CLI::error( implode( "\n", $response->get_error_messages() ) );
		}

		/**
		 * If we have errors, try broken documents up to 5 times. After 5 tries, log errors
		 */
		if ( isset( $response['errors'] ) && true === $response['errors'] ) {
			if ( $attempts < 5 ) {
				foreach ( $response['items'] as $item ) {
					if ( empty( $item['index']['error'] ) ) {
						unset( $this->objects[ $item['index']['_id'] ] );
					}
				}

				$this->bulk_index( $indexable, $show_bulk_errors );
			} else {
				foreach ( $response['items'] as $item ) {
					if ( ! empty( $item['index']['_id'] ) ) {
						$this->failed_objects[] = [
							'ID'        => $item['index']['_id'],
							'indexable' => $indexable,
							'error'     => $item['index']['error'],
						];
					}
				}

				$attempts = 0;
			}
		} else {
			// there were no errors, all the objects were added.
			$attempts = 0;
		}
	}

	/**
	 * Formatting bulk error message recursively
	 *
	 * @param  array $message_array Messages.
	 * @since  2.2
	 * @return string
	 */
	private function format_bulk_error_message( $message_array ) {
		$message = '';

		foreach ( $message_array as $key => $value ) {
			if ( is_array( $value ) ) {
				$message .= $this->format_bulk_error_message( $value );
			} else {
				$message .= "$key: $value" . PHP_EOL;
			}
		}

		return $message;
	}

	/**
	 * Send any bulk indexing errors
	 *
	 * @since 0.9.2
	 */
	private function send_bulk_errors() {
		if ( ! empty( $this->failed_objects ) ) {
			$error_text = esc_html__( "The following failed to index:\r\n\r\n", 'elasticpress' );

			foreach ( $this->failed_objects as $failed_array ) {
				$error_text .= '- ' . $failed_array['ID'] . ' (' . $failed_array['indexable']->labels['singular'] . '): ' . "\r\n";

				if ( ! empty( $failed_array['error'] ) ) {
					$error_text .= $this->format_bulk_error_message( $failed_array['error'] ) . PHP_EOL;
				}
			}

			WP_CLI::log( $error_text );

			// clear failed objects after printing to the screen.
			$this->failed_posts = [];
		}
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

		foreach ( $sites as $site ) {
			$index_names[] = Indexables::factory()->get( 'post' )->get_index_name( $site['blog_id'] );
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

		foreach ( $sites as $site ) {
			$index_names[] = Indexables::factory()->get( 'post' )->get_index_name( $site['blog_id'] );
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
			$current_index = Indexables::factory()->get( 'post' )->get_index_name( $site['blog_id'] );

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

		if ( ! empty( $user_indexable ) ) {
			$user_index = $user_indexable->get_index_name();

			if ( isset( $body['indices'][ $user_index ] ) ) {
				WP_CLI::log( '====== Stats for: ' . $user_index . ' ======' );
				WP_CLI::log( 'Documents:  ' . $body['indices'][ $user_index ]['primaries']['docs']['count'] );
				WP_CLI::log( 'Index Size: ' . size_format( $body['indices'][ $user_index ]['primaries']['store']['size_in_bytes'], 2 ) );
				WP_CLI::log( 'Index Size (including replicas): ' . size_format( $body['indices'][ $user_index ]['total']['store']['size_in_bytes'], 2 ) );
				WP_CLI::log( '====== End Stats ======' );
			} else {
				WP_CLI::warning( $user_index . ' is not currently indexed.' );
			}
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
			WP_CLI::error( esc_html__( 'An index is already occuring. Try again later.', 'elasticpress' ) );
		} elseif ( ! Elasticsearch::factory()->get_elasticsearch_version( true ) ) {
			WP_CLI::error( esc_html__( 'An index is already occuring. Try again later.', 'elasticpress' ) );
		}
	}

	/**
	 * Error out if index is already occurring
	 *
	 * @param  array $assoc_args Associative args passed to command
	 * @since 3.0
	 */
	private function index_occurring( $assoc_args ) {

		if ( ! empty( $assoc_args['network-wide'] ) ) {
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
	 * @since 2.2
	 */
	private function reset_transient() {
		if ( $this->is_network_transient ) {
			set_site_transient( 'ep_wpcli_sync', true, $this->transient_expiration );
		} else {
			set_transient( 'ep_wpcli_sync', true, $this->transient_expiration );
		}
	}

	/**
	 * Delete transient that indicates indexing is occuring
	 *
	 * @since 3.1
	 */
	private function delete_transient() {
		if ( $this->is_network_transient ) {
			delete_site_transient( 'ep_wpcli_sync' );
		} else {
			delete_transient( 'ep_wpcli_sync' );
		}
	}


	/**
	 * maybe change Elastic host on the fly
	 *
	 * @param array $assoc_args Associative CLI args.
	 *
	 * @since 3.x
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
	 * @since 3.x
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

}
