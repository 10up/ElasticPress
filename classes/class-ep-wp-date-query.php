<?php

class EP_WP_Date_Query extends WP_Date_Query {

	public function get_es_filter() {
		$filter = $this->get_es_filter_for_clauses();
		return $filter;
	}

	protected function get_es_filter_for_clauses() {
		$sql = $this->get_es_filter_for_query( $this->queries );

		if ( ! empty( $sql['where'] ) ) {
			$sql['where'] = ' AND ' . $sql['where'];
		}

		return $sql;
	}

	public function get_compare( $query ) {
		if ( ! empty( $query['compare'] ) && in_array( $query['compare'], array( '=', '!=', '>', '>=', '<', '<=', 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN' ) ) )
			return strtoupper( $query['compare'] );

		return $this->compare;
	}

	protected function get_es_filter_for_query( $query, $depth = 0 ) {
		$filter_chunks = array(
			'filters'  => array(),
		);

		$filter_array = array();
		echo '<pre>';
		var_dump($query);
		echo '</pre>';

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
					$clause_filter = $this->get_es_filter_for_query( $clause, $depth + 1 );
					$filter_chunks['filters'][] = $clause_filter;
				}
			}
		}

		echo '<pre>';
		echo 'filter chunks';
		var_dump($filter_chunks['filters']);
		echo '</pre>';

		if ( empty( $relation ) ) {
			$relation = 'AND';
		}

		if ( 'AND' === $relation ) {
			$filter_array['and'] = array();
			foreach ( $filter_chunks['filters'] as $key => $filter ) {
				$filter_type = key($filter);
				var_dump($filter);
				if ( 'date_terms' === $filter_type ) {
					$filter_array['and'] = array(
						'bool' => array(
							'must' => $filter['date_terms'],
						)
					);
				} else if ( 'range' === $filter_type ) {
					if( !is_array( $filter_array['and']['range'] ) )
						$filter_array['and']['range'] = array();

					$range_column = key($filter['range'][0]);
					$filter_array['and']['range'][$range_column] = $filter['range'][0][$range_column];

				}
			}
		}

		return $filter_array;
	}

	protected function get_es_filter_for_clause( $query, $parent_query ) {
		global $wpdb;

		// The sub-parts of a $where part.
		$filter_parts = array();

		$column = ( ! empty( $query['column'] ) ) ? esc_sql( $query['column'] ) : $this->column;

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
		if ( ! empty( $query['after'] ) || ! empty( $query['before'] ) )
			$range_filter[$column] = array();

		if ( ! empty( $query['after'] ) )
			$range_filter[$column][$gt] = $this->build_mysql_datetime( $query['after'] );

		if ( ! empty( $query['before'] ) )
			$range_filter[$column][$lt] = $this->build_mysql_datetime( $query['before'] );

		if ( ! empty( $query['after'] ) || ! empty( $query['before'] ) )
			$filter_parts['range'] = array($range_filter);

		// Specific value queries.

		$date_parameters = array(
			'year' => ! empty( $query['year'] ) ? $query['year'] :false,
			'month' => ! empty( $query['month']) ? $query['month'] :false,
			'monthnum' => ! empty( $query['monthnum']) ? $query['monthnum'] :false,
			'w' => ! empty( $query['w']) ?  $query['w'] :false,
			'week' => ! empty( $query['week']) ?  $query['week'] :false,
			'dayofyear' => ! empty( $query['dayofyear']) ?  $query['dayofyear'] :false,
			'day' => ! empty( $query['day']) ?  $query['day'] :false,
			'dayofweek' => ! empty( $query['dayofweek']) ?  $query['dayofweek'] :false,
			'dayofweek_iso' => ! empty( $query['dayofweek_iso']) ?  $query['dayofweek_iso'] :false,
			'hour' => ! empty( $query['hour']) ? $query['hour'] :false,
			'minute' => ! empty( $query['minute']) ? $query['minute'] :false,
			'second' => ! empty( $query['second'] ) ? $query['second'] :false,
			'm' => ! empty( $query['m'] ) ? $query['m'] :false, // yearmonth
		);

		foreach( $date_parameters as $param => $value ) {
			if ( false === $value ) {
				unset( $date_parameters[$param] );
			}
		}

		if (  $date_parameters ) {
			EP_WP_Date_Query::validate_date_values( $date_parameters );
			$date_terms = array();
			foreach ( $date_parameters as $param => $value ) {
				if ( '=' === $compare ) {
					$date_terms[]['term']["date_terms.{$param}"] = $value;
				}
				//@todo support other compare operators
			}

			if ( ! empty( $date_terms ) ) {
				$filter_parts['date_terms'] = $date_terms;
			}
		}
		echo '<pre>';
		var_dump($filter_parts);
		echo '</pre>';
		return $filter_parts;
	}
}
