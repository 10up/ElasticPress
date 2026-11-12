<?php
/**
 * WooCommerce Orders HPOS Query Integration.
 *
 * @since 5.4.0
 * @package elasticpress
 */

namespace ElasticPress\Feature\WooCommerce;

use Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableQuery;
use Automattic\WooCommerce\Internal\Utilities\DatabaseUtil;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * OrdersHPOS Query Integration class.
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
	 * Constructor.
	 *
	 * @param \Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableQuery $hpos_query The HPOS query object.
	 */
	public function __construct( \Automattic\WooCommerce\Internal\DataStores\Orders\OrdersTableQuery $hpos_query ) {
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
		if ( empty( $this->wp_query_args ) ) {
			return null;
		}

		$wp_query = new \WP_Query( $this->wp_query_args );
		if ( empty( $wp_query->elasticsearch_success ) ) {
			return null;
		}

		$limit       = $this->wp_query_args['posts_per_page'];
		$found_posts = (int) $wp_query->found_posts;

		// Elasticsearch returns up to `ep_max_results_window` results, so WooCommerce runs unlimited queries with more.
		if ( -1 === $limit && $found_posts > count( $wp_query->posts ) + ( $this->wp_query_args['offset'] ?? 0 ) ) {
			return null;
		}

		// If the order exists in Elasticsearch but not in the database, the Order List table page will throw an error. This workaround ensures that the Order List table page works correctly.
		$class_names = \WC_Order_Factory::get_class_names_for_order_ids( $wp_query->posts );
		$order_ids   = array_values( array_intersect( $wp_query->posts, array_keys( array_filter( $class_names ) ) ) );

		// Don't count the orders that are missing from the database.
		$found_posts  -= count( $wp_query->posts ) - count( $order_ids );
		$max_num_pages = $limit > 0 ? (int) ceil( $found_posts / $limit ) : (int) $wp_query->max_num_pages;

		return [
			$order_ids,
			$found_posts,
			$max_num_pages,
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

		// Like WooCommerce, an offset (even 0) takes precedence over the page, but only when a valid limit is set.
		if ( $this->hpos_query->arg_isset( 'offset' ) && is_numeric( $this->hpos_query->get( 'limit' ) ) && (int) $this->hpos_query->get( 'limit' ) >= -1 ) {
			$args['offset'] = absint( $this->hpos_query->get( 'offset' ) );
			$args['paged']  = 1;
		}

		// Handle HPOS meta_query.
		$hpos_meta_query = $this->hpos_query->get( 'meta_query' );
		if ( ! empty( $hpos_meta_query ) && is_array( $hpos_meta_query ) ) {
			$args['meta_query'] = [ $hpos_meta_query ];
		}

		// Order field arguments, like `billing_email` or `customer`, are matched like field_query clauses.
		$field_query = $this->get_order_fields_field_query();

		$hpos_field_query = $this->hpos_query->get( 'field_query' );
		if ( ! empty( $hpos_field_query ) && is_array( $hpos_field_query ) ) {
			$field_query[] = $hpos_field_query;
		}

		// Handle date_query and field_query. They are added to the Elasticsearch filters by OrdersHPOS::add_hpos_filters().
		$hpos_filters = [
			'date_query'  => ( new OrdersHPOSDateQuery( $this->hpos_query ) )->get_es_filter(),
			'field_query' => ( new OrdersHPOSFieldQuery( $this->hpos_query, $field_query ) )->get_es_filter(),
		];

		if ( in_array( null, $hpos_filters, true ) ) {
			// The query cannot be translated, so WooCommerce runs it.
			return [];
		}

		$hpos_filters = array_filter( $hpos_filters );
		if ( ! empty( $hpos_filters ) ) {
			$args['ep_hpos_filters'] = $hpos_filters;
		}

		/**
		 * Filter translated WP_Query args for HPOS queries.
		 *
		 * @hook ep_woocommerce_hpos_query_args
		 * @since 5.4.0
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
		return $this->hpos_query->get( 'type' );
	}

	/**
	 * Gets the post status for the query.
	 *
	 * @return string|array Post status(es).
	 */
	protected function get_post_status() {
		$status = $this->hpos_query->get( 'status' );

		// WooCommerce turns the `all` status into an empty list, meaning no status filter.
		if ( empty( $status ) ) {
			return array_values( get_post_stati() );
		}

		return $status;
	}

	/**
	 * Gets the limit for the query.
	 *
	 * @return int Limit number.
	 */
	protected function get_limit(): int {
		$limit = $this->hpos_query->get( 'limit' );

		// Like WooCommerce, return all orders if the limit is missing or invalid.
		return is_numeric( $limit ) && (int) $limit >= -1 ? (int) $limit : -1;
	}

	/**
	 * Gets the page number for the query.
	 *
	 * @return int Page number.
	 */
	protected function get_page(): int {
		return max( 1, absint( $this->hpos_query->get( 'page' ) ) );
	}

	/**
	 * Gets the orderby parameter for the query.
	 *
	 * @return string|array Orderby value.
	 */
	protected function get_orderby() {
		$orderby = $this->hpos_query->get( 'orderby' );

		$mapping = [
			'id'            => 'ID',
			'date_created'  => 'date',
			'date_modified' => 'modified',
			'parent'        => 'post_parent',
			'total'         => 'meta._order_total.double',
			'order_total'   => 'meta._order_total.double',
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
		$order = strtoupper( (string) $this->hpos_query->get( 'order' ) );

		return in_array( $order, [ 'ASC', 'DESC' ], true ) ? $order : 'DESC';
	}

	/**
	 * Gets search fields for the query.
	 *
	 * @return array Search fields configuration.
	 */
	protected function get_search_fields(): array {
		$search_filter = $this->hpos_query->get( 'search_filter' ) ?? 'all';

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
				return [
					'meta' => [
						'_billing_address_index',
						'_shipping_address_index',
					],
				];

			case 'products':
				// Search in order items (product names).
				return [
					'meta' => [ '_items' ],
				];

			case 'all':
			default:
				$fields = [];

				$search = $this->hpos_query->get( 's' );
				if ( ! empty( $search ) && ctype_digit( $search ) ) {
					$fields[] = 'ID';
				}

				// Match the default shop order search fields used in admin.
				$fields['meta'] = array_map(
					'wc_clean',
					/** This filter is documented in includes/classes/Feature/WooCommerce/Orders.php */
					apply_filters(
						'shop_order_search_fields',
						[
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
							'_billing_address_index',
							'_shipping_address_index',
						]
					)
				);

				return $fields;
		}
	}

	/**
	 * Converts the order field arguments, like `billing_email` or `customer`, to field_query clauses.
	 *
	 * Mirrors how OrdersTableQuery turns them into SQL, so they are compared the way MySQL does, e.g. strings are
	 * case-insensitive.
	 *
	 * @return array Field query clauses.
	 */
	protected function get_order_fields_field_query(): array {
		$fields = [
			'orders'           => [ 'id', 'currency', 'tax_amount', 'customer_id', 'billing_email', 'parent_order_id', 'payment_method', 'payment_method_title', 'transaction_id', 'ip_address', 'user_agent' ],
			'operational_data' => [ 'created_via', 'woocommerce_version', 'prices_include_tax', 'order_key', 'discount_total_amount', 'discount_tax_amount', 'shipping_total_amount', 'shipping_tax_amount' ],
		];

		$clauses = [];
		foreach ( $fields as $mapping_id => $columns ) {
			foreach ( $columns as $column ) {
				if ( $this->hpos_query->arg_isset( $column ) ) {
					$clauses[] = $this->get_field_clause( "{$mapping_id}.{$column}", '=', $this->hpos_query->get( $column ) );
				}
			}
		}

		$exclusions = [
			'exclude'        => 'orders.id',
			'parent_exclude' => 'orders.parent_order_id',
		];

		foreach ( $exclusions as $arg => $field ) {
			if ( $this->hpos_query->arg_isset( $arg ) ) {
				$clauses[] = $this->get_field_clause( $field, '!=', $this->hpos_query->get( $arg ) );
			}
		}

		foreach ( [ 'billing', 'shipping' ] as $address_type ) {
			foreach ( [ 'first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country', 'phone' ] as $column ) {
				if ( $this->hpos_query->arg_isset( "{$address_type}_{$column}" ) ) {
					$clauses[] = $this->get_field_clause( "{$address_type}_address.{$column}", '=', $this->hpos_query->get( "{$address_type}_{$column}" ) );
				}
			}
		}

		// An empty customer_note matches orders without a note.
		if ( null !== $this->hpos_query->get( 'customer_note' ) ) {
			$clauses[] = $this->get_field_clause( 'orders.customer_note', '=', $this->hpos_query->get( 'customer_note' ) );
		}

		if ( $this->hpos_query->arg_isset( 'customer' ) ) {
			$clauses[] = $this->get_customer_field_query( $this->hpos_query->get( 'customer' ) );
		}

		if ( $this->hpos_query->arg_isset( 'total_amount' ) ) {
			$clauses[] = $this->get_total_field_query( $this->hpos_query->get( 'total_amount' ) );
		}

		return array_values( array_filter( $clauses ) );
	}

	/**
	 * Converts the `customer` argument to a field_query, like OrdersTableQuery::generate_customer_query().
	 *
	 * Numbers are customer IDs and strings are emails. Nested arrays use the AND relation.
	 *
	 * @param mixed  $values   Customer IDs and emails.
	 * @param string $relation Relation between the values.
	 * @return array Field query.
	 */
	protected function get_customer_field_query( $values, string $relation = 'OR' ): array {
		$ids     = [];
		$emails  = [];
		$clauses = [];

		foreach ( (array) $values as $value ) {
			if ( is_array( $value ) ) {
				$clauses[] = $this->get_customer_field_query( $value, 'AND' );
			} elseif ( is_numeric( $value ) ) {
				$ids[] = absint( $value );
			} elseif ( is_string( $value ) && is_email( $value ) ) {
				$emails[] = sanitize_email( $value );
			} else {
				// WooCommerce matches no orders for invalid values.
				$clauses[] = $this->get_field_clause( 'orders.id', '=', 0 );
			}
		}

		if ( ! empty( $ids ) ) {
			$clauses[] = $this->get_field_clause( 'orders.customer_id', '=', $ids );
		}

		if ( ! empty( $emails ) ) {
			$clauses[] = $this->get_field_clause( 'orders.billing_email', '=', $emails );
		}

		$clauses = array_filter( $clauses );

		return empty( $clauses ) ? [] : array_merge( [ 'relation' => $relation ], $clauses );
	}

	/**
	 * Converts the `total` argument to a field_query clause, like OrdersTableQuery::generate_total_query().
	 *
	 * @param mixed $total Total, or an array with the `value` and `operator`.
	 * @return array Field query clause, empty if WooCommerce ignores the argument.
	 */
	protected function get_total_field_query( $total ): array {
		if ( is_numeric( $total ) ) {
			$total = [ 'value' => $total ];
		}

		$total = (array) $total;
		if ( ! isset( $total['value'] ) ) {
			return [];
		}

		$operator = $total['operator'] ?? '=';
		$value    = $total['value'];
		$format   = fn( $value ) => wc_format_decimal( $value, wc_get_price_decimals() );

		if ( in_array( $operator, [ 'BETWEEN', 'NOT BETWEEN' ], true ) ) {
			if ( ! is_array( $value ) || count( $value ) !== 2 ) {
				return [];
			}

			return $this->get_field_clause( 'orders.total_amount', $operator, [ $format( $value[0] ), $format( $value[1] ) ] );
		}

		if ( ! in_array( $operator, [ '=', '!=', '>', '>=', '<', '<=' ], true ) || ! is_numeric( $value ) ) {
			return [];
		}

		return $this->get_field_clause( 'orders.total_amount', $operator, $format( $value ) );
	}

	/**
	 * Builds a field_query clause, formatting the value for its column like OrdersTableQuery::where() does.
	 *
	 * For example, `yes` becomes true for boolean columns.
	 *
	 * @param string $field   Field, as `{mapping}.{column}`.
	 * @param string $compare Comparison operator.
	 * @param mixed  $value   Value, or values for `IN` and `BETWEEN`.
	 * @return array Field query clause.
	 */
	protected function get_field_clause( string $field, string $compare, $value ): array {
		$type    = $this->hpos_query->get_field_mapping_info( $field )['column_type'];
		$db_util = wc_get_container()->get( DatabaseUtil::class );
		$format  = fn( $value ) => $db_util->format_object_value_for_db( $value, $type );

		// `=` and `!=` are shorthands for IN and NOT IN with array values.
		if ( is_array( $value ) && in_array( $compare, [ '=', '!=' ], true ) ) {
			$compare = '=' === $compare ? 'IN' : 'NOT IN';
		}

		return [
			'field'   => $field,
			'value'   => is_array( $value ) ? array_map( $format, $value ) : $format( $value ),
			'compare' => $compare,
		];
	}
}
