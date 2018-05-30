<?php
/**
 * User indexable
 *
 * @since  2.6
 * @package  elasticpress
 */

namespace ElasticPress\Indexable\User;

use ElasticPress\Indexable as Indexable;
use ElasticPress\Elasticsearch as Elasticsearch;
use \WP_User_Query as WP_User_Query;
use ElasticPress\Utils as Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * User indexable class
 */
class User extends Indexable {

	/**
	 * We only need one user index
	 *
	 * @var boolean
	 * @since  2.6
	 */
	public $global = true;

	/**
	 * Indexable slug
	 *
	 * @var string
	 * @since  2.6
	 */
	public $slug = 'user';

	/**
	 * Create indexable and setup dependencies
	 *
	 * @since  2.6
	 */
	public function __construct() {
		$this->labels = [
			'plural'   => esc_html__( 'Users', 'elasticpress' ),
			'singular' => esc_html__( 'User', 'elasticpress' ),
		];

		$this->sync_manager      = new SyncManager( $this->slug );
		$this->query_integration = new QueryIntegration( $this->slug );
	}

	/**
	 * Format query vars into ES query
	 *
	 * @param  array $query_vars WP_User_Query args.
	 * @since  2.6
	 * @return array
	 */
	public function format_args( $query_vars ) {
		global $wpdb;

		/**
		 * Handle `number` query var
		 */
		if ( ! empty( $query_vars['number'] ) ) {
			$number = (int) $query_vars['number'];

			// ES have a maximum size allowed so we have to convert "-1" to a maximum size.
			if ( -1 === $number ) {
				/**
				 * Set the maximum results window size.
				 *
				 * The request will return a HTTP 500 Internal Error if the size of the
				 * request is larger than the [index.max_result_window] parameter in ES.
				 * See the scroll api for a more efficient way to request large data sets.
				 *
				 * @return int The max results window size.
				 *
				 * @since 2.3.0
				 */
				$number = apply_filters( 'ep_max_results_window', 10000 );
			}
		} else {
			$number = 10; // @todo Not sure what the default is.
		}

		$formatted_args = [
			'from' => 0,
			'size' => $number,
		];

		$filter = [
			'bool' => [
				'must' => [],
			],
		];

		$use_filters = false;

		$blog_id = false;
		if ( isset( $query_vars['blog_id'] ) ) {
			$blog_id = (int) $query_vars['blog_id'];
		}

		if ( ! empty( $blog_id ) ) {
			if ( ! empty( $query_vars['role'] ) ) {
				$roles = (array) $query_vars['role'];

				foreach ( $roles as $role ) {
					$filter['bool']['must'][] = array(
						'terms' => array(
							'capabilities.' . $blog_id . '.roles' => [
								$role,
							],
						),
					);
				}

				$use_filters = true;
			}
		}

		/**
		 * Support `fields` query var.
		 */
		if ( isset( $query_vars['fields'] ) && 'all' !== $query_vars['fields'] ) {
			$formatted_args['_source'] = [
				'include' => (array) $query_vars['fields'],
			];
		}

		/**
		 * Support `nicename` query var
		 */
		if ( ! empty( $query_vars['nicename'] ) ) {
			$filter['bool']['must'][] = array(
				'terms' => array(
					'user_nicename' => [
						$query_vars['nicename'],
					],
				),
			);

			$use_filters = true;
		}

		/**
		 * Support `nicename` query var
		 */
		if ( ! empty( $query_vars['nicename'] ) ) {
			$filter['bool']['must'][] = array(
				'terms' => array(
					'user_nicename' => [
						$query_vars['nicename'],
					],
				),
			);

			$use_filters = true;
		}

		/**
		 * Support `nicename__in` query var
		 */
		if ( ! empty( $query_vars['nicename__in'] ) ) {
			$filter['bool']['must'][] = array(
				'terms' => array(
					'user_nicename' => (array) $query_vars['nicename__in'],
				),
			);

			$use_filters = true;
		}

		/**
		 * Support `login` query var
		 */
		if ( ! empty( $query_vars['login'] ) ) {
			$filter['bool']['must'][] = array(
				'terms' => array(
					'user_login' => [
						$query_vars['login'],
					],
				),
			);

			$use_filters = true;
		}

		/**
		 * Support `login__in` query var
		 */
		if ( ! empty( $query_vars['login__in'] ) ) {
			$filter['bool']['must'][] = array(
				'terms' => array(
					'user_login' => (array) $query_vars['login__in'],
				),
			);

			$use_filters = true;
		}

		/**
		 * Support `login__not_in` query var
		 */
		if ( ! empty( $query_vars['login__not_in'] ) ) {
			$filter['bool']['must'][] = [
				'bool' => [
					'must_not' => [
						[
							'terms' => [
								'user_login' => (array) $query_vars['login__not_in'],
							],
						],
					],
				],
			];

			$use_filters = true;
		}

		/**
		 * Handle `offset` and `paged` query vars. Paged takes priority if both are set.
		 */
		if ( isset( $query_vars['offset'] ) ) {
			$formatted_args['from'] = (int) $query_vars['offset'];
		}

		if ( isset( $query_vars['paged'] ) && $query_vars['paged'] > 1 ) {
			$formatted_args['from'] = $number * ( $query_vars['paged'] - 1 );
		}

		/**
		 * Handle `search` query_var
		 */
		if ( ! empty( $query_vars['search'] ) ) {

			$search_fields = ( ! empty( $query_vars['search_columns'] ) ) ? $query_vars['search_columns'] : [];

			if ( ! empty( $query_vars['search_fields'] ) ) {
				$search_fields = array_merge( $search_fields, $query_vars['search_fields'] );
			}

			/**
			 * Handle `search_fields` query var and `search_columns`. search_columns is a bit too
			 * simplistic for our needs since we want to be able to search meta too. We just merge
			 * search columns into search_fields. search_fields overwrites search_columns.
			 */
			if ( ! empty( $search_fields ) ) {
				$prepared_search_fields = [];

				// WP_User_Query uses shortened column names so we need to expand those.
				if ( ! empty( $search_fields['login'] ) ) {
					$prepared_search_fields['user_login'] = $search_fields['login'];

					unset( $search_fields['login'] );
				}

				if ( ! empty( $search_fields['url'] ) ) {
					$prepared_search_fields['user_url'] = $search_fields['url'];

					unset( $search_fields['url'] );
				}

				if ( ! empty( $search_fields['nicename'] ) ) {
					$prepared_search_fields['user_nicename'] = $search_fields['nicename'];

					unset( $search_fields['nicename'] );
				}

				if ( ! empty( $search_fields['email'] ) ) {
					$prepared_search_fields['user_email'] = $search_fields['email'];

					unset( $search_fields['email'] );
				}

				if ( ! empty( $search_fields['meta'] ) ) {
					$metas = (array) $search_fields['meta'];

					foreach ( $metas as $meta ) {
						$prepared_search_fields[] = 'meta.' . $meta . '.value';
					}

					unset( $search_fields['meta'] );
				}

				$prepared_search_fields = array_merge( $search_fields, $prepared_search_fields );
			} else {
				$prepared_search_fields = [
					'user_login',
					'user_nicename',
					'user_url',
					'user_email',
					'ID',
				];
			}

			$prepared_search_fields = apply_filters( 'ep_user_search_fields', $prepared_search_fields, $query_vars );

			$query = array(
				'bool' => array(
					'should' => array(
						array(
							'multi_match' => array(
								'query'  => $query_vars['search'],
								'type'   => 'phrase',
								'fields' => $prepared_search_fields,
								'boost'  => apply_filters( 'ep_user_match_phrase_boost', 4, $prepared_search_fields, $query_vars ),
							),
						),
						array(
							'multi_match' => array(
								'query'     => $query_vars['search'],
								'fields'    => $search_fields,
								'boost'     => apply_filters( 'ep_user_match_boost', 2, $prepared_search_fields, $query_vars ),
								'fuzziness' => 0,
								'operator'  => 'and',
							),
						),
						array(
							'multi_match' => array(
								'fields'    => $prepared_search_fields,
								'query'     => $query_vars['search'],
								'fuzziness' => apply_filters( 'ep_user_fuzziness_arg', 1, $prepared_search_fields, $query_vars ),
							),
						),
					),
				),
			);

			$formatted_args['query'] = apply_filters( 'ep_user_formatted_args_query', $query, $query_vars );

		} else {
			$formatted_args['query']['match_all'] = [
				'boost' => 1,
			];
		}

		if ( $use_filters ) {
			$formatted_args['post_filter'] = $filter;
		}

		return apply_filters( 'ep_user_formatted_args', $formatted_args, $query_vars );
	}

	/**
	 * Query DB for users
	 *
	 * @param  array $args
	 * @since  2.6
	 * @return array
	 */
	public function query_db( $args ) {
		global $wpdb;

		$defaults = [
			'number'  => 350,
			'offset'  => 0,
			'orderby' => 'id',
			'order'   => 'desc',
		];

		if ( isset( $args['per_page'] ) ) {
			$args['number'] = $args['per_page'];
		}

		$args = apply_filters( 'ep_user_query_db_args', wp_parse_args( $args, $defaults ) );

		$args['order'] = trim( strtolower( $args['order'] ) );

		if ( ! in_array( $args['order'], [ 'asc', 'desc' ], true ) ) {
			$args['order'] = 'desc';
		}

		/**
		 * WP_User_Query doesn't let us get users across all blogs easily. This is the best
		 * way to do that.
		 */
		$objects = $wpdb->get_results( $wpdb->prepare( "SELECT SQL_CALC_FOUND_ROWS ID FROM {$wpdb->prefix}users ORDER BY %s %s LIMIT %d, %d", $args['orderby'], $args['orderby'], (int) $args['offset'], (int) $args['number'] ) );

		return [
			'objects'       => $objects,
			'total_objects' => ( 0 === count( $objects ) ) ? 0 : (int) $wpdb->get_var( 'SELECT FOUND_ROWS()' ),
		];
	}

	/**
	 * Put mapping for users
	 *
	 * @since  2.6
	 * @return boolean
	 */
	public function put_mapping() {
		$mapping = require( apply_filters( 'ep_user_mapping_file', __DIR__ . '/../../../mappings/user/initial.php' ) );

		$mapping = apply_filters( 'ep_user_mapping', $mapping );

		return Elasticsearch::factory()->put_mapping( $this->get_index_name(), $mapping );
	}

	/**
	 * Prepare a user document for indexing
	 *
	 * @param  int $user_id
	 * @since  2.6
	 * @return array
	 */
	public function prepare_document( $user_id ) {
		$user = get_user_by( 'ID', $user_id );

		if ( empty( $user ) ) {
			return false;
		}

		$user_args = [
			'ID'              => $user_id,
			'user_login'      => $user->user_login,
			'user_email'      => $user->user_email,
			'user_nicename'   => $user->user_nicename,
			'spam'            => $user->spam,
			'deleted'         => $user->spam,
			'user_status'     => $user->user_status,
			'display_name'    => $user->display_name,
			'user_registered' => $user->user_registered,
			'user_url'        => $user->user_url,
			'capabilities'    => $this->prepare_capabilities( $user_id ),
			'meta'            => $this->prepare_meta_types( $this->prepare_meta( $user_id ) ),
		];

		$user_args = apply_filters( 'ep_user_sync_args', $user_args, $user_id );

		return $user_args;
	}

	/**
	 * Prepare capabilities for indexing
	 *
	 * @param  int $user_id User ID
	 * @since  2.6
	 * @return array
	 */
	public function prepare_capabilities( $user_id ) {
		global $wpdb;

		$sites = Utils\get_sites();

		$prepared_roles = [];

		foreach ( $sites as $site ) {
			$roles = get_user_meta( $user_id, $wpdb->get_blog_prefix( $site['blog_id'] ) . 'capabilities', true );

			if ( ! empty( $roles ) ) {
				$prepared_roles[ (int) $site['blog_id'] ] = [
					'roles' => array_keys( $roles ),
				];
			}
		}

		return $prepared_roles;
	}

	/**
	 * Prepare meta to send to ES
	 *
	 * @param int $user_id
	 * @since 2.6
	 * @return array
	 */
	public function prepare_meta( $user_id ) {
		$meta = (array) get_user_meta( $user_id );

		if ( empty( $meta ) ) {
			return [];
		}

		$prepared_meta = [];

		/**
		 * Filter index-able private meta
		 *
		 * Allows for specifying private meta keys that may be indexed in the same manor as public meta keys.
		 *
		 * @since 2.6
		 *
		 * @param         array Array of index-able private meta keys.
		 * @param WP_Post $post The current post to be indexed.
		 */
		$allowed_protected_keys = apply_filters( 'ep_prepare_user_meta_allowed_protected_keys', [], $user_id );

		/**
		 * Filter non-indexed public meta
		 *
		 * Allows for specifying public meta keys that should be excluded from the ElasticPress index.
		 *
		 * @since 2.6
		 *
		 * @param         array Array of public meta keys to exclude from index.
		 * @param WP_Post $post The current post to be indexed.
		 */
		$excluded_public_keys = apply_filters(
			'ep_prepare_user_meta_excluded_public_keys', [
				'session_tokens',
			], $user_id
		);

		foreach ( $meta as $key => $value ) {

			$allow_index = false;

			if ( is_protected_meta( $key ) ) {

				if ( true === $allowed_protected_keys || in_array( $key, $allowed_protected_keys ) ) {
					$allow_index = true;
				}
			} else {

				if ( true !== $excluded_public_keys && ! in_array( $key, $excluded_public_keys ) ) {
					$allow_index = true;
				}
			}

			if ( true === $allow_index || apply_filters( 'ep_prepare_user_meta_whitelist_key', false, $key, $user_id ) ) {
				$prepared_meta[ $key ] = maybe_unserialize( $value );
			}
		}

		return $prepared_meta;
	}
}
