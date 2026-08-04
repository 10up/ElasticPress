<?php
/**
 * WooCommerce Orders HPOS integration.
 *
 * @since 5.4.0
 * @package elasticpress
 */

namespace ElasticPress\Feature\WooCommerce;

use ElasticPress\Indexables;
use Automattic\WooCommerce\Enums\OrderStatus;
use Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableQuery;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * WooCommerce Orders HPOS.
 */
class OrdersHPOS {
	/**
	 * Orders object instance
	 *
	 * @var Orders
	 */
	protected $orders;

	/**
	 * Elasticsearch order IDs keyed by order query hash.
	 *
	 * @var array<string, int[]>
	 */
	protected $elasticsearch_order_results = [];

	/**
	 * Class constructor
	 *
	 * @param Orders $orders Orders object instance.
	 */
	public function __construct( Orders $orders ) {
		$this->orders = $orders;
	}

	/**
	 * Setup order HPOS related hooks.
	 */
	public function setup(): void {
		add_action( 'woocommerce_new_order', [ $this, 'sync_order' ] );
		add_action( 'woocommerce_refund_created', [ $this, 'sync_order' ] );
		add_action( 'woocommerce_update_order', [ $this, 'sync_order' ] );
		add_filter( 'ep_post_sync_args_post_prepare_meta', [ $this, 'set_order_data' ], 10, 2 );
		add_filter( 'woocommerce_hpos_pre_query', [ $this, 'maybe_intercept_wc_orders_query' ], 10, 2 );
		add_filter( 'woocommerce_order_query', [ $this, 'add_elasticsearch_success_to_orders' ], 10, 2 );
	}

	/**
	 * Unsetup order HPOS related hooks.
	 */
	public function tear_down(): void {
		remove_action( 'woocommerce_new_order', [ $this, 'sync_order' ] );
		remove_action( 'woocommerce_refund_created', [ $this, 'sync_order' ] );
		remove_action( 'woocommerce_update_order', [ $this, 'sync_order' ] );
		remove_filter( 'ep_post_sync_args_post_prepare_meta', [ $this, 'set_order_data' ] );
		remove_filter( 'woocommerce_hpos_pre_query', [ $this, 'maybe_intercept_wc_orders_query' ] );
		remove_filter( 'woocommerce_order_query', [ $this, 'add_elasticsearch_success_to_orders' ] );
	}

	/**
	 * Add orders to the sync queue.
	 *
	 * @param int $order_id Order ID.
	 */
	public function sync_order( $order_id ): void {
		Indexables::factory()->get( 'post' )->sync_manager->add_to_queue( $order_id );
	}

	/**
	 * Add order data to ES document args.
	 *
	 * @param array $post_args Post arguments
	 * @param int   $post_id   Post ID.
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

		if ( ! $order ) {
			return $post_args;
		}

		$post_args['post_status']   = $this->get_order_status( $order );
		$post_args['post_type']     = $order->get_type();
		$post_args['post_parent']   = $order->get_changes()['parent_id'] ?? $order->get_data()['parent_id'] ?? 0;
		$post_args['post_date']     = gmdate( 'Y-m-d H:i:s', $order->get_date_created( 'edit' )->getOffsetTimestamp() );
		$post_args['post_date_gmt'] = gmdate( 'Y-m-d H:i:s', $order->get_date_created( 'edit' )->getTimestamp() );
		$post_args['post_excerpt']  = method_exists( $order, 'get_customer_note' ) ? $order->get_customer_note() : '';

		$post_order = new \WP_Post( (object) $post_args );

		add_filter( 'ep_prepared_post_meta', [ $this, 'prepare_meta_data' ], 10, 2 );
		$post_args['meta'] = $post_indexable->prepare_meta_types( $post_indexable->prepare_meta( $post_order ) );
		remove_filter( 'ep_prepared_post_meta', [ $this, 'prepare_meta_data' ] );

		return $post_args;
	}

	/**
	 * Prepare meta data for an order or refund order.
	 *
	 * @param array    $order_meta Meta data
	 * @param \WP_Post $order_post Order object
	 * @return array
	 */
	public function prepare_meta_data( $order_meta, $order_post ) {
		$order = wc_get_order( $order_post->ID );

		if ( ! $order ) {
			return $order_meta;
		}

		// Handle refund orders differently.
		if ( 'shop_order_refund' === $order->get_type() ) {
			return $this->prepare_refund_meta_data( $order );
		}

		$data_store = new \WC_Order_Data_Store_CPT();

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
					$value = is_null( $value ) ? '' : $value->getTimestamp();
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
			if ( is_bool( $value ) && in_array( $prop, $data_store->get_internal_data_store_key_getters(), true ) ) {
				$value = wc_bool_to_string( $value );
			}

			$meta_data[ $meta_key ] = [ $value ];
		}

		$address_props = [
			'billing'  => [
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
			],
			'shipping' => [
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
			],
		];

		foreach ( $address_props as $props ) {
			foreach ( $props as $meta_key => $prop ) {
				$value = $order->{"get_$prop"}( 'edit' );
				$value = is_string( $value ) ? wp_slash( $value ) : $value;

				$meta_data[ $meta_key ] = [ $value ];
			}
		}

		$meta_data['_billing_address_index']  = [ $order->get_meta( '_billing_address_index', true ) ];
		$meta_data['_shipping_address_index'] = [ $order->get_meta( '_shipping_address_index', true ) ];

		$meta_data['_order_total']    = [ $order->get_total( 'edit' ) ];
		$meta_data['_order_shipping'] = [ $order->get_shipping_total( 'edit' ) ];
		$meta_data['_cart_discount']  = [ $order->get_discount_total( 'edit' ) ];

		foreach ( $order->get_meta_data() as $meta ) {
			if ( isset( $meta_data[ $meta->key ] ) ) {
				continue;
			}
			$meta_data[ $meta->key ] = [ $meta->value ];
		}

		return $meta_data;
	}

	/**
	 * Prepare meta data for a refund order.
	 *
	 * @param \WC_Order_Refund $refund Refund order object
	 * @return array
	 */
	protected function prepare_refund_meta_data( $refund ) {
		$meta_data         = [];
		$meta_key_to_props = [
			'_refund_amount'    => 'amount',
			'_refunded_by'      => 'refunded_by',
			'_refunded_payment' => 'refunded_payment',
			'_refund_reason'    => 'reason',
		];

		foreach ( $meta_key_to_props as $meta_key => $prop ) {
			$value = $refund->{"get_$prop"}( 'edit' );
			$value = is_string( $value ) ? wp_slash( $value ) : $value;

			// Handle boolean values for refunded_payment.
			if ( 'refunded_payment' === $prop && is_bool( $value ) ) {
				$value = wc_bool_to_string( $value );
			}

			$meta_data[ $meta_key ] = [ $value ];
		}

		return $meta_data;
	}

	/**
	 * Intercept WooCommerce orders query.
	 *
	 * @param array|null       $order_data Order data or null.
	 * @param OrdersTableQuery $query      The OrdersTableQuery object.
	 * @return array|null Order data array or null to continue with default query.
	 */
	public function maybe_intercept_wc_orders_query( $order_data, OrdersTableQuery $query ) {
		if ( ! $this->should_integrate_with_query( $query->get_query_args(), $query ) ) {
			return null;
		}

		$orders_query = new OrdersHPOSQuery( $query );
		$result       = $orders_query->query();

		// Store order IDs from Elasticsearch keyed by query hash.
		if ( null !== $result && ! empty( $result[0] ) ) {
			$query_hash                                       = $this->get_order_query_hash( $query->get_query_args() );
			$this->elasticsearch_order_results[ $query_hash ] = array_map( 'absint', $result[0] );
		}

		return $result;
	}

	/**
	 * Determines whether or not ES should be integrating with the provided query.
	 *
	 * @param array                                                               $args  HPOS order query arguments.
	 * @param \Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableQuery $query OrdersTableQuery instance.
	 */
	protected function should_integrate_with_query( $args, $query ): bool {
		if ( isset( $args['ep_integrate'] ) && ! filter_var( $args['ep_integrate'], FILTER_VALIDATE_BOOLEAN ) ) {
			return false;
		}

		/** This filter is documented in includes/classes/Indexable/Post/QueryIntegration.php */
		return ! apply_filters( 'ep_skip_query_integration', false, $query );
	}

	/**
	 * Add elasticsearch property to order objects.
	 *
	 * @param array|object $orders Array of WC_Order objects or paginated result object.
	 * @param array        $args   Order query arguments.
	 * @return array|object Modified orders with elasticsearch property.
	 */
	public function add_elasticsearch_success_to_orders( $orders, $args ) {
		$query_hash = $this->get_order_query_hash( $this->normalize_order_query_args( $args ) );
		if ( empty( $this->elasticsearch_order_results[ $query_hash ] ) ) {
			return $orders;
		}

		$order_ids  = $this->elasticsearch_order_results[ $query_hash ];
		$order_list = ( is_object( $orders ) && ! empty( $orders->orders ) ) ? $orders->orders : $orders;

		foreach ( $order_list as $order ) {
			if ( ! $order instanceof \WC_Abstract_Order ) {
				continue;
			}

			if ( in_array( $order->get_id(), $order_ids, true ) ) {
				$order->elasticsearch_success = true;
			}
		}

		unset( $this->elasticsearch_order_results[ $query_hash ] );

		return $orders;
	}

	/**
	 * Normalize order query arguments to match OrdersTableQuery.
	 *
	 * @param array $args Order query arguments.
	 */
	protected function normalize_order_query_args( array $args ): array {
		unset( $args['suppress_filters'] );

		/**
		 * Filter the query args before executing the query.
		 *
		 * @param array $query_vars The query vars.
		 * @return array
		 * @since 10.4.0
		 */
		return apply_filters(
			'woocommerce_orders_table_datastore_get_orders_query',
			$args,
			\WC_Data_Store::load( 'order' )
		);
	}

	/**
	 * Generate a stable hash for HPOS order query arguments.
	 *
	 * @param array $args Order query arguments.
	 */
	protected function get_order_query_hash( array $args ): string {
		unset( $args['suppress_filters'], $args['no_found_rows'], $args['name'] );

		ksort( $args );

		return md5( wp_json_encode( $args ) );
	}

	/**
	 * WC_Order::get_status() returns the order status without the wc- prefix. This function prepends the wc- prefix to match the post_status format used in the query. It mirrors the behavior of Abstract_WC_Order_Data_Store_CPT::get_post_status().
	 *
	 * @param \WC_Abstract_Order $order Order object.
	 * @return string
	 */
	protected function get_order_status( \WC_Abstract_Order $order ) {
		$order_status = $order->get_status( 'edit' );

		$post_status    = $order_status;
		$valid_statuses = get_post_stati();

		if (
			! in_array(
				$post_status,
				[
					OrderStatus::AUTO_DRAFT,
					OrderStatus::DRAFT,
					OrderStatus::TRASH,
				],
				true
			)
			&& in_array( 'wc-' . $post_status, $valid_statuses, true )
		) {
			$post_status = 'wc-' . $post_status;
		}

		return $post_status;
	}
}
