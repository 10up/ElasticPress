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
use ElasticPress\Indexables as Indexables;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Facets feature class
 */
class Facets extends Feature {
	/**
	 * Facet types (taxonomy, meta fields, etc.)
	 *
	 * @since 4.3.0
	 * @var array
	 */
	public $types = [];

	/**
	 * Initialize feature setting it's config
	 *
	 * @since  3.0
	 */
	public function __construct() {
		$this->slug = 'facets';

		$this->title = esc_html__( 'Facets', 'elasticpress' );

		$this->summary = __( 'Add controls to your website to filter content by one or more taxonomies.', 'elasticpress' );

		$this->docs_url = __( 'https://elasticpress.zendesk.com/hc/en-us/articles/360050447492-Configuring-ElasticPress-via-the-Plugin-Dashboard#facets', 'elasticpress' );

		$this->requires_install_reindex = false;

		$this->default_settings = [
			'match_type' => 'all',
		];

		$types = [
			'taxonomy' => __NAMESPACE__ . '\Types\Taxonomy\FacetType',
			'meta'     => __NAMESPACE__ . '\Types\Meta\FacetType',
		];

		/**
		 * Filter the Facet types available.
		 *
		 * ```
		 * add_filter(
		 *     'ep_facet_types',
		 *     function ( $types ) {
		 *         $types['post_type'] = '\MyPlugin\PostType';
		 *         return $types;
		 *     }
		 * );
		 * ```
		 *
		 * @since 4.3.0
		 * @hook ep_facet_types
		 * @param {array} $types Array of types available. Keys are slugs, values are class names.
		 * @return {array} New array of types available
		 */
		$types = apply_filters( 'ep_facet_types', $types );

		foreach ( $types as $type => $class ) {
			if ( is_a( $class, __NAMESPACE__ . '\FacetType', true ) ) {
				$this->types[ $type ] = new $class();
			}
		}

		parent::__construct();
	}

	/**
	 * Setup hooks and filters for feature
	 *
	 * @since 2.5
	 */
	public function setup() {
		global $pagenow;

		// This feature should not run while in the editor.
		if ( in_array( $pagenow, [ 'post-new.php', 'post.php' ], true ) ) {
			return;
		}

		foreach ( $this->types as $type => $class ) {
			$this->types[ $type ]->setup();
		}

		add_filter( 'widget_types_to_hide_from_legacy_widget_block', [ $this, 'hide_legacy_widget' ] );
		add_action( 'ep_valid_response', [ $this, 'get_aggs' ], 10, 4 );
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_scripts' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'front_scripts' ] );
		add_action( 'ep_feature_box_settings_facets', [ $this, 'settings' ], 10, 1 );
		add_filter( 'ep_post_formatted_args', [ $this, 'set_agg_filters' ], 10, 3 );
		add_action( 'pre_get_posts', [ $this, 'facet_query' ] );
		add_filter( 'ep_post_filters', [ $this, 'apply_facets_filters' ], 10, 3 );
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
		<div class="field">
			<div class="field-name status"><?php esc_html_e( 'Match Type', 'elasticpress' ); ?></div>
			<div class="input-wrap">
				<label><input name="settings[match_type]" type="radio" <?php checked( $settings['match_type'], 'all' ); ?> value="all"><?php echo wp_kses_post( __( 'Show any content tagged to <strong>all</strong> selected terms', 'elasticpress' ) ); ?></label><br>
				<label><input name="settings[match_type]" type="radio" <?php checked( $settings['match_type'], 'any' ); ?> value="any"><?php echo wp_kses_post( __( 'Show all content tagged to <strong>any</strong> selected term', 'elasticpress' ) ); ?></label>
				<p class="field-description"><?php esc_html_e( '"All" will only show content that matches all facets. "Any" will show content that matches any facet.', 'elasticpress' ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * If we are doing `or` matches, we need to remove filters from aggs.
	 *
	 * By default, the same filters applied to the main query are applied to aggregations.
	 * If doing `or` matches, those should be removed so we get a broader set of results.
	 *
	 * @param  array    $args ES arguments
	 * @param  array    $query_args Query arguments
	 * @param  WP_Query $query WP Query instance
	 * @since  2.5
	 * @return array
	 */
	public function set_agg_filters( $args, $query_args, $query ) {
		// Not a facetable query
		if ( empty( $query_args['ep_facet'] ) ) {
			return $args;
		}

		if ( 'any' === $this->get_match_type() ) {
			add_filter( 'ep_post_filters', [ $this, 'remove_facets_filter' ], 11 );
		}

		/**
		 * Filter WP query arguments that will be used to build the aggregations filter.
		 *
		 * The returned `$query_args` will be used to build the aggregations filter passing
		 * it through `Indexable\Post\Post::format_args()`.
		 *
		 * @hook ep_facet_agg_filters
		 * @since 4.3.0
		 * @param {array} $query_args Query arguments
		 * @param {array} $args       ES arguments
		 * @param {array} $query      WP Query instance
		 * @return {array} New facets aggregations
		 */
		$query_args = apply_filters( 'ep_facet_agg_filters', $query_args, $args, $query );

		remove_filter( 'ep_post_formatted_args', [ $this, 'set_agg_filters' ], 10, 3 );
		$facet_formatted_args = Indexables::factory()->get( 'post' )->format_args( $query_args, $query );
		add_filter( 'ep_post_formatted_args', [ $this, 'set_agg_filters' ], 10, 3 );

		remove_filter( 'ep_post_filters', [ $this, 'remove_facets_filter' ], 11 );

		$args['aggs']['terms']['filter'] = $facet_formatted_args['post_filter'];

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
			EP_URL . 'dist/css/facets-admin-styles.css',
			Utils\get_asset_info( 'facets-admin-styles', 'dependencies' ),
			Utils\get_asset_info( 'facets-admin-styles', 'version' )
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
			EP_URL . 'dist/js/facets-script.js',
			Utils\get_asset_info( 'facets-script', 'dependencies' ),
			Utils\get_asset_info( 'facets-script', 'version' ),
			true
		);

		wp_set_script_translations( 'elasticpress-facets', 'elasticpress' );

		wp_register_style(
			'elasticpress-facets',
			EP_URL . 'dist/css/facets-styles.css',
			Utils\get_asset_info( 'facets-styles', 'dependencies' ),
			Utils\get_asset_info( 'facets-styles', 'version' )
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

		if ( is_admin() || is_feed() ) {
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

		if ( ! $this->is_facetable_page( $query ) ) {
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

		// If any filter was selected, there is no reason to prepend the list with sticky posts.
		$selected_filters = $this->get_selected();
		if ( ! empty( array_filter( $selected_filters ) ) ) {
			$query->set( 'ignore_sticky_posts', true );
		}

		/**
		 * Filter facet aggregations.
		 *
		 * This is used by facet types to add their own aggregations to the
		 * general facet.
		 *
		 * @hook ep_facet_wp_query_aggs_facet
		 * @since 4.3.0
		 * @param {array} $facets Facets aggregations
		 * @return {array} New facets aggregations
		 */
		$facets = apply_filters( 'ep_facet_wp_query_aggs_facet', [] );

		if ( empty( $facets ) ) {
			return;
		}

		$query->set( 'ep_integrate', true );
		$query->set( 'ep_facet', true );

		$aggs = array(
			'name'       => 'terms',
			'use-filter' => true,
			'aggs'       => $facets,
		);

		$query->set( 'aggs', $aggs );
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
		$allowed_args = $this->get_allowed_query_args();

		$filters            = [];
		$filter_names       = [];
		$sanitize_callbacks = [];
		foreach ( $this->types as $type_obj ) {
			$filter_type = $type_obj->get_filter_type();

			$filters[ $filter_type ]            = [];
			$filter_names[ $filter_type ]       = $type_obj->get_filter_name();
			$sanitize_callbacks[ $filter_type ] = $type_obj->get_sanitize_callback();
		}

		foreach ( $_GET as $key => $value ) { // phpcs:ignore WordPress.Security.NonceVerification
			$key = sanitize_key( $key );

			foreach ( $filter_names as $filter_type => $filter_name ) {
				if ( 0 === strpos( $key, $filter_name ) ) {
					$facet             = str_replace( $filter_name, '', $key );
					$sanitize_callback = $sanitize_callbacks[ $filter_type ];
					$terms             = explode( ',', trim( $value, ',' ) );

					$filters[ $filter_type ][ $facet ] = array(
						'terms' => array_fill_keys( array_map( $sanitize_callback, $terms ), true ),
					);
				}
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

		foreach ( $this->types as $type_obj ) {
			$filter_type = $type_obj->get_filter_type();

			if ( ! empty( $filters[ $filter_type ] ) ) {
				$type_filters = $filters[ $filter_type ];

				foreach ( $type_filters as $facet => $filter ) {
					if ( ! empty( $filter['terms'] ) ) {
						$query_param[ $type_obj->get_filter_name() . $facet ] = implode( ',', array_keys( $filter['terms'] ) );
					}
				}
			}
		}

		$feature      = Features::factory()->get_registered_feature( 'facets' );
		$allowed_args = $feature->get_allowed_query_args();

		if ( ! empty( $filters ) ) {
			foreach ( $filters as $filter => $value ) {
				if ( in_array( $filter, $allowed_args, true ) ) {
					$query_param[ $filter ] = $value;
				}
			}
		}

		$query_string = build_query( $query_param );

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
	 * @since 2.5, deprecated in 4.3.0
	 */
	public function register_widgets() {
		_deprecated_function( __METHOD__, '4.3.0', "\ElasticPress\Features::factory()->get_registered_feature( 'facets' )->types[ \$type ]->register_widgets()" );
	}

	/**
	 * Hide the legacy widget.
	 *
	 * Hides the legacy widget in favor of the Block when the block editor
	 * is in use and the legacy widget has not been used.
	 *
	 * @since 4.3
	 * @param array $widgets An array of excluded widget-type IDs.
	 * @return array array of excluded widget-type IDs to hide.
	 */
	public function hide_legacy_widget( $widgets ) {
		$widgets[] = 'ep-facet';

		return $widgets;
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
		$args = array( 's', 'post_type', 'orderby' );

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

	/**
	 * Get the facet filter name.
	 *
	 * @return string The filter name.
	 */
	protected function get_filter_name() {
		_deprecated_function( __METHOD__, '4.3.0', "\ElasticPress\Features::factory()->get_registered_feature( 'facets' )->types['taxonomy']->get_filter_name()" );

		return $this->types['taxonomy']->get_filter_name();
	}

	/**
	 * Get all taxonomies that could be selected for a facet.
	 *
	 * @since 4.2.0, deprecated in 4.3.0
	 * @return array
	 */
	public function get_facetable_taxonomies() {
		_deprecated_function( __METHOD__, '4.3.0', "\ElasticPress\Features::factory()->get_registered_feature( 'facets' )->types['taxonomy']->get_facetable_taxonomies()" );

		return $this->types['taxonomy']->get_filter_name();

	}

	/**
	 * Add a new filter to the ES query with selected facets
	 *
	 * @since 4.4.0
	 * @param array    $filters  Current filters
	 * @param array    $args     WP Query args
	 * @param WP_Query $query    WP Query object
	 * @return array
	 */
	public function apply_facets_filters( $filters, $args, $query ) {
		if ( ! $this->is_facetable( $query ) ) {
			return $filters;
		}

		/**
		 * Filter facet selection filters to be applied to the ES query
		 *
		 * @hook  ep_facet_query_filters
		 * @since 4.4.0
		 * @param  {array}    $filters Current filters
		 * @param  {array}    $args    WP Query args
		 * @param  {WP_Query} $query   WP Query object
		 * @return {array} New filters
		 */
		$facets_filters = apply_filters( 'ep_facet_query_filters', [], $args, $query );

		if ( empty( $facets_filters ) ) {
			return $filters;
		}

		$es_operator = ( 'any' === $this->get_match_type() ) ? 'should' : 'must';

		$filters['facets'] = [
			'bool' => [
				$es_operator => $facets_filters,
			],
		];

		return $filters;
	}

	/**
	 * Utilitary function to retrieve the match type selected by the user.
	 *
	 * @since 4.4.0
	 * @return string
	 */
	public function get_match_type() {
		$settings = wp_parse_args(
			$this->get_settings(),
			array(
				'match_type' => 'all',
			)
		);

		/**
		 * Filter the match type of all facets. Can be 'all' or 'any'.
		 *
		 * @hook  ep_facet_match_type
		 * @since 4.4.0
		 * @param  {string} $match_type Current selection
		 * @return {string} New selection
		 */
		return apply_filters( 'ep_facet_match_type', $settings['match_type'] );
	}

	/**
	 * Given an array of filters, remove the facets filter.
	 *
	 * This is used when the user wants posts matching ANY criteria, so aggregations should not restrict their results.
	 *
	 * @since 4.4.0
	 * @param array $filters Filters to be applied to the ES query
	 * @return array
	 */
	public function remove_facets_filter( $filters ) {
		unset( $filters['facets'] );
		return $filters;
	}

	/**
	 * Figure out if Facet widget can display on page.
	 *
	 * @param  WP_Query $query WP Query
	 * @since  4.2.1
	 * @return bool
	 */
	protected function is_facetable_page( $query ) {
		return $query->is_home() || $query->is_search() || $query->is_tax() || $query->is_tag() || $query->is_category() || $query->is_post_type_archive();
	}
}
