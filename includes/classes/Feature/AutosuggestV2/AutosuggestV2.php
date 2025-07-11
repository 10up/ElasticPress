<?php
/**
 * Autosuggest V2 feature
 *
 * @package elasticpress
 */

namespace ElasticPress\Feature\AutosuggestV2;

use ElasticPress\Elasticsearch;
use ElasticPress\Feature;
use ElasticPress\FeatureRequirementsStatus;
use ElasticPress\Features;
use ElasticPress\Indexables;
use ElasticPress\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * AutosuggestV2 feature class.
 *
 * @since 5.3.0
 */
class AutosuggestV2 extends Feature {
	/**
	 * Elasticsearch index name.
	 *
	 * @var string
	 */
	protected $index;

	/**
	 * Host URL.
	 *
	 * @var string
	 */
	protected $host;

	/**
	 * WooCommerce is in use.
	 *
	 * @var boolean
	 */
	protected $is_woocommerce;

	/**
	 * Elasticsearch query template.
	 *
	 * @var string
	 */
	protected $search_template = '';

	/**
	 * Feature settings
	 *
	 * @var array
	 */
	protected $settings = [];

	/**
	 * Initialize feature.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->slug = 'autosuggest-v2';

		$this->group = 'live-search';

		$this->host = trailingslashit( Utils\get_host() );

		$this->index = Indexables::factory()->get( 'post' )->get_index_name();

		$this->is_woocommerce = function_exists( 'WC' );

		$this->default_settings = [
			'match_type'      => 'all',
			'term_count'      => '1',
			'per_page'        => get_option( 'posts_per_page', 6 ),
			'search_behavior' => '0',
		];

		$this->settings = $this->get_settings();

		$this->requires_install_reindex = true;

		$this->available_during_installation = true;

		$this->is_powered_by_epio = Utils\is_epio();

		parent::__construct();
	}

	/**
	 * Sets i18n strings.
	 *
	 * @return void
	 * @since 5.3.0
	 */
	public function set_i18n_strings(): void {
		$this->title = esc_html__( 'Autosuggest V2', 'elasticpress' );

		$this->short_title = esc_html__( 'Autosuggest V2', 'elasticpress' );

		$this->summary = '<p>' . __( 'Input fields of type "search" or with the CSS class "search-field" or "ep-autosuggest" will be enhanced with autosuggest functionality. As text is entered into the search field, suggested content will appear below it, based on top search results for the text. Suggestions link directly to the content.', 'elasticpress' ) . '</p>' .
		'<p>' . __( 'Requires an <a href="https://www.elasticpress.io/" target="_blank">ElasticPress.io plan</a> or a custom proxy to function.', 'elasticpress' ) . '</p>';
	}

	/**
	 * Tell user whether requirements for feature are met or not.
	 *
	 * @return array $status Status array
	 */
	public function requirements_status() {
		$status = new FeatureRequirementsStatus( 2 );

		$status->message = [];

		if ( Utils\is_epio() ) {
			$status->code = 1;

			/**
			 * Whether the feature is available for non ElasticPress.io customers.
			 *
			 * Installations using self-hosted Elasticsearch will need to implement an API for
			 * handling search requests before making the feature available.
			 *
			 * @since 5.3.0
			 * @hook ep_autosuggest_v2_available
			 * @param {string} $available Whether the feature is available.
			 */
		} elseif ( apply_filters( 'ep_autosuggest_v2_available', false ) ) {
			$status->code      = 1;
			$status->message[] = esc_html__( 'You are using a custom proxy. Make sure you implement all security measures needed.', 'elasticpress' );
		} else {
			$status->message[] = wp_kses_post( __( "To use this feature you need to be an <a href='https://elasticpress.io'>ElasticPress.io</a> customer or implement a <a href='https://github.com/10up/elasticpress-proxy'>custom proxy</a>.", 'elasticpress' ) );
		}

		/**
		 * Display a warning if ElasticPress is network activated.
		 */
		if ( defined( 'EP_IS_NETWORK' ) && EP_IS_NETWORK ) {
			$status->message[] = wp_kses_post(
				sprintf(
					/* translators: Article URL */
					__(
						'ElasticPress is network activated. Additional steps are required to ensure AutosuggestV2 works for all sites on the network. See our article on <a href="%s" target="_blank">running ElasticPress in network mode</a> for more details.',
						'elasticpress'
					),
					'https://www.elasticpress.io/documentation/article/running-elasticpress-in-a-wordpress-multisite-network-mode/'
				)
			);
		}

		return $status;
	}

	/**
	 * Setup feature functionality.
	 *
	 * @return void
	 */
	public function setup() {
		add_filter( 'ep_after_update_feature', [ $this, 'after_update_feature' ], 10, 3 );
		add_filter( 'ep_post_mapping', [ $this, 'add_mapping_properties' ] );
		add_filter( 'ep_post_sync_args', [ $this, 'add_post_sync_args' ], 10, 2 );
		add_filter( 'ep_after_sync_index', [ $this, 'epio_save_search_template' ] );
		add_filter( 'ep_saved_weighting_configuration', [ $this, 'epio_save_search_template' ] );
		add_filter( 'ep_bypass_exclusion_from_search', [ $this, 'maybe_bypass_post_exclusion' ], 10, 2 );
		add_action( 'pre_get_posts', [ $this, 'maybe_apply_product_visibility' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );
	}

	/**
	 * Enqueue our autosuggest script.
	 */
	public function enqueue_frontend_assets() {
		if ( Utils\is_indexing() ) {
			return;
		}

		wp_enqueue_style(
			'elasticpress-autosuggest-v2',
			EP_URL . 'dist/css/autosuggest-v2-styles.css',
			Utils\get_asset_info( 'autosuggest-v2-styles', 'dependencies' ),
			Utils\get_asset_info( 'autosuggest-v2-styles', 'version' )
		);

		wp_enqueue_script(
			'elasticpress-autosuggest-v2',
			EP_URL . 'dist/js/autosuggest-v2-script.js',
			Utils\get_asset_info( 'autosuggest-v2-script', 'dependencies' ),
			Utils\get_asset_info( 'autosuggest-v2-script', 'version' ),
			true
		);

		wp_set_script_translations( 'elasticpress-autosuggest-v2', 'elasticpress' );

		/**
		 * The search API endpoint.
		 *
		 * @since 5.3.0
		 * @hook ep_autosuggest_v2_search_endpoint
		 * @param {string} $endpoint Endpoint path.
		 * @param {string} $index Elasticsearch index.
		 */
		$api_endpoint = apply_filters( 'ep_autosuggest_v2_search_endpoint', "api/v1/search/posts/{$this->index}", $this->index );

		wp_localize_script(
			'elasticpress-autosuggest-v2',
			'epAutosuggestV2',
			array(
				'apiEndpoint'         => $api_endpoint,
				'apiHost'             => ( 0 !== strpos( $api_endpoint, 'http' ) ) ? esc_url_raw( $this->host ) : '',
				'argsSchema'          => $this->get_args_schema(),
				'currencyCode'        => $this->is_woocommerce ? get_woocommerce_currency() : false,
				'isWooCommerce'       => $this->is_woocommerce,
				'locale'              => str_replace( '_', '-', get_locale() ),
				'matchType'           => $this->settings['match_type'],
				'paramPrefix'         => 'ep-',
				'termCount'           => $this->settings['term_count'],
				'requestIdBase'       => Utils\get_request_id_base(),
				'showSuggestions'     => \ElasticPress\Features::factory()->get_registered_feature( 'did-you-mean' )->is_active(),
				'suggestionsBehavior' => $this->settings['search_behavior'],
			)
		);
	}

	/**
	 * Save or delete the search template on ElasticPress.io based on whether
	 * the AutosuggestV2 feature is being activated or deactivated.
	 *
	 * @param string $feature  Feature slug
	 * @param array  $settings Feature settings
	 * @param array  $data     Feature activation data
	 *
	 * @return void
	 *
	 * @since 5.3.0
	 */
	public function after_update_feature( $feature, $settings, $data ) {
		if ( $feature !== $this->slug ) {
			return;
		}

		if ( true === $data['active'] ) {
			$this->epio_save_search_template();
		} else {
			$this->epio_delete_search_template();
		}
	}

	/**
	 * Get the endpoint for the AutosuggestV2 search template.
	 *
	 * @return string AutosuggestV2 search template endpoint.
	 */
	public function get_template_endpoint() {
		/**
		 * Filters the search template API endpoint.
		 *
		 * @since 5.3.0
		 * @hook ep_autosuggest_v2_template_endpoint
		 * @param {string} $endpoint Endpoint path.
		 * @param {string} $index Elasticsearch index.
		 * @returns {string} Search template API endpoint.
		 */
		return apply_filters( 'ep_autosuggest_v2_template_endpoint', "api/v1/search/posts/{$this->index}/template/", $this->index );
	}

	/**
	 * Save the search template to ElasticPress.io.
	 *
	 * @return void
	 */
	public function epio_save_search_template() {
		$endpoint = $this->get_template_endpoint();
		$template = $this->get_search_template();

		Elasticsearch::factory()->remote_request(
			$endpoint,
			[
				'blocking' => false,
				'body'     => $template,
				'method'   => 'PUT',
			]
		);

		/**
		 * Fires after the request is sent the search template API endpoint.
		 *
		 * @since 5.3.0
		 * @hook ep_autosuggest_v2_template_saved
		 * @param {string} $template The search template (JSON).
		 * @param {string} $index Index name.
		 */
		do_action( 'ep_autosuggest_v2_template_saved', $template, $this->index );
	}

	/**
	 * Delete the search template from ElasticPress.io.
	 *
	 * @return void
	 *
	 * @since 5.3.0
	 */
	public function epio_delete_search_template() {
		$endpoint = $this->get_template_endpoint();

		Elasticsearch::factory()->remote_request(
			$endpoint,
			[
				'blocking' => false,
				'method'   => 'DELETE',
			]
		);

		/**
		 * Fires after the request is sent the search template API endpoint.
		 *
		 * @since 5.3.0
		 * @hook ep_autosuggest_v2_template_deleted
		 * @param {string} $index Index name.
		 */
		do_action( 'ep_autosuggest_v2_template_deleted', $this->index );
	}

	/**
	 * Get the saved search template from ElasticPress.io.
	 *
	 * @return string|WP_Error Search template if found, WP_Error on error.
	 *
	 * @since 5.3.0
	 */
	public function epio_get_search_template() {
		$endpoint = $this->get_template_endpoint();
		$request  = Elasticsearch::factory()->remote_request( $endpoint );

		if ( is_wp_error( $request ) ) {
			return $request;
		}

		$response = wp_remote_retrieve_body( $request );

		return $response;
	}

	/**
	 * Generate a search template.
	 *
	 * A search template is the JSON for an Elasticsearch query with a
	 * placeholder search term. The template is sent to ElasticPress.io where
	 * it's used to make Elasticsearch queries using search terms sent from
	 * the front end.
	 *
	 * @return string The search template as JSON.
	 */
	public function get_search_template() {
		$post_types    = Features::factory()->get_registered_feature( 'search' )->get_searchable_post_types();
		$post_statuses = get_post_stati(
			[
				'public'              => true,
				'exclude_from_search' => false,
			]
		);

		/**
		 * The ID of the current user when generating the AutosuggestV2
		 * search template.
		 *
		 * By default AutosuggestV2 sets the current user as anomnymous when
		 * generating the search template, so that any filters applied to
		 * queries for logged-in or specific users are not applied to the
		 * template. This filter supports setting a specific user as the
		 * current user while the template is generated.
		 *
		 * @since 5.3.0
		 * @hook ep_search_template_user_id
		 * @param {int} $user_id User ID to use.
		 * @return {int} New user ID to use.
		 */
		$template_user_id = apply_filters( 'ep_search_template_user_id', 0 );
		$original_user_id = get_current_user_id();

		wp_set_current_user( $template_user_id );

		add_filter( 'ep_intercept_remote_request', '__return_true' );
		add_filter( 'ep_do_intercept_request', [ $this, 'intercept_search_request' ], 10, 4 );
		add_filter( 'ep_is_integrated_request', [ $this, 'is_integrated_request' ], 10, 2 );

		$query = new \WP_Query(
			array(
				'ep_integrate'       => true,
				'ep_search_template' => true,
				'post_status'        => array_values( $post_statuses ),
				'post_type'          => $post_types,
				's'                  => '{{ep_placeholder}}',
			)
		);

		remove_filter( 'ep_intercept_remote_request', '__return_true' );
		remove_filter( 'ep_do_intercept_request', [ $this, 'intercept_search_request' ], 10 );
		remove_filter( 'ep_is_integrated_request', [ $this, 'is_integrated_request' ], 10 );

		wp_set_current_user( $original_user_id );

		return $this->search_template;
	}

	/**
	 * Return true if a given feature is supported by AutosuggestV2.
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

		return in_array( $context, $supported_contexts, true );
	}

	/**
	 * Store intercepted request body and return request result.
	 *
	 * @param object $response Response
	 * @param array  $query Query
	 * @param array  $args WP_Query argument array
	 * @param int    $failures Count of failures in request loop
	 * @return object $response Response
	 */
	public function intercept_search_request( $response, $query = [], $args = [], $failures = 0 ) {
		$this->search_template = $query['args']['body'];

		return wp_remote_request( $query['url'], $args );
	}

	/**
	 * If generating the search template query, do not bypass the post exclusion
	 *
	 * @since 5.3.0
	 * @param bool     $bypass_exclusion_from_search Whether the post exclusion from search should be applied or not
	 * @param WP_Query $query The WP Query
	 * @return bool
	 */
	public function maybe_bypass_post_exclusion( $bypass_exclusion_from_search, $query ) {
		return true === $query->get( 'ep_search_template' ) ?
			false : // not bypass, apply
			$bypass_exclusion_from_search;
	}

	/**
	 * Apply product visibility taxonomy query to search template queries.
	 *
	 * @param \WP_Query $query Query instance.
	 * @return void
	 */
	public function maybe_apply_product_visibility( $query ) {
		if ( true !== $query->get( 'ep_search_template' ) ) {
			return;
		}

		if ( ! $this->is_woocommerce ) {
			return;
		}

		$this->apply_product_visibility( $query );
	}

	/**
	 * Apply product visibility taxonomy query.
	 *
	 * Applies filters to exclude products set to be excluded from search. Out
	 * of stock products will also be excluded if WooCommerce is configured to
	 * hide those products.
	 *
	 * Mimics the logic of WC_Query::get_tax_query().
	 *
	 * @param \WP_Query $query Query instance.
	 * @return void
	 */
	public function apply_product_visibility( $query ) {
		$product_visibility_terms  = wc_get_product_visibility_term_ids();
		$product_visibility_not_in = (array) $product_visibility_terms['exclude-from-search'];

		if ( 'yes' === get_option( 'woocommerce_hide_out_of_stock_items' ) ) {
			$product_visibility_not_in[] = $product_visibility_terms['outofstock'];
		}

		if ( ! empty( $product_visibility_not_in ) ) {
			$tax_query = $query->get( 'tax_query', array() );

			$tax_query[] = array(
				'taxonomy' => 'product_visibility',
				'field'    => 'term_taxonomy_id',
				'terms'    => $product_visibility_not_in,
				'operator' => 'NOT IN',
			);

			$query->set( 'tax_query', $tax_query );
		}
	}

	/**
	 * Add additional fields to post mapping.
	 *
	 * @param array $mapping Post mapping.
	 * @return array Post mapping.
	 */
	public function add_mapping_properties( $mapping ) {
		$elasticsearch_version = Elasticsearch::factory()->get_elasticsearch_version();

		$properties = array(
			'post_content_plain' => array( 'type' => 'text' ),
		);

		if ( version_compare( (string) $elasticsearch_version, '7.0', '<' ) ) {
			$mapping['mappings']['post']['properties'] = array_merge(
				$mapping['mappings']['post']['properties'],
				$properties
			);
		} else {
			$mapping['mappings']['properties'] = array_merge(
				$mapping['mappings']['properties'],
				$properties
			);
		}

		return $mapping;
	}

	/**
	 * Add data for additional mapping properties.
	 *
	 * @param array   $post_args Post arguments.
	 * @param integer $post_id   Post ID.
	 * @return array Post sync args.
	 */
	public function add_post_sync_args( $post_args, $post_id ) {
		$post = get_post( $post_id );

		$post_args['post_content_plain'] = $this->prepare_plain_content_arg( $post );

		return $post_args;
	}


	/**
	 * Get data for the plain post content.
	 *
	 * @param WP_Post $post Post object.
	 * @return string Post content.
	 */
	public function prepare_plain_content_arg( $post ) {
		$post_content = apply_filters( 'the_content', $post->post_content );

		return wp_strip_all_tags( $post_content );
	}

	/**
	 * Get schema for search args.
	 *
	 * @return array Search args schema.
	 */
	public function get_args_schema() {
		/**
		 * The number of results per page for AutosuggestV2.
		 *
		 * @since 5.3.0
		 * @hook ep_autosuggest_v2_per_page
		 * @param {int} $per_page Results per page.
		 */
		$per_page = apply_filters( 'ep_autosuggest_v2_per_page', $this->settings['per_page'] );

		$args_schema = array(
			'offset'    => array(
				'type'    => 'number',
				'default' => 0,
			),
			'orderby'   => array(
				'type'          => 'string',
				'default'       => 'relevance',
				'allowedValues' => [ 'date', 'price', 'relevance' ],
			),
			'order'     => array(
				'type'          => 'string',
				'default'       => 'desc',
				'allowedValues' => [ 'asc', 'desc' ],
			),
			'per_page'  => array(
				'type'    => 'number',
				'default' => absint( $per_page ),
			),
			'post_type' => array(
				'type' => 'strings',
			),
			'search'    => array(
				'type'    => 'string',
				'default' => '',
			),
			'relation'  => array(
				'type'          => 'string',
				'default'       => 'all' === $this->settings['match_type'] ? 'and' : 'or',
				'allowedValues' => [ 'and', 'or' ],
			),
		);

		/**
		 * The schema defining the API arguments used by AutosuggestV2.
		 *
		 * The argument schema is used to configure the APISearchProvider
		 * component used by AutosuggestV2, and should conform to what is
		 * supported by the API being used. The AutosuggestV2 UI expects
		 * the default list of arguments to be available, so caution is advised
		 * when adding or removing arguments.
		 *
		 * @since 5.3.0
		 * @hook ep_autosuggest_v2_args_schema
		 * @param {array} $args_schema Results per page.
		 */
		return apply_filters( 'ep_autosuggest_v2_args_schema', $args_schema );
	}

	/**
	 * Set the `settings_schema` attribute
	 *
	 * @since 5.3.0
	 */
	protected function set_settings_schema() {

		$this->settings_schema = [
			[
				'default' => get_option( 'posts_per_page', 6 ),
				'key'     => 'per_page',
				'type'    => 'hidden',
			],
		];
	}
}
