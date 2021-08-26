<?php
/**
 * Instant Search feature
 *
 * @package elasticpress
 */

namespace ElasticPress\Feature\InstantSearch;

use ElasticPress\Elasticsearch as Elasticsearch;
use ElasticPress\Feature as Feature;
use ElasticPress\Features as Features;
use ElasticPress\Indexables as Indexables;
use ElasticPress\Utils as Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Instant Search feature class.
 *
 * @since 3.7.0
 */
class InstantSearch extends Feature {
	/**
	 * Elasticsearch query template.
	 *
	 * @var array
	 */
	protected $search_template = [];

	/**
	 * Initialize feature.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->slug = 'instant-search';

		$this->title = esc_html__( 'Instant Search', 'elasticpress' );

		$this->requires_install_reindex = false;

		$this->default_settings = [];

		parent::__construct();
	}

	/**
	 * Output feature box summary.
	 *
	 * @return void
	 */
	public function output_feature_box_summary() {
		// TODO Short feature description.
	}

	/**
	 * Output feature box long.
	 *
	 * @return void
	 */
	public function output_feature_box_long() {
		// TODO Detailed feature description.
	}

	/**
	 * Setup feature functionality.
	 *
	 * @return void
	 */
	public function setup() {
		add_filter( 'ep_saved_weighting_configuration', [ $this, 'epio_send_search_template' ] );
		add_filter( 'ep_pre_dashboard_index', [ $this, 'epio_send_search_template' ] );
		add_filter( 'ep_wp_cli_pre_index', [ $this, 'epio_send_search_template' ] );
	}

	/**
	 * Send the search template to EP.io.
	 *
	 * @return void
	 */
	public function epio_send_search_template() {
		if ( ! Utils\is_epio() ) {
			return;
		}

		$endpoint = 'api/v1/instant-search-template/' . Indexables::factory()->get( 'post' )->get_index_name();
		$response = Elasticsearch::factory()->remote_request(
			$endpoint,
			[
				'blocking' => false,
				'body'     => $this->get_search_template(),
				'method'   => 'PUT',
			]
		);
	}

	/**
	 * Generate a search template.
	 *
	 * A search template is the JSON for an Elasticsearch query with a
	 * placeholder search term. The template is sent to EP.io where it's used
	 * to make Elasticsearch queries using search terms sent from the front
	 * end.
	 *
	 * @return string The search template JSON.
	 */
	public function get_search_template() {
		$placeholder = '{{ep_placeholder}}';
		$post_type   = Features::factory()->get_registered_feature( 'search' )->get_searchable_post_types();
		$post_status = get_post_stati(
			[
				'public'              => true,
				'exclude_from_search' => false,
			]
		);

		add_filter( 'ep_intercept_remote_request', '__return_true' );
		add_filter( 'ep_do_intercept_request', [ $this, 'intercept_search_request' ], 10, 4 );
		add_filter( 'ep_is_integrated_request', [ $this, 'is_integrated_request' ], 10, 2 );
		add_filter( 'posts_pre_query', '__return_empty_array', 100, 1 );

		$query = new \WP_Query(
			[
				'post_type'    => $post_type,
				'post_status'  => array_values( $post_status ),
				's'            => $placeholder,
				'ep_integrate' => true,
			]
		);

		remove_filter( 'posts_pre_query', '__return_empty_array', 100 );
		remove_filter( 'ep_is_integrated_request', [ $this, 'is_integrated_request' ], 10, 2 );
		remove_filter( 'ep_do_intercept_request', [ $this, 'intercept_search_request' ], 10 );
		remove_filter( 'ep_intercept_remote_request', '__return_true' );

		return $this->search_template;
	}

	/**
	 * Return true if a given feature is supported by instant search.
	 *
	 * Applied as a filter on Utils\is_integrated_request() so that features
	 * are enabled for the query that is used to generate the search template,
	 * regardless of the request type. This avoids the need to send a request
	 * to the front end.
	 *
	 * @param bool   $is_integrated Whether queries for the request will be
	 *                              integrated.
	 * @param string $context       Context for the original check. Usually the
	 *                              slug of the feature doing the check.
	 * @return bool True if the check is for a feature supported by instant
	 *              search.
	 */
	public function is_integrated_request( $is_integrated, $context ) {
		$supported_contexts = [
			'autosuggest',
			'documents',
			'search',
			'weighting',
			'woocommerce',
		];

		if ( in_array( $context, $supported_contexts, true ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Store intercepted request value and return (cached) request result
	 *
	 * @param object $response Response
	 * @param array  $query Query
	 * @param array  $args WP_Query Argument array
	 * @param int    $failures Count of failures in request loop
	 * @return object $response Response
	 */
	public function intercept_search_request( $response, $query = [], $args = [], $failures = 0 ) {
		$this->search_template = $query['args']['body'];

		return wp_remote_request( $query['url'], $args );
	}
}
