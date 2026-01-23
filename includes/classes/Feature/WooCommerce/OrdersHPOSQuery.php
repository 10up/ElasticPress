<?php
/**
 * Translation layer of a HPOS query to a regular WP_Query object
 *
 * @since 5.4.0
 * @package elasticpress
 */

namespace ElasticPress\Feature\WooCommerce;

use Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableQuery;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * OrdersHPOS Query class
 *
 * Handles the translation of HPOS queries to WP_Query objects.
 *
 * @since 5.4.0
 */
class OrdersHPOSQuery {

	/**
	 * The WooCommerce OrdersTableQuery object.
	 *
	 * @var OrdersTableQuery|null
	 */
	protected $hpos_query;

	/**
	 * Original query arguments from HPOS.
	 *
	 * @var array
	 */
	protected $query_args = [];

	/**
	 * Translated WP_Query compatible arguments.
	 *
	 * @var array
	 */
	protected $wp_query_args = [];


	/**
	 * Constructor for the OrdersHPOS_Query class.
	 *
	 * @param OrdersTableQuery $hpos_query The WooCommerce OrdersTableQuery object.
	 */
	public function __construct( OrdersTableQuery $hpos_query ) {
		$this->hpos_query    = $hpos_query;
		$this->query_args    = $hpos_query->get_query_args();
		$this->wp_query_args = $this->translate_args();
	}

	/**
	 * Executes the Elasticsearch query and returns results.
	 *
	 * @return array|null Array with [orders, found_orders, max_num_pages] or null if ES query failed.
	 */
	public function query() {
		$wp_query = new \WP_Query( $this->wp_query_args );
		if ( isset( $wp_query->elasticsearch_success ) && false === $wp_query->elasticsearch_success ) {
			return null;
		}

		return [
			$wp_query->posts,
			(int) $wp_query->found_posts,
			(int) $wp_query->max_num_pages,
		];
	}

	/**
	 * Translates HPOS query arguments to WP_Query compatible arguments.
	 *
	 * @return array WP_Query compatible arguments.
	 */
	protected function translate_args(): array {
		$args = [
			'ep_integrate'   => true,
			'post_type'      => $this->get_post_type(),
			'post_status'    => $this->get_post_status(),
			'posts_per_page' => $this->get_limit(),
			'paged'          => $this->get_page(),
			'orderby'        => $this->get_orderby(),
			'order'          => $this->get_order(),
			'fields'         => 'ids', // HPOS always returns IDs
		];

		// Handle search.
		$search = $this->hpos_query->get( 's' );
		if ( ! empty( $search ) ) {
			$args['s']             = sanitize_text_field( $search );
			$args['search_fields'] = $this->get_search_fields();
		}

		// Handle date query.
		$date_query = $this->build_date_query();
		if ( ! empty( $date_query ) ) {
			$args['date_query'] = $date_query;
		}

		// Handle customer filtering.
		if ( $this->hpos_query->arg_isset( 'customer' ) ) {
			$customer_meta_query = $this->generate_customer_query();
			if ( ! empty( $customer_meta_query ) ) {
				$args['meta_query'] = isset( $args['meta_query'] )
					? array_merge( $args['meta_query'], [ $customer_meta_query ] )
					: [ $customer_meta_query ];
			}
		}

		// Handle created_via filtering.
		if ( $this->hpos_query->arg_isset( 'created_via' ) ) {
			$created_via_clause = $this->build_created_via_query();
			if ( ! empty( $created_via_clause ) ) {
				$args['meta_query'] = isset( $args['meta_query'] )
					? array_merge( $args['meta_query'], [ $created_via_clause ] )
					: [ $created_via_clause ];
			}
		}

		/**
		 * Filter translated WP_Query args for HPOS queries.
		 *
		 * @hook ep_woocommerce_hpos_query_args
		 * @since 5.3.0
		 * @param {array} $args        WP_Query compatible arguments.
		 * @param {array} $query_args  Original HPOS query arguments.
		 * @return {array} New args.
		 */
		return apply_filters( 'ep_woocommerce_hpos_query_args', $args, $this->query_args );
	}

	/**
	 * Gets the post type for the query.
	 *
	 * @return string|array Post type(s).
	 */
	protected function get_post_type() {
		$type = $this->hpos_query->get( 'type' );

		if ( empty( $type ) || 'any' === $type ) {
			return [ 'shop_order', 'shop_order_refund' ];
		}

		return $type;
	}

	/**
	 * Gets the post status for the query.
	 *
	 * @return string|array Post status(es).
	 */
	protected function get_post_status() {
		$status = $this->hpos_query->get( 'status' );

		if ( empty( $status ) || 'any' === $status ) {
			$status = array_keys( wc_get_order_statuses() );
		}

		return $status;
	}

	/**
	 * Gets the limit (posts per page) for the query.
	 *
	 * @return int Posts per page.
	 */
	protected function get_limit(): int {
		return $this->hpos_query->get( 'limit' );
	}

	/**
	 * Gets the page number for the query.
	 *
	 * @return int Page number.
	 */
	protected function get_page(): int {
		return $this->hpos_query->get( 'page' );
	}

	/**
	 * Gets the orderby parameter for the query.
	 *
	 * @return string|array Orderby value.
	 */
	protected function get_orderby() {
		$orderby = $this->hpos_query->get( 'orderby' );

		// @todo: check if this is correct
		$mapping = [
			'ID'            => 'ID',
			'id'            => 'ID',
			'date'          => 'date',
			'date_created'  => 'date',
			'modified'      => 'modified',
			'date_modified' => 'modified',
			'parent'        => 'parent',
			'total'         => 'meta_value_num',
			'order_total'   => 'meta_value_num',
			'include'       => 'post__in',
			'post__in'      => 'post__in',
		];

		if ( is_string( $orderby ) && isset( $mapping[ $orderby ] ) ) {
			return $mapping[ $orderby ];
		}

		if ( is_array( $orderby ) ) {
			$translated = [];
			foreach ( $orderby as $key => $order ) {
				$mapped_key                = $mapping[ $key ] ?? $key;
				$translated[ $mapped_key ] = $order;
			}
			return $translated;
		}

		return $orderby;
	}

	/**
	 * Gets the order direction for the query.
	 *
	 * @return string Order direction (ASC or DESC).
	 */
	protected function get_order(): string {
		return $this->hpos_query->get( 'order' );
	}

	/**
	 * Gets search fields for the query.
	 *
	 * Translates HPOS search_filter parameter to ElasticPress search_fields.
	 * This mirrors WooCommerce's OrdersTableSearchQuery::generate_where_for_search_filter() logic.
	 *
	 * @return array Search fields configuration.
	 */
	protected function get_search_fields(): array {
		$search_filter = $this->hpos_query->get( 'search_filter' );

		// Handle specific search filters.
		$search_fields = $this->get_fields_for_search_filter( $search_filter );

		/**
		 * Filter search fields for HPOS queries.
		 *
		 * @hook ep_woocommerce_hpos_search_fields
		 * @since 5.4.0
		 * @param {array}  $search_fields Search fields configuration.
		 * @param {string} $search_filter The HPOS search filter.
		 * @param {array}  $query_args    Original HPOS query arguments.
		 * @return {array} New search fields.
		 */
		return apply_filters( 'ep_woocommerce_hpos_search_fields', $search_fields, $search_filter, $this->query_args );
	}

	/**
	 * Gets search fields based on HPOS search filter.
	 *
	 * Mirrors WooCommerce's OrdersTableSearchQuery search filter logic.
	 *
	 * @param string $search_filter The search filter (order_id, transaction_id, customer_email, customers, products, all).
	 * @return array Search fields for ElasticPress.
	 */
	protected function get_fields_for_search_filter( string $search_filter ): array {

		switch ( $search_filter ) {
			case 'order_id':
				return [ 'ID' ];

			case 'transaction_id':
				return [
					'meta' => [ '_transaction_id' ],
				];

			case 'customer_email':
				return [
					'meta' => [ '_billing_email' ],
				];

			case 'customers':
				// Search in all customer/address meta fields. WooCommerce does search from _billing_address_index and _shipping_address_index fields.
				return [
					'meta' => [
						'_billing_first_name',
						'_billing_last_name',
						'_billing_company',
						'_billing_address_1',
						'_billing_address_2',
						'_billing_city',
						'_billing_state',
						'_billing_postcode',
						'_billing_country',
						'_billing_email',
						'_billing_phone',
						'_shipping_first_name',
						'_shipping_last_name',
						'_shipping_company',
						'_shipping_address_1',
						'_shipping_address_2',
						'_shipping_city',
						'_shipping_state',
						'_shipping_postcode',
						'_shipping_country',
						'_shipping_phone',
					],
				];

			case 'products':
				// Search in order items (product names).
				return [
					'meta' => [ '_items' ],
				];

			case 'all':
			default:
				// Search all fields - combine all filters.
				$fields = [ 'post_title', 'post_content', 'post_excerpt' ];

				// Include all searchable meta fields.
				$fields['meta'] = [
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
				];

				return $fields;
		}
	}

	/**
	 * Builds date query from HPOS query arguments.
	 *
	 * @return array Date query array.
	 */
	protected function build_date_query(): array {
		$date_query = [];

		// Handle date shorthand parameters.
		$date_params = [
			'date_created'  => 'post_date',
			'date_modified' => 'post_modified',
			'date_updated'  => 'post_modified',
		];

		foreach ( $date_params as $hpos_key => $wp_column ) {
			$value = $this->query_args[ $hpos_key ] ?? null;
			if ( empty( $value ) ) {
				continue;
			}

			$parsed = $this->parse_date_shorthand( $value );
			if ( ! empty( $parsed ) ) {
				$parsed['column'] = $wp_column;
				$date_query[]     = $parsed;
			}
		}

		// Set relation if multiple date queries.
		if ( count( $date_query ) > 1 && ! isset( $date_query['relation'] ) ) {
			$date_query['relation'] = 'AND';
		}

		return $date_query;
	}

	/**
	 * Parses date shorthand format (e.g. "2024-01-01...2024-12-31").
	 *
	 * @param mixed $value The date value.
	 * @return array Parsed date query clause.
	 */
	protected function parse_date_shorthand( $value ): array {
		if ( ! is_string( $value ) || ! str_contains( $value, '...' ) ) {
			return [];
		}

		$dates = explode( '...', $value );
		return [
			'after'     => trim( $dates[0] ),
			'before'    => trim( $dates[1] ),
			'inclusive' => true,
		];
	}

	/**
	 * Translates WooCommerce HPOS customer query to ElasticPress meta_query.
	 *
	 * @return array Meta query array for WP_Query, empty if no customer filter.
	 */
	protected function generate_customer_query(): array {
		$customer = $this->hpos_query->get( 'customer' );

		if ( empty( $customer ) ) {
			return [];
		}

		$meta_query = [];

		$customer_clause = $this->build_customer_meta_clause( $customer );
		if ( ! empty( $customer_clause ) ) {
			$meta_query = $customer_clause;
		}

		return $meta_query;
	}

	/**
	 * Builds meta query clause for 'customer' parameter.
	 *
	 * Mirrors WooCommerce's generate_customer_query() logic:
	 * - Numeric values are treated as customer user IDs
	 * - String values are validated as emails
	 * - Arrays can contain mixed IDs and emails (OR relation)
	 * - Nested arrays use AND relation
	 *
	 * @param mixed  $values   Customer value(s) - ID(s), email(s), or mixed.
	 * @param string $relation Relation for combining clauses ('OR' or 'AND').
	 * @return array Meta query clause.
	 */
	protected function build_customer_meta_clause( $values, string $relation = 'OR' ): array {
		$values = is_array( $values ) ? $values : [ $values ];

		$ids    = [];
		$emails = [];
		$nested = [];

		foreach ( $values as $value ) {
			if ( is_array( $value ) ) {
				// Nested array - recursive call with AND relation (mirrors WooCommerce).
				$nested_clause = $this->build_customer_meta_clause( $value, 'AND' );
				if ( ! empty( $nested_clause ) ) {
					$nested[] = $nested_clause;
				}
			} elseif ( is_numeric( $value ) ) {
				$ids[] = absint( $value );
			} elseif ( is_string( $value ) && is_email( $value ) ) {
				$emails[] = sanitize_email( $value );
			}
		}

		$clauses = [];

		// Build _customer_user clause for IDs.
		if ( ! empty( $ids ) ) {
			$clauses[] = [
				'key'     => '_customer_user',
				'value'   => count( $ids ) === 1 ? $ids[0] : $ids,
				'compare' => count( $ids ) === 1 ? '=' : 'IN',
				'type'    => 'NUMERIC',
			];
		}

		// Build _billing_email clause for emails.
		if ( ! empty( $emails ) ) {
			$clauses[] = [
				'key'     => '_billing_email',
				'value'   => count( $emails ) === 1 ? $emails[0] : $emails,
				'compare' => count( $emails ) === 1 ? '=' : 'IN',
			];
		}

		// Add nested clauses.
		foreach ( $nested as $nested_clause ) {
			$clauses[] = $nested_clause;
		}

		// Return based on number of clauses.
		if ( empty( $clauses ) ) {
			return [];
		}

		if ( count( $clauses ) === 1 ) {
			return $clauses[0];
		}

		// Multiple clauses - add relation.
		return array_merge( [ 'relation' => $relation ], $clauses );
	}

	/**
	 * Builds meta query for 'created_via' parameter.
	 *
	 * Translates HPOS created_via to _created_via meta query.
	 *
	 * @return array Meta query clause.
	 */
	protected function build_created_via_query(): array {
		$created_via = $this->hpos_query->get( 'created_via' );

		if ( empty( $created_via ) ) {
			return [];
		}

		// Handle array of values.
		if ( is_array( $created_via ) ) {
			return [
				'key'     => '_created_via',
				'value'   => array_map( 'sanitize_text_field', $created_via ),
				'compare' => 'IN',
			];
		}

		// Handle single value.
		return [
			'key'     => '_created_via',
			'value'   => sanitize_text_field( $created_via ),
			'compare' => '=',
		];
	}
}
