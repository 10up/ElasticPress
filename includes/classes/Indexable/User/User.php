<?php
/**
 * User indexable
 *
 * @since  3.0
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
	 * @since  3.0
	 */
	public $global = true;

	/**
	 * Indexable slug
	 *
	 * @var string
	 * @since  3.0
	 */
	public $slug = 'user';

	/**
	 * Create indexable and setup dependencies
	 *
	 * @since  3.0
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
	 * @param  array         $query_vars WP_User_Query args.
	 * @param  WP_User_Query $query      User query object
	 * @since  3.0
	 * @return array
	 */
	public function format_args( $query_vars, $query ) {
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

				/**
				 * Filter max result size if set to -1
				 *
				 * @hook ep_max_results_window
				 * @param  {int} $window Max result window
				 * @return {int} New window
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

		/**
		 * Support `blog_id` query arg
		 */
		$blog_id = false;
		if ( isset( $query_vars['blog_id'] ) ) {
			$blog_id = (int) $query_vars['blog_id'];
		}

		/**
		 * Support `role` query arg
		 */
		if ( ! empty( $blog_id ) ) {
			if ( ! empty( $query_vars['role'] ) ) {
				$roles = (array) $query_vars['role'];

				foreach ( $roles as $role ) {
					$filter['bool']['must'][] = array(
						'terms' => array(
							'capabilities.' . $blog_id . '.roles' => [
								strtolower( $role ),
							],
						),
					);
				}

				$use_filters = true;
			} else {
				if ( ! empty( $query_vars['role__in'] ) ) {
					$roles_in = (array) $query_vars['role__in'];

					$roles_in = array_map( 'strtolower', $roles_in );

					$filter['bool']['must'][] = array(
						'terms' => array(
							'capabilities.' . $blog_id . '.roles' => $roles_in,
						),
					);

					$use_filters = true;
				}

				if ( ! empty( $query_vars['role__not_in'] ) ) {
					$roles_not_in = (array) $query_vars['role__not_in'];

					foreach ( $roles_not_in as $role ) {
						$filter['bool']['must_not'][] = array(
							'terms' => array(
								'capabilities.' . $blog_id . '.roles' => [
									strtolower( $role ),
								],
							),
						);
					}

					$use_filters = true;
				}
			}
		}

		$meta_queries = [];

		/**
		 * Support `meta_key`, `meta_value`, and `meta_compare`
		 */
		if ( ! empty( $query_vars['meta_key'] ) ) {
			$meta_query_array = [
				'key' => $query_vars['meta_key'],
			];

			if ( isset( $query_vars['meta_value'] ) ) {
				$meta_query_array['value'] = $query_vars['meta_value'];
			}

			if ( isset( $query_vars['meta_compare'] ) ) {
				$meta_query_array['compare'] = $query_vars['meta_compare'];
			}

			$meta_queries[] = $meta_query_array;
		}

		/**
		 * 'meta_query' arg support.
		 */
		if ( ! empty( $query_vars['meta_query'] ) ) {
			$meta_queries = array_merge( $meta_queries, $query_vars['meta_query'] );
		}

		if ( ! empty( $meta_queries ) ) {
			$filter['bool']['must'][] = $this->build_meta_query( $meta_queries );

			$use_filters = true;
		}

		/**
		 * Support `fields` query var.
		 */
		if ( isset( $query_vars['fields'] ) && 'all' !== $query_vars['fields'] && 'all_with_meta' !== $query_vars['fields'] ) {
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
		if ( ! empty( $query_vars['nicename__not_in'] ) ) {
			$filter['bool']['must'][] = [
				'bool' => [
					'must_not' => [
						[
							'terms' => [
								'user_nicename' => (array) $query_vars['nicename__not_in'],
							],
						],
					],
				],
			];

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
		 * Support `include` parameter
		 */
		if ( ! empty( $query_vars['include'] ) ) {
			$filter['bool']['must'][] = [
				'bool' => [
					'must' => [
						'terms' => [
							'ID' => array_values( (array) $query_vars['include'] ),
						],
					],
				],
			];

			$use_filters = true;
		}

		/**
		 * Support `exclude` parameter
		 */
		if ( ! empty( $query_vars['exclude'] ) ) {
			$filter['bool']['must'][] = [
				'bool' => [
					'must_not' => [
						'terms' => [
							'ID' => array_values( (array) $query_vars['exclude'] ),
						],
					],
				],
			];

			$use_filters = true;
		}

		/**
		 * Need to support a few more params
		 *
		 * @todo  Support the following parameters:
		 *
		 * $who
		 * $has_published_posts
		 */

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
				];
			}

			/**
			 * Filter search fields in user query
			 *
			 * @hook ep_user_search_fields
			 * @param  {array} $prepared_search_fields Prepared search fields
			 * @param  {array} $query_vars Query variables
			 * @since  3.0
			 * @return {array} Search fields
			 */
			$prepared_search_fields = apply_filters( 'ep_user_search_fields', $prepared_search_fields, $query_vars );

			$query = array(
				'bool' => array(
					'should' => array(
						array(
							'multi_match' => array(
								'query'  => $query_vars['search'],
								'type'   => 'phrase',
								'fields' => $prepared_search_fields,
								/**
								 * Filter boost for user match phrase query
								 *
								 * @hook ep_user_match_phrase_boost
								 * @param  {int} $boost Phrase boost
								 * @param {array} $prepared_search_fields Search fields
								 * @param {array} $query_vars Query variables
								 * @since  3.0
								 * @return  {int} New phrase boost
								 */
								'boost'  => apply_filters( 'ep_user_match_phrase_boost', 4, $prepared_search_fields, $query_vars ),
							),
						),
						array(
							'multi_match' => array(
								'query'     => $query_vars['search'],
								'fields'    => $prepared_search_fields,
								/**
								 * Filter boost for user match query
								 *
								 * @hook ep_user_match_boost
								 * @param  {int} $boost Boost
								 * @param {array} $prepared_search_fields Search fields
								 * @param {array} $query_vars Query variables
								 * @since  3.0
								 * @return  {int} New boost
								 */
								'boost'     => apply_filters( 'ep_user_match_boost', 2, $prepared_search_fields, $query_vars ),
								'fuzziness' => 0,
								'operator'  => 'and',
							),
						),
						array(
							'multi_match' => array(
								'fields'    => $prepared_search_fields,
								'query'     => $query_vars['search'],
								/**
								 * Filter fuzziness for user query
								 *
								 * @hook ep_user_fuzziness_arg
								 * @param  {int} $fuzziness Fuzziness
								 * @param {array} $prepared_search_fields Search fields
								 * @param {array} $query_vars Query variables
								 * @since  3.0
								 * @return  {int} New fuzziness
								 */
								'fuzziness' => apply_filters( 'ep_user_fuzziness_arg', 1, $prepared_search_fields, $query_vars ),
							),
						),
					),
				),
			);

			/**
			 * Filter formatted Elasticsearch user query (only contains query part)
			 *
			 * @hook ep_user_formatted_args_query
			 * @param {array} $query Current query
			 * @param {array} $query_vars Query variables
			 * @since  3.0
			 * @return  {array} New query
			 */
			$formatted_args['query'] = apply_filters( 'ep_user_formatted_args_query', $query, $query_vars );

		} else {
			$formatted_args['query']['match_all'] = [
				'boost' => 1,
			];
		}

		if ( $use_filters ) {
			$formatted_args['post_filter'] = $filter;
		}

		/**
		 * Handle order and orderby
		 */
		if ( ! empty( $query_vars['order'] ) ) {
			$order = trim( strtolower( $query_vars['order'] ) );
		} else {
			$order = 'desc';
		}

		if ( empty( $query_vars['orderby'] ) && ( ! isset( $query_vars['search'] ) || '' === $query_vars['search'] ) ) {
			$query_vars['orderby'] = 'user_login';
		}

		// Set sort type.
		if ( ! empty( $query_vars['orderby'] ) ) {
			$formatted_args['sort'] = $this->parse_orderby( $query_vars['orderby'], $order, $query_vars );
		} else {
			// Default sort is to use the score (based on relevance).
			$formatted_args['sort'] = array(
				array(
					'_score' => array(
						'order' => $order,
					),
				),
			);
		}

		/**
		 * Filter formatted Elasticsearch user query (entire query)
		 *
		 * @hook ep_user_formatted_args_query
		 * @param {array} $formatted_args Formatted Elasticsearch query
		 * @param {array} $query_vars Query variables
		 * @param {array} $query Query part
		 * @since  3.0
		 * @return  {array} New query
		 */
		return apply_filters( 'ep_user_formatted_args', $formatted_args, $query_vars, $query );
	}

	/**
	 * Convert the alias to a properly-prefixed sort value.
	 *
	 * @since  3.0
	 * @param  string $orderby Orderby query var
	 * @param  string $default_order Order direction
	 * @param  array  $query_vars Query vars
	 * @return array
	 */
	public function parse_orderby( $orderby, $default_order, $query_vars ) {
		/**
		 * More params to support
		 *
		 * @todo  Need to support:
		 *
		 * include
		 * login__in
		 * nicename__in
		 * user_registered registered
		 * post_count
		 */

		if ( ! is_array( $orderby ) ) {
			$orderby = explode( ' ', $orderby );
		}

		$sort = [];

		foreach ( $orderby as $key => $value ) {
			if ( is_string( $key ) ) {
				$orderby_clause = $key;
				$order          = $value;
			} else {
				$orderby_clause = $value;
				$order          = $default_order;
			}

			if ( ! empty( $orderby_clause ) && 'rand' !== $orderby_clause ) {
				if ( 'relevance' === $orderby_clause ) {
					$sort[] = array(
						'_score' => array(
							'order' => $order,
						),
					);
				} elseif ( 'user_login' === $orderby_clause || 'login' === $orderby_clause ) {
					$sort[] = array(
						'user_login.raw' => array(
							'order' => $order,
						),
					);
				} elseif ( 'ID' === $orderby_clause ) {
					$sort[] = array(
						'ID' => array(
							'order' => $order,
						),
					);
				} elseif ( 'display_name' === $orderby_clause || 'name' === $orderby_clause ) {
					$sort[] = array(
						'display_name' => array(
							'order' => $order,
						),
					);
				} elseif ( 'user_nicename' === $orderby_clause || 'nicename' === $orderby_clause ) {
					$sort[] = array(
						'user_nicename' => array(
							'order' => $order,
						),
					);
				} elseif ( 'user_email' === $orderby_clause || 'email' === $orderby_clause ) {
					$sort[] = array(
						'user_email' => array(
							'order' => $order,
						),
					);
				} elseif ( 'user_url' === $orderby_clause || 'url' === $orderby_clause ) {
					$sort[] = array(
						'user_url' => array(
							'order' => $order,
						),
					);
				} elseif ( 'meta_value' === $orderby_clause ) {
					if ( ! empty( $query_vars['meta_key'] ) ) {
						$sort[] = array(
							'meta.' . $query_vars['meta_key'] . '.raw' => array(
								'order' => $order,
							),
						);
					}
				} elseif ( 'meta_value_num' === $orderby_clause ) {
					if ( ! empty( $query_vars['meta_key'] ) ) {
						$sort[] = array(
							'meta.' . $query_vars['meta_key'] . '.long' => array(
								'order' => $order,
							),
						);
					}
				} else {
					$sort[] = array(
						$orderby_clause => array(
							'order' => $order,
						),
					);
				}
			}
		}

		return $sort;
	}

	/**
	 * Query DB for users
	 *
	 * @param  array $args Query arguments
	 * @since  3.0
	 * @return array
	 */
	public function query_db( $args ) {
		global $wpdb;

		$defaults = [
			'number'  => 350,
			'offset'  => 0,
			'orderby' => 'ID',
			'order'   => 'desc',
		];

		if ( isset( $args['per_page'] ) ) {
			$args['number'] = $args['per_page'];
		}

		/**
		 * Filter query database arguments for user indexable
		 *
		 * @hook ep_user_query_db_args
		 * @param {array} $args Database query arguments
		 * @since  3.0
		 * @return  {array} New arguments
		 */
		$args = apply_filters( 'ep_user_query_db_args', wp_parse_args( $args, $defaults ) );

		$args['order'] = trim( strtolower( $args['order'] ) );

		if ( ! in_array( $args['order'], [ 'asc', 'desc' ], true ) ) {
			$args['order'] = 'desc';
		}

		/**
		 * WP_User_Query doesn't let us get users across all blogs easily. This is the best
		 * way to do that.
		 */
		$objects = $wpdb->get_results( $wpdb->prepare( "SELECT SQL_CALC_FOUND_ROWS ID FROM {$wpdb->users} ORDER BY %s %s LIMIT %d, %d", $args['orderby'], $args['orderby'], (int) $args['offset'], (int) $args['number'] ) );

		return [
			'objects'       => $objects,
			'total_objects' => ( 0 === count( $objects ) ) ? 0 : (int) $wpdb->get_var( 'SELECT FOUND_ROWS()' ),
		];
	}

	/**
	 * Put mapping for users
	 *
	 * @since  3.0
	 * @return boolean
	 */
	public function put_mapping() {
		$es_version = Elasticsearch::factory()->get_elasticsearch_version();
		if ( empty( $es_version ) ) {
			/**
			 * Filter fallback Elasticsearch version
			 *
			 * @hook ep_fallback_elasticsearch_version
			 * @param {string} $version Fall back Elasticsearch version
			 * @return  {string} New version
			 */
			$es_version = apply_filters( 'ep_fallback_elasticsearch_version', '2.0' );
		}

		$mapping_file = 'initial.php';

		if ( version_compare( $es_version, '5.0', '<' ) ) {
			$mapping_file = 'pre-5-0.php';
		} elseif ( version_compare( $es_version, '7.0', '>=' ) ) {
			$mapping_file = '7-0.php';
		}

		/**
		 * Filter user indexable mapping file
		 *
		 * @hook ep_user_mapping_file
		 * @param {string} $file Path to file
		 * @since  3.0
		 * @return  {string} New file path
		 */
		$mapping = require apply_filters( 'ep_user_mapping_file', __DIR__ . '/../../../mappings/user/' . $mapping_file );

		/**
		 * Filter user indexable mapping
		 *
		 * @hook ep_user_mapping
		 * @param {array} $mapping Mapping
		 * @since  3.0
		 * @return  {array} New mapping
		 */
		$mapping = apply_filters( 'ep_user_mapping', $mapping );

		return Elasticsearch::factory()->put_mapping( $this->get_index_name(), $mapping );
	}

	/**
	 * Prepare a user document for indexing
	 *
	 * @param  int $user_id User id
	 * @since  3.0
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

		/**
		 * Filter prepared user document before index
		 *
		 * @hook ep_user_sync_args
		 * @param {array} $user_args Document
		 * @param  {int} $user_id User ID
		 * @since  3.0
		 * @return  {array} New document
		 */
		$user_args = apply_filters( 'ep_user_sync_args', $user_args, $user_id );

		return $user_args;
	}

	/**
	 * Prepare capabilities for indexing
	 *
	 * @param  int $user_id User ID
	 * @since  3.0
	 * @return array
	 */
	public function prepare_capabilities( $user_id ) {
		global $wpdb;

		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			$sites = Utils\get_sites();
		} else {
			$sites = [
				[
					'blog_id' => (int) get_current_blog_id(),
				],
			];
		}

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
	 * @param int $user_id User id
	 * @since 3.0
	 * @return array
	 */
	public function prepare_meta( $user_id ) {
		$meta = (array) get_user_meta( $user_id );

		if ( empty( $meta ) ) {
			return [];
		}

		$prepared_meta = [];

		/**
		 * Filter indexable private meta for users
		 *
		 * @hook ep_prepare_user_meta_allowed_protected_keys
		 * @param {array} $meta Meta keys
		 * @param  {int} $user_id User ID
		 * @since  3.0
		 * @return  {array} New meta array
		 */
		$allowed_protected_keys = apply_filters( 'ep_prepare_user_meta_allowed_protected_keys', [], $user_id );

		/**
		 * Filter out excluded indexable public meta keys for users
		 *
		 * @hook ep_prepare_user_meta_excluded_public_keys
		 * @param {array} $meta Meta keys
		 * @param  {int} $user_id User ID
		 * @since  3.0
		 * @return  {array} New meta array
		 */
		$excluded_public_keys = apply_filters(
			'ep_prepare_user_meta_excluded_public_keys',
			[
				'session_tokens',
			],
			$user_id
		);

		foreach ( $meta as $key => $value ) {

			$allow_index = false;

			if ( is_protected_meta( $key ) ) {

				if ( true === $allowed_protected_keys || in_array( $key, $allowed_protected_keys, true ) ) {
					$allow_index = true;
				}
			} else {

				if ( true !== $excluded_public_keys && ! in_array( $key, $excluded_public_keys, true ) ) {
					$allow_index = true;
				}
			}

			/**
			 * Filter whether to whitelist a specific user meta key
			 *
			 * @hookep_prepare_user_meta_whitelist_key
			 * @param {bool} $index True to force index
			 * @param {string} $key User meta key
			 * @param  {int} $user_id User ID
			 * @since  3.0
			 * @return  {bool} New index value
			 */
			if ( true === $allow_index || apply_filters( 'ep_prepare_user_meta_whitelist_key', false, $key, $user_id ) ) {
				$prepared_meta[ $key ] = maybe_unserialize( $value );
			}
		}

		return $prepared_meta;
	}
}
