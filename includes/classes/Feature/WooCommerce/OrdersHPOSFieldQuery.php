<?php
/**
 * WooCommerce Orders HPOS field_query integration.
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
 * Translates the HPOS `field_query` argument to an Elasticsearch filter.
 *
 * @since 5.4.0
 */
class OrdersHPOSFieldQuery {

	/**
	 * Valid field_query `compare` values, the same as WooCommerce's.
	 *
	 * @var array
	 */
	const VALID_COMPARISON_OPERATORS = [ '=', '!=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'EXISTS', 'NOT EXISTS', 'RLIKE', 'REGEXP', 'NOT REGEXP', '>', '>=', '<', '<=', 'BETWEEN', 'NOT BETWEEN' ];

	/**
	 * Negative comparison operators and their positive counterparts.
	 *
	 * @var array
	 */
	const NEGATIVE_OPERATORS = [
		'!='          => '=',
		'NOT IN'      => 'IN',
		'NOT LIKE'    => 'LIKE',
		'NOT REGEXP'  => 'REGEXP',
		'NOT BETWEEN' => 'BETWEEN',
	];

	/**
	 * Order table columns indexed as post fields, keyed by column name.
	 *
	 * @var array
	 */
	const POST_FIELDS = [
		'id'               => [ 'ID', 'number' ],
		'parent_order_id'  => [ 'post_parent', 'number' ],
		'status'           => [ 'post_status', 'string' ],
		'type'             => [ 'post_type.raw', 'string' ],
		'date_created_gmt' => [ 'post_date_gmt', 'datetime' ],
		'date_updated_gmt' => [ 'post_modified_gmt', 'datetime' ],
	];

	/**
	 * Indexed address fields, keyed by address type.
	 *
	 * @var array
	 */
	const ADDRESS_FIELDS = [
		'billing'  => [ 'first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country', 'email', 'phone' ],
		'shipping' => [ 'first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country', 'phone' ],
	];

	/**
	 * Indexed meta keys of order properties, if they are not the property name prefixed with `_`.
	 *
	 * @var array
	 */
	const META_KEYS = [
		'cart_tax'       => '_order_tax',
		'currency'       => '_order_currency',
		'customer_id'    => '_customer_user',
		'discount_tax'   => '_cart_discount_tax',
		'discount_total' => '_cart_discount',
		'shipping_tax'   => '_order_shipping_tax',
		'shipping_total' => '_order_shipping',
		'total'          => '_order_total',
		'version'        => '_order_version',
	];

	/**
	 * The WooCommerce OrdersTableQuery object.
	 *
	 * @var OrdersTableQuery
	 */
	protected $query;

	/**
	 * The field query to translate.
	 *
	 * @var array
	 */
	protected $field_query;

	/**
	 * Whether the field query should produce no results, as WooCommerce does when a field is unknown.
	 *
	 * @var bool
	 */
	protected $force_no_results = false;

	/**
	 * Address types used by the field query, as keys.
	 *
	 * @var array
	 */
	protected $address_types = [];

	/**
	 * Constructor.
	 *
	 * @param OrdersTableQuery $query       The HPOS query object.
	 * @param array            $field_query The field query to translate.
	 */
	public function __construct( OrdersTableQuery $query, array $field_query ) {
		$this->query       = $query;
		$this->field_query = $field_query;
	}

	/**
	 * Translates the field_query to an Elasticsearch filter.
	 *
	 * @return array|null Elasticsearch filter, empty if there is nothing to filter, null if the field_query cannot be translated.
	 */
	public function get_es_filter(): ?array {
		$field_query = $this->field_query;
		if ( empty( $field_query ) ) {
			return [];
		}

		// Values of unexpected types, e.g. nested arrays, are left to WooCommerce too.
		try {
			$filter = $this->get_es_filter_for_query( $field_query );
		} catch ( \InvalidArgumentException | \TypeError $e ) {
			return null;
		}

		if ( $this->force_no_results ) {
			return $this->get_match_none_filter();
		}

		$filters = array_filter( [ $filter ] );

		// WooCommerce INNER JOINs the address tables, and an address is only stored if one of its fields is set.
		foreach ( array_keys( $this->address_types ) as $address_type ) {
			$filters[] = [
				'bool' => [
					'should' => array_map(
						fn( $field ) => $this->get_not_empty_filter( "meta._{$address_type}_{$field}.raw" ),
						self::ADDRESS_FIELDS[ $address_type ]
					),
				],
			];
		}

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
	 * Recursively translates a field_query (or a nested group) to an Elasticsearch filter.
	 *
	 * @param array $query Field query.
	 * @return array Elasticsearch filter, empty if all clauses are skipped.
	 */
	protected function get_es_filter_for_query( array $query ): array {
		$filters = [];

		foreach ( $query as $key => $clause ) {
			if ( 'relation' === $key || ! is_array( $clause ) ) {
				continue;
			}

			$filter = isset( $clause['field'] )
				? $this->get_es_filter_for_clause( $clause )
				: $this->get_es_filter_for_query( $clause );

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
	 * Translates a single field_query clause to an Elasticsearch filter.
	 *
	 * @param array $clause Field query clause.
	 * @return array Elasticsearch filter, empty if the clause is skipped.
	 * @throws \InvalidArgumentException When the clause cannot be translated.
	 */
	protected function get_es_filter_for_clause( array $clause ): array {
		$value = $clause['value'] ?? '';
		if ( [] === $value ) {
			return [];
		}

		$field_info = $this->query->get_field_mapping_info( $clause['field'] );
		if ( ! $field_info ) {
			$this->force_no_results = true;
			return [];
		}

		if ( in_array( $field_info['mapping_id'], [ 'billing_address', 'shipping_address' ], true ) ) {
			$this->address_types[ substr( $field_info['mapping_id'], 0, -8 ) ] = true;
		}

		// Sanitize `compare` and `value` the same way WooCommerce does.
		$compare = strtoupper( $clause['compare'] ?? '=' );
		$compare = in_array( $compare, self::VALID_COMPARISON_OPERATORS, true ) ? $compare : '=';
		$compare = 'RLIKE' === $compare ? 'REGEXP' : $compare;

		if ( '=' === $compare && is_array( $value ) ) {
			$compare = 'IN';
		}

		if ( in_array( $compare, [ 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN' ], true ) ) {
			$value = is_array( $value ) ? array_values( $value ) : preg_split( '/[,\s]+/', $value );
		} elseif ( is_string( $value ) ) {
			$value = trim( $value );
		}

		// EXISTS with a value is interpreted as '='.
		if ( 'EXISTS' === $compare && $value ) {
			$compare = '=';
		}

		$field = $this->get_field( $field_info, $clause['type'] ?? '' );

		if ( 'EXISTS' === $compare ) {
			return $field['not_null'];
		}

		if ( 'NOT EXISTS' === $compare ) {
			return $this->negate( $field['not_null'] );
		}

		if ( 'bool' === $field['type'] ) {
			return $this->get_bool_filter( $field['path'], $compare, (array) $value );
		}

		$is_negative = isset( self::NEGATIVE_OPERATORS[ $compare ] );
		$filter      = $this->get_compare_filter( $field, self::NEGATIVE_OPERATORS[ $compare ] ?? $compare, (array) $value );

		if ( $is_negative ) {
			$filter = $this->negate( $filter );
		}

		// As in SQL, NULL values never match.
		if ( ! $is_negative && ! $field['empty_is_null'] ) {
			return $filter;
		}

		return [
			'bool' => [
				'must' => [ $field['not_null'], $filter ],
			],
		];
	}

	/**
	 * Gets how an order field is indexed.
	 *
	 * @param array  $field_info Field info from OrdersTableQuery::get_field_mapping_info().
	 * @param string $cast       The clause `type`.
	 * @return array {
	 *     @type string $path          Indexed field path.
	 *     @type string $type          Value type.
	 *     @type string $raw_path      Case-sensitive keyword path of `string` and `keyword` values.
	 *     @type bool   $empty_is_null Whether WooCommerce stores empty values as NULL.
	 *     @type array  $not_null      Elasticsearch filter matching non-NULL values.
	 * }
	 * @throws \InvalidArgumentException When the field is not indexed or the cast cannot be translated.
	 */
	protected function get_field( array $field_info, string $cast ): array {
		// Columns without an order property, e.g. `shipping_address.email`, are not indexed.
		if ( empty( $field_info['field_name'] ) ) {
			throw new \InvalidArgumentException();
		}

		$meta_path = 'meta.' . ( self::META_KEYS[ $field_info['field_name'] ] ?? '_' . $field_info['field_name'] );

		if ( 'orders' === $field_info['mapping_id'] && isset( self::POST_FIELDS[ $field_info['column'] ] ) ) {
			list( $path, $type ) = self::POST_FIELDS[ $field_info['column'] ];
			$raw_path            = $path;
		} else {
			$column_types = [
				'int'     => [ "{$meta_path}.long", 'number' ],
				'decimal' => [ "{$meta_path}.double", 'number' ],
				// Paid and completed dates are indexed as Unix timestamps.
				'date'    => [ "{$meta_path}.long", 'timestamp' ],
				// Unset booleans are stored as 0 in HPOS and indexed as false.
				'bool'    => [ "{$meta_path}.boolean", 'bool' ],
			];

			list( $path, $type ) = $column_types[ $field_info['column_type'] ] ?? [ "{$meta_path}.value.sortable", 'string' ];
			$raw_path            = "{$meta_path}.raw";
		}

		// Empty address fields and billing emails are stored as NULL.
		$empty_is_null = in_array( $field_info['mapping_id'], [ 'billing_address', 'shipping_address' ], true )
			|| ( 'orders' === $field_info['mapping_id'] && 'billing_email' === $field_info['column'] );

		$field = [
			'path'          => $path,
			'type'          => $type,
			'raw_path'      => $raw_path,
			'empty_is_null' => $empty_is_null,
			'not_null'      => $empty_is_null ? $this->get_not_empty_filter( $raw_path ) : [ 'exists' => [ 'field' => $path ] ],
		];

		// Sanitize the cast the same way WooCommerce does. Invalid casts and CHAR compare the column as it is.
		$cast = strtoupper( $cast );
		if ( 'CHAR' === $cast || ! preg_match( '/^(?:BINARY|CHAR|DATE|DATETIME|SIGNED|UNSIGNED|TIME|NUMERIC(?:\(\d+(?:,\s?\d+)?\))?|DECIMAL(?:\(\d+(?:,\s?\d+)?\))?)$/', $cast ) ) {
			return $field;
		}

		/*
		 * Only casts with the same result in Elasticsearch are supported, e.g. MySQL casts strings to numbers by
		 * their leading digits and limits DECIMAL casts to their precision.
		 */
		$is_decimal = 'decimal' === $field_info['column_type'];

		if ( 'BINARY' === $cast && 'string' === $type ) {
			$field['type'] = 'keyword';
			$field['path'] = $raw_path;
			return $field;
		}

		// WooCommerce casts NUMERIC as SIGNED. With a precision, it is not valid in MySQL.
		if ( in_array( $cast, [ 'SIGNED', 'UNSIGNED', 'NUMERIC' ], true ) ) {
			if ( 'bool' === $type || ( 'number' === $type && ! $is_decimal ) ) {
				return $field;
			}

			// Decimals are rounded, but UNSIGNED turns negative ones, like refund totals, into huge numbers.
			if ( $is_decimal && 'UNSIGNED' !== $cast ) {
				$field['type'] = 'rounded';
				return $field;
			}
		}

		if ( 'DATE' === $cast && 'datetime' === $type ) {
			$field['type'] = 'day';
			return $field;
		}

		if ( 'DATETIME' === $cast && in_array( $type, [ 'datetime', 'timestamp' ], true ) ) {
			return $field;
		}

		throw new \InvalidArgumentException();
	}

	/**
	 * Builds the filter for a positive comparison.
	 *
	 * @param array  $field   Field from get_field().
	 * @param string $compare Positive comparison operator.
	 * @param array  $values  Clause values.
	 * @return array Elasticsearch filter.
	 * @throws \InvalidArgumentException When the comparison cannot be translated.
	 */
	protected function get_compare_filter( array $field, string $compare, array $values ): array {
		$path = $field['path'];
		$type = $field['type'];

		// Regular expressions are left to WooCommerce, as MySQL and Lucene interpret them differently.
		if ( 'REGEXP' === $compare || ( 'LIKE' === $compare && ! in_array( $type, [ 'string', 'keyword' ], true ) ) ) {
			throw new \InvalidArgumentException();
		}

		$values = $this->prepare_values( $values, $type );

		if ( 'LIKE' === $compare ) {
			return [ 'wildcard' => [ $path => '*' . addcslashes( $values[0], '\\*?' ) . '*' ] ];
		}

		if ( 'rounded' === $type ) {
			return $this->get_rounded_compare_filter( $path, $compare, $values );
		}

		if ( 'day' === $type ) {
			return $this->get_day_compare_filter( $path, $compare, $values );
		}

		switch ( $compare ) {
			case 'IN':
				return [ 'terms' => [ $path => $values ] ];
			case 'BETWEEN':
				return $this->get_range_filter(
					$path,
					[
						'gte' => $values[0],
						'lte' => $values[1] ?? $values[0],
					]
				);
			case '>':
				return $this->get_range_filter( $path, [ 'gt' => $values[0] ] );
			case '>=':
				return $this->get_range_filter( $path, [ 'gte' => $values[0] ] );
			case '<':
				return $this->get_range_filter( $path, [ 'lt' => $values[0] ] );
			case '<=':
				return $this->get_range_filter( $path, [ 'lte' => $values[0] ] );
			default:
				return [ 'terms' => [ $path => [ $values[0] ] ] ];
		}
	}

	/**
	 * Converts clause values to the indexed format of a value type.
	 *
	 * @param array  $values Clause values.
	 * @param string $type   Value type.
	 * @return array Prepared values. Dates compared by day are Unix timestamps.
	 * @throws \InvalidArgumentException When a date value is not a valid date.
	 */
	protected function prepare_values( array $values, string $type ): array {
		return array_map(
			function ( $value ) use ( $type ) {
				switch ( $type ) {
					case 'number':
					case 'rounded':
						return $this->to_number( $value );
					case 'string':
						return mb_strtolower( remove_accents( $value ) );
					case 'datetime':
						return gmdate( 'Y-m-d H:i:s', $this->to_timestamp( $value ) );
					case 'day':
					case 'timestamp':
						return $this->to_timestamp( $value );
					default:
						return (string) $value;
				}
			},
			$values
		);
	}

	/**
	 * Builds the filter for a positive comparison of decimals rounded to integers.
	 *
	 * @param string $path    Field path.
	 * @param string $compare Positive comparison operator.
	 * @param array  $values  Prepared values.
	 * @return array Elasticsearch filter.
	 */
	protected function get_rounded_compare_filter( string $path, string $compare, array $values ): array {
		if ( 'IN' === $compare ) {
			return [
				'bool' => [
					'should' => array_map(
						fn( $value ) => $this->get_rounded_compare_filter( $path, '=', [ $value ] ),
						$values
					),
				],
			];
		}

		// The smallest and largest integers the rounded value can be.
		switch ( $compare ) {
			case 'BETWEEN':
				$min = ceil( $values[0] );
				$max = floor( $values[1] ?? $values[0] );
				break;
			case '>':
				$min = floor( $values[0] ) + 1;
				$max = null;
				break;
			case '>=':
				$min = ceil( $values[0] );
				$max = null;
				break;
			case '<':
				$min = null;
				$max = ceil( $values[0] ) - 1;
				break;
			case '<=':
				$min = null;
				$max = floor( $values[0] );
				break;
			default:
				$min = ceil( $values[0] );
				$max = floor( $values[0] );
		}

		$range = [];

		// E.g. 0.5 rounds to 1, but -0.5 rounds to -1.
		if ( null !== $min ) {
			$range[ $min > 0 ? 'gte' : 'gt' ] = $min - 0.5;
		}

		if ( null !== $max ) {
			$range[ $max < 0 ? 'lte' : 'lt' ] = $max + 0.5;
		}

		return $this->get_range_filter( $path, $range );
	}

	/**
	 * Builds the filter for a positive comparison of dates cast to DATE.
	 *
	 * @param string $path    Field path.
	 * @param string $compare Positive comparison operator.
	 * @param array  $values  Prepared values, as Unix timestamps.
	 * @return array Elasticsearch filter.
	 */
	protected function get_day_compare_filter( string $path, string $compare, array $values ): array {
		if ( 'IN' === $compare ) {
			return [
				'bool' => [
					'should' => array_map(
						fn( $value ) => $this->get_day_compare_filter( $path, '=', [ $value ] ),
						$values
					),
				],
			];
		}

		// The first midnight at or after the value.
		$midnight = fn( $timestamp ) => '00:00:00' === gmdate( 'H:i:s', $timestamp ) ? $timestamp : $this->next_midnight( $timestamp );

		switch ( $compare ) {
			case 'BETWEEN':
				$range = [
					'gte' => $midnight( $values[0] ),
					'lt'  => $this->next_midnight( $values[1] ?? $values[0] ),
				];
				break;
			case '>':
				$range = [ 'gte' => $this->next_midnight( $values[0] ) ];
				break;
			case '>=':
				$range = [ 'gte' => $midnight( $values[0] ) ];
				break;
			case '<':
				$range = [ 'lt' => $midnight( $values[0] ) ];
				break;
			case '<=':
				$range = [ 'lt' => $this->next_midnight( $values[0] ) ];
				break;
			default:
				$range = [
					'gte' => $midnight( $values[0] ),
					'lt'  => $this->next_midnight( $values[0] ),
				];
		}

		return $this->get_range_filter( $path, array_map( fn( $timestamp ) => gmdate( 'Y-m-d H:i:s', $timestamp ), $range ) );
	}

	/**
	 * Builds the filter for boolean columns.
	 *
	 * Booleans are stored as 1 or 0 in HPOS, so the matching values are found by comparing 1 and 0 with the
	 * clause values.
	 *
	 * @param string $path    Field path.
	 * @param string $compare Comparison operator.
	 * @param array  $values  Clause values.
	 * @return array Elasticsearch filter.
	 * @throws \InvalidArgumentException When the comparison cannot be translated.
	 */
	protected function get_bool_filter( string $path, string $compare, array $values ): array {
		if ( in_array( $compare, [ 'LIKE', 'NOT LIKE', 'REGEXP', 'NOT REGEXP' ], true ) ) {
			throw new \InvalidArgumentException();
		}

		$values  = array_map( [ $this, 'to_number' ], $values );
		$matches = [];

		foreach ( [
			1 => true,
			0 => false,
		] as $number => $bool ) {
			switch ( self::NEGATIVE_OPERATORS[ $compare ] ?? $compare ) {
				case 'IN':
					$is_match = in_array( $number, $values, true );
					break;
				case 'BETWEEN':
					$is_match = $number >= $values[0] && $number <= ( $values[1] ?? $values[0] );
					break;
				case '>':
					$is_match = $number > $values[0];
					break;
				case '>=':
					$is_match = $number >= $values[0];
					break;
				case '<':
					$is_match = $number < $values[0];
					break;
				case '<=':
					$is_match = $number <= $values[0];
					break;
				default:
					$is_match = $number === $values[0];
			}

			if ( isset( self::NEGATIVE_OPERATORS[ $compare ] ) !== $is_match ) {
				$matches[] = $bool;
			}
		}

		return empty( $matches ) ? $this->get_match_none_filter() : [ 'terms' => [ $path => $matches ] ];
	}

	/**
	 * Builds a range filter.
	 *
	 * @param string $path  Field path.
	 * @param array  $range Range bounds, e.g. `gte` and `lt`.
	 * @return array Elasticsearch filter.
	 */
	protected function get_range_filter( string $path, array $range ): array {
		return [
			'range' => [
				$path => $range,
			],
		];
	}

	/**
	 * Builds a filter matching non-empty keyword values.
	 *
	 * @param string $path Keyword field path.
	 * @return array Elasticsearch filter.
	 */
	protected function get_not_empty_filter( string $path ): array {
		return $this->get_range_filter( $path, [ 'gt' => '' ] );
	}

	/**
	 * Builds a filter that matches nothing.
	 *
	 * @return array Elasticsearch filter.
	 */
	protected function get_match_none_filter(): array {
		return $this->negate( [ 'match_all' => new \stdClass() ] );
	}

	/**
	 * Negates a filter.
	 *
	 * @param array $filter Elasticsearch filter.
	 * @return array Elasticsearch filter.
	 */
	protected function negate( array $filter ): array {
		return [
			'bool' => [
				'must_not' => [ $filter ],
			],
		];
	}

	/**
	 * Converts a value to a number the way MySQL does, e.g. `'9.5abc'` becomes 9.5 and `'abc'` becomes 0.
	 *
	 * @param mixed $value Value.
	 * @return int|float Number.
	 */
	protected function to_number( $value ) {
		$number = (float) $value;

		return fmod( $number, 1 ) ? $number : (int) $number;
	}

	/**
	 * Converts a GMT date value to a Unix timestamp.
	 *
	 * @param string $value Date value.
	 * @return int Unix timestamp.
	 * @throws \InvalidArgumentException When the value is not a supported date.
	 */
	protected function to_timestamp( string $value ): int {
		if (
			! preg_match( '/^(\d{4})-(\d{2})-(\d{2})(?: ([01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?)?$/', $value, $matches )
			|| ! checkdate( $matches[2], $matches[3], $matches[1] )
		) {
			throw new \InvalidArgumentException();
		}

		return strtotime( "{$value} UTC" );
	}

	/**
	 * Gets the first midnight after the day of a timestamp.
	 *
	 * @param int $timestamp Unix timestamp.
	 * @return int Unix timestamp.
	 */
	protected function next_midnight( int $timestamp ): int {
		return strtotime( gmdate( 'Y-m-d', $timestamp ) . ' UTC' ) + DAY_IN_SECONDS;
	}
}
