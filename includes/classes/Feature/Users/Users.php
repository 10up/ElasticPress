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
	 * @since  3.0
	 */
	public function __construct() {
		$this->slug = 'users';

		$this->title = esc_html__( 'User Search', 'elasticpress' );

		parent::__construct();
	}

	/**
	 * Hook search functionality
	 *
	 * @since  3.0
	 */
	public function setup() {
		add_action( 'init', [ $this, 'search_setup' ] );
	}

	/**
	 * Setup feature on each page load
	 *
	 * @since  3.0
	 */
	public function search_setup() {
		add_filter( 'ep_elasticpress_enabled', [ $this, 'integrate_search_queries' ], 10, 2 );
	}

	/**
	 * Output feature box summary
	 *
	 * @since 3.0
	 */
	public function output_feature_box_summary() {
		?>
		<p><?php esc_html_e( 'Improve user search performance and relevancy.', 'elasticpress' ); ?></p>
		<?php
	}

	/**
	 * Output feature box long text
	 *
	 * @since 3.0
	 */
	public function output_feature_box_long() {
		?>
		<p><?php esc_html_e( 'If you run a website with a lot of users, traditional WordPress user search can be slow and taxing on your website. This feature empowers ElasticPress to return user search results from Elasticsearch.', 'elasticpress' ); ?></p>

		<?php
	}

	/**
	 * Enable integration on search queries
	 *
	 * @param  bool          $enabled Whether EP is enabled
	 * @param  WP_User_Query $query Current query object.
	 * @since  3.0
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
