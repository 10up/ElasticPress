<?php

class EP_WP_Date_Query extends WP_Date_Query {

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
	 * @param $query array of date query clauses
	 * @param int $depth unused but may be necessary if we do nested date queries
	 *
	 * @since 0.1.4
	 * @return array
	 */
	protected function get_es_filter_for_query( $query, $depth = 0 ) {
		$filter_chunks = array(
			'filters' => array(),
		);

		$filter_array = array();

		foreach ( $query as $key => $clause ) {
			if ( 'relation' === $key ) {
				$relation = $query['relation'];
			} else if ( is_array( $clause ) ) {

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
				} else {
					//@todo WP_Date_Query supports nested date queries, revisit if necessary
					//Removed because this implementation had incorrect results
				}
			}
		}

		//@todo implement OR filter relationships
		if ( empty( $relation ) ) {
			$relation = 'AND';
		}

		if ( 'AND' === $relation ) {
			$filter_array['and'] = array();

			$range_filters = array();
			$term_filters  = array();
			foreach ( $filter_chunks['filters'] as $key => $filter ) {
				$filter_type = key( $filter );
				if ( 'date_terms' === $filter_type ) {
					$term_filters[] = $filter['date_terms'];
				} else if ( 'range_filters' === $filter_type ) {
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
	 * @param array $date_term_filters
	 * @param string $type type of relationship between date term filters (AND, OR)
	 *
	 * @since 0.1.4
	 * @return array
	 */
	protected function build_es_date_term_filter( $date_term_filters = array(), $type = 'AND' ) {
		$date_term_filter_array = array(
			'must'     => array(),
			'should'   => array(),
			'must_not' => array(),
		);

		if ( 'AND' === $type ) {
			foreach ( $date_term_filters as $date_term_filter ) {
				$date_term_filter_array['must']     = ! empty( $date_term_filter['must'] ) ? array_merge( $date_term_filter_array['must'], $date_term_filter['must'] ) : array();
				$date_term_filter_array['should']   = ! empty( $date_term_filter['should'] ) ? array_merge( $date_term_filter_array['should'], $date_term_filter['should'] ) : array();
				$date_term_filter_array['must_not'] = ! empty( $date_term_filter['must_not'] ) ? array_merge( $date_term_filter_array['must_not'], $date_term_filter['must_not'] ) : array();
			}
		}

		return array_filter( $date_term_filter_array );
	}

	/**
	 * Takes array of range filters and groups them into a single filter
	 *
	 * @param array $range_filters
	 *
	 * @since 0.1.4
	 * @return array
	 */
	protected function build_es_range_filter( $range_filters = array() ) {
		$range_filter_array = array(
			'must'     => array(),
			'should'   => array(),
			'must_not' => array(),
		);

		foreach ( $range_filters as $key => $range_filter ) {
			if ( 'not' === key( $range_filter ) ) {
				$range_filter_array['must_not'][] = array(
					'range' => $range_filter['not']
				);
			} else {
				$range_filter_array['must'][] = array(
					'range' => $range_filter
				);
			}
		}

		return array_filter( $range_filter_array );
	}

	/**
	 * Takes SQL query part, and translates it into an ES filter
	 *
	 * @param $query
	 *
	 * @return array ES filter
	 */
	protected function get_es_filter_for_clause( $query ) {

		// The sub-parts of a $where part.
		$filter_parts = array();

		$column = ( ! empty( $query['column'] ) ) ? $query['column'] : $this->column;

		$compare = $this->get_compare( $query );

		$inclusive = ! empty( $query['inclusive'] );

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
					"{$gt}" => $this->build_mysql_datetime( $query['after'] )
				)
			);
		}

		if ( ! empty( $query['before'] ) ) {
			$range_filters                   = empty( $range_filters[ $column ] ) ? array( "{$column}" => array( "{$lt}" => array() ) ) : $range_filters;
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
			EP_WP_Date_Query::validate_date_values( $date_parameters );
			$date_terms = array(
				'must'     => array(),
				'should'   => array(),
				'must_not' => array(),
			);

			foreach ( $date_parameters as $param => $value ) {
				if ( '=' === $compare ) {
					$date_terms['must'][]['term']["date_terms.{$param}"] = $value;
				} else if ( '!=' === $compare ) {
					$date_terms['must_not'][]['term']["date_terms.{$param}"] = $value;
				} else if ( 'IN' === $compare ) {
					foreach ( $value as $in_value ) {
						$date_terms['should'][]['term']["date_terms.{$param}"] = $in_value;
					}
				} else if ( 'NOT IN' === $compare ) {
					foreach ( $value as $in_value ) {
						$date_terms['must_not'][]['term']["date_terms.{$param}"] = $in_value;
					}
				} else if ( 'BETWEEN' === $compare ) {
					$range_filter["date_terms.{$param}"]       = array();
					$range_filter["date_terms.{$param}"]['gt'] = $value[0];
					$range_filter["date_terms.{$param}"]['lt'] = $value[1];
					$filter_parts['range_filters']             = $range_filter;
				} else if ( 'NOT BETWEEN' === $compare ) {
					$range_filter["date_terms.{$param}"]       = array();
					$range_filter["date_terms.{$param}"]['gt'] = $value[0];
					$range_filter["date_terms.{$param}"]['lt'] = $value[1];
					$filter_parts['range_filters']             = array( 'not' => $range_filter );
				} else if ( strpos( $compare, '>' ) !== false ) {
					$range                                         = ( strpos( $compare, '=' ) !== false ) ? 'gte' : 'gt';
					$range_filter["date_terms.{$param}"]           = array();
					$range_filter["date_terms.{$param}"][ $range ] = $value;
					$filter_parts['range_filters']                 = $range_filter;
				} else if ( strpos( $compare, '<' ) !== false ) {
					$range                                         = ( strpos( $compare, '=' ) !== false ) ? 'lte' : 'lt';
					$range_filter["date_terms.{$param}"]           = array();
					$range_filter["date_terms.{$param}"][ $range ] = $value;
					$filter_parts['range_filters']                 = $range_filter;
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
	 * @param $args
	 *
	 * @return array|bool
	 */
	static function simple_es_date_filter( $args ) {
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

		$date_terms = array();
		foreach ( $date_parameters as $param => $value ) {
			$date_terms[]['term']["date_terms.{$param}"] = $value;
		}

		return array(
			'bool' => array(
				'must' => $date_terms,
			)
		);
	}
}
