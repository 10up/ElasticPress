<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class EP_User_Query_Integration {

	/** @var EP_Object_Index */
	private $user_index;

	private $aggregations;

	/**
	 * EP_User_Query_Integration constructor.
	 *
	 * @param EP_Object_Index $user_index
	 */
	public function __construct( $user_index = null ) {
		$this->user_index = $user_index ? $user_index : ep_get_object_type( 'user' );
	}

	public function setup() {
		$this->disable();
		/**
		 * By default EP will not integrate on admin or ajax requests. Since admin-ajax.php is
		 * technically an admin request, there is some weird logic here. If we are doing ajax
		 * and ep_ajax_user_query_integration is filtered true, then we skip the next admin check.
		 */
		$admin_integration = apply_filters( 'ep_admin_user_query_integration', false );

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			if ( ! apply_filters( 'ep_ajax_user_query_integration', false ) ) {
				return;
			} else {
				$admin_integration = true;
			}
		}

		if ( is_admin() && ! $admin_integration ) {
			return;
		}

		if ( ! $this->is_user_indexing_active() ) {
			return;
		}
		$action = $this->get_pre_get_users_action();
		add_action( $action, array( $this, "action_$action" ), 99999 );
		add_action( 'ep_wp_cli_pre_index', array( $this, 'disable' ) );
		add_action( 'ep_wp_cli_pre_user_index', array( $this, 'disable' ) );
	}

	/**
	 * Disable the query integration
	 */
	public function disable() {
		$action = $this->get_pre_get_users_action();
		remove_action( $action, array( $this, "action_$action" ), 99999 );
		remove_action( 'ep_wp_cli_pre_index', array( $this, 'disable' ) );
		remove_action( 'ep_wp_cli_pre_user_index', array( $this, 'disable' ) );
	}

	/**
	 * Wrapper for action_pre_get_users when the pre_get_users action isn't available
	 *
	 * This only runs when the pre_get_users action is unavailable, so we can't preempt the query, instead we need to
	 * clean up what WP_User_Query has already done in prepare_query().
	 *
	 * The pre_get_users action was introduced in WordPress 4.0
	 *
	 * @param WP_User_Query $wp_user_query
	 */
	public function action_pre_user_query( $wp_user_query ) {
		if (
			! empty( $wp_user_query->query_vars['meta_query'] ) &&
			( ! empty( $wp_user_query->query_vars['role'] ) || is_multisite() )
		) {
			$wp_user_query->query_vars['meta_query'] = array_filter(
				$wp_user_query->query_vars['meta_query'],
				array( $this, 'remove_role_meta_query' )
			);
		}
		$this->action_pre_get_users( $wp_user_query );
		if ( empty( $wp_user_query->query_vars['elasticpress'] ) ) {
			return;
		}
		$this->disable();
		$wp_user_query->prepare_query();
		$this->setup();
	}

	/**
	 * Filter the role meta query out so that we're not looking users up with the wrong data
	 *
	 * @param mixed $item
	 *
	 * @return bool
	 */
	public function remove_role_meta_query( $item ) {
		if ( is_array( $item ) && ! empty( $item['key'] ) ) {
			return ! preg_match( '/^.+_capabilities$/', $item['key'] );
		}

		return true;
	}

	/**
	 * @param WP_User_Query $wp_user_query
	 */
	public function action_pre_get_users( $wp_user_query ) {
		if ( $this->is_query_basic_enough_to_skip( $wp_user_query ) || $this->skip_integration( $wp_user_query ) ) {
			// The User query MUST hit the database, so if this query is so basic that it wouldn't even join any tables
			// then we should just skip it outright
			return;
		}
		$default_args = array(
			'blog_id'             => null,
			'role'                => '',
			'meta_key'            => '',
			'meta_value'          => '',
			'meta_compare'        => '',
			'include'             => array(),
			'exclude'             => array(),
			'search'              => '',
			'search_columns'      => array(),
			'orderby'             => 'login',
			'order'               => 'ASC',
			'offset'              => '',
			'number'              => '',
			'count_total'         => false,
			'fields'              => 'all',
			'who'                 => '',
			'has_published_posts' => null,
		);

		$qv    = $wp_user_query->query_vars;
		$scope = $qv['blog_id'];
		if ( -1 === $scope ) {
			$scope = 'all';
		}
		if ( 'all' === $scope && ! apply_filters( 'ep_user_global_search_active', false ) ) {
			$scope = 'current';
		}
		if ( ! in_array( $scope, array( 'all', 'current' ) ) ) {
			$scope = array_filter( wp_parse_id_list( $scope ) );
		}
		try {
			$results = ep_query( $this->format_args( $wp_user_query, $scope ), $qv ,$scope ? $scope : 'current', 'user' );
		} catch ( Exception $e ) {
			/**
			 * Allow visibility into any exceptions that we catch here
			 *
			 * @param Exception                 $e
			 * @param WP_User_Query             $wp_user_query
			 * @param mixed                     $scope
			 * @param EP_User_Query_Integration $this
			 */
			do_action( 'ep_user_search_exception_handler', $e, $wp_user_query, $scope, $this );
			$results = array( 'found_objects' => 0, 'objects' => array() );
		}

		if ( $results['found_objects'] < 1 ) {
			$wp_user_query->query_vars = $default_args;
			add_action( 'pre_user_query', array( $this, 'kill_query' ), 999999 );

			return;
		}

		$new_qv                 = $default_args;
		$new_qv['include']      = wp_list_pluck( $results['objects'], 'user_id' );
		$new_qv['orderby']      = 'include';
		$new_qv['fields']       = $qv['fields'];
		$new_qv['number']       = $qv['number'];
		$new_qv['count_total']  = false;
		$new_qv['elasticpress'] = true;

		$wp_user_query->query_vars  = $new_qv;
		$wp_user_query->total_users = $results['found_objects'];
	}

	/**
	 * @param WP_User_Query $wp_user_query
	 * @param               $scope
	 *
	 * @throws Exception If the current query must be aborted
	 *
	 * @return array
	 */
	public function format_args( $wp_user_query, $scope ) {
		$arguments    = $wp_user_query->query_vars;
		$ep_arguments = array();
		if ( empty( $arguments['number'] ) ) {
			$arguments['number'] = (int) apply_filters(
				'ep_wp_user_query_integration_default_size',
				1000,
				$wp_user_query
			);
		}
		// Can't have negative numbers for size
		$ep_arguments['size'] = max( 0, (int) $arguments['number'] );
		$ep_arguments['from'] = max( 0, empty( $arguments['offset'] ) ? 0 : (int) $arguments['offset'] );

		if ( ! empty( $arguments['search'] ) && trim( $arguments['search'] ) ) {
			if ( empty( $arguments['order'] ) ) {
				$arguments['order'] = 'desc';
			}
			if ( empty( $arguments['orderby'] ) ) {
				$arguments['orderby'] = 'relevance';
			}
		}

		if ( $sorts = $this->parse_sorting( $arguments ) ) {
			$ep_arguments['sort'] = $sorts;
		}

		$filter     = array(
			'and' => array(),
		);
		$use_filter = false;

		/**
		 * Tax queries
		 *
		 * Because why not?
		 */
		if ( ! empty( $arguments['tax_query'] ) ) {
			$tax_filter = array();

			foreach ( $arguments['tax_query'] as $single_tax_query ) {
				if ( ! empty( $single_tax_query['terms'] ) && ! empty( $single_tax_query['field'] ) && 'slug' === $single_tax_query['field'] ) {
					$terms = (array) $single_tax_query['terms'];

					// Set up our terms object
					$terms_obj = array(
						'terms.' . $single_tax_query['taxonomy'] . '.slug' => $terms,
					);

					// Use the AND operator if passed
					if ( ! empty( $single_tax_query['operator'] ) && 'AND' === $single_tax_query['operator'] ) {
						$terms_obj['execution'] = 'and';
					}

					// Add the tax query filter
					$tax_filter[]['terms'] = $terms_obj;
				}
			}

			if ( ! empty( $tax_filter ) ) {
				$filter['and'][]['bool']['must'] = $tax_filter;

				$use_filter = true;
			}
		}
		// End tax queries

		/**
		 * Has Published Posts filter
		 */
		if ( ! empty( $arguments['has_published_posts'] ) ) {
			$authors = $this->get_users_with_posts( $arguments['has_published_posts'], $ep_arguments['size'], $scope );
			if ( ! empty( $arguments['include'] ) ) {
				$ids = array_values( array_intersect( wp_parse_id_list( $arguments['include'] ), $authors ) );
			} else {
				$ids = $authors;
			}
			if ( ! $ids ) {
				throw new LogicException( 'No users could possibly be matched' );
			}
			$arguments['include'] = $ids;
		}
		// end has published posts filter

		/**
		 * include ID list
		 */
		if ( ! empty( $arguments['include'] ) ) {
			$filter['and'][]['bool']['must'] = array(
				'terms' => array(
					'user_id' => array_values( wp_parse_id_list( $arguments['include'] ) ),
				)
			);

			$use_filter = true;
		}
		// end include id list

		/**
		 * exclude ID list
		 */
		if ( ! empty( $arguments['exclude'] ) ) {
			$filter['and'][]['bool']['must_not'] = array(
				'terms' => array(
					'user_id' => array_values( wp_parse_id_list( $arguments['exclude'] ) ),
				)
			);

			$use_filter = true;
		}
		// end exclude id list

		/**
		 * 'date_query' arg support.
		 *
		 */
		if ( ! empty( $arguments['date_query'] ) ) {
			$date_query  = new EP_WP_Date_Query( $arguments['date_query'], 'user_registered' );
			$date_filter = $date_query->get_es_filter();
			if ( array_key_exists( 'and', $date_filter ) ) {
				$filter['and'][] = $date_filter['and'];
				$use_filter      = true;
			}
		}
		// end date query section

		/**
		 * 'role' arg support
		 */
		if ( ! empty( $arguments['role'] ) ) {
			$filter['and'][]['bool']['must'] = array(
				'term' => array(
					'role.raw' => $arguments['role'],
				),
			);

			$use_filter = true;
		}
		// End role query support

		$meta_query = new WP_Meta_Query();
		$meta_query->parse_query_vars( $arguments );
		/**
		 * 'meta_query' arg support.
		 *
		 * Relation supports 'AND' and 'OR'. 'AND' is the default. For each individual query, the
		 * following 'compare' values are supported: =, !=, EXISTS, NOT EXISTS. '=' is the default.
		 * 'type' is NOT support at this time.
		 */
		if ( ! empty( $meta_query->queries ) ) {
			$meta_filter = array();

			$relation = 'must';
			if ( ! empty( $meta_query->relation ) && 'or' === strtolower( $meta_query->relation ) ) {
				$relation = 'should';
			}

			$meta_query_type_mapping = array(
				'numeric'  => 'long',
				'binary'   => 'raw',
				'char'     => 'raw',
				'date'     => 'date',
				'datetime' => 'datetime',
				'decimal'  => 'double',
				'signed'   => 'long',
				'time'     => 'time',
				'unsigned' => 'long',
			);

			foreach ( $meta_query->queries as $single_meta_query ) {
				if ( empty( $single_meta_query['key'] ) ) {
					continue;
				}

				$terms_obj = array();

				$compare = '=';
				if ( ! empty( $single_meta_query['compare'] ) ) {
					$compare = strtolower( $single_meta_query['compare'] );
				}

				$type = null;
				if ( ! empty( $single_meta_query['type'] ) ) {
					$type = strtolower( $single_meta_query['type'] );
				}

				// Comparisons need to look at different paths
				if ( in_array( $compare, array( 'exists', 'not exists' ) ) ) {
					$meta_key_path = 'meta.' . $single_meta_query['key'];
				} elseif ( in_array( $compare, array( '=', '!=' ) ) && ! $type ) {
					$meta_key_path = 'meta.' . $single_meta_query['key'] . '.raw';
				} elseif ( 'like' === $compare ) {
					$meta_key_path = 'meta.' . $single_meta_query['key'] . '.value';
				} elseif ( $type && isset( $meta_query_type_mapping[ $type ] ) ) {
					// Map specific meta field types to different ElasticSearch core types
					$meta_key_path = 'meta.' . $single_meta_query['key'] . '.' . $meta_query_type_mapping[ $type ];
				} elseif ( in_array( $compare, array( '>=', '<=', '>', '<' ) ) ) {
					$meta_key_path = 'meta.' . $single_meta_query['key'] . '.double';
				} else {
					$meta_key_path = 'meta.' . $single_meta_query['key'] . '.raw';
				}

				switch ( $compare ) {
					case '!=':
						if ( isset( $single_meta_query['value'] ) ) {
							$terms_obj = array(
								'bool' => array(
									'must_not' => array(
										array(
											'terms' => array(
												$meta_key_path => (array) $single_meta_query['value'],
											),
										),
									),
								),
							);
						}

						break;
					case 'exists':
						$terms_obj = array(
							'exists' => array(
								'field' => $meta_key_path,
							),
						);

						break;
					case 'not exists':
						$terms_obj = array(
							'bool' => array(
								'must_not' => array(
									array(
										'exists' => array(
											'field' => $meta_key_path,
										),
									),
								),
							),
						);

						break;
					case '>=':
						if ( isset( $single_meta_query['value'] ) ) {
							$terms_obj = array(
								'bool' => array(
									'must' => array(
										array(
											'range' => array(
												$meta_key_path => array(
													"gte" => $single_meta_query['value'],
												),
											),
										),
									),
								),
							);
						}

						break;
					case '<=':
						if ( isset( $single_meta_query['value'] ) ) {
							$terms_obj = array(
								'bool' => array(
									'must' => array(
										array(
											'range' => array(
												$meta_key_path => array(
													"lte" => $single_meta_query['value'],
												),
											),
										),
									),
								),
							);
						}

						break;
					case '>':
						if ( isset( $single_meta_query['value'] ) ) {
							$terms_obj = array(
								'bool' => array(
									'must' => array(
										array(
											'range' => array(
												$meta_key_path => array(
													"gt" => $single_meta_query['value'],
												),
											),
										),
									),
								),
							);
						}

						break;
					case '<':
						if ( isset( $single_meta_query['value'] ) ) {
							$terms_obj = array(
								'bool' => array(
									'must' => array(
										array(
											'range' => array(
												$meta_key_path => array(
													"lt" => $single_meta_query['value'],
												),
											),
										),
									),
								),
							);
						}

						break;
					case 'like':
						if ( isset( $single_meta_query['value'] ) ) {
							$terms_obj = array(
								'query' => array(
									"match" => array(
										$meta_key_path => $single_meta_query['value'],
									)
								),
							);
						}
						break;
					case '=':
					default:
						if ( isset( $single_meta_query['value'] ) ) {
							$terms_obj = array(
								'terms' => array(
									$meta_key_path => (array) $single_meta_query['value'],
								),
							);
						}

						break;
				}

				// Add the meta query filter
				if ( false !== $terms_obj ) {
					$meta_filter[] = $terms_obj;
				}
			}

			if ( ! empty( $meta_filter ) ) {
				$filter['and'][]['bool'][ $relation ] = $meta_filter;

				$use_filter = true;
			}
		}
		// End meta query filter

		/**
		 * Search support
		 */
		$search = '';
		if ( isset( $arguments['search'] ) ) {
			$search = trim( $arguments['search'] );
		}
		if ( $search ) {
			$search_columns = $this->parse_search_columns( $arguments, $search, $wp_user_query );

			$ep_arguments['query']['bool']['should'] = array(
				array(
					'multi_match' => array(
						'query'     => $search,
						'fields'    => $search_columns,
						'boost'     => apply_filters( 'ep_user_match_boost', 2, $search_columns, $arguments ),
						'fuzziness' => 0,
					)
				),
				array(
					'multi_match' => array(
						'fields'    => $search_columns,
						'query'     => $search,
						'fuzziness' => apply_filters( 'ep_min_user_similarity', 2, $search_columns, $arguments ),
						'operator'  => 'or',
					),
				)
			);
		}
		// End search support

		if ( $use_filter ) {
			$ep_arguments['filter'] = $filter;
		}

		return $ep_arguments;
	}

	/**
	 * @param WP_User_Query $wp_user_query
	 */
	public function kill_query( $wp_user_query ) {
		global $wpdb;
		remove_action( 'pre_user_query', array( $this, 'kill_query' ), 999999 );
		$wp_user_query->query_fields  = "{$wpdb->users}.ID";
		$wp_user_query->query_from    = "FROM {$wpdb->users}";
		$wp_user_query->query_where   = 'WHERE 1=0';
		$wp_user_query->query_orderby = $wp_user_query->query_limit = '';
	}

	/**
	 * @param EP_User_Index $index
	 *
	 * @return EP_User_Query_Integration
	 */
	public static function factory( $index = null ) {
		static $instance;
		if ( ! $instance ) {
			$instance = new self( $index );
			$instance->setup();
		} elseif ( $index && $index !== $instance->user_index ) {
			$instance->user_index = $index;
		}

		return $instance;
	}

	/**
	 * @return bool
	 */
	private function is_user_indexing_active() {
		return ( $this->user_index && $this->user_index->active() );
	}

	/**
	 * @param WP_User_Query $wp_user_query
	 *
	 * @return bool
	 */
	private function is_query_basic_enough_to_skip( $wp_user_query ) {
		$args      = $wp_user_query->query_vars;
		$safe_args = array( 'include', 'order', 'paged', 'offset', 'number', 'count_total', 'fields', );
		if ( ! is_multisite() ) {
			$safe_args[] = 'blog_id';
		}
		if ( in_array( $args['orderby'], array( 'login', 'nicename', 'user_login', 'user_nicename', 'ID', 'id' ) ) ) {
			$safe_args[] = 'orderby';
		}
		if ( ! array_diff( array_keys( array_filter( $args ) ), $safe_args ) ) {
			return true;
		}

		return false;
	}

	/**
	 * @param WP_User_Query $wp_user_query
	 *
	 * @return bool
	 */
	private function skip_integration( $wp_user_query ) {
		return apply_filters( 'ep_skip_user_query_integration', false, $wp_user_query );
	}

	private function toggle_user_prefix( $thing, $on = null ) {
		$_thing = $thing;
		if ( 'user_' === substr( $thing, 0, 5 ) ) {
			$thing = substr( $thing, 5 );
		}
		if ( true === $on ) {
			return "user_$thing";
		} elseif ( false === $on ) {
			return $thing;
		}

		return $_thing === $thing ? "user_$_thing" : $thing;
	}

	/**
	 * @param $arguments
	 *
	 * @return array
	 */
	private function parse_sorting( $arguments ) {
		if ( empty( $arguments['order'] ) ) {
			$arguments['order'] = 'asc';
		}
		$order = strtolower( $arguments['order'] ) === 'asc' ? 'asc' : 'desc';
		if ( empty( $arguments['orderby'] ) ) {
			$orderby = array( 'user_login' => $order );
		} elseif ( is_array( $arguments['orderby'] ) ) {
			$orderby = $arguments['orderby'];
		} else {
			$orderby = preg_split( '/[,\s]+/', $arguments['orderby'] );
		}
		$sorts = array();
		foreach ( $orderby as $_key => $_value ) {
			if ( empty( $_value ) ) {
				continue;
			}
			if ( is_int( $_key ) ) {
				$_orderby = $_value;
				$_order   = $order;
			} else {
				$_orderby = $_key;
				$_order   = strtolower( $_value ) === 'asc' ? 'asc' : 'desc';
			}
			$sort_field = false;
			switch ( strtolower( $_orderby ) ) {
				case 'id':
				case 'user_id':
				case 'registered':
				case 'user_registered':
					$sort_field = array(
						strtolower( $this->toggle_user_prefix( $_orderby, true ) ) => array( 'order' => $_order )
					);
					break;
				case 'login':
				case 'nicename':
				case 'email':
				case 'url':
				case 'user_login':
				case 'user_nicename':
				case 'user_email':
				case 'user_url':
					$sort_field = array(
						$this->toggle_user_prefix( $_orderby, true ) . ".sortable" => array( 'order' => $_order )
					);
					break;
				case 'name':
				case 'display_name':
					$sort_field = array( 'display_name.sortable' => array( 'order' => $_order ) );
					break;
				case 'score':
				case 'relevance':
					$sort_field = array( '_score' => array( 'order' => $_order ) );
					break;
				default:
					$sort_field = array( $_orderby => array( 'order' => $_order ) );
			}
			if ( $sort_field ) {
				$sorts[] = $sort_field;
			}
		}

		return $sorts;
	}

	/**
	 * @param $arguments
	 * @param $search
	 * @param $query
	 *
	 * @return array|mixed|void
	 */
	private function parse_search_columns( $arguments, $search, $query ) {
		// First we need to build up our list of search columns the way WordPress does on its own
		$search_columns     = array();
		$search_column_args = array();
		if ( isset( $arguments['search_columns'] ) && is_array( $arguments['search_columns'] ) ) {
			$search_columns     = array_intersect(
				$arguments['search_columns'],
				array( 'ID', 'user_login', 'user_email', 'user_url', 'user_nicename' )
			);
			$search_column_args = $arguments['search_columns'];
		}
		if ( ! $search_columns ) {
			if ( false !== strpos( $search, '@' ) ) {
				$search_columns = array( 'user_email' );
			} elseif ( is_numeric( $search ) ) {
				$search_columns = array( 'user_login', 'ID' );
			} elseif (
				preg_match( '|^https?://|', $search ) && ! ( is_multisite() && wp_is_large_network( 'users' ) )
			) {
				$search_columns = array( 'user_url' );
			} else {
				$search_columns = array( 'user_login', 'user_url', 'user_email', 'user_nicename', 'display_name' );
			}
		}

		$search_columns = apply_filters( 'user_search_columns', $search_columns, $search, $query );
		if ( false !== ( $key = array_search( 'ID', $search_columns ) ) ) {
			$search_columns[ $key ] = 'user_id';
		}

		// Ok, now we have our search columns. Now for some elasicpress-specific search columns

		if ( false !== ( $key = array_search( 'user_id', $search_column_args ) ) ) {
			$search_columns[] = 'user_id';
		}

		if ( ! empty( $search_column_args['taxonomies'] ) ) {
			$taxes = (array) $search_column_args['taxonomies'];

			foreach ( $taxes as $tax ) {
				$search_columns[] = 'terms.' . $tax . '.name';
			}
		}

		if ( ! empty( $search_column_args['meta'] ) ) {
			$metas = (array) $search_column_args['meta'];

			foreach ( $metas as $meta ) {
				$search_columns[] = 'meta.' . $meta . '.value';
			}
		}

		$search_columns = apply_filters( 'ep_user_search_fields', $search_columns, $arguments );

		return array_unique( $search_columns );
	}

	/**
	 * @param $has_published_posts
	 * @param $size
	 * @param $scope
	 *
	 * @return array
	 */
	private function get_users_with_posts( $has_published_posts, $size, $scope ) {
		if ( true === $has_published_posts ) {
			$post_types = array_values( get_post_types( array( 'public' => true ) ) );
		} else {
			$post_types = (array) $has_published_posts;
		}
		$author_search      = array(
			'size' => 0,
			'aggs' => array(
				'author_ids' => array(
					'terms' => array(
						'field' => 'post_author.id',
						'order' => array( 'post_types' => 'desc' ),
						'size'  => $size,
					),
					'aggs'  => array(
						'post_types' => array(
							'filter' => array(
								'terms' => array(
									'post_type.raw' => (array) $post_types
								)
							)
						)
					),
				),
			),
		);
		$this->aggregations = null;
		add_action( 'ep_retrieve_aggregations', array( $this, 'intercept_aggregations' ) );
		ep_query( $author_search, $scope ? $scope : 'current', 'post' );
		remove_action( 'ep_retrieve_aggregations', array( $this, 'intercept_aggregations' ) );
		if (
			! $this->aggregations ||
			! is_array( $this->aggregations ) ||
			empty( $this->aggregations['author_ids']['buckets'] )
		) {
			return array();
		}
		$user_ids = array();
		foreach ( $this->aggregations['author_ids']['buckets'] as $bucket ) {
			if ( empty( $bucket['post_types']['doc_count'] ) ) {
				continue;
			}
			$user_ids[] = (int) $bucket['key'];
		}

		return array_filter( $user_ids );
	}

	public function intercept_aggregations( $aggregations ) {
		$this->aggregations = $aggregations;
	}

	/**
	 * Get the correct action to hook into user queries based on the WP version
	 *
	 * @return string
	 */
	private function get_pre_get_users_action() {
		return version_compare( $GLOBALS['wp_version'], '4.0', '<' ) ? 'pre_user_query' : 'pre_get_users';
	}

}

add_action( 'plugins_loaded', array( 'EP_User_Query_Integration', 'factory' ), 20 );
