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
		add_filter( 'ep_prepare_meta_allowed_protected_keys', [ $this, 'allow_meta_keys' ] );
		add_filter( 'ep_post_sync_args_post_prepare_meta', [ $this, 'add_order_items_search' ], 20, 2 );
		add_action( 'parse_query', [ $this, 'maybe_hook_woocommerce_search_fields' ], 1 );
		add_action( 'parse_query', [ $this, 'search_order' ], 11 );
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
	 * @param  array $meta Existing post meta
	 * @return array
	 */
	public function allow_meta_keys( $meta ) {
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
		$searchable_post_types = $this->get_admin_searchable_post_types();

		// Make sure it is only WooCommerce orders we touch.
		if ( ! in_array( $post_args['post_type'], $searchable_post_types, true ) ) {
			return $post_args;
		}

		$post_indexable = Indexables::factory()->get( 'post' );

		// Get order items.
		$order     = wc_get_order( $post_id );
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
	 * Sets woocommerce meta search fields to an empty array if we are integrating the main query with ElasticSearch
	 *
	 * Woocommerce calls this action as part of its own callback on parse_query. We add this filter only if the query
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
		if ( ! $this->woocommerce->should_integrate_with_query( $wp ) ) {
			return;
		}

		global $pagenow;

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
}
