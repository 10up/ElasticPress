<?php
/**
 * Comments feature
 *
 * @since   3.1
 * @package elasticpress
 */

namespace ElasticPress\Feature\Comments;

use ElasticPress\Feature as Feature;
use ElasticPress\Indexables as Indexables;
use ElasticPress\Indexable as Indexable;
use ElasticPress\FeatureRequirementsStatus as FeatureRequirementsStatus;

/**
 * Comments feature class
 */
class Comments extends Feature {

	/**
	 * Initialize feature, setting it's config
	 *
	 * @since 3.1
	 */
	public function __construct() {
		$this->slug                     = 'comments';
		$this->title                    = esc_html__( 'Comments', 'elasticpress' );
		$this->requires_install_reindex = true;

		parent::__construct();
	}

	/**
	 * Setup search functionality
	 *
	 * @since 3.1
	 */
	public function setup() {
		Indexables::factory()->register( new Indexable\Comment\Comment() );

		add_action( 'init', [ $this, 'search_setup' ] );
	}

	/**
	 * Setup search integration
	 *
	 * @since 3.1
	 */
	public function search_setup() {
		add_filter( 'ep_elasticpress_enabled', [ $this, 'integrate_search_queries' ], 10, 2 );
	}

	/**
	 * Output feature box summary
	 *
	 * @since 3.1
	 */
	public function output_feature_box_summary() {
		?>
		<p><?php esc_html_e( 'Improve comment search relevancy and query performance.', 'elasticpress' ); ?></p>
		<?php
	}

	/**
	 * Output feature box long text
	 *
	 * @since 3.1
	 */
	public function output_feature_box_long() {
		?>
		<p><?php esc_html_e( 'This feature will empower your website to overcome traditional WordPress comment search and query limitations that can present themselves at scale.', 'elasticpress' ); ?></p>
		<?php
	}

	/**
	 * Enable integration on search queries
	 *
	 * @param  bool              $enabled Whether EP is enabled
	 * @param  \WP_Comment_Query $query Current query object.
	 * @since  3.1
	 * @return bool
	 */
	public function integrate_search_queries( $enabled, $query ) {
		if ( ! is_a( $query, 'WP_Comment_Query' ) ) {
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
	 * @since  3.1
	 * @return FeatureRequirementsStatus
	 */
	public function requirements_status() {
		$status = new FeatureRequirementsStatus( 1 );

		return $status;
	}

}
