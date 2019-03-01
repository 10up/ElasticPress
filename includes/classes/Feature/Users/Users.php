<?php
/**
 * Users feature
 *
 * @since  1.9
 * @package  elasticpress
 */

namespace ElasticPress\Feature\Users;

use ElasticPress\Feature as Feature;
use ElasticPress\Indexables as Indexables;
use ElasticPress\Indexable as Indexable;
use ElasticPress\FeatureRequirementsStatus as FeatureRequirementsStatus;
use ElasticPress\Utils as Utils;

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
		$this->slug                     = 'users';
		$this->title                    = esc_html__( 'Users', 'elasticpress' );
		$this->requires_install_reindex = true;

		parent::__construct();
	}

	/**
	 * Hook search functionality
	 *
	 * @since  3.0
	 */
	public function setup() {
		Indexables::factory()->register( new Indexable\User\User() );

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
		<p><?php esc_html_e( 'Improve user search relevancy and query performance.', 'elasticpress' ); ?></p>
		<?php
	}

	/**
	 * Output feature box long text
	 *
	 * @since 3.0
	 */
	public function output_feature_box_long() {
		?>
		<p><?php esc_html_e( 'If you run a website with a lot of users, traditional WordPress user search can return poor results and queries quite slow. This feature empowers ElasticPress to return user queries and search results from Elasticsearch.', 'elasticpress' ); ?></p>

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

	/**
	 * Determine feature reqs status
	 *
	 * @since  2.2
	 * @return FeatureRequirementsStatus
	 */
	public function requirements_status() {
		$status = new FeatureRequirementsStatus( 0 );

		if ( ! Utils\is_epio() ) {
			$status->code    = 1;
			$status->message = __( "You aren't using <a href='https://elasticpress.io'>ElasticPress.io</a> so we can't be sure your Elasticsearch instance is secure.", 'elasticpress' );
		}

		return $status;
	}
}
