<?php
/**
 * ElasticPress Protected Content feature
 *
 * @since  2.2
 * @package elasticpress
 */

namespace ElasticPress\Feature\ProtectedContent;

use ElasticPress\Utils as Utils;
use ElasticPress\Feature as Feature;
use ElasticPress\Features as Features;
use ElasticPress\FeatureRequirementsStatus as FeatureRequirementsStatus;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Protected content feature
 */
class ProtectedContent extends Feature {

	/**
	 * Initialize feature setting it's config
	 *
	 * @since  3.0
	 */
	public function __construct() {
		$this->slug = 'protected_content';

		$this->title = esc_html__( 'Protected Content', 'elasticpress' );

		$this->requires_install_reindex = true;

		parent::__construct();
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
		add_filter( 'ep_search_post_return_args', [ $this, 'return_post_password' ] );
		add_filter( 'ep_skip_autosave_sync', '__return_false' );
		add_filter( 'ep_index_posts_args', [ $this, 'query_password_protected_posts' ] );
		add_filter( 'ep_post_sync_args', [ $this, 'include_post_password' ], 10, 2 );

		if ( is_admin() ) {
			add_filter( 'ep_admin_wp_query_integration', '__return_true' );
			add_action( 'pre_get_posts', [ $this, 'integrate' ] );
			add_filter( 'ep_post_query_db_args', [ $this, 'query_password_protected_posts' ] );
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

		// We don't want to deal with nav menus
		if ( $pc_post_types['nav_menu_item'] ) {
			unset( $pc_post_types['nav_menu_item'] );
		}

		if ( ! empty( $pc_post_types['revision'] ) ) {
			unset( $pc_post_types['revision'] );
		}

		if ( ! empty( $pc_post_types['custom_css'] ) ) {
			unset( $pc_post_types['custom_css'] );
		}

		if ( ! empty( $pc_post_types['customize_changeset'] ) ) {
			unset( $pc_post_types['customize_changeset'] );
		}

		if ( ! empty( $pc_post_types['oembed_cache'] ) ) {
			unset( $pc_post_types['oembed_cache'] );
		}

		if ( ! empty( $pc_post_types['wp_block'] ) ) {
			unset( $pc_post_types['wp_block'] );
		}

		if ( ! empty( $pc_post_types['user_request'] ) ) {
			unset( $pc_post_types['user_request'] );
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
		} else {
			if ( ! empty( $supported_post_types[ $post_type ] ) ) {
				$query->set( 'ep_integrate', true );
			}
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
	 * @param  array $post_args Post arguments
	 * @param  int   $post_id   Post ID
	 */
	public function include_post_password( $post_args, $post_id ) {
		$post                       = get_post( $post_id );
		$post_args['post_password'] = ! empty( $post->post_password ) ? $post->post_password : null; // Assign null value so we can use the EXISTS filter.
		return $post_args;
	}

	/**
	 * Exclude proctected post from the frontend queries.
	 *
	 * @param  array $formatted_args Formatted Elasticsearch query
	 * @param  array $args           Query variables
	 * @return array
	 */
	public function exclude_protected_posts( $formatted_args, $args ) {
		/**
		 * Filter to exclude protected posts from search.
		 *
		 * @hook ep_exclude_password_protected_from_search
		 * @param  {bool} $exclude Exclude post from search.
		 * @return {bool}
		 */
		if ( ! is_admin() && apply_filters( 'ep_exclude_password_protected_from_search', true ) ) {
			$formatted_args['post_filter']['bool']['must_not'][] = array(
				'exists' => array(
					'field' => 'post_password',
				),
			);
		}

		return $formatted_args;
	}

	/**
	 * Add post_password to post object properties set after query
	 *
	 * @param  array $properties Post properties
	 * @return array
	 */
	public function return_post_password( $properties ) {
		return $properties + [ 'post_password' ];
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
		} else {
			if ( in_array( $comment_type, $supported_comment_types, true ) ) {
				$comment_query->query_vars['ep_integrate'] = true;
			}
		}

	}

	/**
	 * Output feature box summary
	 *
	 * @since 2.1
	 */
	public function output_feature_box_summary() {
		?>
		<p><?php esc_html_e( 'Optionally index all of your content, including private and unpublished content, to speed up searches and queries in places like the administrative dashboard.', 'elasticpress' ); ?></p>
		<?php
	}

	/**
	 * Output feature box long
	 *
	 * @since 2.1
	 */
	public function output_feature_box_long() {
		?>
		<p><?php echo wp_kses_post( __( 'Securely indexes unpublished content—including private, draft, and scheduled posts —improving load times in places like the administrative dashboard where WordPress needs to include protected content in a query.', 'elasticpress' ) ); // VIP: Remove EP.io reference since VIP is secure. ?></p>
		<?php
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

		// VIP: Remove prompt for EP.io as secure instance.

		return $status;
	}
}
