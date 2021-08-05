<?php
/**
 * Boolean Search Operators Feature
 *
 * @package elasticpress
 */

namespace ElasticPress\Feature\BooleanSearchOperators;

use ElasticPress\Feature;
use ElasticPress\FeatureRequirementsStatus;
use ElasticPress\Features;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Boolean Search Feature
 *
 * @package ElasticPress\Feature\BooleanSearch
 */
class BooleanSearchOperators extends Feature {

	/**
	 * Initialize feature setting it's config
	 */
	public function __construct() {
		$this->slug = 'boolean_search_operators';

		$this->title = esc_html__( 'Boolean Search Operators', 'elasticpress' );

		$this->requires_install_reindex = false;
		$this->default_settings         = [
			'active' => false,
		];

		parent::__construct();
	}

	/**
	 * Setup Feature Functionality
	 */
	public function setup() {
		/** Features Class @var Features $features */
		$features = Features::factory();

		/** Search Feature @var Feature\Search\Search $search */
		$search = $features->get_registered_feature( 'search' );

		if ( ! $search->is_active() && $this->is_active() ) {
			$features->deactivate_feature( $this->slug );

			return false;
		}

		add_filter( 'ep_elasticpress_enabled', [ $this, 'integrate_boolean_search_operators' ], 10, 2 );
	}

	public function integrate_boolean_search_operators( $enabled, $query ) {
		if ( ! $enabled ) {
			return;
		}

		if ( true === $query->query_vars['ep_integrate'] &&
			 ( $this->is_active() || true === $query->query_vars['ep_boolean_search'] ) ) {

			\add_filter( 'ep_formatted_args_query', [ $this, 'format_boolean_query_args' ], 999, 4 );
		}
	}

	public function format_boolean_query_args( $query, $args, $search_text, $search_fields ) {
		$boolean_query = array(
			'simple_query_string' => array(
				'query'                               => $search_text,
				'fields'                              => \apply_filters( 'ep_boolean_operators_fields', $search_fields ),
				'default_operator'                    => \apply_filters( 'ep_boolean_operators_default', 'OR' ),
				'flags'                               => \apply_filters( 'ep_boolean_operators_flags', 'ALL' ),
				'auto_generate_synonyms_phrase_query' => \apply_filters( 'ep_boolean_operators_generate_synonyms', true ),
			),
		);

		return \apply_filters( 'ep_boolean_operators_query_args', $boolean_query, $args, $search_text, $search_fields, $query );
	}

	/**
	 * Returns requirements status of feature
	 *
	 * Requires the search feature to be activated
	 *
	 * @return FeatureRequirementsStatus
	 */
	public function requirements_status() {
		/** Features Class @var Features $features */
		$features = Features::factory();

		/** Search Feature @var Feature\Search\Search $search */
		$search = $features->get_registered_feature( 'search' );

		if ( ! $search->is_active() ) {
			return new FeatureRequirementsStatus( 2, esc_html__( 'This feature requires the "Post Search" feature to be enabled', 'elasticpress' ) );
		}

		return parent::requirements_status();
	}

	/**
	 * Output feature box summary
	 */
	public function output_feature_box_summary() {
		?>
		<p><?php esc_html_e( 'Use boolean operators for search queries', 'elasticpress' ); ?></p>
		<?php
	}

	/**
	 * Output feature box long
	 */
	public function output_feature_box_long() {
		?>
		<p><?php esc_html_e( 'Allows users to search using boolean operators: +, |, -, "", *, (), ~#', 'elasticpress' ); ?></p>
		<?php
	}
}
