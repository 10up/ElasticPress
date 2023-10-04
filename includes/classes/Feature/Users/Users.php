<?php
/**
 * Users feature
 *
 * @since  1.9
 * @package  elasticpress
 */

namespace ElasticPress\Feature\Users;

use ElasticPress\Feature;
use ElasticPress\FeatureRequirementsStatus;
use ElasticPress\Indexable;
use ElasticPress\Indexables;
use ElasticPress\Utils;

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

		$this->title = esc_html__( 'Users', 'elasticpress' );

		$this->summary = __( 'Improve user search relevancy and query performance.', 'elasticpress' );

		$this->docs_url = __( 'https://elasticpress.zendesk.com/hc/en-us/articles/360050447492-Configuring-ElasticPress-via-the-Plugin-Dashboard#users', 'elasticpress' );

		$this->requires_install_reindex = true;

		Indexables::factory()->register( new Indexable\User\User(), false );

		parent::__construct();
	}

	/**
	 * Hook search functionality
	 *
	 * @since  3.0
	 */
	public function setup() {
		Indexables::factory()->activate( 'user' );

		add_action( 'init', [ $this, 'search_setup' ] );
		add_filter( 'ep_admin_notices', [ $this, 'add_migration_notice' ] );
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
	 * Output feature box long text
	 *
	 * @since 3.0
	 */
	public function output_feature_box_long() {
		?>
		<p><?php esc_html_e( 'This feature will empower your website to overcome traditional WordPress user search and query limitations that can present themselves at scale.', 'elasticpress' ); ?></p>
		<p><?php esc_html_e( 'Be aware that storing user data may bound you to certain legal obligations depending on your local government regulations.', 'elasticpress' ); ?></p>
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

		if ( isset( $query->query_vars['ep_integrate'] ) && ! filter_var( $query->query_vars['ep_integrate'], FILTER_VALIDATE_BOOLEAN ) ) {
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
		$status = new FeatureRequirementsStatus( 1 );

		$status->message = [
			sprintf(
				/* translators: ElasticPress Labs URL */
				__( 'Due to the potential for inadvertently exposing user data on non-ElasticPress.io installations, the Users Feature will be moving to the <a href="%s">ElasticPress Labs</a> plugin as of ElasticPress 5.0.', 'elasticpress' ),
				'https://github.com/10up/ElasticPressLabs'
			),
		];

		return $status;
	}

	/**
	 * Display a notice about the feature migration to ElasticPress Labs
	 *
	 * @since 4.5.0
	 * @param array $notices Notices array
	 * @return array
	 */
	public function add_migration_notice( $notices ) {
		if ( ! current_user_can( Utils\get_capability() ) ) {
			return $notices;
		}

		// Dismissed.
		if ( Utils\get_option( 'ep_hide_users_migration_notice', false ) ) {
			return $notices;
		}

		$notices['users_migration'] = [
			'html'    => sprintf(
				/* translators: ElasticPress Labs URL */
				__( 'Due to the potential for inadvertently exposing user data on non-ElasticPress.io installations, the Users Feature will be moving to the <a href="%s">ElasticPress Labs</a> plugin as of ElasticPress 5.0.', 'elasticpress' ),
				'https://github.com/10up/ElasticPressLabs'
			),
			'type'    => 'warning',
			'dismiss' => true,
		];

		return $notices;
	}
}
