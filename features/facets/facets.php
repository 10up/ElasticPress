<?php

/**
 * Performant utility function for building a term tree.
 *
 * Tree will look like this:
 * [
 * 		WP_Term(
 * 	 		name
 * 	   		slug
 * 	     	children ->[
 * 	      		WP_Term()
 * 		    ]
 * 	    ),
 * 	    WP_Term()
 * ]
 *
 * @param  array       $all_terms Pass get_terms() as this argument where terms are objects NOT arrays
 * @param  string|bool $orderby   Can be count|name|false. This is how each tree branch will be ordered
 * @param  string      $order     Can be asc|desc. This is the direction ordering will occur.
 * @param  bool        $flat      If false, a tree will be returned e.g. an array of top level terms
 *                                which children linked within each node. If true, the tree will be
 *                                "flattened"
 * @since  2.5
 * @return array
 */
function ep_get_term_tree( $all_terms, $orderby = 'count', $order = 'desc', $flat = false ) {
	$terms_map = array();
	$terms_tree = array();
	$iteration_id = 0;

	while ( true ) {
		if ( empty( $all_terms ) ) {
			break;
		}

		foreach ( $all_terms as $key => $term ) {
			$iteration_id++;

			if ( ! isset( $term->children ) ) {
				$term->children = array();
			}

			if ( ! isset( $terms_map[ $term->term_id ] ) ) {
				$terms_map[ $term->term_id ] = $term;
			}

			if ( empty( $term->parent ) ) {
				$term->level = 0;

				if ( empty( $orderby ) ) {
					$terms_tree[] = $term;
				} elseif ( 'count' === $orderby ) {
					/**
					 * We add this weird number to get past terms with the same count
					 */
					$terms_tree[ ( ( $term->count * 10000000 ) + $iteration_id ) ] = $term;
				} elseif ( 'name' === $orderby ) {
					$terms_tree[ strtolower( $term->name ) ] = $term;
				}

				unset( $all_terms[ $key ] );
			} else {
				if ( ! empty( $terms_map[ $term->parent ] ) && isset( $terms_map[ $term->parent ]->level ) ) {

					if ( empty( $orderby ) ) {
						$terms_map[ $term->parent ]->children[] = $term;
					} elseif ( 'count' === $orderby ) {
						$terms_map[ $term->parent ]->children[ ( ( $term->count * 10000000 ) + $iteration_id ) ] = $term;
					} elseif ( 'name' === $orderby ) {
						$terms_map[ $term->parent ]->children[ $term->name ] = $term;
					}

					$parent_level = ( $terms_map[ $term->parent ]->level ) ? $terms_map[ $term->parent ]->level : 0;

					$term->level = $parent_level + 1;
					$term->parent_term = $terms_map[ $term->parent ];

					unset( $all_terms[ $key ] );
				}
			}
		}
	}

	if ( ! empty( $orderby ) ) {
		if ( 'asc' === $order ) {
			ksort( $terms_tree );
		} else {
			krsort( $terms_tree );
		}

		foreach ( $terms_map as $term ) {
			if ( 'asc' === $order ) {
				ksort( $term->children );
			} else {
				krsort( $term->children );
			}

			$term->children = array_values( $term->children );
		}

		$terms_tree = array_values( $terms_tree );
	}

	if ( $flat ) {
		$flat_tree = array();

		foreach ( $terms_tree as $term ) {
			$flat_tree[] = $term;
			$to_process = $term->children;
			while ( ! empty( $to_process ) ) {
				$term = array_shift( $to_process );
				$flat_tree[] = $term;

				if ( ! empty( $term->children ) ) {
					$to_process = $term->children + $to_process;
				}
			}
		}

		return $flat_tree;
	}

	return $terms_tree;
}

/**
 * Setup hooks and filters for feature
 *
 * @since 2.5
 */
function ep_facets_setup() {
	add_action( 'widgets_init', 'ep_facets_register_widgets' );
	add_action( 'ep_retrieve_raw_response', 'ep_facets_get_aggs' );
	add_action( 'ep_formatted_args', 'ep_facets_set_agg_filters', 10, 2 );
	add_action( 'pre_get_posts', 'ep_facets_facet_query' );
	add_action( 'admin_enqueue_scripts', 'ep_facets_admin_scripts' );
	add_action( 'wp_enqueue_scripts', 'ep_facets_front_scripts' );
	add_action( 'ep_feature_box_settings_facets', 'ep_facets_settings', 10, 1 );
}

/**
 * Dashboard facet settings
 *
 * @since 2.5
 * @param EP_Feature $feature Feature object.
 */
function ep_facets_settings( $feature ) {
	$settings = $feature->get_settings();
	if ( ! $settings ) {
		$settings = array();
	}

	$settings = wp_parse_args( $settings, $feature->default_settings );
	?>
	<div class="field js-toggle-feature" data-feature="<?php echo esc_attr( $feature->slug ); ?>">
		<div class="field-name status"><?php esc_html_e( 'Match Type', 'elasticpress' ); ?></div>
		<div class="input-wrap">
			<label for="match_type_all"><input name="match_type" id="match_type_all" data-field-name="match_type" class="setting-field" type="radio" <?php if ( 'all' === $settings['match_type'] ) : ?>checked<?php endif; ?> value="all"><?php echo wp_kses_post( __( 'Show any content tagged to <strong>all</strong> selected terms', 'elasticpress' ) ); ?></label><br>
			<label for="match_type_any"><input name="match_type" id="match_type_any" data-field-name="match_type" class="setting-field" type="radio" <?php if ( 'any' === $settings['match_type'] ) : ?>checked<?php endif; ?> value="any"><?php echo wp_kses_post( __( 'Show all content tagged to <strong>any</strong> selected term', 'elasticpress' ) ); ?></label>
			<p class="field-description"><?php esc_html_e( '"All" will only show content that matches all facets. "Any" will show content that matches any facet.', 'elasticpress' ); ?></p>
		</div>
	</div>
<?php
}

/**
 * If we are doing or matches, we need to remove filters from aggs
 *
 * @param  array $args
 * @param  array $query_args
 * @since  2.5
 * @return array
 */
function ep_facets_set_agg_filters( $args, $query_args ) {
	if ( empty( $query_args['ep_facet'] ) ) {
		return $args;
	}

	/**
	 * @todo For some reason these are appearing in the query args, need to investigate
	 */
	unset( $query_args['category_name'] );
	unset( $query_args['cat'] );
	unset( $query_args['tag'] );
	unset( $query_args['tag_id'] );
	unset( $query_args['taxonomy'] );
	unset( $query_args['term'] );

	$facet_query_args = $query_args;

	$feature  = ep_get_registered_feature( 'facets' );
	$settings = array();

	if ( $feature ) {
		$settings = $feature->get_settings();
	}

	$settings = wp_parse_args( $settings, array(
		'match_type' => 'all',
	) );

	if ( ! empty( $facet_query_args['tax_query'] ) ) {
		remove_action( 'ep_formatted_args', 'ep_facets_set_agg_filters', 10, 2 );

		foreach ( $facet_query_args['tax_query'] as $key => $taxonomy ) {
			if ( is_array( $taxonomy ) ) {
				if ( 'any' === $settings['match_type'] ) {
					unset( $facet_query_args['tax_query'][ $key ] );
				}
			}
		}

		$facet_formatted_args = EP_API::factory()->format_args( $facet_query_args );

		$args['aggs']['terms']['filter'] = $facet_formatted_args['post_filter'];

		add_action( 'ep_formatted_args', 'ep_facets_set_agg_filters', 10, 2 );
	}

	return $args;
}

/**
 * Output scripts for widget admin
 *
 * @param  string $hook
 * @since  2.5
 */
function ep_facets_admin_scripts( $hook ) {
	if ( 'widgets.php' !== $hook ) {
        return;
    }

    $css_url = ( defined( 'SCRIPT_DEBUG' ) && true === SCRIPT_DEBUG ) ? EP_URL . 'features/facets/assets/css/admin.css' : EP_URL . 'features/facets/assets/css/admin.min.css';

	wp_enqueue_style(
		'elasticpress-facets-admin',
		$css_url,
		array(),
		EP_VERSION
	);
}

/**
 * Output front end facets styles
 *
 * @since 2.5
 */
function ep_facets_front_scripts() {
	$js_url = ( defined( 'SCRIPT_DEBUG' ) && true === SCRIPT_DEBUG ) ? EP_URL . 'features/facets/assets/js/src/facets.js' : EP_URL . 'features/facets/assets/js/facets.min.js';
    $css_url = ( defined( 'SCRIPT_DEBUG' ) && true === SCRIPT_DEBUG ) ? EP_URL . 'features/facets/assets/css/facets.css' : EP_URL . 'features/facets/assets/css/facets.min.css';

	wp_enqueue_script(
		'elasticpress-facets',
		$js_url,
		array( 'jquery', 'underscore' ),
		EP_VERSION,
		true
	);

	wp_enqueue_style(
		'elasticpress-facets',
		$css_url,
		array(),
		EP_VERSION
	);
}

/**
 * Figure out if we can/should facet the query
 *
 * @param  WP_Query $query
 * @since  2.5
 * @return bool
 */
function ep_facets_is_facetable( $query ) {
	if ( is_admin() ) {
		return false;
	}

	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		return false;
	}

	if ( ! $query->is_main_query() ) {
		return false;
	}

	$page_id = $query->get( 'page_id' );

	if ( ! ( $query->is_post_type_archive() || $query->is_search() || ( is_home() && empty( $page_id ) ) ) ) {
		return false;
	}

	return true;
}
/**
 * We enable ElasticPress facet on all archive/search queries as well as non-static home pages. There is no way to know
 * when a facet widget is used before the main query is executed so we enable EP
 * everywhere where a facet widget could be used.
 *
 * @since  2.5
 */
function ep_facets_facet_query( $query ) {
	if ( ! ep_facets_is_facetable( $query ) ) {
		return;
	}

	$taxonomies = get_taxonomies( array( 'public' => true ) );

	if ( empty( $taxonomies ) ) {
		return;
	}

	$query->set( 'ep_integrate', true );
	$query->set( 'ep_facet', true );

    $facets = array();

	foreach ( $taxonomies as $slug ) {
		$facets[ $slug ] = array(
			'terms' => array(
				'size' => 10000,
				'field' => 'terms.' . $slug . '.slug',
			),
		);
	}

	$aggs = array(
		'name' => 'terms',
    	'use-filter' => true,
    	'aggs' => $facets,
    );

	$query->set( 'aggs', $aggs );

	$selected_filters = ep_facets_get_selected();

	$feature  = ep_get_registered_feature( 'facets' );
	$settings = array();

	if ( $feature ) {
		$settings = $feature->get_settings();
	}

	$settings = wp_parse_args( $settings, array(
		'match_type' => 'all',
	) );

	$tax_query = $query->get( 'tax_query', array() );

	foreach ( $selected_filters['taxonomies'] as $taxonomy => $filter ) {
		$tax_query[] = [
			'taxonomy' => $taxonomy,
			'field'    => 'slug',
			'terms'    => array_keys( $filter['terms'] ),
			'operator' => ( 'any' === $settings['match_type'] ) ? 'or' : 'and',
		];
	}

	if ( ! empty( $selected_filters['taxonomies'] ) && 'any' === $settings['match_type'] ) {
		$tax_query['relation'] = 'or';
	}

	$query->set( 'tax_query', $tax_query );
}

/**
 * Hacky. Save aggregation data for later in a global
 *
 * @param  array $response
 * @since  2.5
 */
function ep_facets_get_aggs( $response ) {
	$response_body = wp_remote_retrieve_body( $response );
	$response = json_decode( $response_body, true );

	$GLOBALS['ep_facet_aggs'] = false;

	if ( ! empty( $response['aggregations'] ) && ! empty( $response['aggregations']['terms'] ) ) {
		$GLOBALS['ep_facet_aggs'] = array();

		foreach ( $response['aggregations']['terms'] as $key => $agg ) {
			if ( 'doc_count' === $key ) {
				continue;
			}

			$GLOBALS['ep_facet_aggs'][ $key ] = array();

			foreach ( $agg['buckets'] as $bucket ) {
				$GLOBALS['ep_facet_aggs'][ $key ][ $bucket['key'] ] = $bucket['doc_count'];
			}

		}
	}
}

/**
 * Get currently selected facets from query args
 *
 * @since  2.5
 * @return array
 */
function ep_facets_get_selected() {
	$filters = array(
		'taxonomies' => array(),
	);

	foreach ( $_GET as $key => $value ) {
		if ( 0 === strpos( $key, 'filter' ) ) {
			$taxonomy = str_replace( 'filter_', '', $key );

			$filters['taxonomies'][ $taxonomy ] = array(
				'terms'      => array_fill_keys( array_map( 'trim', explode( ',', trim( $value, ',' ) ) ), true ),
			);
		}
	}

	return $filters;
}

/**
 * Build query url
 *
 * @since  2.5
 * @return string
 */
function ep_facets_build_query_url( $filters ) {
	$query_string = '';

	$s = get_search_query();

	if ( ! empty( $s ) ) {
		$query_string .= 's=' . $s;
	}

	if ( ! empty( $filters['taxonomies'] ) ) {
		$tax_filters = $filters['taxonomies'];

		foreach ( $tax_filters as $taxonomy => $filter ) {
			if ( ! empty( $filter['terms'] ) ) {
				if ( ! empty( $query_string ) ) {
					$query_string .= '&';
				}

				$query_string .= 'filter_' . $taxonomy . '=' . implode( array_keys( $filter['terms'] ), ',' );
			}
		}
	}

	$query_string = apply_filters( 'ep_facet_query_string', $query_string );

	return strtok( $_SERVER['REQUEST_URI'], '?' ) . ( ( ! empty( $query_string ) ) ? '?' . $query_string : '' );
}

/**
 * Register facet widget(s)
 *
 * @since 2.5
 */
function ep_facets_register_widgets() {
	require_once( dirname( __FILE__ ) . '/class-ep-facet-widget.php' );

	register_widget( 'EP_Facet_Widget' );
}

/**
 * Output feature box summary
 *
 * @since 2.5
 */
function ep_facets_feature_box_summary() {
	?>
	<p><?php esc_html_e( 'Add controls to your website to filter content by one or more taxonomies.', 'elasticpress' ); ?></p>
	<?php
}

/**
 * Output feature box long
 *
 * @since 2.5
 */
function ep_facets_feature_box_long() {
	?>
	<p><?php echo wp_kses_post( sprintf( __( "Adds a <a href='%s'>Facet widget</a> that administrators can add to the website's sidebars (widgetized areas), so that visitors can filter applicable content and search results by one or more taxonomy terms.", 'elasticpress' ), esc_url( admin_url( 'widgets.php' ) ) ) ); ?></p>
	<?php
}

/**
 * Register the feature
 *
 * @since  2.5
 */
ep_register_feature( 'facets', array(
	'title'                    => 'Facets',
	'setup_cb'                 => 'ep_facets_setup',
	'feature_box_summary_cb'   => 'ep_facets_feature_box_summary',
	'feature_box_long_cb'      => 'ep_facets_feature_box_long',
	'requires_install_reindex' => false,
	'default_settings'         => array(
		'match_type' => 'all',
	),
) );
