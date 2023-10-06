<?php
/**
 * WooCommerce Orders Feature
 *
 * @since 4.5.0
 * @package elasticpress
 */

namespace ElasticPress\Feature\WooCommerce;

use ElasticPress\Elasticsearch;
use ElasticPress\Indexables;
use ElasticPress\REST;
use ElasticPress\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * WooCommerce OrdersAutosuggest Feature
 */
class OrdersAutosuggest {
	/**
	 * The name of the index.
	 *
	 * @var string
	 */
	protected $index;

	/**
	 * The search template.
	 *
	 * @var string
	 */
	protected $search_template;

	/**
	 * Initialize feature.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->index = Indexables::factory()->get( 'post' )->get_index_name();
	}

	/**
	 * Setup feature functionality.
	 *
	 * @return void
	 */
	public function setup() {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
		add_filter( 'ep_after_update_feature', [ $this, 'after_update_feature' ], 10, 3 );
		add_filter( 'ep_after_sync_index', [ $this, 'epio_save_search_template' ] );
		add_filter( 'ep_saved_weighting_configuration', [ $this, 'epio_save_search_template' ] );
		add_filter( 'ep_indexable_post_status', [ $this, 'post_statuses' ] );
		add_filter( 'ep_indexable_post_types', [ $this, 'post_types' ] );
		add_action( 'rest_api_init', [ $this, 'rest_api_init' ] );
		add_filter( 'ep_post_sync_args', [ $this, 'filter_term_suggest' ], 10 );
		add_filter( 'ep_post_mapping', [ $this, 'mapping' ] );
		add_action( 'ep_woocommerce_shop_order_search_fields', [ $this, 'set_search_fields' ], 10, 2 );
		add_filter( 'ep_index_posts_args', [ $this, 'maybe_query_password_protected_posts' ] );
		add_filter( 'posts_where', [ $this, 'maybe_set_posts_where' ], 10, 2 );
	}

	/**
	 * Get the endpoint for WooCommerce Orders search.
	 *
	 * @return string WooCommerce orders search endpoint.
	 */
	public function get_search_endpoint() {
		/**
		 * Filters the WooCommerce Orders search endpoint.
		 *
		 * @since 4.5.0
		 * @hook ep_woocommerce_order_search_endpoint
		 * @param {string} $endpoint Endpoint path.
		 * @param {string} $index Elasticsearch index.
		 */
		return apply_filters( 'ep_woocommerce_order_search_endpoint', "api/v1/search/orders/{$this->index}", $this->index );
	}

	/**
	 * Get the endpoint for the WooCommerce Orders search template.
	 *
	 * @return string WooCommerce Orders search template endpoint.
	 */
	public function get_template_endpoint() {
		/**
		 * Filters the WooCommerce Orders search template API endpoint.
		 *
		 * @since 4.5.0
		 * @hook ep_woocommerce_order_search_template_endpoint
		 * @param {string} $endpoint Endpoint path.
		 * @param {string} $index Elasticsearch index.
		 * @returns {string} Search template API endpoint.
		 */
		return apply_filters( 'ep_woocommerce_order_search_template_endpoint', "api/v1/search/orders/{$this->index}/template", $this->index );
	}

	/**
	 * Registers the API endpoint to get a token.
	 *
	 * @return void
	 */
	public function rest_api_init() {
		$controller = new REST\Token();
		$controller->register_routes();
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook_suffix The current admin page.
	 */
	public function enqueue_admin_assets( $hook_suffix ) {
		if ( 'edit.php' !== $hook_suffix ) {
			return;
		}

		if ( ! isset( $_GET['post_type'] ) || 'shop_order' !== $_GET['post_type'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		wp_enqueue_style(
			'elasticpress-woocommerce-order-search',
			EP_URL . 'dist/css/woocommerce-order-search-styles.css',
			Utils\get_asset_info( 'woocommerce-order-search-styles', 'dependencies' ),
			Utils\get_asset_info( 'woocommerce-order-search-styles', 'version' )
		);

		wp_enqueue_script(
			'elasticpress-woocommerce-order-search',
			EP_URL . 'dist/js/woocommerce-order-search-script.js',
			Utils\get_asset_info( 'woocommerce-order-search-script', 'dependencies' ),
			Utils\get_asset_info( 'woocommerce-order-search-script', 'version' ),
			true
		);

		wp_set_script_translations( 'elasticpress-woocommerce-order-search', 'elasticpress' );

		$api_endpoint = $this->get_search_endpoint();
		$api_host     = Utils\get_host();

		wp_localize_script(
			'elasticpress-woocommerce-order-search',
			'epWooCommerceOrderSearch',
			array(
				'adminUrl'          => admin_url( 'post.php' ),
				'apiEndpoint'       => $api_endpoint,
				'apiHost'           => ( 0 !== strpos( $api_endpoint, 'http' ) ) ? trailingslashit( esc_url_raw( $api_host ) ) : '',
				'argsSchema'        => $this->get_args_schema(),
				'credentialsApiUrl' => rest_url( 'elasticpress/v1/token' ),
				'credentialsNonce'  => wp_create_nonce( 'wp_rest' ),
				'dateFormat'        => wc_date_format(),
				'statusLabels'      => wc_get_order_statuses(),
				'timeFormat'        => wc_time_format(),
				'requestIdBase'     => Utils\get_request_id_base(),
			)
		);
	}

	/**
	 * Save or delete the search template on ElasticPress.io based on whether
	 * the WooCommerce feature is being activated or deactivated.
	 *
	 * @param string $feature  Feature slug
	 * @param array  $settings Feature settings
	 * @param array  $data     Feature activation data
	 *
	 * @return void
	 */
	public function after_update_feature( $feature, $settings, $data ) {
		if ( 'woocommerce' !== $feature ) {
			return;
		}

		if ( true === $data['active'] ) {
			$this->epio_save_search_template();
		} else {
			$this->epio_delete_search_template();
		}
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
		 * @since 4.5.0
		 * @hook ep_woocommerce_order_search_template_saved
		 * @param {string} $template The search template (JSON).
		 * @param {string} $index Index name.
		 */
		do_action( 'ep_woocommerce_order_search_template_saved', $template, $this->index );
	}

	/**
	 * Delete the search template from ElasticPress.io.
	 *
	 * @return void
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
		 * @since 4.5.0
		 * @hook ep_woocommerce_order_search_template_deleted
		 * @param {string} $index Index name.
		 */
		do_action( 'ep_woocommerce_order_search_template_deleted', $this->index );
	}

	/**
	 * Get the saved search template from ElasticPress.io.
	 *
	 * @return string|WP_Error Search template if found, WP_Error on error.
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
		$order_statuses = wc_get_order_statuses();

		add_filter( 'ep_bypass_exclusion_from_search', '__return_true', 10 );
		add_filter( 'ep_intercept_remote_request', '__return_true' );
		add_filter( 'ep_do_intercept_request', [ $this, 'intercept_search_request' ], 10, 4 );
		add_filter( 'ep_is_integrated_request', [ $this, 'is_integrated_request' ], 10, 2 );

		$query = new \WP_Query(
			array(
				'ep_integrate'             => true,
				'ep_order_search_template' => true,
				'post_status'              => array_keys( $order_statuses ),
				'post_type'                => 'shop_order',
				's'                        => '{{ep_placeholder}}',
			)
		);

		remove_filter( 'ep_bypass_exclusion_from_search', '__return_true', 10 );
		remove_filter( 'ep_intercept_remote_request', '__return_true' );
		remove_filter( 'ep_do_intercept_request', [ $this, 'intercept_search_request' ], 10 );
		remove_filter( 'ep_is_integrated_request', [ $this, 'is_integrated_request' ], 10 );

		return $this->search_template;
	}

	/**
	 * Return true if a given feature is supported by WooCommerce Orders.
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
	 * @return bool True if the check is for a feature supported by WooCommerce
	 *              Order search.
	 */
	public function is_integrated_request( $is_integrated, $context ) {
		$supported_contexts = [
			'search',
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
	 * Get schema for search args.
	 *
	 * @return array Search args schema.
	 */
	public function get_args_schema() {
		$args = array(
			'customer' => array(
				'type' => 'number',
			),
			'm'        => array(
				'type' => 'string',
			),
			'offset'   => array(
				'type'    => 'number',
				'default' => 0,
			),
			'per_page' => array(
				'type'    => 'number',
				'default' => 6,
			),
			'search'   => array(
				'type'    => 'string',
				'default' => '',
			),
		);

		return $args;
	}

	/**
	 * Index shop orders.
	 *
	 * @param array $post_types Indexable post types.
	 * @return array Indexable post types.
	 */
	public function post_types( $post_types ) {
		$post_types['shop_order'] = 'shop_order';

		return $post_types;
	}

	/**
	 * Index order statuses.
	 *
	 * @param array $post_statuses Indexable post statuses.
	 * @return array Indexable post statuses.
	 */
	public function post_statuses( $post_statuses ) {
		$order_statuses = wc_get_order_statuses();

		return array_unique( array_merge( $post_statuses, array_keys( $order_statuses ) ) );
	}

	/**
	 * Add term suggestions to be indexed
	 *
	 * @param array $post_args Array of ES args.
	 * @return array
	 */
	public function filter_term_suggest( $post_args ) {
		if ( empty( $post_args['post_type'] ) || 'shop_order' !== $post_args['post_type'] ) {
			return $post_args;
		}

		if ( empty( $post_args['meta'] ) ) {
			return $post_args;
		}

		/**
		 * Add the order number as a meta (text) field, so we can freely search on it.
		 */
		$order_id = $post_args['ID'];
		if ( function_exists( 'wc_get_order' ) ) {
			$order = wc_get_order( $post_args['ID'] );
			if ( $order && is_a( $order, 'WC_Order' ) && method_exists( $order, 'get_order_number' ) ) {
				$order_id = $order->get_order_number();
			}
		}

		$post_args['meta']['order_number'] = [
			[
				'raw'   => $order_id,
				'value' => $order_id,
			],
		];

		$suggest = [];

		$fields_to_ngram = [
			'_billing_email',
			'_billing_last_name',
			'_billing_first_name',
		];

		foreach ( $fields_to_ngram as $field_to_ngram ) {
			if ( ! empty( $post_args['meta'][ $field_to_ngram ] )
				&& ! empty( $post_args['meta'][ $field_to_ngram ][0] )
				&& ! empty( $post_args['meta'][ $field_to_ngram ][0]['value'] ) ) {
				$suggest[] = $post_args['meta'][ $field_to_ngram ][0]['value'];
			}
		}

		if ( ! empty( $suggest ) ) {
			$post_args['term_suggest'] = $suggest;
		}

		return $post_args;
	}

	/**
	 * Add mapping for suggest fields
	 *
	 * @param  array $mapping ES mapping.
	 * @return array
	 */
	public function mapping( $mapping ) {
		$post_indexable = Indexables::factory()->get( 'post' );

		$mapping = $post_indexable->add_ngram_analyzer( $mapping );
		$mapping = $post_indexable->add_term_suggest_field( $mapping );

		return $mapping;
	}

	/**
	 * Set the search_fields parameter in the search template.
	 *
	 * @param array     $search_fields Current search fields
	 * @param \WP_Query $query         Query being executed
	 * @return array New search fields
	 */
	public function set_search_fields( array $search_fields, \WP_Query $query ) : array {
		$is_orders_search_template = (bool) $query->get( 'ep_order_search_template' );

		if ( $is_orders_search_template ) {
			$search_fields = [
				'meta.order_number.value',
				'term_suggest',
				'meta' => [
					'_billing_email',
					'_billing_last_name',
					'_billing_first_name',
				],
			];
		}

		return $search_fields;
	}

	/**
	 * Allow password protected to be indexed.
	 *
	 * If Protected Content is enabled, do nothing. Otherwise, allow pw protected posts to be indexed.
	 * The feature restricts it back in maybe_set_posts_where()
	 *
	 * @see maybe_set_posts_where()
	 * @param array $args WP_Query args
	 * @return array
	 */
	public function maybe_query_password_protected_posts( $args ) {
		// Password protected posts are already being indexed, no need to do anything.
		if ( isset( $args['has_password'] ) && is_null( $args['has_password'] ) ) {
			return $args;
		}

		/**
		 * Set a flag in the query but allow it to index all password protected posts for now,
		 * so WP does not inject its own where clause.
		 */
		$args['ep_orders_has_password'] = true;
		$args['has_password']           = null;

		return $args;
	}

	/**
	 * Restrict password protected posts back but allow orders.
	 *
	 * @see maybe_query_password_protected_posts
	 * @param string   $where Current where clause
	 * @param WP_Query $query WP_Query
	 * @return string
	 */
	public function maybe_set_posts_where( $where, $query ) {
		global $wpdb;

		if ( ! $query->get( 'ep_orders_has_password' ) ) {
			return $where;
		}

		$where .= " AND ( {$wpdb->posts}.post_password = '' OR {$wpdb->posts}.post_type = 'shop_order' )";

		return $where;
	}
}
