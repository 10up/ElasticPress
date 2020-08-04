<?php
/**
 * ElasticPress Protected Content feature
 *
 * @package elasticpress
 */

namespace ElasticPress\Feature\CoAuthorsPlus;

use ElasticPress\Feature as Feature;
use ElasticPress\FeatureRequirementsStatus as FeatureRequirementsStatus;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Protected content feature
 */
class CoAuthorsPlus extends Feature {

	/**
	 * Initialize feature setting it's config
	 */
	public function __construct() {
		$this->slug = 'co_authors_plus';

		$this->title = esc_html__( 'Co-Authors Plus', 'elasticpress' );

		$this->requires_install_reindex = true;

		parent::__construct();
	}

	/**
	 * Setup all feature filters
	 *
	 * @since  2.1
	 */
	public function setup() {
		add_filter( 'ep_sync_taxonomies', array( $this, 'include_author_term' ) );

		if ( is_admin() ) {
			add_action( 'pre_get_posts', [ $this, 'integrate' ] );
		}
	}

	/**
	 * Integrate EP into proper queries
	 *
	 * @param  WP_Query $query WP Query
	 * @since  2.1
	 */
	public function integrate( $query ) {

		// Lets make sure this doesn't interfere with the CLI
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return;
		}

		if ( ! $query->is_main_query() ) {
			return;
		}

		$author_name = $query->get( 'author_name' );

		if ( ! $author_name ) {
			return;
		}

		global $coauthors_plus;

		$guest_author = $coauthors_plus->guest_authors->get_guest_author_by( 'login', $author_name );

		if ( ! $guest_author ) {
			return;
		}

		$term      = $coauthors_plus->get_author_term( $guest_author );
		$tax_query = $query->get( 'tax_query' );

		if ( ! $tax_query ) {
			$tax_query = array();
		}

		$tax_query[] = array(
			'taxonomy' => 'author',
			'terms'    => array( "cap-$author_name" ),
			'field'    => 'slug',
		);

		$tax_query[] = array(
			'taxonomy' => 'author',
			'terms'    => array( $term->term_id ),
			'field'    => 'term_id',
		);

		$query->set( 'author_name', '' );
		$query->set( 'tax_query', $tax_query );
		$query->set( 'ep_integrate', true );
	}

	/**
	 * Include author taxonomy when indexing posts.
	 *
	 * @param array $taxonomies Selected taxonomies.
	 */
	public function include_author_term( $taxonomies ) {
		$taxonomies[] = get_taxonomy( 'author' );
		return $taxonomies;
	}

	/**
	 * Output feature box summary
	 */
	public function output_feature_box_summary() {
		?>
		<p><?php esc_html_e( 'Add support for Co-Authors Plus guest author feature.', 'elasticpress' ); ?></p>
		<?php
	}

	/**
	 * Output feature box long
	 */
	public function output_feature_box_long() {
		return '';
	}

	/**
	 * Determine feature reqs status
	 *
	 * @return FeatureRequirementsStatus
	 */
	public function requirements_status() {
		$status = new FeatureRequirementsStatus( 0 );

		if ( ! class_exists( 'CoAuthors_Plus' ) ) {
			$status->code    = 2;
			$status->message = esc_html__( 'Co-Authors Plus is not installed.', 'elasticpress' );
		}

		return $status;
	}
}

