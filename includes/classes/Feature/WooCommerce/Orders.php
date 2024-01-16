<?php
/**
 * WooCommerce Orders
 *
 * @since 4.7.0
 * @package elasticpress
 */

namespace ElasticPress\Feature\WooCommerce;

use ElasticPress\Indexables;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * WooCommerce Orders
 */
class Orders {
	/**
	 * WooCommerce feature object instance
	 *
	 * @var WooCommerce
	 */
	protected $woocommerce;

	/**
	 * Class constructor
	 *
	 * @param WooCommerce $woocommerce WooCommerce feature object instance
	 */
	public function __construct( WooCommerce $woocommerce ) {
		$this->woocommerce = $woocommerce;
	}

	/**
	 * Setup order related hooks
	 */
	public function setup() {
		add_filter( 'ep_sync_insert_permissions_bypass', [ $this, 'bypass_order_permissions_check' ], 10, 2 );
		add_filter( 'ep_prepare_meta_allowed_protected_keys', [ $this, 'allow_meta_keys' ], 10, 2 );
		add_filter( 'ep_post_sync_args_post_prepare_meta', [ $this, 'add_order_items_search' ], 20, 2 );
		add_filter( 'ep_pc_skip_post_content_cleanup', [ $this, 'keep_order_fields' ], 20, 2 );
		add_action( 'parse_query', [ $this, 'maybe_hook_woocommerce_search_fields' ], 1 );
		add_action( 'parse_query', [ $this, 'search_order' ], 11 );
		add_action( 'pre_get_posts', [ $this, 'translate_args' ], 11, 1 );
	}

	/**
	 * Unsetup order related hooks
	 *
	 * @since 5.0.0
	 */
	public function tear_down() {
		remove_filter( 'ep_sync_insert_permissions_bypass', [ $this, 'bypass_order_permissions_check' ] );
		remove_filter( 'ep_prepare_meta_allowed_protected_keys', [ $this, 'allow_meta_keys' ] );
		remove_filter( 'ep_post_sync_args_post_prepare_meta', [ $this, 'add_order_items_search' ], 20 );
		remove_filter( 'ep_pc_skip_post_content_cleanup', [ $this, 'keep_order_fields' ], 20 );
		remove_action( 'parse_query', [ $this, 'maybe_hook_woocommerce_search_fields' ], 1 );
		remove_action( 'parse_query', [ $this, 'search_order' ], 11 );
		remove_action( 'pre_get_posts', [ $this, 'translate_args' ], 11 );
	}

	/**
	 * Allow order creations on the front end to get synced
	 *
	 * @param  bool $override Original order perms check value
	 * @param  int  $post_id Post ID
	 * @return bool
	 */
	public function bypass_order_permissions_check( $override, $post_id ) {
		$searchable_post_types = $this->get_admin_searchable_post_types();

		if ( in_array( get_post_type( $post_id ), $searchable_post_types, true ) ) {
			return true;
		}

		return $override;
	}

	/**
	 * Returns the WooCommerce-oriented post types in admin that EP will search
	 *
	 * @return array
	 */
	public function get_admin_searchable_post_types() {
		$searchable_post_types = array( 'shop_order' );

		/**
		 * Filter admin searchable WooCommerce post types
		 *
		 * @hook ep_woocommerce_admin_searchable_post_types
		 * @since 4.4.0
		 * @param {array} $post_types Post types
		 * @return {array} New post types
		 */
		return apply_filters( 'ep_woocommerce_admin_searchable_post_types', $searchable_post_types );
	}

	/**
	 * Index WooCommerce orders meta fields
	 *
	 * @param array    $meta Existing post meta
	 * @param \WP_Post $post Post object.
	 * @return array
	 */
	public function allow_meta_keys( $meta, $post ) {
		if ( ! in_array( $post->post_type, $this->get_supported_post_types(), true ) ) {
			return $meta;
		}

		return array_unique(
			array_merge(
				$meta,
				array(
					'_customer_user',
					'_order_key',
					'_billing_company',
					'_billing_address_1',
					'_billing_address_2',
					'_billing_city',
					'_billing_postcode',
					'_billing_country',
					'_billing_state',
					'_billing_email',
					'_billing_phone',
					'_shipping_address_1',
					'_shipping_address_2',
					'_shipping_city',
					'_shipping_postcode',
					'_shipping_country',
					'_shipping_state',
					'_billing_last_name',
					'_billing_first_name',
					'_shipping_first_name',
					'_shipping_last_name',
					'_variations_skus',
				)
			)
		);
	}

	/**
	 * Add order items as a searchable string.
	 *
	 * This mimics how WooCommerce currently does in the order_itemmeta
	 * table. They combine the titles of the products and put them in a
	 * meta field called "Items".
	 *
	 * @param array      $post_args Post arguments
	 * @param string|int $post_id Post id
	 *
	 * @return array
	 */
	public function add_order_items_search( $post_args, $post_id ) {
		$order = wc_get_order( $post_id );
		if ( ! $order ) {
			return $post_args;
		}

		$searchable_post_types = $this->get_admin_searchable_post_types();

		// Make sure it is only WooCommerce orders we touch.
		if ( ! in_array( $post_args['post_type'], $searchable_post_types, true ) ) {
			return $post_args;
		}

		$post_indexable = Indexables::factory()->get( 'post' );

		// Get order items.
		$item_meta = [];
		foreach ( $order->get_items() as $delta => $product_item ) {
			// WooCommerce 3.x uses WC_Order_Item_Product instance while 2.x an array
			if ( is_object( $product_item ) && method_exists( $product_item, 'get_name' ) ) {
				$item_meta['_items'][] = $product_item->get_name( 'edit' );
			} elseif ( is_array( $product_item ) && isset( $product_item['name'] ) ) {
				$item_meta['_items'][] = $product_item['name'];
			}
		}

		// Prepare order items.
		$item_meta['_items'] = empty( $item_meta['_items'] ) ? '' : implode( '|', $item_meta['_items'] );
		$post_args['meta']   = array_merge( $post_args['meta'], $post_indexable->prepare_meta_types( $item_meta ) );

		return $post_args;
	}

	/**
	 * Prevent order fields from being removed.
	 *
	 * When Protected Content is enabled, all posts with password have their content removed.
	 * This can't happen for orders, as the order key is added in that field.
	 *
	 * @see https://github.com/10up/ElasticPress/issues/2726
	 *
	 * @param bool  $skip      Whether the password protected content should have their content, and meta removed
	 * @param array $post_args Post arguments
	 * @return bool
	 */
	public function keep_order_fields( $skip, $post_args ) {
		$searchable_post_types = $this->get_admin_searchable_post_types();

		if ( in_array( $post_args['post_type'], $searchable_post_types, true ) ) {
			return true;
		}

		return $skip;
	}

	/**
	 * Sets WooCommerce meta search fields to an empty array if we are integrating the main query with ElasticSearch
	 *
	 * WooCommerce calls this action as part of its own callback on parse_query. We add this filter only if the query
	 * is integrated with ElasticSearch.
	 * If we were to always return array() on this filter, we'd break admin searches when WooCommerce module is activated
	 * without the Protected Content Module
	 *
	 * @param \WP_Query $query Current query
	 */
	public function maybe_hook_woocommerce_search_fields( $query ) {
		global $pagenow, $wp, $wc_list_table;

		if ( ! $this->woocommerce->should_integrate_with_query( $query ) ) {
			return;
		}

		/**
		 * Determines actions to be applied, or removed, if doing a WooCommerce serarch
		 *
		 * @hook ep_woocommerce_hook_search_fields
		 * @since  4.4.0
		 */
		do_action( 'ep_woocommerce_hook_search_fields' );

		if ( 'edit.php' !== $pagenow || empty( $wp->query_vars['s'] ) || 'shop_order' !== $wp->query_vars['post_type'] || ! isset( $_GET['s'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		remove_action( 'parse_query', [ $wc_list_table, 'search_custom_fields' ] );
	}

	/**
	 * Enhance WooCommerce search order by order id, email, phone number, name, etc..
	 * What this function does:
	 * 1. Reverse the woocommerce shop_order_search_custom_fields query
	 * 2. If the search key is integer and it is an Order Id, just query with post__in
	 * 3. If the search key is integer but not an order id ( might be phone number ), use ES to find it
	 *
	 * @param WP_Query $wp WP Query
	 */
	public function search_order( $wp ) {
		global $pagenow;

		if ( ! $this->woocommerce->should_integrate_with_query( $wp ) ) {
			return;
		}

		$searchable_post_types = $this->get_admin_searchable_post_types();

		if ( 'edit.php' !== $pagenow || empty( $wp->query_vars['post_type'] ) || ! in_array( $wp->query_vars['post_type'], $searchable_post_types, true ) ||
			( empty( $wp->query_vars['s'] ) && empty( $wp->query_vars['shop_order_search'] ) ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
		if ( isset( $_GET['s'] ) ) {
			$search_key_safe = str_replace( array( 'Order #', '#' ), '', wc_clean( $_GET['s'] ) );
			unset( $wp->query_vars['post__in'] );
			$wp->query_vars['s'] = $search_key_safe;
		}
		// phpcs:enable WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
	}

	/**
	 * Determines whether or not ES should be integrating with the provided query
	 *
	 * @param \WP_Query $query Query we might integrate with
	 * @return bool
	 */
	public function should_integrate_with_query( \WP_Query $query ) : bool {
		/**
		 * Check the post type
		 */
		$supported_post_types = $this->get_supported_post_types( $query );
		$post_type            = $query->get( 'post_type', false );
		if ( ! empty( $post_type ) &&
			( in_array( $post_type, $supported_post_types, true ) ||
			( is_array( $post_type ) && ! array_diff( $post_type, $supported_post_types ) ) )
		) {
			return true;
		}

		return false;
	}

	/**
	 * Get the supported post types for Order related queries
	 *
	 * @return array
	 */
	public function get_supported_post_types() : array {
		$post_types = [ 'shop_order', 'shop_order_refund' ];

		/**
		 * DEPRECATED. Expands or contracts the post_types eligible for indexing.
		 *
		 * @hook ep_woocommerce_default_supported_post_types
		 * @since 4.4.0
		 * @param  {array} $post_types Post types
		 * @return  {array} New post types
		 */
		$supported_post_types = apply_filters_deprecated(
			'ep_woocommerce_default_supported_post_types',
			[ $post_types ],
			'4.7.0',
			'ep_woocommerce_orders_supported_post_types'
		);

		/**
		 * Expands or contracts the post_types related to orders eligible for indexing.
		 *
		 * @hook ep_woocommerce_orders_supported_post_types
		 * @since 4.7.0
		 * @param {array} $supported_post_types Post types
		 * @return {array} New post types
		 */
		$supported_post_types = apply_filters( 'ep_woocommerce_orders_supported_post_types', $supported_post_types );

		$supported_post_types = array_intersect(
			$supported_post_types,
			Indexables::factory()->get( 'post' )->get_indexable_post_types()
		);

		return $supported_post_types;
	}

	/**
	 * If the query has a search term, add the order fields that need to be searched.
	 *
	 * @param \WP_Query $query The WP_Query
	 * @return \WP_Query
	 */
	protected function maybe_set_search_fields( \WP_Query $query ) {
		$search_term = $this->woocommerce->get_search_term( $query );
		if ( empty( $search_term ) ) {
			return $query;
		}

		$searchable_post_types = $this->get_admin_searchable_post_types();

		$post_type = $query->get( 'post_type', false );
		if ( ! in_array( $post_type, $searchable_post_types, true ) ) {
			return $query;
		}

		$default_search_fields = array( 'post_title', 'post_content', 'post_excerpt' );
		if ( ctype_digit( $search_term ) ) {
			$default_search_fields[] = 'ID';
		}
		$search_fields = $query->get( 'search_fields', $default_search_fields );

		$search_fields['meta'] = array_map(
			'wc_clean',
			/**
			 * Filter shop order meta fields to search for WooCommerce
			 *
			 * @hook shop_order_search_fields
			 * @param  {array} $fields Shop order fields
			 * @return  {array} New fields
			 */
			apply_filters(
				'shop_order_search_fields',
				array(
					'_order_key',
					'_billing_company',
					'_billing_address_1',
					'_billing_address_2',
					'_billing_city',
					'_billing_postcode',
					'_billing_country',
					'_billing_state',
					'_billing_email',
					'_billing_phone',
					'_shipping_address_1',
					'_shipping_address_2',
					'_shipping_city',
					'_shipping_postcode',
					'_shipping_country',
					'_shipping_state',
					'_billing_last_name',
					'_billing_first_name',
					'_shipping_first_name',
					'_shipping_last_name',
					'_items',
				)
			)
		);

		$query->set(
			'search_fields',
			/**
			 * Filter all the shop order fields to search for WooCommerce
			 *
			 * @hook ep_woocommerce_shop_order_search_fields
			 * @since 4.0.0
			 * @param {array}    $fields Shop order fields
			 * @param {WP_Query} $query  WP Query
			 * @return {array} New fields
			 */
			apply_filters( 'ep_woocommerce_shop_order_search_fields', $search_fields, $query )
		);
	}

	/**
	 * Translate args to ElasticPress compat format. This is the meat of what the feature does
	 *
	 * @param  \WP_Query $query WP Query
	 */
	public function translate_args( $query ) {
		if ( ! $this->woocommerce->should_integrate_with_query( $query ) ) {
			return;
		}

		if ( ! $this->should_integrate_with_query( $query ) ) {
			return;
		}

		$query->set( 'ep_integrate', true );

		/**
		 * Make sure filters are suppressed
		 */
		$query->query['suppress_filters'] = false;
		$query->set( 'suppress_filters', false );

		$this->maybe_set_search_fields( $query );
	}

	/**
	 * Handle calls to OrdersAutosuggest methods
	 *
	 * @param string $method_name The method name
	 * @param array  $arguments   Array of arguments
	 */
	public function __call( $method_name, $arguments ) {
		$orders_autosuggest_methods = [
			'after_update_feature',
			'check_token_permission',
			'enqueue_admin_assets',
			'epio_delete_search_template',
			'epio_get_search_template',
			'epio_save_search_template',
			'filter_term_suggest',
			'get_args_schema',
			'get_search_endpoint',
			'get_search_template',
			'get_template_endpoint',
			'get_token',
			'get_token_endpoint',
			'intercept_search_request',
			'is_integrated_request',
			'post_statuses',
			'post_types',
			'mapping',
			'maybe_query_password_protected_posts',
			'maybe_set_posts_where',
			'refresh_token',
			'rest_api_init',
			'set_search_fields',
		];

		if ( in_array( $method_name, $orders_autosuggest_methods, true ) ) {
			_deprecated_function(
				"\ElasticPress\Feature\WooCommerce\WooCommerce\Orders::{$method_name}", // phpcs:ignore
				'4.7.0',
				"\ElasticPress\Features::factory()->get_registered_feature( 'woocommerce' )->orders_autosuggest->{$method_name}()" // phpcs:ignore
			);

			if ( $this->woocommerce->is_orders_autosuggest_enabled() && method_exists( $this->woocommerce->orders_autosuggest, $method_name ) ) {
				call_user_func_array( [ $this->woocommerce->orders_autosuggest, $method_name ], $arguments );
			}
		}
	}
}
