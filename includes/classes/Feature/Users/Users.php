<?php
/**
 * Users feature
 *
 * @since  1.9
 * @package  elasticpress
 */

namespace ElasticPress\Feature\Users;
use ElasticPress\Feature as Feature;

/**
 * Users feature class
 */
class Users extends Feature {
	/**
	 * Initialize feature setting it's config
	 *
	 * @since  2.6
	 */
	public function __construct() {
		$this->slug = 'users';

		$this->title = esc_html__( 'User Search', 'elasticpress' );
	}

	public function setup() {
		add_action( 'init', [ $this, 'search_setup' ] );
	}

	/**
	 * Setup feature on each page load
	 *
	 * @since  2.6
	 */
	public function search_setup() {
		/**
		 * By default EP will not integrate on admin or ajax requests. Since admin-ajax.php is
		 * technically an admin request, there is some weird logic here. If we are doing ajax
		 * and ep_ajax_wp_query_integration is filtered true, then we skip the next admin check.
		 */
		$admin_integration = apply_filters( 'ep_admin_wp_user_query_integration', false );

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			if ( ! apply_filters( 'ep_ajax_wp_query_integration', false ) ) {
				return;
			} else {
				$admin_integration = true;
			}
		}

		if ( is_admin() && ! $admin_integration ) {
			return;
		}

		add_filter( 'ep_elasticpress_enabled', [ $this, 'integrate_search_queries' ], 10, 2 );
	}

	/**
	 * Output feature box summary
	 *
	 * @since 2.6
	 */
	public function output_feature_box_summary() {
		?>
		<p><?php esc_html_e( 'Improve user search performance and relevancy.', 'elasticpress' ); ?></p>
		<?php
	}

	/**
	 * Output feature box long text
	 *
	 * @since 2.6
	 */
	public function output_feature_box_long() {
		?>
		<p><?php esc_html_e( 'If you run a website with a lot of users, traditional WordPress user search can be slow and taxing on your website. This feature empowers ElasticPress to return user search results from Elasticsearch.', 'elasticpress' ); ?></p>

		<?php
	}

	/**
	 * Enable integration on search queries
	 *
	 * @param  bool     $enabled Whether EP is enabled
	 * @param  WP_User_Query $query Current query object.
	 * @since  2.6
	 * @return bool
	 */
	public function integrate_search_queries( $enabled, $query ) {
		if ( ! is_a( $query, 'WP_User_Query' ) ) {
			return $enabled;
		}

		if ( isset( $query->query_vars['ep_integrate'] ) && false === $query->query_vars['ep_integrate'] ) {
			$enabled = false;
		} elseif ( ! empty( $query->query_vars['search'] ) ) {
			$enabled = true;
		}

		return $enabled;
	}
}
