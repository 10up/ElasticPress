<?php
/**
 * Terms feature
 *
 * @since   3.1
 * @package elasticpress
 */

namespace ElasticPress\Feature\Terms;

use ElasticPress\Feature as Feature;
use ElasticPress\Indexables as Indexables;
use ElasticPress\Indexable as Indexable;
use ElasticPress\FeatureRequirementsStatus as FeatureRequirementsStatus;

/**
 * Terms feature class
 */
class Terms extends Feature {

	/**
	 * Initialize feature, setting it's config
	 *
	 * @since 3.1
	 */
	public function __construct() {
		$this->slug                     = 'terms';
		$this->title                    = esc_html__( 'Terms', 'elasticpress' );
		$this->requires_install_reindex = true;

		parent::__construct();
	}

	/**
	 * Setup search functionality
	 *
	 * @since 3.1
	 */
	public function setup() {
		Indexables::factory()->register( new Indexable\Term\Term() );

		add_action( 'init', [ $this, 'search_setup' ] );
	}

	/**
	 * Setup search integration
	 *
	 * @since 3.1
	 */
	public function search_setup() {
		add_filter( 'ep_elasticpress_enabled', [ $this, 'integrate_search_queries' ], 10, 2 );
		add_filter( 'ep_term_fuzziness_arg', [ $this, 'set_admin_terms_search_fuzziness' ] );
	}

	/**
	 * Output feature box summary
	 *
	 * @since 3.1
	 */
	public function output_feature_box_summary() {
		?>
		<p><?php esc_html_e( 'Improve WP_Term_Query relevancy and query performance. This feature is only needed if you are using WP_Term_Query directly.', 'elasticpress' ); ?></p>
		<?php
	}

	/**
	 * Output feature box long text
	 *
	 * @since 3.1
	 */
	public function output_feature_box_long() {
		?>
		<p><?php esc_html_e( 'This feature will empower your website to overcome traditional WordPress term search and query limitations that can present themselves at scale.', 'elasticpress' ); ?></p>
		<?php
	}

	/**
	 * Enable integration on search queries
	 *
	 * @param  bool           $enabled Whether EP is enabled
	 * @param  \WP_Term_Query $query Current query object.
	 * @since  3.1
	 * @return bool
	 */
	public function integrate_search_queries( $enabled, $query ) {
		if ( ! is_a( $query, 'WP_Term_Query' ) ) {
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

	/**
	 * Change fuzziness level for terms search in admin
	 *
	 * @param  {int} $fuzziness Amount of fuzziness to factor into search
	 * @since  3.6.4
	 * @return int
	 */
	public function set_admin_terms_search_fuzziness( $fuzziness ) {
		if ( is_admin() ) {
			$fuzziness = 0;
		}
		return $fuzziness;
	}

}
