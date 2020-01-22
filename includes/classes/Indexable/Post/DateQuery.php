<?php
/**
 * Support date query integration
 *
 * @package elasticpress
 * @since   1.3
 */

namespace ElasticPress\Indexable\Post;

use \WP_Date_Query as WP_Date_Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Date query class
 */
class DateQuery extends WP_Date_Query {
	/**
	 * Like WP_Date_Query::get_sql
	 * takes WP_Date_Query class queries and returns ES filter arrays
	 *
	 * @since 0.1.4
	 * @return array
	 */
	public function get_es_filter() {
		$filter = $this->get_es_filter_for_clauses();

		return $filter;
	}

	/**
	 * Like WP_Date_Query::get_sql_for_clauses
	 * takes all queries in WP_Date_Query object and gets ES filters for each
	 *
	 * @since 0.1.4
	 * @return array
	 */
	protected function get_es_filter_for_clauses() {
		$filter = $this->get_es_filter_for_query( $this->queries );

		return $filter;
	}

	/**
	 * Get Elaticsearch formatted filters
	 *
	 * @param array $query array of date query clauses.
	 * @param int   $depth unused but may be necessary if we do nested date queries.
	 * @since 0.1.4
	 * @return array
	 */
	protected function get_es_filter_for_query( $query, $depth = 0 ) {
		$filter_chunks = array(
			'filters' => [],
		);

		$filter_array = [];

		foreach ( $query as $key => $clause ) {
			if ( 'relation' === $key ) {
				$relation = $query['relation'];
			} elseif ( is_array( $clause ) ) {

				// This is a first-order clause.
				if ( $this->is_first_order_clause( $clause ) ) {

					$clause_filter = $this->get_es_filter_for_clause( $clause, $query );

					$filter_count = count( $clause_filter );
					if ( ! $filter_count ) {
						$filter_chunks['filters'][] = '';
					} else {
						$filter_chunks['filters'][] = $clause_filter;
					}

					// This is a subquery, so we recurse.
				}
			}
		}

		// @todo implement OR filter relationships.
		if ( empty( $relation ) ) {
			$relation = 'AND';
		}

		if ( 'AND' === $relation ) {
			$filter_array['and'] = [];

			$range_filters = [];
			$term_filters  = [];
			foreach ( $filter_chunks['filters'] as $key => $filter ) {
				$filter_type = key( $filter );
				if ( 'date_terms' === $filter_type ) {
					$term_filters[] = $filter['date_terms'];
				} elseif ( 'range_filters' === $filter_type ) {
					$range_filters[] = $filter['range_filters'];
				}
			}

			if ( $range_filters ) {
				$filter_array['and'] = array(
					'bool' => $this->build_es_range_filter( $range_filters ),
				);
			}

			if ( $term_filters ) {
				$filter_array['and'] = array(
					'bool' => $this->build_es_date_term_filter( $term_filters ),
				);
			}
		}

		return $filter_array;
	}

	/**
	 * Takes array of date term filters and groups them into a filter based on
	 * relationship type
	 *
	 * @param array  $date_term_filters Date term filters.
	 * @param string $type type of relationship between date term filters (AND, OR).
	 *
	 * @since 0.1.4
	 * @return array
	 */
	protected function build_es_date_term_filter( $date_term_filters = [], $type = 'AND' ) {
		$date_term_filter_array = array(
			'must'     => [],
			'should'   => [],
			'must_not' => [],
		);

		if ( 'AND' === $type ) {
			foreach ( $date_term_filters as $date_term_filter ) {
				$date_term_filter_array['must']     = ! empty( $date_term_filter['must'] ) ? array_merge( $date_term_filter_array['must'], $date_term_filter['must'] ) : [];
				$date_term_filter_array['should']   = ! empty( $date_term_filter['should'] ) ? array_merge( $date_term_filter_array['should'], $date_term_filter['should'] ) : [];
				$date_term_filter_array['must_not'] = ! empty( $date_term_filter['must_not'] ) ? array_merge( $date_term_filter_array['must_not'], $date_term_filter['must_not'] ) : [];
			}
		}

		return array_filter( $date_term_filter_array );
	}

	/**
	 * Takes array of range filters and groups them into a single filter
	 *
	 * @param array $range_filters Range filters.
	 *
	 * @since 0.1.4
	 * @return array
	 */
	protected function build_es_range_filter( $range_filters = [] ) {
		$range_filter_array = array(
			'must'     => [],
			'should'   => [],
			'must_not' => [],
		);

		foreach ( $range_filters as $key => $range_filter ) {
			if ( 'not' === key( $range_filter ) ) {
				$range_filter_array['must_not'][] = array(
					'range' => $range_filter['not'],
				);
			} else {
				$range_filter_array['must'][] = array(
					'range' => $range_filter,
				);
			}
		}

		return array_filter( $range_filter_array );
	}

	/**
	 * Takes SQL query part, and translates it into an ES filter
	 *
	 * @param array $query SQL query piece.
	 * @return array ES filter.
	 */
	protected function get_es_filter_for_clause( $query ) {

		// The sub-parts of a $where part.
		$filter_parts = [];

		$column = ( ! empty( $query['column'] ) ) ? $query['column'] : $this->column;

		$compare = $this->get_compare( $query );

		$inclusive = ! empty( $query['inclusive'] ) && true === $query['inclusive'] ? true : false;

		// Assign greater- and less-than values.
		$lt = 'lt';
		$gt = 'gt';

		if ( $inclusive ) {
			$lt .= 'e';
			$gt .= 'e';
		}

		// Range queries.
		if ( ! empty( $query['after'] ) ) {
			$range_filters = array(
				"{$column}" => array(
					"{$gt}" => $this->build_mysql_datetime( $query['after'] ),
				),
			);
		}

		if ( ! empty( $query['before'] ) ) {
			$range_filters                   = empty( $range_filters[ $column ] ) ? array( "{$column}" => array( "{$lt}" => [] ) ) : $range_filters;
			$range_filters[ $column ][ $lt ] = $this->build_mysql_datetime( $query['before'] );
		}

		if ( ! empty( $query['after'] ) || ! empty( $query['before'] ) ) {
			$filter_parts['range_filters'] = $range_filters;
		}

		// Specific value queries.
		$date_parameters = array(
			'year'          => ! empty( $query['year'] ) ? $query['year'] : false,
			'month'         => ! empty( $query['month'] ) ? $query['month'] : false,
			'week'          => ! empty( $query['week'] ) ? $query['week'] : false,
			'dayofyear'     => ! empty( $query['dayofyear'] ) ? $query['dayofyear'] : false,
			'day'           => ! empty( $query['day'] ) ? $query['day'] : false,
			'dayofweek'     => ! empty( $query['dayofweek'] ) ? $query['dayofweek'] : false,
			'dayofweek_iso' => ! empty( $query['dayofweek_iso'] ) ? $query['dayofweek_iso'] : false,
			'hour'          => ! empty( $query['hour'] ) ? $query['hour'] : false,
			'minute'        => ! empty( $query['minute'] ) ? $query['minute'] : false,
			'second'        => ! empty( $query['second'] ) ? $query['second'] : false,
			'm'             => ! empty( $query['m'] ) ? $query['m'] : false, // yearmonth
		);

		if ( empty( $date_parameters['month'] ) && ! empty( $query['monthnum'] ) ) {
			$date_parameters['month'] = $query['monthnum'];
		}

		if ( empty( $date_parameters['week'] ) && ! empty( $query['w'] ) ) {
			$date_parameters['week'] = $query['w'];
		}

		foreach ( $date_parameters as $param => $value ) {
			if ( false === $value ) {
				unset( $date_parameters[ $param ] );
			}
		}

		if ( $date_parameters ) {

			self::validate_date_values( $date_parameters );

			$date_terms = array(
				'must'     => [],
				'should'   => [],
				'must_not' => [],
			);

			foreach ( $date_parameters as $param => $value ) {
				if ( '=' === $compare ) {
					$date_terms['must'][]['term'][ "date_terms.{$param}" ] = $value;
				} elseif ( '!=' === $compare ) {
					$date_terms['must_not'][]['term'][ "date_terms.{$param}" ] = $value;
				} elseif ( 'IN' === $compare ) {
					foreach ( $value as $in_value ) {
						$date_terms['should'][]['term'][ "date_terms.{$param}" ] = $in_value;
					}
				} elseif ( 'NOT IN' === $compare ) {
					foreach ( $value as $in_value ) {
						$date_terms['must_not'][]['term'][ "date_terms.{$param}" ] = $in_value;
					}
				} elseif ( 'BETWEEN' === $compare ) {
					$range_filter[ "date_terms.{$param}" ]        = [];
					$range_filter[ "date_terms.{$param}" ]['gte'] = $value[0];
					$range_filter[ "date_terms.{$param}" ]['lte'] = $value[1];
					$filter_parts['range_filters']                = $range_filter;
				} elseif ( 'NOT BETWEEN' === $compare ) {
					$range_filter[ "date_terms.{$param}" ]       = [];
					$range_filter[ "date_terms.{$param}" ]['gt'] = $value[0];
					$range_filter[ "date_terms.{$param}" ]['lt'] = $value[1];
					$filter_parts['range_filters']               = array( 'not' => $range_filter );
				} elseif ( strpos( $compare, '>' ) !== false ) {
					$range                                 = ( strpos( $compare, '=' ) !== false ) ? 'gte' : 'gt';
					$range_filter[ "date_terms.{$param}" ] = [];
					$range_filter[ "date_terms.{$param}" ][ $range ] = $value;
					$filter_parts['range_filters']                   = $range_filter;
				} elseif ( strpos( $compare, '<' ) !== false ) {
					$range                                 = ( strpos( $compare, '=' ) !== false ) ? 'lte' : 'lt';
					$range_filter[ "date_terms.{$param}" ] = [];
					$range_filter[ "date_terms.{$param}" ][ $range ] = $value;
					$filter_parts['range_filters']                   = $range_filter;
				}
			}

			$date_terms = array_filter( $date_terms );

			if ( ! empty( $date_terms ) ) {
				$filter_parts['date_terms'] = $date_terms;
			}
		}

		return $filter_parts;
	}

	/**
	 * Takes WP_Query args, and returns ES filters for query
	 * Support for older style WP_Query date params
	 *
	 * @param array $args WP_Query args.
	 * @return array|bool
	 */
	public static function simple_es_date_filter( $args ) {
		$date_parameters = array(
			'year'   => ! empty( $args['year'] ) ? $args['year'] : false,
			'month'  => ! empty( $args['month'] ) ? $args['month'] : false,
			'week'   => ! empty( $args['week'] ) ? $args['week'] : false,
			'day'    => ! empty( $args['day'] ) ? $args['day'] : false,
			'hour'   => ! empty( $args['hour'] ) ? $args['hour'] : false,
			'minute' => ! empty( $args['minute'] ) ? $args['minute'] : false,
			'second' => ! empty( $args['second'] ) ? $args['second'] : false,
			'm'      => ! empty( $args['m'] ) ? $args['m'] : false, // yearmonth
		);

		if ( ! $date_parameters['month'] && ! empty( $args['monthnum'] ) ) {
			$date_parameters['month'] = $args['monthnum'];
		}

		if ( ! $date_parameters['week'] && ! empty( $args['w'] ) ) {
			$date_parameters['week'] = $args['w'];
		}

		foreach ( $date_parameters as $param => $value ) {
			if ( false === $value ) {
				unset( $date_parameters[ $param ] );
			}
		}

		if ( ! $date_parameters ) {
			return false;
		}

		$date_terms = [];
		foreach ( $date_parameters as $param => $value ) {
			$date_terms[]['term'][ "date_terms.{$param}" ] = $value;
		}

		return array(
			'bool' => array(
				'must' => $date_terms,
			),
		);
	}

	/**
	 * Introduced in WP 4.1 added here for backwards compatibility
	 *
	 * @var array
	 */
	public $time_keys = array( 'after', 'before', 'year', 'month', 'monthnum', 'week', 'w', 'dayofyear', 'day', 'dayofweek', 'dayofweek_iso', 'hour', 'minute', 'second' );

	/**
	 * Introduced in WP 4.1 added here for backwards compatibility
	 *
	 * @param  array $query Date query array
	 * @return boolean
	 */
	protected function is_first_order_clause( $query ) {
		$time_keys = array_intersect( $this->time_keys, array_keys( $query ) );
		return ! empty( $time_keys );
	}

	/**
	 * Introduced in WP 4.1 added here for backwards compatibility
	 *
	 * @param  array $date_query Date query array
	 * @return boolean
	 */
	public function validate_date_values( $date_query = [] ) {
		if ( empty( $date_query ) ) {
			return false;
		}

		$valid = true;

		/*
		 * Validate 'before' and 'after' up front, then let the
		 * validation routine continue to be sure that all invalid
		 * values generate errors too.
		 */
		if ( array_key_exists( 'before', $date_query ) && is_array( $date_query['before'] ) ) {
			$valid = $this->validate_date_values( $date_query['before'] );
		}

		if ( array_key_exists( 'after', $date_query ) && is_array( $date_query['after'] ) ) {
			$valid = $this->validate_date_values( $date_query['after'] );
		}

		// Array containing all min-max checks.
		$min_max_checks = [];

		// Days per year.
		if ( array_key_exists( 'year', $date_query ) ) {
			/*
			 * If a year exists in the date query, we can use it to get the days.
			 * If multiple years are provided (as in a BETWEEN), use the first one.
			 */
			if ( is_array( $date_query['year'] ) ) {
				$_year = reset( $date_query['year'] );
			} else {
				$_year = $date_query['year'];
			}

			$max_days_of_year = date( 'z', mktime( 0, 0, 0, 12, 31, $_year ) ) + 1;
		} else {
			// otherwise we use the max of 366 (leap-year).
			$max_days_of_year = 366;
		}

		$min_max_checks['dayofyear'] = array(
			'min' => 1,
			'max' => $max_days_of_year,
		);

		// Days per week.
		$min_max_checks['dayofweek'] = array(
			'min' => 1,
			'max' => 7,
		);

		// Days per week.
		$min_max_checks['dayofweek_iso'] = array(
			'min' => 1,
			'max' => 7,
		);

		// Months per year.
		$min_max_checks['month'] = array(
			'min' => 1,
			'max' => 12,
		);

		// Weeks per year.
		if ( isset( $_year ) ) {
			// If we have a specific year, use it to calculate number of weeks.
			$date = new \DateTime();
			$date->setISODate( $_year, 53 );
			$week_count = $date->format( 'W' ) === '53' ? 53 : 52;

		} else {
			// Otherwise set the week-count to a maximum of 53.
			$week_count = 53;
		}

		$min_max_checks['week'] = array(
			'min' => 1,
			'max' => $week_count,
		);

		// Days per month.
		$min_max_checks['day'] = array(
			'min' => 1,
			'max' => 31,
		);

		// Hours per day.
		$min_max_checks['hour'] = array(
			'min' => 0,
			'max' => 23,
		);

		// Minutes per hour.
		$min_max_checks['minute'] = array(
			'min' => 0,
			'max' => 59,
		);

		// Seconds per minute.
		$min_max_checks['second'] = array(
			'min' => 0,
			'max' => 59,
		);

		// Concatenate and throw a notice for each invalid value.
		foreach ( $min_max_checks as $key => $check ) {
			if ( ! array_key_exists( $key, $date_query ) ) {
				continue;
			}

			// Throw a notice for each failing value.
			$is_between = true;
			foreach ( (array) $date_query[ $key ] as $_value ) {
				$is_between = $_value >= $check['min'] && $_value <= $check['max'];

				if ( ! is_numeric( $_value ) || ! $is_between ) {
					$error = sprintf(
						/* translators: Date query invalid date message: 1: invalid value, 2: type of value, 3: minimum valid value, 4: maximum valid value */
						__( 'Invalid value %1$s for %2$s. Expected value should be between %3$s and %4$s.' ),
						'<code>' . esc_html( $_value ) . '</code>',
						'<code>' . esc_html( $key ) . '</code>',
						'<code>' . esc_html( $check['min'] ) . '</code>',
						'<code>' . esc_html( $check['max'] ) . '</code>'
					);

					_doing_it_wrong( __CLASS__, esc_html( $error ), '4.1.0' );

					$valid = false;
				}
			}
		}

		// If we already have invalid date messages, don't bother running through checkdate().
		if ( ! $valid ) {
			return $valid;
		}

		$day_month_year_error_msg = '';

		$day_exists   = array_key_exists( 'day', $date_query ) && is_numeric( $date_query['day'] );
		$month_exists = array_key_exists( 'month', $date_query ) && is_numeric( $date_query['month'] );
		$year_exists  = array_key_exists( 'year', $date_query ) && is_numeric( $date_query['year'] );

		if ( $day_exists && $month_exists && $year_exists ) {
			// 1. Checking day, month, year combination.
			if ( ! wp_checkdate( $date_query['month'], $date_query['day'], $date_query['year'], sprintf( '%s-%s-%s', $date_query['year'], $date_query['month'], $date_query['day'] ) ) ) {
				/* translators: 1: year, 2: month, 3: day of month */
				$day_month_year_error_msg = sprintf(
					// translators: 1: Year, 2: Month, 3: Day
					__( 'The following values do not describe a valid date: year %1$s, month %2$s, day %3$s.' ),
					'<code>' . esc_html( $date_query['year'] ) . '</code>',
					'<code>' . esc_html( $date_query['month'] ) . '</code>',
					'<code>' . esc_html( $date_query['day'] ) . '</code>'
				);

				$valid = false;
			}
		} elseif ( $day_exists && $month_exists ) {
			/*
			 * 2. checking day, month combination
			 * We use 2012 because, as a leap year, it's the most permissive.
			 */
			if ( ! wp_checkdate( $date_query['month'], $date_query['day'], 2012, sprintf( '2012-%s-%s', $date_query['month'], $date_query['day'] ) ) ) {
				/* translators: 1: month, 2: day of month */
				$err_msg                  = __( 'The following values do not describe a valid date: month %1$s, day %2$s.' );
				$day_month_year_error_msg = sprintf(
					$err_msg,
					'<code>' . esc_html( $date_query['month'] ) . '</code>',
					'<code>' . esc_html( $date_query['day'] ) . '</code>'
				);

				$valid = false;
			}
		}

		if ( ! empty( $day_month_year_error_msg ) ) {
			_doing_it_wrong( __CLASS__, esc_html( $day_month_year_error_msg ), '4.1.0' );
		}

		return $valid;
	}
}
