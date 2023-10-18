<?php
/**
 * Instant Search feature
 *
 * @package elasticpress
 */

namespace ElasticPress\Feature\InstantResults;

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
 * Instant Results feature class.
 *
 * @since 4.0.0
 */
class InstantResults extends Feature {
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
		$this->slug = 'instant-results';

		$this->title = esc_html__( 'Instant Results', 'elasticpress' );

		$this->short_title = esc_html__( 'Instant Results', 'elasticpress' );

		$this->summary = __( '<p>Search forms display results instantly after submission. A modal opens that populates results by querying ElasticPress directly.</p><p>WordPress search forms will display results instantly. When the search query is submitted, a modal will open that populates results by querying ElasticPress directly, bypassing WordPress. As the user refines their search, results are refreshed.</p><p>Requires an <a href="https://www.elasticpress.io/" target="_blank">ElasticPress.io plan</a> or a custom proxy to function.</p>', 'elasticpress' );

		$this->docs_url = __( 'https://elasticpress.zendesk.com/hc/en-us/articles/360050447492-Configuring-ElasticPress-via-the-Plugin-Dashboard#instant-results', 'elasticpress' );

		$this->host = trailingslashit( Utils\get_host() );

		$this->index = Indexables::factory()->get( 'post' )->get_index_name();

		$this->is_woocommerce = function_exists( 'WC' );

		$this->default_settings = [
			'highlight_tag'   => 'mark',
			'facets'          => 'post_type,tax-category,tax-post_tag',
			'match_type'      => 'all',
			'term_count'      => '1',
			'per_page'        => get_option( 'posts_per_page', 6 ),
			'search_behavior' => '0',
		];

		$this->settings = $this->get_settings();

		$this->requires_install_reindex = true;

		$this->available_during_installation = true;

		$this->is_powered_by_epio = Utils\is_epio();

		$this->set_settings_schema();

		parent::__construct();
	}

	/**
	 * Output detailed feature description.
	 *
	 * @return void
	 */
	public function output_feature_box_long() {
		?>
		<p>
			<?php
			printf(
				/* translators: %s: ElasticPress.io link. */
				esc_html__( 'WordPress search forms will display results instantly. When the search query is submitted, a modal will open that populates results by querying ElasticPress directly, bypassing WordPress. As the user refines their search, results are refreshed. Requires an %s or a custom proxy to function.', 'elasticpress' ),
				sprintf(
					'<a href="%1$s" target="_blank">%2$s</a>',
					'https://www.elasticpress.io/',
					esc_html__( 'ElasticPress.io plan', 'elasticpress' )
				)
			);
			?>
		</p>
		<?php
	}

	/**
	 * Display feature settings.
	 *
	 * @return void
	 */
	public function output_feature_box_settings() {
		if ( ! $this->is_active() ) {
			return;
		}

		$highlight_tags = [ 'mark', 'span', 'strong', 'em', 'i' ];
		?>

		<div class="field">
			<label for="instant-results-highlight-tag" class="field-name status"><?php echo esc_html_e( 'Highlight tag ', 'elasticpress' ); ?></label>
			<div class="input-wrap">
				<select id="instant-results-highlight-tag" name="settings[highlight_tag]">
					<option value=""><?php esc_html_e( 'None', 'elasticpress' ); ?></option>
					<?php
					foreach ( $highlight_tags as $highlight_tag ) {
						printf(
							'<option value="%1$s" %2$s>%3$s</option>',
							esc_attr( $highlight_tag ),
							selected( $this->settings['highlight_tag'], $highlight_tag, false ),
							esc_html( $highlight_tag )
						);
					}
					?>
				</select>
				<p class="field-description"><?php esc_html_e( 'Highlight search terms in results with the selected HTML tag.', 'elasticpress' ); ?></p>
			</div>
		</div>
		<div class="field">
			<label for="feature_instant_results_facets" class="field-name status"><?php esc_html_e( 'Filters', 'elasticpress' ); ?></label>
			<div class="input-wrap">
				<input value="<?php echo esc_attr( $this->settings['facets'] ); ?>" type="text" name="settings[facets]" id="feature_instant_results_facets">
			</div>
		</div>
		<div class="field">
			<div class="field-name status"><?php esc_html_e( 'Match Type', 'elasticpress' ); ?></div>
			<div class="input-wrap">
				<label>
					<input name="settings[match_type]" type="radio" <?php checked( $this->settings['match_type'], 'all' ); ?> value="all">
					<?php echo wp_kses_post( __( 'Show any content tagged to <strong>all</strong> selected terms', 'elasticpress' ) ); ?>
				</label><br>
				<label>
					<input name="settings[match_type]" type="radio" <?php checked( $this->settings['match_type'], 'any' ); ?> value="any">
					<?php echo wp_kses_post( __( 'Show all content tagged to <strong>any</strong> selected term', 'elasticpress' ) ); ?>
				</label>
				<p class="field-description"><?php esc_html_e( '"All" will only show content that matches all filters. "Any" will show content that matches any filter.', 'elasticpress' ); ?></p>
			</div>
		</div>
		<div class="field">
			<div class="field-name status"><?php esc_html_e( 'Term Count', 'elasticpress' ); ?></div>
			<div class="input-wrap">
				<label>
					<input name="settings[term_count]" <?php checked( (bool) $this->settings['term_count'] ); ?> type="radio" value="1"><?php esc_html_e( 'Enabled', 'elasticpress' ); ?>
				</label><br>
				<label>
					<input name="settings[term_count]" <?php checked( ! (bool) $this->settings['term_count'] ); ?> type="radio" value="0"><?php esc_html_e( 'Disabled', 'elasticpress' ); ?>
				</label>
				<p class="field-description"><?php esc_html_e( 'When enabled, it will show the term count in the instant results widget.', 'elasticpress' ); ?></p>
			</div>
		</div>
		<?php
		$show_suggestions = \ElasticPress\Features::factory()->get_registered_feature( 'did-you-mean' )->is_active();

		if ( $show_suggestions ) :
			?>
			<div class="field">
				<div class="field-name status"><?php esc_html_e( 'Search behavior when no result is found', 'elasticpress' ); ?></div>
				<div class="input-wrap">
					<label><input name="settings[search_behavior]" type="radio" <?php checked( $this->settings['search_behavior'], '0' ); ?> <?php disabled( $show_suggestions, false ); ?> value="0"><?php esc_html_e( 'Display the top suggestion', 'elasticpress' ); ?></label><br>
					<label><input name="settings[search_behavior]" type="radio" <?php checked( $this->settings['search_behavior'], 'list' ); ?> <?php disabled( $show_suggestions, false ); ?> value="list"><?php esc_html_e( 'Display all the suggestions', 'elasticpress' ); ?></label><br>
				</div>
			</div>
			<?php
		endif;
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
			 * @since 4.0.0
			 * @hook ep_instant_results_available
			 * @param {string} $available Whether the feature is available.
			 */
		} elseif ( apply_filters( 'ep_instant_results_available', false ) ) {
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
						'ElasticPress is network activated. Additional steps are required to ensure Instant Results works for all sites on the network. See our article on <a href="%s" target="_blank">running ElasticPress in network mode</a> for more details.',
						'elasticpress'
					),
					'https://elasticpress.zendesk.com/hc/en-us/articles/10841087797901-Running-ElasticPress-in-a-WordPress-Multisite-Network-Mode-'
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
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
		add_filter( 'ep_after_update_feature', [ $this, 'after_update_feature' ], 10, 3 );
		add_filter( 'ep_formatted_args', [ $this, 'maybe_apply_aggs_args' ], 10, 3 );
		add_filter( 'ep_post_mapping', [ $this, 'add_mapping_properties' ] );
		add_filter( 'ep_post_sync_args', [ $this, 'add_post_sync_args' ], 10, 2 );
		add_filter( 'ep_after_sync_index', [ $this, 'epio_save_search_template' ] );
		add_filter( 'ep_saved_weighting_configuration', [ $this, 'epio_save_search_template' ] );
		add_filter( 'ep_bypass_exclusion_from_search', [ $this, 'maybe_bypass_post_exclusion' ], 10, 2 );
		add_action( 'pre_get_posts', [ $this, 'maybe_apply_product_visibility' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );
		add_action( 'wp_footer', [ $this, 'render' ] );
	}

	/**
	 * Output modal markup.
	 */
	public function render() {
		echo '<div id="ep-instant-results"></div>';
	}

	/**
	 * Enqueue our autosuggest script.
	 */
	public function enqueue_frontend_assets() {
		if ( Utils\is_indexing() ) {
			return;
		}

		wp_enqueue_style(
			'elasticpress-instant-results',
			EP_URL . 'dist/css/instant-results-styles.css',
			Utils\get_asset_info( 'instant-results-styles', 'dependencies' ),
			Utils\get_asset_info( 'instant-results-styles', 'version' )
		);

		wp_enqueue_script(
			'elasticpress-instant-results',
			EP_URL . 'dist/js/instant-results-script.js',
			Utils\get_asset_info( 'instant-results-script', 'dependencies' ),
			Utils\get_asset_info( 'instant-results-script', 'version' ),
			true
		);

		wp_set_script_translations( 'elasticpress-instant-results', 'elasticpress' );

		/**
		 * The search API endpoint.
		 *
		 * @since 4.0.0
		 * @hook ep_instant_results_search_endpoint
		 * @param {string} $endpoint Endpoint path.
		 * @param {string} $index Elasticsearch index.
		 */
		$api_endpoint = apply_filters( 'ep_instant_results_search_endpoint', "api/v1/search/posts/{$this->index}", $this->index );

		wp_localize_script(
			'elasticpress-instant-results',
			'epInstantResults',
			array(
				'apiEndpoint'         => $api_endpoint,
				'apiHost'             => ( 0 !== strpos( $api_endpoint, 'http' ) ) ? esc_url_raw( $this->host ) : '',
				'argsSchema'          => $this->get_args_schema(),
				'currencyCode'        => $this->is_woocommerce ? get_woocommerce_currency() : false,
				'facets'              => $this->get_facets_for_frontend(),
				'highlightTag'        => $this->settings['highlight_tag'],
				'isWooCommerce'       => $this->is_woocommerce,
				'locale'              => str_replace( '_', '-', get_locale() ),
				'matchType'           => $this->settings['match_type'],
				'paramPrefix'         => 'ep-',
				'postTypeLabels'      => $this->get_post_type_labels(),
				'termCount'           => $this->settings['term_count'],
				'requestIdBase'       => Utils\get_request_id_base(),
				'showSuggestions'     => \ElasticPress\Features::factory()->get_registered_feature( 'did-you-mean' )->is_active(),
				'suggestionsBehavior' => $this->settings['search_behavior'],
			)
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook_suffix The current admin page.
	 */
	public function enqueue_admin_assets( $hook_suffix ) {
		if ( 'toplevel_page_elasticpress' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style( 'wp-edit-post' );

		wp_enqueue_script(
			'elasticpress-instant-results-admin',
			EP_URL . 'dist/js/instant-results-admin-script.js',
			Utils\get_asset_info( 'instant-results-admin-script', 'dependencies' ),
			Utils\get_asset_info( 'instant-results-admin-script', 'version' ),
			true
		);

		wp_set_script_translations( 'elasticpress-instant-results-admin', 'elasticpress' );

		wp_localize_script(
			'elasticpress-instant-results-admin',
			'epInstantResultsAdmin',
			array(
				'facets' => $this->get_facets_for_admin(),
			)
		);
	}

	/**
	 * Save or delete the search template on ElasticPress.io based on whether
	 * the Instant Results feature is being activated or deactivated.
	 *
	 * @param string $feature  Feature slug
	 * @param array  $settings Feature settings
	 * @param array  $data     Feature activation data
	 *
	 * @return void
	 *
	 * @since 4.3.0
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
	 * Get the endpoint for the Instant Results search template.
	 *
	 * @return string Instant Results search template endpoint.
	 */
	public function get_template_endpoint() {
		/**
		 * Filters the search template API endpoint.
		 *
		 * @since 4.0.0
		 * @hook ep_instant_results_template_endpoint
		 * @param {string} $endpoint Endpoint path.
		 * @param {string} $index Elasticsearch index.
		 * @returns {string} Search template API endpoint.
		 */
		return apply_filters( 'ep_instant_results_template_endpoint', "api/v1/search/posts/{$this->index}/template/", $this->index );
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
		 * @since 4.0.0
		 * @hook ep_instant_results_template_saved
		 * @param {string} $template The search template (JSON).
		 * @param {string} $index Index name.
		 */
		do_action( 'ep_instant_results_template_saved', $template, $this->index );
	}

	/**
	 * Delete the search template from ElasticPress.io.
	 *
	 * @return void
	 *
	 * @since 4.3.0
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
		 * @since 4.3.0
		 * @hook ep_instant_results_template_deleted
		 * @param {string} $index Index name.
		 */
		do_action( 'ep_instant_results_template_deleted', $this->index );
	}

	/**
	 * Get the saved search template from ElasticPress.io.
	 *
	 * @return string|WP_Error Search template if found, WP_Error on error.
	 *
	 * @since 4.4.0
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
		 * The ID of the current user when generating the Instant Results
		 * search template.
		 *
		 * By default Instant Results sets the current user as anomnymous when
		 * generating the search template, so that any filters applied to
		 * queries for logged-in or specific users are not applied to the
		 * template. This filter supports setting a specific user as the
		 * current user while the template is generated.
		 *
		 * @since 4.1.0
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
	 * Return true if a given feature is supported by Instant Results.
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
	 * @since 4.4.0
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
	 * Apply aggregation args to search templates.
	 *
	 * @param array     $formatted_args Formatted Elasticsearch query.
	 * @param array     $query_vars Query variables
	 * @param \WP_Query $query Query instance.
	 * @return array Formatted Elasticsearch query.
	 */
	public function maybe_apply_aggs_args( $formatted_args, $query_vars, $query ) {
		if ( true !== $query->get( 'ep_search_template' ) ) {
			return $formatted_args;
		}

		return $this->apply_aggs_args( $formatted_args );
	}

	/**
	 * Add aggregation args to Elasticsearch query for facets.
	 *
	 * @param array $formatted_args Formatted Elasticsearch query.
	 * @return array Formatted Elasticsearch query.
	 */
	public function apply_aggs_args( $formatted_args ) {
		$filter = $formatted_args['post_filter'];
		$facets = $this->get_facets();

		foreach ( $facets as $key => $facet ) {
			$formatted_args['aggs'][ $key ]['aggs'] = $facet['aggs'];

			if ( $filter ) {
				$formatted_args['aggs'][ $key ]['filter'] = $filter;
			}
		}

		return $formatted_args;
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
			'price_html'         => array( 'type' => 'text' ),
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
		$post_args['price_html']         = $this->prepare_price_html_arg( $post );

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
	 * Get data for the price HTML arg.
	 *
	 * @param WP_Post $post Post object.
	 * @return string|null Price HTML.
	 */
	public function prepare_price_html_arg( $post ) {
		if ( 'product' !== $post->post_type ) {
			return null;
		}

		if ( ! $this->is_woocommerce ) {
			return null;
		}

		$product = wc_get_product( $post );

		return $product->get_price_html();
	}

	/**
	 * Get post type labels.
	 *
	 * Only the post type slug is indexed, so we'll need the labels on the
	 * front end for display.
	 *
	 * @return array Array of post types and their labels.
	 */
	public function get_post_type_labels() {
		$labels = [];

		$post_types = Features::factory()->get_registered_feature( 'search' )->get_searchable_post_types();

		foreach ( $post_types as $post_type ) {
			$post_type_object = get_post_type_object( $post_type );
			$post_type_labels = get_post_type_labels( $post_type_object );

			$labels[ $post_type ] = array(
				'plural'   => $post_type_labels->name,
				'singular' => $post_type_labels->singular_name,
			);
		}

		return $labels;
	}

	/**
	 * Get available facets.
	 *
	 * @return array Available facets.
	 */
	public function get_facets() {
		$facets = [];

		/**
		 * Post type facet.
		 */
		$facets['post_type'] = array(
			'type'       => 'post_type',
			'post_types' => [],
			'labels'     => array(
				'admin'    => __( 'Post type', 'elasticpress' ),
				'frontend' => __( 'Type', 'elasticpress' ),
			),
			'aggs'       => array(
				'post_type' => array(
					'terms' => array(
						'field' => 'post_type.raw',
					),
				),
			),
			/**
			 * The post_type arg needs to be supported regardless of whether
			 * the Post Type facet is present to be able to support setting the
			 * post type from the search form.
			 *
			 * @see ElasticPress\Feature\InstantResults::get_args_schema()
			 */
			'args'       => array(),
		);

		/**
		 * Taxonomy facets.
		 */
		$taxonomies = get_taxonomies( array( 'public' => true ), 'object' );
		$taxonomies = apply_filters( 'ep_facet_include_taxonomies', $taxonomies );

		foreach ( $taxonomies as $slug => $taxonomy ) {
			$name   = 'tax-' . $slug;
			$labels = get_taxonomy_labels( $taxonomy );

			$admin_label = sprintf(
				/* translators: $1$s: Taxonomy name. %2$s: Taxonomy slug. */
				esc_html__( '%1$s (%2$s)' ),
				$labels->singular_name,
				$slug
			);

			$post_types = Features::factory()->get_registered_feature( 'search' )->get_searchable_post_types();
			$post_types = array_intersect( $post_types, $taxonomy->object_type );
			$post_types = array_values( $post_types );

			$facets[ $name ] = array(
				'type'       => 'taxonomy',
				'post_types' => $post_types,
				'labels'     => array(
					'admin'    => $admin_label,
					'frontend' => $labels->singular_name,
				),
				'aggs'       => array(
					$name => array(
						'terms' => array(
							'field' => 'terms.' . $slug . '.facet',
							'size'  => apply_filters( 'ep_facet_taxonomies_size', 10000, $taxonomy ),
						),
					),
				),
				'args'       => array(
					$name => array(
						'type' => 'strings',
					),
				),
			);
		}

		/**
		 * Price facet.
		 */
		if ( $this->is_woocommerce ) {
			$facets['price_range'] = array(
				'type'       => 'price_range',
				'post_types' => [ 'product' ],
				'labels'     => array(
					'admin'    => __( 'Price range', 'elasticpress' ),
					'frontend' => __( 'Price', 'elasticpress' ),
				),
				'aggs'       => array(
					'max_price' => array(
						'max' => array(
							'field' => 'meta._price.double',
						),
					),
					'min_price' => array(
						'min' => array(
							'field' => 'meta._price.double',
						),
					),
				),
				'args'       => array(
					'max_price' => array(
						'type' => 'number',
					),
					'min_price' => array(
						'type' => 'number',
					),
				),
			);
		}

		return $facets;
	}

	/**
	 * Get facet configuration for the front end.
	 *
	 * @return Array Facet configuration for the front end.
	 */
	public function get_facets_for_frontend() {
		$selected_facets  = explode( ',', $this->settings['facets'] );
		$available_facets = $this->get_facets();

		$facets = [];

		foreach ( $selected_facets as $key ) {
			if ( isset( $available_facets[ $key ] ) ) {
				$facet = $available_facets[ $key ];

				$facets[] = array(
					'name'      => $key,
					'label'     => $facet['labels']['frontend'],
					'type'      => $facet['type'],
					'postTypes' => $facet['post_types'],
				);
			}
		}

		return $facets;
	}

	/**
	 * Get facet configuration for the admin.
	 *
	 * @return Array Facet configuration for the admin.
	 */
	public function get_facets_for_admin() {
		$available_facets = $this->get_facets();

		$facets = [];

		foreach ( $available_facets as $key => $facet ) {
			$facets[ $key ] = array(
				'label' => $facet['labels']['admin'],
				'value' => $key,
			);
		}

		return $facets;
	}

	/**
	 * Get schema for search args.
	 *
	 * @return array Search args schema.
	 */
	public function get_args_schema() {
		/**
		 * The number of results per page for Instant Results.
		 *
		 * @since 4.5.0
		 * @hook ep_instant_results_per_page
		 * @param {int} $per_page Results per page.
		 */
		$per_page = apply_filters( 'ep_instant_results_per_page', $this->settings['per_page'] );

		$args_schema = array(
			'highlight' => array(
				'type'          => 'string',
				'default'       => $this->settings['highlight_tag'],
				'allowedValues' => [ $this->settings['highlight_tag'] ],
			),
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

		$selected_facets  = explode( ',', $this->settings['facets'] );
		$available_facets = $this->get_facets();

		foreach ( $selected_facets as $key ) {
			if ( isset( $available_facets[ $key ] ) ) {
				$args_schema = array_merge( $args_schema, $available_facets[ $key ]['args'] );
			}
		}

		/**
		 * The schema defining the API arguments used by Instant Results.
		 *
		 * The argument schema is used to configure the APISearchProvider
		 * component used by Instant Results, and should conform to what is
		 * supported by the API being used. The Instant Results UI expects
		 * the default list of arguments to be available, so caution is advised
		 * when adding or removing arguments.
		 *
		 * @since 4.5.1
		 * @hook ep_instant_results_args_schema
		 * @param {array} $args_schema Results per page.
		 */
		return apply_filters( 'ep_instant_results_args_schema', $args_schema );
	}

	/**
	 * Set the `settings_schema` attribute
	 *
	 * @since 5.0.0
	 */
	protected function set_settings_schema() {
		$facets = $this->get_facets_for_admin();

		$this->settings_schema = [
			[
				'default' => 'mark',
				'help'    => __( 'Highlight search terms in results with the selected HTML tag.', 'elasticpress' ),
				'key'     => 'highlight_tag',
				'label'   => __( 'Highlight tag', 'elasticpress' ),
				'options' => [
					[
						'label' => __( 'None', 'elasticpress' ),
						'value' => '',
					],
					[
						'label' => 'mark',
						'value' => 'mark',
					],
					[
						'label' => 'span',
						'value' => 'span',
					],
					[
						'label' => 'strong',
						'value' => 'strong',
					],
					[
						'label' => 'em',
						'value' => 'em',
					],
					[
						'label' => 'i',
						'value' => 'i',
					],
				],
				'type'    => 'select',
			],
			[
				'default' => 'post_type,tax-category,tax-post_tag',
				'key'     => 'facets',
				'label'   => __( 'Filters', 'elasticpress' ),
				'options' => array_values( $facets ),
				'type'    => 'multiple',
			],
			[
				'default' => 'all',
				'help'    => __( '"All" will only show content that matches all filters. "Any" will show content that matches any filter.', 'elasticpress' ),
				'key'     => 'match_type',
				'label'   => __( 'Match Type', 'elasticpress' ),
				'options' => [
					[
						'label' => __( 'Show any content tagged to <strong>all</strong> selected terms', 'elasticpress' ),
						'value' => 'all',
					],
					[
						'label' => __( 'Show all content tagged to <strong>any</strong> selected term', 'elasticpress' ),
						'value' => 'any',
					],
				],
				'type'    => 'radio',
			],
			[
				'default' => '1',
				'help'    => __( 'When enabled, it will show the term count in the instant results widget.', 'elasticpress' ),
				'key'     => 'term_count',
				'label'   => __( 'Term Count', 'elasticpress' ),
				'options' => [
					[
						'label' => __( 'Enabled', 'elasticpress' ),
						'value' => '1',
					],
					[
						'label' => __( 'Disabled', 'elasticpress' ),
						'value' => '0',
					],
				],
				'type'    => 'radio',
			],
			[
				'default' => get_option( 'posts_per_page', 6 ),
				'key'     => 'per_page',
				'type'    => 'hidden',
			],
			[
				'default'          => '0',
				'key'              => 'search_behavior',
				'label'            => __( 'Search behavior when no result is found', 'elasticpress' ),
				'options'          => [
					[
						'label' => __( 'Display the top suggestion', 'elasticpress' ),
						'value' => '0',
					],
					[
						'label' => __( 'Display all the suggestions', 'elasticpress' ),
						'value' => 'list',
					],
				],
				'requires_feature' => 'did-you-mean',
				'type'             => 'radio',
			],
		];
	}
}
