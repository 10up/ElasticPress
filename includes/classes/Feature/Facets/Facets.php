<?php
/**
 * Facets feature
 *
 * @since  2.5
 * @package  elasticpress
 */

namespace ElasticPress\Feature\Facets;

use ElasticPress\Feature as Feature;
use ElasticPress\Utils as Utils;
use ElasticPress\FeatureRequirementsStatus as FeatureRequirementsStatus;
use ElasticPress\Indexables as Indexables;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Facets feature class
 */
class Facets extends Feature {

	/**
	 * Initialize feature setting it's config
	 *
	 * @since  3.0
	 */
	public function __construct() {
		$this->slug = 'facets';

		$this->title = esc_html__( 'Facets', 'elasticpress' );

		$this->requires_install_reindex = false;
		$this->default_settings         = [
			'match_type' => 'all',
		];

		parent::__construct();
	}

	/**
	 * Setup hooks and filters for feature
	 *
	 * @since 2.5
	 */
	public function setup() {
		add_action( 'widgets_init', [ $this, 'register_widgets' ] );
		add_action( 'ep_valid_response', [ $this, 'get_aggs' ] );
		add_filter( 'ep_post_formatted_args', [ $this, 'set_agg_filters' ], 10, 2 );
		add_action( 'pre_get_posts', [ $this, 'facet_query' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_scripts' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'front_scripts' ] );
		add_action( 'ep_feature_box_settings_facets', [ $this, 'settings' ], 10, 1 );
	}

	/**
	 * Dashboard facet settings
	 *
	 * @since 2.5
	 */
	public function output_feature_box_settings() {
		$settings = $this->get_settings();

		if ( ! $settings ) {
			$settings = [];
		}

		$settings = wp_parse_args( $settings, $this->default_settings );
		?>
		<div class="field js-toggle-feature" data-feature="<?php echo esc_attr( $this->slug ); ?>">
			<div class="field-name status"><?php esc_html_e( 'Match Type', 'elasticpress' ); ?></div>
			<div class="input-wrap">
				<label for="match_type_all"><input name="match_type" id="match_type_all" data-field-name="match_type" class="setting-field" type="radio"
				<?php
				if ( 'all' === $settings['match_type'] ) :
					?>
checked<?php endif; ?> value="all"><?php echo wp_kses_post( __( 'Show any content tagged to <strong>all</strong> selected terms', 'elasticpress' ) ); ?></label><br>
				<label for="match_type_any"><input name="match_type" id="match_type_any" data-field-name="match_type" class="setting-field" type="radio"
				<?php
				if ( 'any' === $settings['match_type'] ) :
					?>
checked<?php endif; ?> value="any"><?php echo wp_kses_post( __( 'Show all content tagged to <strong>any</strong> selected term', 'elasticpress' ) ); ?></label>
				<p class="field-description"><?php esc_html_e( '"All" will only show content that matches all facets. "Any" will show content that matches any facet.', 'elasticpress' ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * If we are doing or matches, we need to remove filters from aggs
	 *
	 * @param  array $args ES arguments
	 * @param  array $query_args Query arguments
	 * @since  2.5
	 * @return array
	 */
	public function set_agg_filters( $args, $query_args ) {
		if ( empty( $query_args['ep_facet'] ) ) {
			return $args;
		}

		// @todo For some reason these are appearing in the query args, need to investigate
		unset( $query_args['category_name'] );
		unset( $query_args['cat'] );
		unset( $query_args['tag'] );
		unset( $query_args['tag_id'] );
		unset( $query_args['taxonomy'] );
		unset( $query_args['term'] );

		$facet_query_args = $query_args;

		$settings = $this->get_settings();

		$settings = wp_parse_args(
			$settings,
			array(
				'match_type' => 'all',
			)
		);

		if ( ! empty( $facet_query_args['tax_query'] ) ) {
			remove_filter( 'ep_post_formatted_args', [ $this, 'set_agg_filters' ], 10, 2 );

			foreach ( $facet_query_args['tax_query'] as $key => $taxonomy ) {
				if ( is_array( $taxonomy ) ) {
					if ( 'any' === $settings['match_type'] ) {
						unset( $facet_query_args['tax_query'][ $key ] );
					}
				}
			}

			$facet_formatted_args = Indexables::factory()->get( 'post' )->format_args( $facet_query_args );

			$args['aggs']['terms']['filter'] = $facet_formatted_args['post_filter'];

			add_filter( 'ep_post_formatted_args', [ $this, 'set_agg_filters' ], 10, 2 );
		}

		return $args;
	}

	/**
	 * Output scripts for widget admin
	 *
	 * @param  string $hook WP hook
	 * @since  2.5
	 */
	public function admin_scripts( $hook ) {
		if ( 'widgets.php' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'elasticpress-facets-admin',
			EP_URL . 'dist/css/admin.min.css',
			[],
			EP_VERSION
		);
	}

	/**
	 * Output front end facets styles
	 *
	 * @since 2.5
	 */
	public function front_scripts() {
		wp_enqueue_script(
			'elasticpress-facets',
			EP_URL . 'dist/js/facets.min.js',
			[ 'jquery', 'underscore' ],
			EP_VERSION,
			true
		);

		wp_enqueue_style(
			'elasticpress-facets',
			EP_URL . 'dist/css/facets.min.css',
			[],
			EP_VERSION
		);
	}

	/**
	 * Figure out if we can/should facet the query
	 *
	 * @param  WP_Query $query WP Query
	 * @since  2.5
	 * @return bool
	 */
	public function is_facetable( $query ) {
		if ( is_admin() ) {
			return false;
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return false;
		}

		if ( ! $query->is_main_query() ) {
			return false;
		}

		if ( ! ( $query->is_post_type_archive() || $query->is_search() || ( is_home() && empty( $query->get( 'page_id' ) ) ) ) ) {
			return false;
		}

		return true;
	}

	/**
	 * We enable ElasticPress facet on all archive/search queries as well as non-static home pages. There is no way to know
	 * when a facet widget is used before the main query is executed so we enable EP
	 * everywhere where a facet widget could be used.
	 *
	 * @param  WP_Query $query WP Query
	 * @since  2.5
	 */
	public function facet_query( $query ) {
		if ( ! $this->is_facetable( $query ) ) {
			return;
		}

		$taxonomies = get_taxonomies( array( 'public' => true ) );

		if ( empty( $taxonomies ) ) {
			return;
		}

		$query->set( 'ep_integrate', true );
		$query->set( 'ep_facet', true );

		$facets = [];

		foreach ( $taxonomies as $slug ) {
			$facets[ $slug ] = array(
				'terms' => array(
					'size'  => 10000,
					'field' => 'terms.' . $slug . '.slug',
				),
			);
		}

		$aggs = array(
			'name'       => 'terms',
			'use-filter' => true,
			'aggs'       => $facets,
		);

		$query->set( 'aggs', $aggs );

		$selected_filters = $this->get_selected();

		$settings = $this->get_settings();

		$settings = wp_parse_args(
			$settings,
			array(
				'match_type' => 'all',
			)
		);

		$tax_query = $query->get( 'tax_query', [] );

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
	 * @param  array $response ES response
	 * @since  2.5
	 */
	public function get_aggs( $response ) {

		$GLOBALS['ep_facet_aggs'] = false;

		if ( ! empty( $response['aggregations'] ) ) {
			$GLOBALS['ep_facet_aggs'] = [];

			foreach ( $response['aggregations']['terms'] as $key => $agg ) {
				if ( 'doc_count' === $key ) {
					continue;
				}

				$GLOBALS['ep_facet_aggs'][ $key ] = [];

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
	public function get_selected() {
		$filters = array(
			'taxonomies' => [],
		);

		foreach ( $_GET as $key => $value ) {
			if ( 0 === strpos( $key, 'filter' ) ) {
				$taxonomy = str_replace( 'filter_', '', $key );

				$filters['taxonomies'][ $taxonomy ] = array(
					'terms' => array_fill_keys( array_map( 'trim', explode( ',', trim( $value, ',' ) ) ), true ),
				);
			}
		}

		return $filters;
	}

	/**
	 * Build query url
	 *
	 * @since  2.5
	 * @param  array $filters Facet filters
	 * @return string
	 */
	public function build_query_url( $filters ) {
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
	public function register_widgets() {
		register_widget( __NAMESPACE__ . '\Widget' );
	}

	/**
	 * Output feature box summary
	 *
	 * @since 2.5
	 */
	public function output_feature_box_summary() {
		?>
		<p><?php esc_html_e( 'Add controls to your website to filter content by one or more taxonomies.', 'elasticpress' ); ?></p>
		<?php
	}

	/**
	 * Output feature box long
	 *
	 * @since 2.5
	 */
	public function output_feature_box_long() {
		?>
		<p><?php echo wp_kses_post( sprintf( __( "Adds a <a href='%s'>Facet widget</a> that administrators can add to the website's sidebars (widgetized areas), so that visitors can filter applicable content and search results by one or more taxonomy terms.", 'elasticpress' ), esc_url( admin_url( 'widgets.php' ) ) ) ); ?></p>
		<?php
	}
}
