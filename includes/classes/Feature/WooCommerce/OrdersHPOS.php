<?php
/**
 * WooCommerce HPOS compatibility layer
 *
 * @since 5.3.0
 * @package elasticpress
 */

namespace ElasticPress\Feature\WooCommerce;

use ElasticPress\Indexables;
use Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableQuery;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * WooCommerce HPOS
 */
class OrdersHPOS {
	/**
	 * Orders object instance
	 *
	 * @var Orders
	 */
	protected $orders;

	/**
	 * Class constructor
	 *
	 * @param Orders $orders Orders object instance.
	 */
	public function __construct( Orders $orders ) {
		$this->orders = $orders;
	}

	/**
	 * Setup order HPOS related hooks
	 *
	 * @return void
	 */
	public function setup(): void {
		add_action( 'woocommerce_new_order', [ $this, 'sync_order' ] );
		add_action( 'woocommerce_refund_created', [ $this, 'sync_order' ] );
		add_action( 'woocommerce_update_order', [ $this, 'sync_order' ] );
		add_filter( 'ep_post_sync_args_post_prepare_meta', [ $this, 'set_order_data' ], 10, 2 );
		add_filter( 'woocommerce_hpos_pre_query', [ $this, 'maybe_intercept_wc_orders_query' ], 10, 2 );
	}

	/**
	 * Unsetup order HPOS related hooks
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_action( 'woocommerce_new_order', [ $this, 'sync_order' ] );
		remove_action( 'woocommerce_refund_created', [ $this, 'sync_order' ] );
		remove_action( 'woocommerce_update_order', [ $this, 'sync_order' ] );
		remove_filter( 'ep_post_sync_args_post_prepare_meta', [ $this, 'set_order_data' ] );
		remove_filter( 'woocommerce_hpos_pre_query', [ $this, 'maybe_intercept_wc_orders_query' ] );
	}

	/**
	 * Add orders to the sync queue
	 *
	 * @param int $order_id Order ID.
	 */
	public function sync_order( $order_id ): void {
		Indexables::factory()->get( 'post' )->sync_manager->add_to_queue( $order_id );
	}

	/**
	 * Add order data to ES document args
	 *
	 * @param array $post_args Post arguments
	 * @param int   $post_id   Post ID
	 * @return array
	 */
	public function set_order_data( $post_args, $post_id ) {
		if (
			\Automattic\WooCommerce\Internal\DataStores\Orders\DataSynchronizer::PLACEHOLDER_ORDER_POST_TYPE !== $post_args['post_type']
			&& ! in_array( $post_args['post_type'], $this->orders->get_supported_post_types(), true ) ) {
			return $post_args;
		}

		$post_indexable = Indexables::factory()->get( 'post' );
		$order          = wc_get_order( $post_id );

		$post_args['post_type']     = $order->get_type();
		// @todo: check if this is correct
		$post_args['post_status']   = 'wc-' . $order->get_status( 'edit' );
		$post_args['post_parent']   = $order->get_changes()['parent_id'] ?? $order->get_data()['parent_id'] ?? 0;
		$post_args['post_date']     = gmdate( 'Y-m-d H:i:s', $order->get_date_created( 'edit' )->getOffsetTimestamp() );
		$post_args['post_date_gmt'] = gmdate( 'Y-m-d H:i:s', $order->get_date_created( 'edit' )->getTimestamp() );
		$post_args['edit_date']     = true;
		$post_args['post_excerpt']  = method_exists( $order, 'get_customer_note' ) ? $order->get_customer_note() : '';

		$post_order = new \WP_Post( (object) $post_args );

		add_filter( 'ep_prepared_post_meta', [ $this, 'prepare_meta_data' ], 10, 2 );
		$post_args['meta'] = $post_indexable->prepare_meta_types( $post_indexable->prepare_meta( $post_order ) );
		remove_filter( 'ep_prepared_post_meta', [ $this, 'prepare_meta_data' ] );

		return $post_args;
	}

	/**
	 * Get meta data from an order as it would be stored in the post_meta table.
	 *
	 * This method is a copy of WC_Order_Data_Store_CPT::update_post_meta() with some simplifications and returning data as an array,
	 * instead of actually storing it in the database.
	 *
	 * @param array   $order_meta Meta data
	 * @param WP_Post $order_post Order object
	 * @return array
	 */
	public function prepare_meta_data( $order_meta, $order_post ) {
		$data_store = new \WC_Order_Data_Store_CPT();
		$order      = wc_get_order( $order_post->ID );

		$meta_data         = [];
		$meta_key_to_props = [
			'_order_key'                    => 'order_key',
			'_customer_user'                => 'customer_id',
			'_payment_method'               => 'payment_method',
			'_payment_method_title'         => 'payment_method_title',
			'_transaction_id'               => 'transaction_id',
			'_customer_ip_address'          => 'customer_ip_address',
			'_customer_user_agent'          => 'customer_user_agent',
			'_created_via'                  => 'created_via',
			'_date_completed'               => 'date_completed',
			'_date_paid'                    => 'date_paid',
			'_cart_hash'                    => 'cart_hash',
			'_download_permissions_granted' => 'download_permissions_granted',
			'_recorded_sales'               => 'recorded_sales',
			'_recorded_coupon_usage_counts' => 'recorded_coupon_usage_counts',
			'_new_order_email_sent'         => 'new_order_email_sent',
			'_order_stock_reduced'          => 'order_stock_reduced',
		];

		foreach ( $meta_key_to_props as $meta_key => $prop ) {
			$value = $order->{"get_$prop"}( 'edit' );
			$value = is_string( $value ) ? wp_slash( $value ) : $value;
			switch ( $prop ) {
				case 'date_paid':
				case 'date_completed':
					$value = ! is_null( $value ) ? $value->getTimestamp() : '';
					break;
				case 'download_permissions_granted':
				case 'recorded_sales':
				case 'recorded_coupon_usage_counts':
				case 'order_stock_reduced':
					if ( is_null( $value ) || '' === $value ) {
						break;
					}
					$value = is_bool( $value ) ? wc_bool_to_string( $value ) : $value;
					break;
				case 'new_order_email_sent':
					if ( is_null( $value ) || '' === $value ) {
						break;
					}
					$value = is_bool( $value ) ? wc_bool_to_string( $value ) : $value;
					$value = 'yes' === $value ? 'true' : 'false'; // For backward compatibility, we store as true/false in DB.
					break;
			}

			// We want to persist internal data store keys as 'yes' or 'no' if they are boolean to maintain compatibility.
			if ( is_bool( $value ) && in_array( $prop, array_values( $data_store->get_internal_data_store_key_getters() ), true ) ) {
				$value = wc_bool_to_string( $value );
			}

			$meta_data[ $meta_key ] = [ $value ];
		}

		$address_props = array(
			'billing'  => array(
				'_billing_first_name' => 'billing_first_name',
				'_billing_last_name'  => 'billing_last_name',
				'_billing_company'    => 'billing_company',
				'_billing_address_1'  => 'billing_address_1',
				'_billing_address_2'  => 'billing_address_2',
				'_billing_city'       => 'billing_city',
				'_billing_state'      => 'billing_state',
				'_billing_postcode'   => 'billing_postcode',
				'_billing_country'    => 'billing_country',
				'_billing_email'      => 'billing_email',
				'_billing_phone'      => 'billing_phone',
			),
			'shipping' => array(
				'_shipping_first_name' => 'shipping_first_name',
				'_shipping_last_name'  => 'shipping_last_name',
				'_shipping_company'    => 'shipping_company',
				'_shipping_address_1'  => 'shipping_address_1',
				'_shipping_address_2'  => 'shipping_address_2',
				'_shipping_city'       => 'shipping_city',
				'_shipping_state'      => 'shipping_state',
				'_shipping_postcode'   => 'shipping_postcode',
				'_shipping_country'    => 'shipping_country',
				'_shipping_phone'      => 'shipping_phone',
			),
		);

		foreach ( $address_props as $props ) {
			foreach ( $props as $meta_key => $prop ) {
				$value = $order->{"get_$prop"}( 'edit' );
				$value = is_string( $value ) ? wp_slash( $value ) : $value;

				$meta_data[ $meta_key ] = [ $value ];
			}
		}

		return $meta_data;
	}

	/**
	 * Intercept WooCommerce orders query
	 *
	 * @param array|null       $order_data Order data or null.
	 * @param OrdersTableQuery $query      The OrdersTableQuery object.
	 * @return array|null Order data array or null to continue with default query.
	 */
	public function maybe_intercept_wc_orders_query( $order_data, OrdersTableQuery $query ) {
		$orders_query = new OrdersHPOSQuery( $query );
		$result       = $orders_query->query();
		return $result;
	}
}
