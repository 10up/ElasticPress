<?php
/**
 * WooCommerce Orders HPOS date_query integration.
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
 * Translates the HPOS `date_query` argument to an Elasticsearch filter.
 *
 * @since 5.4.0
 */
class OrdersHPOSDateQuery {

	/**
	 * Indexed fields of the HPOS date columns, and whether they hold a datetime or a Unix timestamp.
	 *
	 * @var array
	 */
	const FIELDS = [
		'date_created_gmt'   => [ 'post_date_gmt', 'datetime' ],
		'date_updated_gmt'   => [ 'post_modified_gmt', 'datetime' ],
		'date_paid_gmt'      => [ 'meta._date_paid.long', 'timestamp' ],
		'date_completed_gmt' => [ 'meta._date_completed.long', 'timestamp' ],
	];

	/**
	 * Date units, from largest to smallest, and the keys WP_Date_Query reads them from.
	 *
	 * @var array
	 */
	const DATE_UNITS = [
		'year'   => [ 'year' ],
		'month'  => [ 'month', 'monthnum' ],
		'day'    => [ 'day' ],
		'hour'   => [ 'hour' ],
		'minute' => [ 'minute' ],
		'second' => [ 'second' ],
	];

	/**
	 * Date keys that cannot be translated to a date range.
	 *
	 * @var array
	 */
	const UNSUPPORTED_KEYS = [ 'week', 'w', 'dayofyear', 'dayofweek', 'dayofweek_iso' ];

	/**
	 * The WooCommerce OrdersTableQuery object.
	 *
	 * @var OrdersTableQuery
	 */
	protected $query;

	/**
	 * WP_Date_Query instance, used for its parsing helpers.
	 *
	 * @var \WP_Date_Query
	 */
	protected $wp_date_query;

	/**
	 * Constructor.
	 *
	 * @param OrdersTableQuery $query The HPOS query object.
	 */
	public function __construct( OrdersTableQuery $query ) {
		$this->query         = $query;
		$this->wp_date_query = new \WP_Date_Query( [] );
	}

	/**
	 * Translates the date_query to an Elasticsearch filter.
	 *
	 * @return array|null Elasticsearch filter, empty if there is nothing to filter, null if the date_query cannot be translated.
	 */
	public function get_es_filter(): ?array {
		$date_query = $this->query->get( 'date_query' );
		if ( empty( $date_query ) || ! is_array( $date_query ) ) {
			return [];
		}

		// Like WP_Date_Query, support a single clause passed without a wrapping array.
		if ( ! isset( $date_query[0] ) ) {
			$date_query = [ $date_query ];
		}

		// WooCommerce runs WP_Date_Query with date_created_gmt as the default column.
		if ( empty( $date_query['column'] ) ) {
			$date_query['column'] = 'date_created_gmt';
		}

		// Clauses with an invalid compare value use the top-level one.
		$this->wp_date_query->compare = $this->wp_date_query->get_compare( $date_query );

		try {
			return $this->get_es_filter_for_query( $date_query );
		} catch ( \InvalidArgumentException $e ) {
			return null;
		}
	}

	/**
	 * Recursively translates a date_query (or a nested group) to an Elasticsearch filter.
	 *
	 * @param array $query        Date query.
	 * @param array $parent_query Parent query, whose column and compare values are inherited.
	 * @return array Elasticsearch filter, empty if all clauses are skipped.
	 * @throws \InvalidArgumentException When a clause cannot be translated.
	 */
	protected function get_es_filter_for_query( array $query, array $parent_query = [] ): array {
		foreach ( [ 'column', 'compare' ] as $key ) {
			if ( ! isset( $query[ $key ] ) && isset( $parent_query[ $key ] ) ) {
				$query[ $key ] = $parent_query[ $key ];
			}
		}

		$filters = [];
		foreach ( $query as $key => $clause ) {
			if ( 'relation' === $key || ! is_array( $clause ) ) {
				continue;
			}

			$filter = $this->is_first_order_clause( $clause )
				? $this->get_es_filter_for_clause( $clause + array_intersect_key( $query, array_flip( [ 'column', 'compare' ] ) ) )
				: $this->get_es_filter_for_query( $clause, $query );

			if ( ! empty( $filter ) ) {
				$filters[] = $filter;
			}
		}

		if ( count( $filters ) <= 1 ) {
			return $filters[0] ?? [];
		}

		$relation = 'OR' === strtoupper( $query['relation'] ?? '' ) ? 'should' : 'must';

		return [
			'bool' => [
				$relation => $filters,
			],
		];
	}

	/**
	 * Translates a single date_query clause to an Elasticsearch filter.
	 *
	 * @param array $clause Date query clause.
	 * @return array Elasticsearch filter, empty if the clause is skipped.
	 * @throws \InvalidArgumentException When the clause cannot be translated.
	 */
	protected function get_es_filter_for_clause( array $clause ): array {
		foreach ( self::UNSUPPORTED_KEYS as $key ) {
			if ( isset( $clause[ $key ] ) ) {
				throw new \InvalidArgumentException();
			}
		}

		// WooCommerce prefixes the columns with their table name.
		$column = substr( strrchr( '.' . $clause['column'], '.' ), 1 );
		if ( ! isset( self::FIELDS[ $column ] ) ) {
			throw new \InvalidArgumentException();
		}

		list( $path, $type ) = self::FIELDS[ $column ];

		$inclusive = ! empty( $clause['inclusive'] );
		$ranges    = [];

		if ( ! empty( $clause['after'] ) ) {
			$ranges[] = [ $inclusive ? 'gte' : 'gt' => $this->wp_date_query->build_mysql_datetime( $clause['after'], ! $inclusive ) ];
		}

		if ( ! empty( $clause['before'] ) ) {
			$ranges[] = [ $inclusive ? 'lte' : 'lt' => $this->wp_date_query->build_mysql_datetime( $clause['before'], $inclusive ) ];
		}

		$date_range = $this->get_date_units_range( $clause );
		if ( null !== $date_range ) {
			$ranges[] = $date_range;
		}

		$filters = array_map(
			function ( $range ) use ( $path, $type ) {
				if ( 'timestamp' === $type ) {
					$range = array_map( fn( $datetime ) => strtotime( "{$datetime} UTC" ), $range );
				}

				return [
					'range' => [
						$path => $range,
					],
				];
			},
			$ranges
		);

		if ( count( $filters ) <= 1 ) {
			return $filters[0] ?? [];
		}

		return [
			'bool' => [
				'must' => $filters,
			],
		];
	}

	/**
	 * Converts the date units of a clause, like `year` and `month`, to a datetime range.
	 *
	 * @param array $clause Date query clause.
	 * @return array|null Datetime range, or null if the clause has no date units.
	 * @throws \InvalidArgumentException When the units cannot be translated.
	 */
	protected function get_date_units_range( array $clause ): ?array {
		$units = [];
		foreach ( self::DATE_UNITS as $unit => $keys ) {
			foreach ( $keys as $key ) {
				// WP_Date_Query ignores non-numeric year, month and day values.
				if ( isset( $clause[ $key ] ) && ( is_numeric( $clause[ $key ] ) || in_array( $unit, [ 'hour', 'minute', 'second' ], true ) ) ) {
					$units[ $unit ] = $clause[ $key ];
					break;
				}
			}
		}

		if ( empty( $units ) ) {
			return null;
		}

		if (
			'=' !== $this->wp_date_query->get_compare( $clause )
			|| array_keys( $units ) !== array_slice( array_keys( self::DATE_UNITS ), 0, count( $units ) )
			|| array_filter( $units, 'is_numeric' ) !== $units
		) {
			throw new \InvalidArgumentException();
		}

		$last_unit = array_key_last( $units );
		$units     = array_map( 'intval', $units ) + [
			'month'  => 1,
			'day'    => 1,
			'hour'   => 0,
			'minute' => 0,
			'second' => 0,
		];

		// Invalid dates, like February 30, are left to WooCommerce.
		$start = gmmktime( $units['hour'], $units['minute'], $units['second'], $units['month'], $units['day'], $units['year'] );
		if ( gmdate( 'Y-n-j G:i:s', $start ) !== vsprintf( '%d-%d-%d %d:%02d:%02d', array_values( $units ) ) ) {
			throw new \InvalidArgumentException();
		}

		++$units[ $last_unit ];
		$end = gmmktime( $units['hour'], $units['minute'], $units['second'], $units['month'], $units['day'], $units['year'] ) - 1;

		return [
			'gte' => gmdate( 'Y-m-d H:i:s', $start ),
			'lte' => gmdate( 'Y-m-d H:i:s', $end ),
		];
	}

	/**
	 * Checks whether a date_query array is a clause rather than a group of clauses.
	 *
	 * @param array $query Date query array.
	 * @return bool
	 */
	protected function is_first_order_clause( array $query ): bool {
		return ! empty( array_intersect( $this->wp_date_query->time_keys, array_keys( $query ) ) );
	}
}
