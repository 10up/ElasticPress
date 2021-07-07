<?php
/**
 * Facets feature
 *
 * @since  2.5
 * @package  elasticpress
 */

namespace ElasticPress\Feature\Facets;

use ElasticPress\Feature as Feature;
use ElasticPress\Features as Features;
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
		add_action( 'ep_valid_response', [ $this, 'get_aggs' ], 10, 4 );
		add_filter( 'ep_post_formatted_args', [ $this, 'set_agg_filters' ], 10, 3 );
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
	 * @param  array    $args ES arguments
	 * @param  array    $query_args Query arguments
	 * @param  WP_Query $query WP Query instance
	 * @since  2.5
	 * @return array
	 */
	public function set_agg_filters( $args, $query_args, $query ) {
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
			remove_filter( 'ep_post_formatted_args', [ $this, 'set_agg_filters' ], 10, 3 );

			foreach ( $facet_query_args['tax_query'] as $key => $taxonomy ) {
				if ( is_array( $taxonomy ) ) {
					if ( 'any' === $settings['match_type'] ) {
						unset( $facet_query_args['tax_query'][ $key ] );
					}
				}
			}

			$facet_formatted_args = Indexables::factory()->get( 'post' )->format_args( $facet_query_args, $query );

			$args['aggs']['terms']['filter'] = $facet_formatted_args['post_filter'];

			add_filter( 'ep_post_formatted_args', [ $this, 'set_agg_filters' ], 10, 3 );
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
			EP_URL . 'dist/css/facets-admin-styles.min.css',
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
		wp_register_script(
			'elasticpress-facets',
			EP_URL . 'dist/js/facets-script.min.js',
			[],
			EP_VERSION,
			true
		);

		wp_register_style(
			'elasticpress-facets',
			EP_URL . 'dist/css/facets-styles.min.css',
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

		/**
		 * Bypass the standard checks and set a query to be facetable
		 *
		 * @hook ep_is_facetable
		 * @param  {bool}     $bypass Defaults to false.
		 * @param  {WP_Query} $query  The current WP_Query.
		 * @return {bool}     true to bypass, false to ignore
		 */
		if ( \apply_filters( 'ep_is_facetable', false, $query ) ) {
			return true;
		}

		if ( is_admin() ) {
			return false;
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return false;
		}

		if ( ! $query->is_main_query() ) {
			return false;
		}

		$ep_integrate = $query->get( 'ep_integrate', null );

		if ( false === $ep_integrate ) {
			return false;
		}

		$woocommerce = Features::factory()->get_registered_feature( 'woocommerce' );

		if ( ! $woocommerce->is_active() && ( function_exists( 'is_product_category' ) && is_product_category() ) ) {
			return false;
		}

		if ( ! ( ( function_exists( 'is_product_category' ) && is_product_category() )
			|| $query->is_post_type_archive()
			|| $query->is_search()
			|| ( is_home() && empty( $query->get( 'page_id' ) ) ) )
		) {
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

		$taxonomies = get_taxonomies( array( 'public' => true ), 'object' );

		/**
		 * Filter taxonomies made available for faceting
		 *
		 * @hook ep_facet_include_taxonomies
		 * @param  {array} $taxonomies Taxonomies
		 * @return  {array} New taxonomies
		 */
		$taxonomies = apply_filters( 'ep_facet_include_taxonomies', $taxonomies );

		if ( empty( $taxonomies ) ) {
			return;
		}

		$query->set( 'ep_integrate', true );
		$query->set( 'ep_facet', true );

		$facets = [];

		/**
		 * Retrieve aggregations based on a custom field. This field must exist on the mapping.
		 * Values available out-of-the-box are:
		 *  - slug (default)
		 *  - term_id
		 *  - name
		 *  - parent
		 *  - term_taxonomy_id
		 *  - term_order
		 *  - facet (retrieves a JSON representation of the term object)
		 *
		 * @since 3.6.0
		 * @hook ep_facet_use_field
		 * @param  {string} $field The term field to use
		 * @return  {string} The chosen term field
		 */
		$facet_field = apply_filters( 'ep_facet_use_field', 'slug' );

		foreach ( $taxonomies as $slug => $taxonomy ) {
			$facets[ $slug ] = array(
				'terms' => array(
					'size'  => apply_filters( 'ep_facet_taxonomies_size', 10000, $taxonomy ),
					'field' => 'terms.' . $slug . '.' . $facet_field,
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

		// Account for taxonomies that should be woocommerce attributes, if WC is enabled
		$attribute_taxonomies = [];
		if ( function_exists( 'wc_attribute_taxonomy_name' ) ) {
			$all_attr_taxonomies = wc_get_attribute_taxonomies();

			foreach ( $all_attr_taxonomies as $attr_taxonomy ) {
				$attribute_taxonomies[ $attr_taxonomy->attribute_name ] = wc_attribute_taxonomy_name( $attr_taxonomy->attribute_name );
			}
		}

		foreach ( $selected_filters['taxonomies'] as $taxonomy => $filter ) {
			$tax_query[] = [
				'taxonomy' => isset( $attribute_taxonomies[ $taxonomy ] ) ? $attribute_taxonomies[ $taxonomy ] : $taxonomy,
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
	 * @param  array $query Prepared Elasticsearch query
	 * @param  array $query_args Current WP Query arguments
	 * @param  mixed $query_object Could be WP_Query, WP_User_Query, etc.
	 * @since  2.5
	 */
	public function get_aggs( $response, $query, $query_args, $query_object ) {
		if ( empty( $query_object ) || 'WP_Query' !== get_class( $query_object ) || ! $this->is_facetable( $query_object ) ) {
			return;
		}

		$GLOBALS['ep_facet_aggs'] = false;

		if ( ! empty( $response['aggregations'] ) ) {
			$GLOBALS['ep_facet_aggs'] = [];

			if ( isset( $response['aggregations']['terms'] ) && is_array( $response['aggregations']['terms'] ) ) {
				foreach ( $response['aggregations']['terms'] as $key => $agg ) {
					if ( 'doc_count' === $key ) {
						continue;
					}

					if ( ! is_array( $agg ) || empty( $agg['buckets'] ) ) {
						continue;
					}

					$GLOBALS['ep_facet_aggs'][ $key ] = [];

					foreach ( $agg['buckets'] as $bucket ) {
						$GLOBALS['ep_facet_aggs'][ $key ][ $bucket['key'] ] = $bucket['doc_count'];
					}
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

		$allowed_args = $this->get_allowed_query_args();

		foreach ( $_GET as $key => $value ) { // phpcs:ignore WordPress.Security.NonceVerification
			if ( 0 === strpos( $key, 'filter_' ) ) {
				$taxonomy = str_replace( 'filter_', '', $key );

				$filters['taxonomies'][ $taxonomy ] = array(
					'terms' => array_fill_keys( array_map( 'trim', explode( ',', trim( $value, ',' ) ) ), true ),
				);
			}

			if ( in_array( $key, $allowed_args, true ) ) {
				$filters[ $key ] = $value;
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
		$query_param = array();

		if ( ! empty( $filters['taxonomies'] ) ) {
			$tax_filters = $filters['taxonomies'];

			foreach ( $tax_filters as $taxonomy => $filter ) {
				if ( ! empty( $filter['terms'] ) ) {
					$query_param[ 'filter_' . $taxonomy ] = implode( ',', array_keys( $filter['terms'] ) );
				}
			}
		}

		$allowed_args = $this->get_allowed_query_args();

		if ( ! empty( $filters ) ) {
			foreach ( $filters as $filter => $value ) {
				if ( ! empty( $value ) && in_array( $filter, $allowed_args, true ) ) {
					$query_param[ $filter ] = $value;
				}
			}
		}

		$query_string = http_build_query( $query_param );

		/**
		 * Filter facet query string
		 *
		 * @hook ep_facet_query_string
		 * @param  {string} $query_string Current query string
		 * @param  {array} $query_param Query parameters
		 * @return  {string} New query string
		 */
		$query_string = apply_filters( 'ep_facet_query_string', $query_string, $query_param );

		$url        = $_SERVER['REQUEST_URI'];
		$pagination = strpos( $url, '/page' );
		if ( false !== $pagination ) {
			$url = substr( $url, 0, $pagination );
		}

		return strtok( trailingslashit( $url ), '?' ) . ( ( ! empty( $query_string ) ) ? '?' . $query_string : '' );
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
		<p>
			<?php
			// translators: URL
			echo wp_kses_post( sprintf( __( "Adds a <a href='%s'>Facet widget</a> that administrators can add to the website's sidebars (widgetized areas), so that visitors can filter applicable content and search results by one or more taxonomy terms.", 'elasticpress' ), esc_url( admin_url( 'widgets.php' ) ) ) );
			?>
		</p>
		<?php
	}

	/**
	 * Returns allowed query args for facets
	 *
	 * @return mixed|void
	 * @since 3.6.0
	 */
	public function get_allowed_query_args() {
		$args = array( 's', 'post_type' );

		/**
		 * Filter allowed query args
		 *
		 * @hook    ep_facet_allowed_query_args
		 * @since 3.6.0
		 * @param   {array} $args Post types
		 * @return  {array} New post types
		 */
		return apply_filters( 'ep_facet_allowed_query_args', $args );
	}
}
