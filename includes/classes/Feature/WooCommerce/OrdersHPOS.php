<?php
/**
 * WooCommerce HPOS compatibility layer
 *
 * @since 5.3.0
 * @package elasticpress
 */

namespace ElasticPress\Feature\WooCommerce;

use ElasticPress\Indexables;

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
	 * @param Orders $orders Orders object instance
	 */
	public function __construct( Orders $orders ) {
		$this->orders = $orders;
	}

	/**
	 * Setup order HPOS related hooks
	 */
	public function setup() {
		add_action( 'woocommerce_new_order', [ $this, 'sync_order' ] );
		add_action( 'woocommerce_refund_created', [ $this, 'sync_order' ] );
		add_action( 'woocommerce_update_order', [ $this, 'sync_order' ] );
		add_filter( 'ep_post_sync_args_post_prepare_meta', [ $this, 'set_order_data' ], 10, 2 );
	}

	/**
	 * Unsetup order HPOS related hooks
	 */
	public function tear_down() {
		remove_action( 'woocommerce_new_order', [ $this, 'sync_order' ], 10, 2 );
		remove_action( 'woocommerce_refund_created', [ $this, 'sync_order' ], 10, 2 );
		remove_action( 'woocommerce_update_order', [ $this, 'sync_order' ], 10, 2 );
		remove_filter( 'ep_post_sync_args_post_prepare_meta', [ $this, 'set_order_data' ], 10, 2 );
	}

	/**
	 * Add orders to the sync queue
	 *
	 * @param int $order_id Order ID.
	 */
	public function sync_order( $order_id ) {
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
		if ( \Automattic\WooCommerce\Internal\DataStores\Orders\DataSynchronizer::PLACEHOLDER_ORDER_POST_TYPE !== $post_args['post_type'] ) {
			return $post_args;
		}

		/**
		 * Post Indexable instance.
		 *
		 * @var \ElasticPress\Indexable\Post\Post
		 */
		$post_indexable = Indexables::factory()->get( 'post' );
		$order          = wc_get_order( $post_id );
		$order_class    = get_class( $order );
		$post_order     = new $order_class();

		$post_args['post_type']     = $order->get_type();
		$post_args['post_status']   = $order->get_status( 'edit' );
		$post_args['post_parent']   = $order->get_changes()['parent_id'] ?? $order->get_data()['parent_id'] ?? 0;
		$post_args['post_date']     = gmdate( 'Y-m-d H:i:s', $order->get_date_created( 'edit' )->getOffsetTimestamp() );
		$post_args['post_date_gmt'] = gmdate( 'Y-m-d H:i:s', $order->get_date_created( 'edit' )->getTimestamp() );
		$post_args['edit_date']     = true;
		$post_args['post_excerpt']  = method_exists( $order, 'get_customer_note' ) ? $order->get_customer_note() : '';

		$post_order = new $order_class();
		$post_order->set_id( $order->get_id() );
		$post_order->set_props( $order->get_data() );

		error_log( var_export( $post_order, true ) );

		add_filter( 'ep_prepare_meta_data', [ $this, 'prepare_meta_data' ], 10, 2 );
		$post_args['meta'] = $post_indexable->prepare_meta_types( $post_indexable->prepare_meta( $post_order ) );
		remove_filter( 'ep_prepare_meta_data', [ $this, 'prepare_meta_data' ] );

		return $post_args;
	}

	/**
	 * Format meta data
	 *
	 * @param array   $order_meta Meta data
	 * @param WP_Post $order_post Order object
	 * @return array
	 */
	public function prepare_meta_data( $order_meta, $order_post ) {
		$order = wc_get_order( $order_post->get_id() );

		if ( is_null( $order->get_meta() ) ) {
			return $order_meta;
		}

		foreach ( $order->get_meta_data() as $meta_data ) {
			$order_meta[ $meta_data->key ] = ( is_object( $meta_data->value ) && '__PHP_Incomplete_Class' === get_class( $meta_data->value ) )
				? maybe_serialize( $meta_data->value )
				: $meta_data->value;
		}

		return $order_meta;
	}
}
