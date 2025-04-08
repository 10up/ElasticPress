<?php
/**
 * Translation layer of a HPOS query to a regular WP_Query object
 *
 * @since 5.3.0
 * @package elasticpress
 */

namespace ElasticPress\Feature\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * OrdersHPOS Query class
 *
 * Handles the translation of HPOS queries to WP_Query objects.
 *
 * @since 5.3.0
 */
class OrdersHPOSQuery {
	/**
	 * Arguments for the query.
	 *
	 * @var array $args
	 */
	protected $args;

	/**
	 * Orders array.
	 *
	 * @var array $orders
	 */
	protected $orders = [];

	/**
	 * Constructor for the OrdersHPOS_Query class.
	 *
	 * @param array $args Arguments for the query.
	 */
	public function __construct( $args ) {
		$this->args   = $args;
		$this->orders = [];
	}

	/**
	 * Retrieves orders based on the provided arguments.
	 *
	 * @return array An array of orders.
	 */
	public function get_orders() {
		return $this->orders;
	}
}
