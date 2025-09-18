<?php
/**
 * ElasticPress Protected Content feature
 *
 * @since  2.2
 * @package elasticpress
 */

namespace ElasticPress\Feature\ProtectedContent;

use ElasticPress\Feature;
use ElasticPress\FeatureRequirementsStatus;
use ElasticPress\Features;
use ElasticPress\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Protected content feature
 */
class ProtectedContent extends Feature {

	/**
	 * Initialize feature setting its config
	 *
	 * @since  3.0
	 */
	public function __construct() {
		$this->slug = 'protected_content';

		$this->group = 'indexing-options';

		$this->requires_install_reindex = true;

		$this->available_during_installation = true;

		parent::__construct();
	}

	/**
	 * Sets i18n strings.
	 *
	 * @return void
	 * @since 5.2.0
	 */
	public function set_i18n_strings(): void {
		$this->title = esc_html__( 'Protected Content', 'elasticpress' );

		$this->summary = '<p>' . __( 'Syncs unpublished content — including private, draft, and scheduled posts — improving load times in places like the administrative dashboard where WordPress needs to include protected content in a query.', 'elasticpress' ) . '</p>' .
		'<p><em>' . __( 'We recommend using a secured Elasticsearch setup, such as ElasticPress.io, to prevent potential exposure of content not intended for the public.', 'elasticpress' ) . '</em></p>';

		$this->docs_url = __( 'https://www.elasticpress.io/documentation/article/configuring-elasticpress-via-the-plugin-dashboard/#protected-content', 'elasticpress' );
	}

	/**
	 * Setup all feature filters
	 *
	 * @since  2.1
	 */
	public function setup() {
		add_filter( 'ep_indexable_post_status', [ $this, 'get_statuses' ] );
		add_filter( 'ep_indexable_post_types', [ $this, 'post_types' ], 10, 1 );
		add_filter( 'ep_post_formatted_args', [ $this, 'exclude_protected_posts' ], 10, 2 );
		add_filter( 'ep_index_posts_args', [ $this, 'query_password_protected_posts' ] );
		add_filter( 'ep_post_sync_args', [ $this, 'include_post_password' ], 10, 2 );
		add_filter( 'ep_post_sync_args', [ $this, 'remove_fields_from_password_protected' ], 11, 2 );
		add_filter( 'ep_search_post_return_args', [ $this, 'return_post_password' ] );
		add_filter( 'ep_skip_autosave_sync', '__return_false' );
		add_filter( 'ep_pre_kill_sync_for_password_protected', [ $this, 'sync_password_protected' ], 10, 2 );

		if ( is_admin() ) {
			add_filter( 'ep_admin_wp_query_integration', '__return_true' );
			add_action( 'pre_get_posts', [ $this, 'integrate' ] );
			add_filter( 'ep_post_query_db_args', [ $this, 'query_password_protected_posts' ] );
			add_filter( 'ep_set_sort', [ $this, 'maybe_change_sort' ] );
			add_filter( 'ep_post_formatted_args', [ $this, 'filter_private_posts_for_current_user' ], 10, 2 );
		}

		if ( Features::factory()->get_registered_feature( 'comments' )->is_active() ) {
			add_filter( 'ep_indexable_comment_status', [ $this, 'get_comment_statuses' ] );
			add_action( 'pre_get_comments', [ $this, 'integrate_comments_query' ] );
		}
	}

	/**
	 * Index all post types
	 *
	 * @param   array $post_types Existing post types.
	 * @since   2.2
	 * @return  array
	 */
	public function post_types( $post_types ) {
		// Let's get non public post types first
		$pc_post_types = get_post_types( array( 'public' => false ) );

		$ignored_post_types = [
			'custom_css',
			'customize_changeset',
			'ep-synonym',
			'ep-pointer',
			'nav_menu_item',
			'oembed_cache',
			'revision',
			'user_request',
			'wp_block',
			'wp_global_styles',
			'wp_navigation',
			'wp_template',
			'wp_template_part',
		];

		foreach ( $ignored_post_types as $ignored_post_type ) {
			unset( $pc_post_types[ $ignored_post_type ] );
		}

		// By default, attachments are not indexed, we have to make sure they are included (Could already be included by documents feature).
		$post_types['attachment'] = 'attachment';

		// Merge non public post types with any pre-filtered post_type
		return array_merge( $post_types, $pc_post_types );
	}

	/**
	 * Integrate EP into proper queries
	 *
	 * @param  WP_Query $query WP Query
	 * @since  2.1
	 */
	public function integrate( $query ) {
		if ( ! Utils\is_integrated_request( $this->slug, [ 'admin' ] ) ) {
			return;
		}

		// Lets make sure this doesn't interfere with the CLI
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return;
		}

		if ( ! $query->is_main_query() ) {
			return;
		}

		/**
		 * We limit to these post types to not conflict with other features like WooCommerce
		 *
		 * @since  2.1
		 * @var array
		 */
		$post_types = array(
			'post'       => 'post',
			'attachment' => 'attachment',
		);

		/**
		 * Filter protected content supported post types. For backwards compatibility.
		 *
		 * @hook ep_admin_supported_post_types
		 * @param  {array} $post_types Post types
		 * @return  {array} New post types
		 */
		$supported_post_types = apply_filters( 'ep_admin_supported_post_types', $post_types );

		/**
		 * Filter protected content supported post types.
		 *
		 * @hook ep_pc_supported_post_types
		 * @param  {array} $supported_post_types Supported post types
		 * @return  {array} New post types
		 */
		$supported_post_types = apply_filters( 'ep_pc_supported_post_types', $supported_post_types );

		$post_type = $query->get( 'post_type' );

		if ( empty( $post_type ) ) {
			$post_type = 'post';
		}

		if ( is_array( $post_type ) ) {
			foreach ( $post_type as $pt ) {
				if ( empty( $supported_post_types[ $pt ] ) ) {
					return;
				}
			}

			$query->set( 'ep_integrate', true );
		} elseif ( ! empty( $supported_post_types[ $post_type ] ) ) {
				$query->set( 'ep_integrate', true );
		}

		/**
		 * Remove articles weighting by date in admin.
		 *
		 * @since 3.0
		 */
		$search_feature = Features::factory()->get_registered_feature( 'search' );

		remove_filter( 'ep_formatted_args', [ $search_feature, 'weight_recent' ], 10 );
	}

	/**
	 * Query all posts with and without password for indexing.
	 *
	 * @since 4.0.0
	 *
	 * @param array $args Database arguments
	 * @return array
	 */
	public function query_password_protected_posts( $args ) {
		$args['has_password'] = null;

		return $args;
	}

	/**
	 * Include post password when indexing.
	 *
	 * @since 4.0.0
	 *
	 * @param array $post_args Post arguments
	 * @param int   $post_id   Post ID
	 * @return array
	 */
	public function include_post_password( $post_args, $post_id ) {
		$post = get_post( $post_id );

		// Assign null value so we can use the EXISTS filter.
		$post_args['post_password'] = ! empty( $post->post_password ) ? $post->post_password : null;

		return $post_args;
	}

	/**
	 * Prevent some fields in password protected posts from being indexed.
	 *
	 * As some solutions publicly expose full post contents, this method prevents password
	 * protected posts to have their full content and their meta fields indexed. Developers
	 * wanting to bypass this behavior can use the `ep_pc_skip_post_content_cleanup` filter.
	 *
	 * @param array $post_args Post arguments
	 * @param int   $post_id   Post ID
	 * @return array
	 */
	public function remove_fields_from_password_protected( $post_args, $post_id ) {
		if ( empty( $post_args['post_password'] ) ) {
			return $post_args;
		}

		/**
		 * Filter to skip the password protected content clean up.
		 *
		 * @hook ep_pc_skip_post_content_cleanup
		 * @since 4.0.0, 4.2.0 added $post_args and $post_id
		 * @param  {bool}  $skip      Whether the password protected content should have their content, and meta removed
		 * @param  {array} $post_args Post arguments
		 * @param  {int}   $post_id   Post ID
		 * @return {bool}
		 */
		if ( apply_filters( 'ep_pc_skip_post_content_cleanup', false, $post_args, $post_id ) ) {
			return $post_args;
		}

		$fields_to_remove = [
			'post_content_filtered',
			'post_content',
			'meta',
			'thumbnail',
			'post_content_plain',
			'price_html',
		];

		foreach ( $fields_to_remove as $field ) {
			if ( ! empty( $post_args[ $field ] ) ) {
				if ( is_array( $post_args[ $field ] ) ) {
					$post_args[ $field ] = [];
				} else {
					$post_args[ $field ] = '';
				}
			}
		}

		return $post_args;
	}

	/**
	 * Exclude protected post from the frontend queries.
	 *
	 * @since 4.0.0
	 *
	 * @param  array $formatted_args Formatted Elasticsearch query
	 * @param  array $args           Query variables
	 * @return array
	 */
	public function exclude_protected_posts( $formatted_args, $args ) {
		if ( empty( $args['has_password'] ) ) {
			/**
			 * Filter to exclude protected posts from search.
			 *
			 * @hook ep_exclude_password_protected_from_search
			 * @since 4.0.0
			 * @param  {bool} $exclude Exclude post from search.
			 * @return {bool}
			 */
			if ( ( ! is_user_logged_in() && ! empty( $args['s'] ) ) || apply_filters( 'ep_exclude_password_protected_from_search', false ) ) {
				$formatted_args['post_filter']['bool']['must_not'][] = array(
					'exists' => array(
						'field' => 'post_password',
					),
				);
			}
		}

		return $formatted_args;
	}

	/**
	 * Add post_password to post object properties set after query
	 *
	 * @since 4.0.0
	 *
	 * @param  array $properties Post properties
	 * @return array
	 */
	public function return_post_password( $properties ) {
		$properties[] = 'post_password';
		return $properties;
	}

	/**
	 * Integrate EP into comment queries
	 *
	 * @param  WP_Comment_Query $comment_query WP Comment Query
	 * @since  3.6.0
	 */
	public function integrate_comments_query( $comment_query ) {
		if ( ! Utils\is_integrated_request( $this->slug, [ 'admin' ] ) ) {
			return;
		}

		// Lets make sure this doesn't interfere with the CLI
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return;
		}

		$comment_types = array( 'comment', 'review' );

		/**
		 * Filter protected content supported comment types.
		 *
		 * @hook ep_pc_supported_comment_types
		 * @since 3.6.0
		 * @param  {array} $comment_types Comment types
		 * @return  {array} New comment types
		 */
		$supported_comment_types = apply_filters( 'ep_pc_supported_comment_types', $comment_types );

		$comment_type = $comment_query->query_vars['type'];

		if ( is_array( $comment_type ) ) {
			foreach ( $comment_type as $comment_type_value ) {
				if ( ! in_array( $comment_type_value, $supported_comment_types, true ) ) {
					return;
				}
			}

			$comment_query->query_vars['ep_integrate'] = true;
		} elseif ( in_array( $comment_type, $supported_comment_types, true ) ) {
				$comment_query->query_vars['ep_integrate'] = true;
		}
	}

	/**
	 * Fetches all post statuses we need to index
	 *
	 * @since  2.1
	 * @param  array $statuses Post statuses array
	 * @return array
	 */
	public function get_statuses( $statuses ) {
		$post_statuses = get_post_stati();

		unset( $post_statuses['auto-draft'] );

		return array_unique( array_merge( $statuses, array_values( $post_statuses ) ) );
	}

	/**
	 * Fetches all comment statuses we need to index
	 *
	 * @since  3.6.0
	 * @param  array $comment_statuses Post statuses array
	 * @return array
	 */
	public function get_comment_statuses( $comment_statuses ) {
		return [ 'all' ];
	}

	/**
	 * Determine feature reqs status
	 *
	 * @since  2.2
	 * @return FeatureRequirementsStatus
	 */
	public function requirements_status() {
		$status = new FeatureRequirementsStatus( 1 );

		if ( ! Utils\is_epio() ) {
			$status->message = __( "You aren't using <a href='https://elasticpress.io'>ElasticPress.io</a> so we can't be sure your Elasticsearch instance is secure.", 'elasticpress' );
		}

		return $status;
	}

	/**
	 * Bypass the default check for password protected posts.
	 *
	 * @since 4.6.0
	 * @param null|bool $new_skip Short-circuit flag
	 * @param bool      $skip     Current value of $skip
	 * @return bool
	 */
	public function sync_password_protected( $new_skip, bool $skip ): bool {
		return $skip;
	}

	/**
	 * Maybe change the sort order for the WP Dashboard.
	 *
	 * If the admin user has enabled the setting to use the default WordPress sort order,
	 * we will change the sort order to (somewhat) match the default WP behavior.
	 *
	 * @since 5.1.4
	 *
	 * @param array $default_sort The previous value of the `ep_set_sort` filter
	 * @return array
	 */
	public function maybe_change_sort( $default_sort ) {
		if ( ! function_exists( '\get_current_screen' ) ) {
			return $default_sort;
		}

		$screen = get_current_screen();
		if ( empty( $screen ) || 'edit' !== $screen->base ) {
			return $default_sort;
		}

		if ( ! $this->get_setting( 'use_default_wp_sort' ) ) {
			return $default_sort;
		}

		return [
			[ 'post_date' => [ 'order' => 'desc' ] ],
			[ 'post_title.sortable' => [ 'order' => 'asc' ] ],
		];
	}

	/**
	 * Filter private posts for current user
	 *
	 * @param array $formatted_args Formatted Elasticsearch query
	 * @param array $args Query variables
	 *
	 * @return array
	 */
	public function filter_private_posts_for_current_user( $formatted_args, $args ): array {
		$post_types = (array) $args['post_type'];

		$valid_post_types = array_filter( $post_types, 'post_type_exists' );

		$base_statuses = array_merge(
			get_post_stati( [ 'public' => true ] ),
			get_post_stati(
				[
					'protected'              => true,
					'show_in_admin_all_list' => true,
				]
			),
			! empty( $args['post_status'] ) ? (array) $args['post_status'] : []
		);

		$post_types_with_capability    = [];
		$post_types_without_capability = [];

		foreach ( $valid_post_types as $post_type ) {
			$post_type_object = get_post_type_object( $post_type );

			if ( empty( $post_type_object ) || empty( $post_type_object->cap->read_private_posts ) ) {
				continue;
			}

			$read_private_cap = $post_type_object->cap->read_private_posts;

			if ( current_user_can( $read_private_cap ) ) {
				$post_types_with_capability[] = $post_type;
			} else {
				$post_types_without_capability[] = $post_type;
			}
		}

		$should_clauses = [];

		if ( ! empty( $post_types_with_capability ) ) {
			$all_statuses = array_merge( $base_statuses, get_post_stati( [ 'private' => true ] ) );

			$should_clauses[] = [
				'bool' => [
					'must' => [
						[ 'terms' => [ 'post_type.raw' => array_values( $post_types_with_capability ) ] ],
						[ 'terms' => [ 'post_status' => array_values( $all_statuses ) ] ],
					],
				],
			];
		}

		if ( ! empty( $post_types_without_capability ) ) {
			$should_clauses[] = [
				'bool' => [
					'must' => [
						[ 'terms' => [ 'post_type.raw' => array_values( $post_types_without_capability ) ] ],
						[ 'terms' => [ 'post_status' => array_values( $base_statuses ) ] ],
					],
				],
			];

			$should_clauses[] = [
				'bool' => [
					'must' => [
						[ 'terms' => [ 'post_type.raw' => array_values( $post_types_without_capability ) ] ],
						[ 'term' => [ 'post_status' => 'private' ] ],
						[ 'term' => [ 'post_author.id' => get_current_user_id() ] ],
					],
				],
			];
		}

		if ( ! empty( $should_clauses ) ) {
			$formatted_args['post_filter']['bool']['should']               = array_merge(
				$formatted_args['post_filter']['bool']['should'] ?? [],
				$should_clauses
			);
			$formatted_args['post_filter']['bool']['minimum_should_match'] = 1;
		}

		return $formatted_args;
	}

	/**
	 * Set the `settings_schema` attribute
	 *
	 * @since 5.1.4
	 */
	protected function set_settings_schema() {
		$this->settings_schema = [
			[
				'default' => '0',
				'key'     => 'use_default_wp_sort',
				'help'    => __( 'Enable to use WordPress default sort for searches inside the WP Dashboard.', 'elasticpress' ),
				'label'   => __( 'Use default WordPress sort on the WP Dashboard', 'elasticpress' ),
				'type'    => 'checkbox',
			],
		];
	}
}
